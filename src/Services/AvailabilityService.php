<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

class AvailabilityService {

    /**
     * Available slot display strings (e.g. "9:00 AM") for a date in counselor timezone.
     * Blocks: existing WP bookings + Google Calendar busy (manual events, Meet, etc.).
     *
     * @return string[]
     */
    public function getAvailableSlots(string $date, $counselor_id = null): array {
        $repo = new \ConsultationBookingPro\Repositories\CounselorRepository();

        if ($counselor_id) {
            $counselor = $repo->find($counselor_id);
        } else {
            $counselor = $repo->getDefault();
        }

        if (!$counselor || $counselor->status !== 'active') {
            LogService::info('getAvailableSlots: no active counselor', ['date' => $date], 'availability');
            return [];
        }

        $timezone = TimezoneHelper::fromCounselor($counselor);
        $day_name = TimezoneHelper::dayName($date, $timezone);

        global $wpdb;
        $table = $wpdb->prefix . 'cbp_bookings';
        $existing_bookings = $wpdb->get_results($wpdb->prepare(
            "SELECT id, booking_time FROM $table WHERE booking_date = %s AND counselor_id = %d AND status != 'cancelled'",
            $date,
            $counselor->id
        ));

        $max_per_day = (int) ($counselor->max_bookings_per_day ?: 15);
        if (count($existing_bookings) >= $max_per_day) {
            return [];
        }

        $slots_data = $this->generateDefaultSlots($date, $counselor, $timezone, $day_name);
        if (empty($slots_data)) {
            return [];
        }

        $busy_periods = $this->fetchGoogleBusyPeriods($counselor, $date, $timezone);

        $booked_times_24 = [];
        foreach ($existing_bookings as $b) {
            $booked_times_24[] = TimezoneHelper::toHi((string) $b->booking_time, $timezone);
        }

        $duration = max(1, (int) ($counselor->slot_duration ?? 30));
        $available = [];

        foreach ($slots_data as $s) {
            $time_24 = $s['time'];
            if (in_array($time_24, $booked_times_24, true)) {
                continue;
            }

            try {
                $slot_start = TimezoneHelper::bookingDateTime($date, $time_24, $timezone);
                $slot_end = $slot_start->modify('+' . $duration . ' minutes');
            } catch (\Exception $e) {
                continue;
            }

            // Skip past slots for "today" in counselor timezone.
            $now = new \DateTimeImmutable('now', TimezoneHelper::zone($timezone));
            if ($slot_end <= $now) {
                continue;
            }

            if ($this->slotOverlapsBusy($slot_start, $slot_end, $busy_periods)) {
                continue;
            }

            $available[] = TimezoneHelper::toDisplay($time_24, $timezone);
        }

        LogService::info('getAvailableSlots result', [
            'date'       => $date,
            'timezone'   => $timezone,
            'day'        => $day_name,
            'generated'  => count($slots_data),
            'busy'       => count($busy_periods),
            'available'  => count($available),
        ], 'availability');

        return $available;
    }

    public function getSlotsForDate(string $date, $counselor_id = null): array {
        return $this->getAvailableSlots($date, $counselor_id);
    }

    public function getRefreshedToken($counselor) {
        return $this->ensureAccessToken($counselor);
    }

    /**
     * @return array<int, array{start:\DateTimeImmutable,end:\DateTimeImmutable}>
     */
    private function fetchGoogleBusyPeriods($counselor, string $date, string $timezone): array {
        $access_token = $this->ensureAccessToken($counselor);
        if (!$access_token) {
            return [];
        }

        $calendar_id = $counselor->google_calendar_id ?: 'primary';
        $calendar = new GoogleCalendarService($access_token, $calendar_id);

        $day_start = new \DateTimeImmutable($date . ' 00:00:00', TimezoneHelper::zone($timezone));
        $day_end = $day_start->modify('+1 day');

        // FreeBusy (covers manual events, recurring, Meet — anything marked Busy).
        $busy_raw = $calendar->getFreeBusy(
            $day_start->format(\DateTimeInterface::RFC3339),
            $day_end->format(\DateTimeInterface::RFC3339),
            $timezone
        );

        $periods = [];
        foreach ($busy_raw as $busy) {
            if (empty($busy['start']) || empty($busy['end'])) {
                continue;
            }
            try {
                $periods[] = [
                    'start' => new \DateTimeImmutable($busy['start']),
                    'end'   => new \DateTimeImmutable($busy['end']),
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        // Fallback / supplement: events.list for opaque events (same window).
        $events_busy = $calendar->listBusyEvents(
            $day_start->format(\DateTimeInterface::RFC3339),
            $day_end->format(\DateTimeInterface::RFC3339),
            $timezone
        );
        foreach ($events_busy as $busy) {
            if (empty($busy['start']) || empty($busy['end'])) {
                continue;
            }
            try {
                $periods[] = [
                    'start' => new \DateTimeImmutable($busy['start']),
                    'end'   => new \DateTimeImmutable($busy['end']),
                ];
            } catch (\Exception $e) {
                continue;
            }
        }

        return $this->mergeBusyPeriods($periods);
    }

    /**
     * @param array<int, array{start:\DateTimeImmutable,end:\DateTimeImmutable}> $periods
     * @return array<int, array{start:\DateTimeImmutable,end:\DateTimeImmutable}>
     */
    private function mergeBusyPeriods(array $periods): array {
        if (empty($periods)) {
            return [];
        }

        usort($periods, static function ($a, $b) {
            return $a['start'] <=> $b['start'];
        });

        $merged = [$periods[0]];
        for ($i = 1, $n = count($periods); $i < $n; $i++) {
            $last = &$merged[count($merged) - 1];
            $cur = $periods[$i];
            if ($cur['start'] <= $last['end']) {
                if ($cur['end'] > $last['end']) {
                    $last['end'] = $cur['end'];
                }
            } else {
                $merged[] = $cur;
            }
        }

        return $merged;
    }

    /**
     * @param array<int, array{start:\DateTimeImmutable,end:\DateTimeImmutable}> $busy_periods
     */
    private function slotOverlapsBusy(
        \DateTimeImmutable $slot_start,
        \DateTimeImmutable $slot_end,
        array $busy_periods
    ): bool {
        foreach ($busy_periods as $busy) {
            if (TimezoneHelper::overlaps($slot_start, $slot_end, $busy['start'], $busy['end'])) {
                return true;
            }
        }
        return false;
    }

    private function ensureAccessToken($counselor) {
        if (!$counselor || empty($counselor->google_refresh_token)) {
            return false;
        }

        if (!empty($counselor->google_token_expiry) && strtotime($counselor->google_token_expiry) > time() + 300) {
            return $counselor->google_access_token;
        }

        $settings = new SettingsService();
        $creds = $settings->getGoogleCredentialsForCounselor($counselor);

        if (empty($creds['client_id']) || empty($creds['client_secret'])) {
            return false;
        }

        $auth = new GoogleAuthService();
        $tokens = $auth->refreshToken($counselor->google_refresh_token, $creds['client_id'], $creds['client_secret']);

        if (!empty($tokens['success']) && !empty($tokens['access_token'])) {
            $expiry = date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600));
            $repo = new \ConsultationBookingPro\Repositories\CounselorRepository();
            $repo->update($counselor->id, [
                'google_access_token' => $tokens['access_token'],
                'google_token_expiry' => $expiry,
            ]);
            return $tokens['access_token'];
        }

        LogService::error('AvailabilityService token refresh failed', ['error' => $tokens['error'] ?? 'unknown'], 'google');
        return false;
    }

    private function safeJsonDecode($json): array {
        if (empty($json)) {
            return [];
        }
        if (is_array($json)) {
            return $json;
        }
        if (is_object($json)) {
            return (array) $json;
        }

        $decoded = json_decode($json, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        $stripped = stripslashes($json);
        $decoded = json_decode($stripped, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (function_exists('wp_unslash')) {
            $decoded = json_decode(wp_unslash($json), true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded = json_decode(stripslashes($stripped), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function generateDefaultSlots(string $date, $counselor, string $timezone, string $day_name): array {
        $avail_mode = $counselor->availability_mode ?? 'auto';

        if ($avail_mode === 'custom') {
            $custom_slots_raw = $this->safeJsonDecode($counselor->custom_slots);
            $day_slots = [];
            if (isset($custom_slots_raw[$day_name]) && is_array($custom_slots_raw[$day_name])) {
                $day_slots = $custom_slots_raw[$day_name];
            } elseif (isset($custom_slots_raw['days'][$day_name]['custom_slots'])) {
                $day_slots = $custom_slots_raw['days'][$day_name]['custom_slots'];
            }

            $slots = [];
            foreach ($day_slots as $time_str) {
                if (is_array($time_str)) {
                    $time_str = $time_str['time'] ?? $time_str['start'] ?? '';
                }
                if ($time_str === '' || $time_str === null) {
                    continue;
                }
                $slots[] = [
                    'time'   => TimezoneHelper::toHi((string) $time_str, $timezone),
                    'status' => 'free',
                ];
            }
            return $slots;
        }

        $schedule = $this->safeJsonDecode($counselor->availability_schedule);
        if (!is_array($schedule) || empty($schedule)) {
            return [];
        }

        $day_schedule = null;
        if (isset($schedule[$day_name]) && is_array($schedule[$day_name])) {
            $day_schedule = $schedule[$day_name];
        } elseif (isset($schedule['days'][$day_name]) && is_array($schedule['days'][$day_name])) {
            $day_schedule = $schedule['days'][$day_name];
        } else {
            return [];
        }

        $is_active = true;
        if (isset($day_schedule['enabled'])) {
            $is_active = (bool) $day_schedule['enabled'];
        } elseif (isset($day_schedule['active'])) {
            $is_active = (bool) $day_schedule['active'];
        }
        if (!$is_active) {
            return [];
        }

        $start_hi = TimezoneHelper::toHi((string) ($day_schedule['start'] ?? '09:00'), $timezone);
        $end_hi = TimezoneHelper::toHi((string) ($day_schedule['end'] ?? '17:00'), $timezone);

        $duration = max(1, (int) ($counselor->slot_duration ?? 30));
        $buffer = max(0, (int) ($counselor->buffer_time ?? 0));

        $tz = TimezoneHelper::zone($timezone);
        $current = new \DateTimeImmutable($date . ' ' . $start_hi . ':00', $tz);
        $last = new \DateTimeImmutable($date . ' ' . $end_hi . ':00', $tz);

        $slots = [];
        while (true) {
            $slot_end = $current->modify('+' . $duration . ' minutes');
            if ($slot_end > $last) {
                break;
            }
            $slots[] = [
                'time'   => $current->format('H:i'),
                'status' => 'free',
            ];
            $current = $current->modify('+' . ($duration + $buffer) . ' minutes');
        }

        return $slots;
    }
}
