<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Single source of truth for counselor timezone + booking datetimes.
 * All slots and Google Calendar events use the counselor's timezone wall-clock.
 */
class TimezoneHelper {

    /**
     * Resolve a valid IANA timezone from counselor / fallbacks.
     */
    public static function resolve(?string $timezone): string {
        $timezone = trim((string) $timezone);
        if ($timezone !== '' && self::isValid($timezone)) {
            return $timezone;
        }

        $wp = wp_timezone_string();
        if ($wp && self::isValid($wp)) {
            return $wp;
        }

        return 'UTC';
    }

    public static function fromCounselor($counselor): string {
        $tz = '';
        if (is_object($counselor)) {
            $tz = $counselor->timezone ?? '';
        } elseif (is_array($counselor)) {
            $tz = $counselor['timezone'] ?? '';
        }
        return self::resolve($tz);
    }

    public static function zone(?string $timezone): \DateTimeZone {
        return new \DateTimeZone(self::resolve($timezone));
    }

    public static function isValid(string $timezone): bool {
        try {
            new \DateTimeZone($timezone);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Build DateTimeImmutable for a booking date + time in counselor timezone.
     * Accepts "09:00", "09:00:00", or "09:00 AM".
     */
    public static function bookingDateTime(string $date, string $time, ?string $timezone): \DateTimeImmutable {
        $tz = self::zone($timezone);
        $time = trim($time);

        $formats = ['Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d h:i A', 'Y-m-d g:i A', 'Y-m-d h:i:s A', 'Y-m-d g:i:s A'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, $date . ' ' . $time, $tz);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt;
            }
        }

        // Last resort: let DateTime parse, still anchored to counselor TZ.
        return new \DateTimeImmutable($date . ' ' . $time, $tz);
    }

    /**
     * Day-of-week name (monday…) for a Y-m-d date in counselor timezone.
     */
    public static function dayName(string $date, ?string $timezone): string {
        $dt = new \DateTimeImmutable($date . ' 12:00:00', self::zone($timezone));
        return strtolower($dt->format('l'));
    }

    /**
     * Normalize any time string to H:i (24h).
     */
    public static function toHi(string $time, ?string $timezone = null): string {
        $tz = self::zone($timezone ?: 'UTC');
        $formats = ['H:i:s', 'H:i', 'h:i A', 'g:i A', 'h:i:s A', 'g:i:s A'];
        foreach ($formats as $format) {
            $dt = \DateTimeImmutable::createFromFormat($format, trim($time), $tz);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt->format('H:i');
            }
        }
        $ts = strtotime($time);
        return $ts ? date('H:i', $ts) : '00:00';
    }

    /**
     * Display time as h:i A.
     */
    public static function toDisplay(string $time, ?string $timezone = null): string {
        $hi = self::toHi($time, $timezone);
        $dt = \DateTimeImmutable::createFromFormat('H:i', $hi, self::zone($timezone ?: 'UTC'));
        return $dt ? $dt->format('h:i A') : $time;
    }

    /**
     * Human label for emails / UI, e.g. "Asia/Karachi (UTC+05:00)".
     */
    public static function label(?string $timezone): string {
        $tz_id = self::resolve($timezone);
        try {
            $dt = new \DateTimeImmutable('now', new \DateTimeZone($tz_id));
            return $tz_id . ' (UTC' . $dt->format('P') . ')';
        } catch (\Exception $e) {
            return $tz_id;
        }
    }

    /**
     * Common timezones shown first in admin selects.
     *
     * @return string[]
     */
    public static function commonList(): array {
        return [
            'Asia/Karachi',
            'Asia/Dubai',
            'Asia/Riyadh',
            'Asia/Kolkata',
            'Asia/Singapore',
            'Asia/Kuala_Lumpur',
            'Asia/Hong_Kong',
            'Asia/Tokyo',
            'Europe/London',
            'Europe/Paris',
            'Europe/Berlin',
            'America/New_York',
            'America/Chicago',
            'America/Denver',
            'America/Los_Angeles',
            'America/Toronto',
            'Australia/Sydney',
            'Australia/Melbourne',
            'Pacific/Auckland',
            'UTC',
        ];
    }

    /**
     * Overlap check between two half-open intervals [a_start, a_end) and [b_start, b_end).
     */
    public static function overlaps(
        \DateTimeInterface $a_start,
        \DateTimeInterface $a_end,
        \DateTimeInterface $b_start,
        \DateTimeInterface $b_end
    ): bool {
        return $a_start < $b_end && $a_end > $b_start;
    }
}
