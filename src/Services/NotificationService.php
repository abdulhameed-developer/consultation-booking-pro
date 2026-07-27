<?php

namespace ConsultationBookingPro\Services;

/**
 * Handles Email Notifications using native Wordpress wp_mail.
 */
class NotificationService {
    /**
     * Send confirmation email to student
     */
    public function sendStudentConfirmation(object $booking) {
        $subject = __('Consultation Booking Confirmed', 'consultation-booking-pro');
        $template = $this->getTemplate('student_confirmation');
        
        $body = $this->parseTemplate($template, [
            'student_name' => $booking->student_name,
            'date' => $booking->booking_date,
            'time' => $booking->booking_time,
            'counselor_name' => $booking->counselor_name ?? __('Assigned Counselor', 'consultation-booking-pro')
        ]);

        return $this->send($booking->student_email, $subject, $body);
    }

    /**
     * Send notification to counselor
     */
    public function sendCounselorAlert(object $booking, string $counselor_email) {
        $subject = __('New Consultation Booking', 'consultation-booking-pro');
        $template = $this->getTemplate('counselor_new_booking');
        
        $body = $this->parseTemplate($template, [
            'student_name' => $booking->student_name,
            'date' => $booking->booking_date,
            'time' => $booking->booking_time
        ]);

        return $this->send($counselor_email, $subject, $body);
    }

    /**
     * Send email helper
     */
    private function send(string $to, string $subject, string $body) {
        $headers = ['Content-Type: text/html; charset=UTF-8'];
        return wp_mail($to, $subject, $body, $headers);
    }

    /**
     * Load template from settings or defaults
     */
    private function getTemplate(string $key): string {
        global $wpdb;
        $table = $wpdb->prefix . 'cbp_settings';
        $db_template = $wpdb->get_var($wpdb->prepare("SELECT setting_value FROM $table WHERE setting_key = %s", "tpl_" . $key));
        
        if ($db_template) return $db_template;

        $templates = [
            'student_confirmation' => '
                <h1>Hello {student_name},</h1>
                <p>Your consultation booking is confirmed for <strong>{date}</strong> at <strong>{time}</strong>.</p>
                <p>Counselor: {counselor_name}</p>
                <p>Thank you!</p>
            ',
            'counselor_new_booking' => '
                <h1>New Booking Alert</h1>
                <p>A new student, {student_name}, has booked a consultation for <strong>{date}</strong> at <strong>{time}</strong>.</p>
            '
        ];
        return $templates[$key] ?? '';
    }

    /**
     * Replace variables in template
     */
    private function parseTemplate(string $template, array $data): string {
        foreach ($data as $key => $value) {
            $template = str_replace('{' . $key . '}', $value, $template);
        }
        return $template;
    }
}
