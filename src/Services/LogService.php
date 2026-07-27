<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Multi-channel file logger under wp-content/uploads/consultation-booking-pro/logs/
 * Channels: plugin, email, google, api, booking
 */
class LogService {
    private static array $channels = ['plugin', 'email', 'google', 'api', 'booking'];

    public static function getLogDir(): string {
        $upload = wp_upload_dir();
        $dir = trailingslashit($upload['basedir']) . 'consultation-booking-pro/logs';
        if (!is_dir($dir)) {
            wp_mkdir_p($dir);
            if (is_dir($dir)) {
                file_put_contents($dir . '/.htaccess', "Deny from all\n");
                file_put_contents($dir . '/index.php', "<?php\n// Silence is golden.\n");
            }
        }
        return $dir;
    }

    public static function getLogFile(string $channel = 'plugin'): string {
        $channel = in_array($channel, self::$channels, true) ? $channel : 'plugin';
        return self::getLogDir() . '/' . $channel . '.log';
    }

    public static function write(string $channel, string $level, string $message, array $context = []): void {
        $timestamp = current_time('Y-m-d H:i:s');
        $line = "[{$timestamp}] [{$level}] {$message}";
        if (!empty($context)) {
            $line .= ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        $line .= PHP_EOL;
        @file_put_contents(self::getLogFile($channel), $line, FILE_APPEND | LOCK_EX);

        if ($channel !== 'plugin' && $level === 'ERROR') {
            $mirror = "[{$timestamp}] [{$level}] [{$channel}] {$message}";
            if (!empty($context)) {
                $mirror .= ' ' . wp_json_encode($context, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }
            $mirror .= PHP_EOL;
            @file_put_contents(self::getLogFile('plugin'), $mirror, FILE_APPEND | LOCK_EX);
        }
    }

    public static function info(string $message, array $context = [], string $channel = 'plugin'): void {
        self::write($channel, 'INFO', $message, $context);
    }

    public static function error(string $message, array $context = [], string $channel = 'plugin'): void {
        self::write($channel, 'ERROR', $message, $context);
    }

    public static function debug(string $message, array $context = [], string $channel = 'plugin'): void {
        self::write($channel, 'DEBUG', $message, $context);
    }

    public static function exception(string $message, \Throwable $e, string $channel = 'plugin'): void {
        self::write($channel, 'ERROR', $message, [
            'exception' => $e->getMessage(),
            'file'      => $e->getFile(),
            'line'      => $e->getLine(),
            'trace'     => $e->getTraceAsString(),
        ]);
    }

    public static function getRecentLines(string $channel = 'plugin', int $limit = 200): array {
        $file = self::getLogFile($channel);
        if (!file_exists($file)) {
            return [];
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        return $lines ? array_slice($lines, -$limit) : [];
    }

    public static function clear(string $channel = 'plugin'): bool {
        $file = self::getLogFile($channel);
        return file_exists($file) ? (bool) file_put_contents($file, '') : true;
    }

    public static function getAllChannels(): array {
        return self::$channels;
    }
}
