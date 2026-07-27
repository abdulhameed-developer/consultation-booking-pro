<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

/**
 * Built-in SMTP mailer — configures PHPMailer only during plugin sends.
 */
class MailService {
    private static ?string $last_error = null;
    private static bool $sending = false;
    private static ?array $session_log = null;

    public static function init(): void {
        add_action('wp_mail_failed', [self::class, 'onMailFailed'], 10, 1);
    }

    public static function onMailFailed(\WP_Error $error): void {
        if (!self::$sending) {
            return;
        }
        self::$last_error = $error->get_error_message();
        $data = $error->get_error_data();
        if (is_array($data) && !empty($data['phpmailer_exception_code'])) {
            self::$last_error .= ' (code: ' . $data['phpmailer_exception_code'] . ')';
        }
        EmailLogService::log('wp_mail_failed', ['error' => self::$last_error, 'data' => $data]);
        EmailLogService::recordFailure(self::$last_error);
    }

    /**
     * Configure PHPMailer for this plugin's SMTP settings only.
     */
    public static function configurePhpMailer($phpmailer): void {
        if (!self::$sending) {
            return;
        }

        $settings = SmtpSettingsService::getSettings();

        if (empty($settings['host'])) {
            EmailLogService::log('SMTP not configured — cannot send');
            return;
        }

        try {
            $phpmailer->isSMTP();
            $phpmailer->Host       = $settings['host'];
            $phpmailer->Port       = (int) $settings['port'];
            $phpmailer->SMTPAuth   = (bool) $settings['auth'];
            $phpmailer->Username   = $settings['username'];
            $phpmailer->Password   = $settings['password'];
            $phpmailer->Timeout    = (int) ($settings['timeout'] ?? 30);
            $phpmailer->CharSet    = 'UTF-8';
            $phpmailer->Encoding   = 'base64';

            switch ($settings['encryption']) {
                case 'ssl':
                    $phpmailer->SMTPSecure = 'ssl';
                    break;
                case 'tls':
                    $phpmailer->SMTPSecure = 'tls';
                    break;
                default:
                    $phpmailer->SMTPSecure = '';
                    $phpmailer->SMTPAutoTLS = false;
                    break;
            }

            if (is_email($settings['from_email'])) {
                $phpmailer->From     = $settings['from_email'];
                $phpmailer->FromName = $settings['from_name'];
            }

            self::$session_log = [
                'host'        => $settings['host'],
                'port'        => $settings['port'],
                'encryption'  => $settings['encryption'],
                'auth'        => $settings['auth'],
                'username'    => $settings['username'],
            ];

            EmailLogService::log('PHPMailer configured', self::$session_log);
        } catch (\Exception $e) {
            EmailLogService::log('PHPMailer configure exception', ['error' => $e->getMessage()]);
        }
    }

    /**
     * @return array{sent:bool,error:?string,auth_ok?:bool,auth_message?:string,smtp:array}
     */
    public function send(
        string $to,
        string $subject,
        string $message,
        array $headers = [],
        ?int $booking_id = null,
        bool $queue_on_fail = true
    ): array {
        self::$last_error = null;

        if (!is_email($to)) {
            $error = 'Invalid recipient email address: ' . $to;
            EmailLogService::log('Send aborted', ['error' => $error]);
            return ['sent' => false, 'error' => $error, 'smtp' => self::getMailerInfo()];
        }

        if (!SmtpSettingsService::isConfigured()) {
            $error = 'Built-in SMTP is not configured. Go to Booking Pro → Settings → Email and enter SMTP Host, Port, and From Email.';
            EmailLogService::log('Send aborted', ['error' => $error]);
            return ['sent' => false, 'error' => $error, 'smtp' => self::getMailerInfo()];
        }

        $settings = SmtpSettingsService::getSettings();

        EmailLogService::log('Connection attempt', [
            'host'       => $settings['host'],
            'port'       => $settings['port'],
            'encryption' => $settings['encryption'],
            'recipient'  => $to,
            'subject'    => $subject,
        ]);

        self::$sending = true;
        add_action('phpmailer_init', [self::class, 'configurePhpMailer'], PHP_INT_MAX);

        $sent = wp_mail($to, $subject, $message, $headers);

        remove_action('phpmailer_init', [self::class, 'configurePhpMailer'], PHP_INT_MAX);
        self::$sending = false;

        $phpmailer_error = self::getPhpMailerError();
        $auth_ok = $sent || (!$phpmailer_error || !self::isAuthError($phpmailer_error));

        if ($sent) {
            EmailLogService::log('Email sent successfully', ['to' => $to, 'subject' => $subject]);
            EmailLogService::recordSuccess($to, $subject);
            LogService::info('Email sent', ['to' => $to]);
            return [
                'sent'          => true,
                'error'         => null,
                'auth_ok'       => true,
                'auth_message'  => 'Authentication successful',
                'smtp'          => self::getMailerInfo(),
            ];
        }

        $error = self::$last_error ?: $phpmailer_error ?: 'wp_mail() returned false';
        EmailLogService::log('Email send failed', [
            'to'        => $to,
            'subject'   => $subject,
            'error'     => $error,
            'exception' => $phpmailer_error,
        ]);
        EmailLogService::recordFailure($error);
        LogService::error('Email failed', ['to' => $to, 'error' => $error]);

        if ($queue_on_fail) {
            EmailQueueService::enqueue($to, $subject, $message, $headers, $booking_id, $error);
        }

        return [
            'sent'          => false,
            'error'         => $error,
            'auth_ok'       => $auth_ok,
            'auth_message'  => self::isAuthError($error) ? $error : ($auth_ok ? 'Authentication successful' : $error),
            'smtp'          => self::getMailerInfo(),
        ];
    }

    private static function isAuthError(string $msg): bool {
        $lower = strtolower($msg);
        return str_contains($lower, 'auth')
            || str_contains($lower, '535')
            || str_contains($lower, 'credentials')
            || str_contains($lower, 'username and password');
    }

    public static function getPhpMailerError(): ?string {
        global $phpmailer;
        if (isset($phpmailer) && is_object($phpmailer) && !empty($phpmailer->ErrorInfo)) {
            return $phpmailer->ErrorInfo;
        }
        return null;
    }

    /**
     * @return array{enabled:bool,host:?string,port:?int,encryption:?string,from:?string,mailer:string}
     */
    public static function getMailerInfo(): array {
        $s = SmtpSettingsService::getSettings();
        return [
            'enabled'    => SmtpSettingsService::isConfigured(),
            'host'       => $s['host'] ?: null,
            'port'       => $s['port'] ?: null,
            'encryption' => $s['encryption'] ?? null,
            'from'       => $s['from_email'] ?? null,
            'mailer'     => 'CBP Built-in SMTP (PHPMailer)',
        ];
    }

    public static function buildHeaders($counselor = null): array {
        $settings = SmtpSettingsService::getSettings();
        $from_name  = $settings['from_name'];
        $from_email = $settings['from_email'];
        if (!is_email($from_email)) {
            $from_email = get_option('admin_email');
        }

        $reply_email = !empty($settings['reply_to']) && is_email($settings['reply_to'])
            ? $settings['reply_to']
            : ($counselor->email ?? $from_email);
        $reply_name = $counselor->name ?? $from_name;

        return [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $from_name . ' <' . $from_email . '>',
            'Reply-To: ' . $reply_name . ' <' . $reply_email . '>',
        ];
    }
}
