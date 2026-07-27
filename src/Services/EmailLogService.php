<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

/** @deprecated Use LogService channel 'email' — kept for backward compatibility */
class EmailLogService {
    public static function getLogFile(): string {
        return LogService::getLogFile('email');
    }

    public static function log(string $message, array $context = []): void {
        LogService::write('email', 'INFO', $message, $context);
    }

    public static function getRecentLines(int $limit = 150): array {
        return LogService::getRecentLines('email', $limit);
    }

    public static function clear(): bool {
        return LogService::clear('email');
    }

    public static function recordSuccess(string $to, string $subject): void {
        update_option('cbp_last_successful_email', wp_json_encode([
            'to' => $to, 'subject' => $subject, 'time' => current_time('mysql'),
        ]));
        LogService::info('Last successful email recorded', ['to' => $to], 'email');
    }

    public static function recordFailure(string $error): void {
        update_option('cbp_last_smtp_error', wp_json_encode([
            'error' => $error, 'time' => current_time('mysql'),
        ]));
        LogService::error('Last SMTP error recorded', ['error' => $error], 'email');
    }

    public static function getLastSuccess(): ?array {
        $raw = get_option('cbp_last_successful_email', '');
        return $raw ? json_decode($raw, true) : null;
    }

    public static function getLastError(): ?array {
        $raw = get_option('cbp_last_smtp_error', '');
        return $raw ? json_decode($raw, true) : null;
    }
}
