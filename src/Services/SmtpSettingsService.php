<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Built-in SMTP settings (stored in wp_options, password encrypted).
 */
class SmtpSettingsService {

    public static function getSettings(): array {
        $encryption = get_option('cbp_smtp_encryption', 'tls');
        if (!in_array($encryption, ['none', 'ssl', 'tls'], true)) {
            $encryption = 'tls';
        }

        return [
            'host'       => get_option('cbp_smtp_host', ''),
            'port'       => (int) get_option('cbp_smtp_port', 587),
            'encryption' => $encryption,
            'username'   => get_option('cbp_smtp_username', ''),
            'password'   => self::decrypt(get_option('cbp_smtp_password', '')),
            'auth'       => (bool) get_option('cbp_smtp_auth', 1),
            'from_name'  => get_option('cbp_email_from_name', get_bloginfo('name')),
            'from_email' => get_option('cbp_email_from_email', get_option('admin_email')),
            'reply_to'   => get_option('cbp_email_reply_to', ''),
            'timeout'    => (int) get_option('cbp_smtp_timeout', 30),
        ];
    }

    public static function isConfigured(): bool {
        $s = self::getSettings();
        return !empty($s['host']) && !empty($s['port']) && is_email($s['from_email']);
    }

    public static function encrypt(string $plain): string {
        if ($plain === '') {
            return '';
        }
        if (!function_exists('openssl_encrypt')) {
            return base64_encode($plain);
        }
        $key = self::encryptionKey();
        $iv  = openssl_random_pseudo_bytes(16);
        $enc = openssl_encrypt($plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $enc);
    }

    public static function decrypt(string $stored): string {
        if ($stored === '') {
            return '';
        }
        if (!function_exists('openssl_decrypt')) {
            $decoded = base64_decode($stored, true);
            return $decoded !== false ? $decoded : '';
        }
        $raw = base64_decode($stored, true);
        if ($raw === false || strlen($raw) < 17) {
            return '';
        }
        $iv  = substr($raw, 0, 16);
        $enc = substr($raw, 16);
        $key = self::encryptionKey();
        $plain = openssl_decrypt($enc, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $plain !== false ? $plain : '';
    }

    private static function encryptionKey(): string {
        $salt = defined('AUTH_KEY') ? AUTH_KEY : 'cbp-default-key';
        $salt .= defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : 'cbp-default-secure';
        return hash('sha256', $salt, true);
    }

    /**
     * Sanitize password on save — keep existing if field left blank or masked.
     */
    public static function sanitizePassword($value): string {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '' || $value === '********' || $value === '••••••••') {
            return get_option('cbp_smtp_password', '');
        }
        return self::encrypt($value);
    }
}
