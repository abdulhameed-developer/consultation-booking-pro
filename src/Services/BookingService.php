<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

class BookingService {

    /**
     * Entry point from REST API (legacy RestApiController).
     */
    public function processBooking(array $data) {
        $result = $this->createBooking($data);
        if (is_wp_error($result)) {
            throw new \Exception($result->get_error_message());
        }

        global $wpdb;
        $booking_id = $result['booking_id'];
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cbp_bookings WHERE id = %d",
            $booking_id
        ));

        $token = bin2hex(random_bytes(16));
        $wpdb->update($wpdb->prefix . 'cbp_bookings', ['token' => $token], ['id' => $booking_id]);

        return (object)[
            'id'                => $booking_id,
            'token'             => $token,
            'google_event_link' => $booking->google_event_link ?? null,
        ];
    }

    /**
     * Create a booking and run post-booking actions (calendar sync, emails).
     *
     * @return array|\WP_Error
     */
    public function createBooking(array $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'cbp_bookings';

        LogService::info('Booking submission started', [
            'email' => $data['student_email'] ?? '',
            'date'  => $data['booking_date'] ?? '',
            'time'  => $data['booking_time'] ?? '',
        ], 'booking');

        $name    = sanitize_text_field($data['student_name'] ?? '');
        $email   = sanitize_email($data['student_email'] ?? '');
        $phone   = sanitize_text_field($data['student_phone'] ?? '');
        $code    = sanitize_text_field($data['student_country_code'] ?? '');
        $country = sanitize_text_field($data['country'] ?? '');
        $level   = sanitize_text_field($data['study_level'] ?? '');
        $field   = sanitize_text_field($data['interested_field'] ?? '');
        $message = sanitize_textarea_field($data['message'] ?? '');
        $date    = sanitize_text_field($data['booking_date'] ?? '');
        $time    = sanitize_text_field($data['booking_time'] ?? '');

        if (!$name || !$email || !$date || !$time) {
            return new \WP_Error('missing_fields', 'Required fields are missing.');
        }

        if (!is_email($email)) {
            return new \WP_Error('invalid_email', 'Please provide a valid email address.');
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return new \WP_Error('invalid_date', 'Invalid booking date format.');
        }

        $counselor = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cbp_counselors WHERE status = 'active' ORDER BY id ASC LIMIT 1"
        ));
        if (!$counselor) {
            return new \WP_Error('no_counselor', 'No active counselors available.');
        }

        $availability = new AvailabilityService();
        $available_slots = $availability->getAvailableSlots($date, $counselor->id);

        if (!in_array($time, $available_slots, true)) {
            return new \WP_Error('slot_taken', 'This time slot is no longer available. Please choose another.');
        }

        $timezone = TimezoneHelper::fromCounselor($counselor);
        try {
            $start_dt = TimezoneHelper::bookingDateTime($date, $time, $timezone);
        } catch (\Exception $e) {
            return new \WP_Error('invalid_time', 'Invalid booking time format.');
        }

        $insert_data = [
            'counselor_id'         => $counselor->id,
            'student_name'         => $name,
            'student_email'        => $email,
            'student_phone'        => $phone,
            'student_country_code' => $code,
            'country'              => $country,
            'study_level'          => $level,
            'interested_field'     => $field,
            'booking_date'         => $date,
            'booking_time'         => $start_dt->format('H:i:s'),
            'status'               => 'pending',
            'custom_data'          => wp_json_encode([
                'message'  => $message,
                'timezone' => $timezone,
            ]),
            'created_at'           => current_time('mysql'),
        ];

        LogService::info('Booking time normalized', [
            'submitted_time' => $time,
            'stored_time'    => $insert_data['booking_time'],
            'timezone'       => $timezone,
            'local_display'  => $start_dt->format('Y-m-d g:i A'),
        ], 'booking');

        $inserted = $wpdb->insert($table, $insert_data);
        if (!$inserted) {
            $err = 'Database insert failed: ' . $wpdb->last_error;
            LogService::error($err, [], 'booking');
            return new \WP_Error('db_error', $err);
        }

        $booking_id = (int) $wpdb->insert_id;
        LogService::info('Booking saved to database', ['booking_id' => $booking_id], 'booking');

        $calendar_result = $this->syncToGoogle($booking_id, $counselor);

        $wpdb->update($table, ['status' => 'confirmed'], ['id' => $booking_id]);

        $email_result = $this->sendConfirmationEmails($booking_id, $counselor, $calendar_result['event_link'] ?? null);

        LogService::info('Booking workflow completed', [
            'booking_id'     => $booking_id,
            'student_email'  => $email_result['student_sent'],
            'counselor_email'=> $email_result['counselor_sent'],
            'calendar_synced'=> $calendar_result['synced'],
        ], 'booking');

        return [
            'booking_id'             => $booking_id,
            'student_email_sent'     => $email_result['student_sent'],
            'counselor_email_sent'   => $email_result['counselor_sent'],
            'student_email_error'    => $email_result['student_error'],
            'counselor_email_error'  => $email_result['counselor_error'],
            'calendar_synced'        => $calendar_result['synced'],
            'calendar_error'         => $calendar_result['error'],
            'google_event_link'      => $calendar_result['event_link'],
            'google_meet_link'       => $calendar_result['meet_link'],
            'google_event_id'        => $calendar_result['event_id'],
            'booking_date'           => $date,
            'booking_time'           => $time,
        ];
    }

    /**
     * @return array{synced:bool,error:?string,event_link:?string,meet_link:?string,event_id:?string}
     */
    private function syncToGoogle(int $booking_id, $counselor): array {
        global $wpdb;

        $result = [
            'synced'     => false,
            'error'      => null,
            'event_link' => null,
            'meet_link'  => null,
            'event_id'   => null,
        ];

        if (empty($counselor->google_refresh_token)) {
            $result['error'] = 'Missing refresh token — Google Calendar is not connected. Admin: Booking Pro → Settings → Connect Google Calendar.';
            LogService::error('Calendar sync skipped', ['booking_id' => $booking_id, 'reason' => $result['error']], 'booking');
            return $result;
        }

        $booking = $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$wpdb->prefix}cbp_bookings WHERE id = %d", $booking_id),
            ARRAY_A
        );

        if (!$booking) {
            $result['error'] = 'Booking record not found for calendar sync (ID ' . $booking_id . ').';
            return $result;
        }

        $diagnostics = new DiagnosticsService();
        $token_result = $diagnostics->resolveAccessToken($counselor);
        if (!$token_result['success']) {
            $result['error'] = $token_result['error'];
            LogService::error('Calendar sync token failure', ['booking_id' => $booking_id, 'error' => $result['error']], 'booking');
            return $result;
        }

        $calendar_id = $counselor->google_calendar_id ?: 'primary';
        $calendar = new GoogleCalendarService($token_result['token'], $calendar_id);
        $event = $calendar->createEvent($booking, (array) $counselor, true);

        if (!$event['success'] || empty($event['id'])) {
            $result['error'] = $event['error'] ?? 'Google Calendar event could not be created.';
            LogService::error('Calendar event creation failed', [
                'booking_id' => $booking_id,
                'error'      => $result['error'],
            ], 'booking');
            return $result;
        }

        $update_data = [
            'google_event_id'   => $event['id'],
            'google_event_link' => $event['htmlLink'] ?? null,
        ];
        if (!empty($event['meetLink'])) {
            $update_data['google_meet_link'] = $event['meetLink'];
        }

        $wpdb->update($wpdb->prefix . 'cbp_bookings', $update_data, ['id' => $booking_id]);

        $result['synced']     = true;
        $result['event_id']   = $event['id'];
        $result['event_link'] = $event['htmlLink'] ?? null;
        $result['meet_link']  = $event['meetLink'] ?? $event['hangoutLink'] ?? null;

        LogService::info('Calendar event created for booking', [
            'booking_id' => $booking_id,
            'event_id'   => $event['id'],
            'meet_link'  => $result['meet_link'],
        ], 'booking');

        return $result;
    }

    /**
     * @return array{student_sent:bool,counselor_sent:bool,student_error:?string,counselor_error:?string}
     */
    private function sendConfirmationEmails(int $booking_id, $counselor, ?string $event_link = null): array {
        global $wpdb;

        $result = [
            'student_sent'    => false,
            'counselor_sent'  => false,
            'student_error'   => null,
            'counselor_error' => null,
        ];

        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cbp_bookings WHERE id = %d",
            $booking_id
        ));

        if (!$booking) {
            $result['student_error'] = 'Booking not found for email (ID ' . $booking_id . ').';
            return $result;
        }

        if ($event_link === null && !empty($booking->google_event_link)) {
            $event_link = $booking->google_event_link;
        }

        $custom_data = json_decode($booking->custom_data, true);
        $message_text = is_array($custom_data) ? ($custom_data['message'] ?? '') : '';
        $timezone = TimezoneHelper::fromCounselor($counselor);
        if (is_array($custom_data) && !empty($custom_data['timezone'])) {
            $timezone = TimezoneHelper::resolve($custom_data['timezone']);
        }
        $headers = MailService::buildHeaders($counselor);
        $mail = new MailService();

        $date_formatted = date('l, F j, Y', strtotime($booking->booking_date));
        $time_formatted = TimezoneHelper::toDisplay((string) $booking->booking_time, $timezone);
        $tz_label = TimezoneHelper::label($timezone);
        $google_link = $event_link ?: '#';

        $merge_tags = [
            '{booking_id}'        => $booking_id,
            '{student_name}'      => $booking->student_name,
            '{student_email}'     => $booking->student_email,
            '{student_phone}'     => ($booking->student_country_code ?? '') . ' ' . ($booking->student_phone ?? ''),
            '{student_country}'   => $booking->country,
            '{study_level}'       => $booking->study_level,
            '{interested_field}'  => $booking->interested_field,
            '{message}'           => $message_text,
            '{booking_date}'      => $date_formatted,
            '{booking_time}'      => $time_formatted,
            '{timezone}'          => $tz_label,
            '{counselor_name}'    => $counselor->name ?? 'Counselor',
            '{counselor_email}'   => $counselor->email ?? get_option('admin_email'),
            '{site_name}'         => get_bloginfo('name'),
            '{site_url}'          => site_url(),
            '{current_year}'      => date('Y'),
            '{google_event_link}' => $google_link,
        ];

        $template_s = CBP_PATH . 'form-files/email-student.html';
        $template_c = CBP_PATH . 'form-files/email-counselor.html';

        $student_subject = 'Booking Confirmed - Consultation on ' . $date_formatted . ' at ' . $time_formatted . ' (' . $timezone . ')';
        $student_body = file_exists($template_s)
            ? strtr(file_get_contents($template_s), $merge_tags)
            : '<h2>Booking Confirmed!</h2><p>Your consultation is scheduled for {booking_date} at {booking_time} ({timezone}).</p>';
        $student_body = strtr($student_body, $merge_tags);

        $student = $mail->send($booking->student_email, $student_subject, $student_body, $headers, $booking_id);
        $result['student_sent'] = $student['sent'];
        $result['student_error'] = $student['error'];

        $counselor_subject = 'New Consultation: ' . $booking->student_name . ' - ' . $date_formatted . ' ' . $time_formatted;
        $counselor_body = file_exists($template_c)
            ? strtr(file_get_contents($template_c), $merge_tags)
            : '<h2>New Booking</h2><p>{student_name} has booked for {booking_date} at {booking_time} ({timezone}).</p>';
        $counselor_body = strtr($counselor_body, $merge_tags);

        $counselor_mail = $mail->send($counselor->email, $counselor_subject, $counselor_body, $headers, $booking_id);
        $result['counselor_sent'] = $counselor_mail['sent'];
        $result['counselor_error'] = $counselor_mail['error'];

        return $result;
    }
}
