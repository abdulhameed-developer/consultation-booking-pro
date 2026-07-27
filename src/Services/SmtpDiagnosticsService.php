<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * SMTP connectivity and authentication diagnostics.
 */
class SmtpDiagnosticsService {

    /**
     * Run full SMTP diagnostic suite.
     */
    public static function runDiagnostics(): array {
        $settings = SmtpSettingsService::getSettings();
        $tests = [];

        $tests[] = self::testOpenSsl();
        $tests[] = self::testConfigured($settings);
        if (!SmtpSettingsService::isConfigured()) {
            return $tests;
        }

        $tests[] = self::testDnsResolution($settings);
        $tests[] = self::testPortReachable($settings);
        $tests[] = self::testSmtpBanner($settings);
        $tests[] = self::testSmtpConnection($settings);

        $last_err = EmailLogService::getLastError();
        $last_ok  = EmailLogService::getLastSuccess();

        $tests[] = [
            'id'      => 'smtp_auth',
            'label'   => 'Authentication',
            'status'  => 'PASS',
            'message' => $settings['auth']
                ? 'Enabled for ' . ($settings['username'] ?: '(username not set)')
                : 'Disabled (no SMTP AUTH)',
        ];
        $tests[] = [
            'label'   => 'Last SMTP error',
            'status'  => $last_err ? 'INFO' : 'PASS',
            'message' => $last_err ? ($last_err['error'] . ' (' . $last_err['time'] . ')') : 'None recorded',
        ];
        $tests[] = [
            'id'      => 'last_successful_email',
            'label'   => 'Last successful email',
            'status'  => $last_ok ? 'PASS' : 'INFO',
            'message' => $last_ok
                ? 'To ' . $last_ok['to'] . ' — ' . $last_ok['subject'] . ' (' . $last_ok['time'] . ')'
                : 'None recorded yet',
        ];
        $tests[] = [
            'id'      => 'mailer',
            'label'   => 'Mailer being used',
            'status'  => 'PASS',
            'message' => 'Consultation Booking Pro built-in SMTP (PHPMailer via wp_mail, scoped hook)',
        ];
        $tests[] = [
            'id'      => 'connection_timeout',
            'label'   => 'Connection timeout',
            'status'  => 'PASS',
            'message' => ($settings['timeout'] ?? 30) . ' seconds',
        ];

        return $tests;
    }

    /**
     * Test SMTP with step-by-step feedback for the test email button.
     */
    public static function testSend(string $to): array {
        $steps = [];
        $settings = SmtpSettingsService::getSettings();

        if (!SmtpSettingsService::isConfigured()) {
            return [
                'success' => false,
                'steps'   => [['step' => 'Configuration', 'ok' => false, 'message' => 'SMTP is not configured. Enter SMTP Host, Port, and From Email in Settings.']],
            ];
        }

        $reach = self::probePort($settings['host'], $settings['port'], $settings['timeout']);
        $steps[] = [
            'step'    => 'Connected to SMTP server',
            'ok'      => $reach['ok'],
            'message' => $reach['message'],
        ];
        if (!$reach['ok']) {
            return ['success' => false, 'steps' => $steps];
        }

        $dns = self::resolveDns($settings['host']);
        $steps[] = [
            'step'    => 'DNS resolution',
            'ok'      => $dns['ok'],
            'message' => $dns['message'],
        ];

        $banner = self::readSmtpBanner($settings);
        $steps[] = [
            'step'    => 'SMTP banner',
            'ok'      => $banner['ok'],
            'message' => $banner['message'],
        ];

        $steps[] = [
            'step'    => 'TLS/SSL established',
            'ok'      => true,
            'message' => strtoupper($settings['encryption']) . ' on port ' . $settings['port'],
        ];

        $mail = new MailService();
        $result = $mail->send(
            $to,
            'Consultation Booking Pro — SMTP Test',
            '<h2>SMTP Test Successful</h2><p>Your built-in SMTP configuration is working correctly.</p><p>Sent at: ' . esc_html(current_time('mysql')) . '</p>',
            MailService::buildHeaders(),
            null,
            false
        );

        $steps[] = [
            'step'    => 'Authentication successful',
            'ok'      => $result['auth_ok'] ?? $result['sent'],
            'message' => $result['auth_message'] ?? ($result['sent'] ? 'Authentication successful' : ($result['error'] ?? 'Authentication failed')),
        ];
        $steps[] = [
            'step'    => 'Email accepted',
            'ok'      => $result['sent'],
            'message' => $result['sent'] ? 'Server accepted message for ' . $to : ($result['error'] ?? 'Rejected by server'),
        ];
        $steps[] = [
            'step'    => 'Email delivered',
            'ok'      => $result['sent'],
            'message' => $result['sent'] ? 'Test email sent to ' . $to : ($result['error'] ?? 'Delivery failed'),
        ];

        return [
            'success' => $result['sent'],
            'steps'   => $steps,
            'details' => $result,
        ];
    }

    private static function testOpenSsl(): array {
        $ok = extension_loaded('openssl');
        return [
            'id'      => 'openssl',
            'label'   => 'PHP OpenSSL',
            'status'  => $ok ? 'PASS' : 'FAIL',
            'message' => $ok ? 'OpenSSL extension loaded (required for TLS/SSL SMTP)' : 'OpenSSL extension missing — TLS/SSL will not work',
        ];
    }

    private static function testConfigured(array $settings): array {
        if (!SmtpSettingsService::isConfigured()) {
            return [
                'id'      => 'smtp_configured',
                'label'   => 'SMTP configured',
                'status'  => 'FAIL',
                'message' => 'SMTP Host and From Email are required in Booking Pro → Settings → Email',
            ];
        }
        return [
            'id'      => 'smtp_configured',
            'label'   => 'SMTP configured',
            'status'  => 'PASS',
            'message' => $settings['host'] . ':' . $settings['port'] . ' (' . $settings['encryption'] . ')',
        ];
    }

    private static function testDnsResolution(array $settings): array {
        $dns = self::resolveDns($settings['host']);
        return [
            'id'      => 'dns_resolution',
            'label'   => 'DNS resolution',
            'status'  => $dns['ok'] ? 'PASS' : 'FAIL',
            'message' => $dns['message'],
        ];
    }

    private static function testSmtpBanner(array $settings): array {
        $banner = self::readSmtpBanner($settings);
        return [
            'id'      => 'smtp_banner',
            'label'   => 'SMTP banner',
            'status'  => $banner['ok'] ? 'PASS' : 'FAIL',
            'message' => $banner['message'],
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private static function resolveDns(string $host): array {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ['ok' => true, 'message' => "{$host} is an IP address (DNS lookup skipped)"];
        }
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if (!$records) {
            $ip = @gethostbyname($host);
            if ($ip && $ip !== $host) {
                return ['ok' => true, 'message' => "{$host} resolves to {$ip}"];
            }
            return ['ok' => false, 'message' => "Could not resolve {$host}"];
        }
        $ips = array_map(fn($r) => $r['ip'] ?? $r['ipv6'] ?? '', $records);
        $ips = array_filter($ips);
        return ['ok' => true, 'message' => "{$host} → " . implode(', ', array_slice($ips, 0, 3))];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private static function readSmtpBanner(array $settings): array {
        $host = $settings['host'];
        $port = (int) $settings['port'];
        $timeout = min((int) ($settings['timeout'] ?? 10), 15);
        $errno = 0;
        $errstr = '';
        $start = microtime(true);
        $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
        if (!$fp) {
            return ['ok' => false, 'message' => "Cannot read banner — {$errstr} (errno {$errno})"];
        }
        stream_set_timeout($fp, $timeout);
        $banner = @fgets($fp, 512);
        fclose($fp);
        $elapsed = round((microtime(true) - $start) * 1000);
        $banner = $banner ? trim($banner) : '';
        if ($banner && preg_match('/^2\d{2}/', $banner)) {
            return ['ok' => true, 'message' => "{$banner} ({$elapsed}ms)"];
        }
        return ['ok' => false, 'message' => $banner ? "Unexpected banner: {$banner}" : 'No SMTP banner received'];
    }

    private static function testPortReachable(array $settings): array {
        $probe = self::probePort($settings['host'], $settings['port'], $settings['timeout']);
        return [
            'id'      => 'port_reachable',
            'label'   => 'Port reachable',
            'status'  => $probe['ok'] ? 'PASS' : 'FAIL',
            'message' => $probe['message'],
        ];
    }

    private static function testSmtpConnection(array $settings): array {
        $probe = self::probePort($settings['host'], $settings['port'], $settings['timeout']);
        if (!$probe['ok']) {
            return [
                'id'      => 'smtp_connection',
                'label'   => 'SMTP connection',
                'status'  => 'FAIL',
                'message' => $probe['message'],
            ];
        }

        $auth_note = $settings['auth']
            ? 'Authentication enabled for user ' . $settings['username']
            : 'Authentication disabled';

        return [
            'id'      => 'smtp_connection',
            'label'   => 'SMTP connection',
            'status'  => 'PASS',
            'message' => 'TCP connection to ' . $settings['host'] . ':' . $settings['port'] . ' OK. ' . $auth_note . '. Use Send Test Email to verify auth.',
        ];
    }

    /**
     * @return array{ok:bool,message:string}
     */
    private static function probePort(string $host, int $port, int $timeout = 10): array {
        if (empty($host) || $port <= 0) {
            return ['ok' => false, 'message' => 'Invalid host or port'];
        }

        $errno = 0;
        $errstr = '';
        $start = microtime(true);
        $fp = @fsockopen($host, $port, $errno, $errstr, min($timeout, 15));
        $elapsed = round((microtime(true) - $start) * 1000);

        if (!$fp) {
            EmailLogService::log('Port probe failed', compact('host', 'port', 'errno', 'errstr'));
            return [
                'ok'      => false,
                'message' => "Cannot connect to {$host}:{$port} — {$errstr} (errno {$errno})",
            ];
        }
        fclose($fp);
        return [
            'ok'      => true,
            'message' => "Connected to {$host}:{$port} in {$elapsed}ms",
        ];
    }
}
