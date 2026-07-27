<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

class DiagnosticsService {

    /**
     * Run all integration diagnostics.
     *
     * @return array<int,array{id:string,label:string,status:string,message:string,details?:array}>
     */
    public function runAll(): array {
        $tests = [];
        $tests[] = $this->testPhpExtensions();
        $tests[] = $this->testDatabase();
        $tests[] = $this->testLogFile();
        $tests[] = $this->testFilePermissions();
        $tests[] = $this->testWpCron();
        $tests[] = $this->testWordPressMail();
        $tests[] = $this->testSmtp();
        $tests[] = $this->testGoogleClientId();
        $tests[] = $this->testGoogleClientSecret();
        $tests[] = $this->testActiveCounselor();
        $tests[] = $this->testOAuthConnection();
        $tests[] = $this->testRefreshToken();
        $tests[] = $this->testAccessToken();
        $tests[] = $this->testCalendarId();
        $tests[] = $this->testGoogleCalendarApi();
        $tests = array_merge($tests, $this->testCalendarWorkflow());
        return $tests;
    }

    /**
     * Extended calendar workflow tests (create, verify, delete test event).
     *
     * @return array<int,array>
     */
    private function testCalendarWorkflow(): array {
        $c = $this->getActiveCounselor();
        if (!$c || empty($c->google_refresh_token)) {
            return [
                $this->fail('event_creation', 'Event creation', 'Skipped — OAuth not connected.'),
                $this->fail('meet_link', 'Meet link creation', 'Skipped — OAuth not connected.'),
                $this->fail('student_attendee', 'Student attendee invitation', 'Skipped — OAuth not connected.'),
                $this->fail('counselor_attendee', 'Counselor attendee invitation', 'Skipped — OAuth not connected.'),
            ];
        }

        $student_email = get_option('admin_email');
        $counselor_email = $c->email;
        $result = $this->testCalendarEvent($student_email, $counselor_email);

        if (!$result['success']) {
            $err = $result['error'] ?? 'Unknown error';
            return [
                $this->fail('event_creation', 'Event creation', $err),
                $this->fail('meet_link', 'Meet link creation', $err),
                $this->fail('student_attendee', 'Student attendee invitation', $err),
                $this->fail('counselor_attendee', 'Counselor attendee invitation', $err),
            ];
        }

        return [
            $this->pass('event_creation', 'Event creation', 'Test event created and deleted. ID: ' . $result['event_id']),
            $result['meet_link']
                ? $this->pass('meet_link', 'Meet link creation', $result['meet_link'])
                : $this->fail('meet_link', 'Meet link creation', 'Event created but no Google Meet link returned. Enable Google Meet in Google Cloud Console.'),
            $result['student_invited']
                ? $this->pass('student_attendee', 'Student attendee invitation', $student_email . ' added as attendee.')
                : $this->fail('student_attendee', 'Student attendee invitation', $student_email . ' was not found in event attendees.'),
            $result['counselor_invited']
                ? $this->pass('counselor_attendee', 'Counselor attendee invitation', $counselor_email . ' added as attendee. Google sends invite emails via sendUpdates=all.')
                : $this->fail('counselor_attendee', 'Counselor attendee invitation', $counselor_email . ' was not found in event attendees.'),
        ];
    }

    /**
     * Send test emails to student and counselor addresses.
     */
    public function testBookingEmails(string $student_email, string $counselor_email): array {
        $mail = new MailService();
        $headers = MailService::buildHeaders();

        $student = $mail->send(
            $student_email,
            'CBP Test — Student Confirmation Email',
            '<h2>Test Student Email</h2><p>If you received this, student confirmation emails are working.</p><p>Time: ' . esc_html(current_time('mysql')) . '</p>',
            $headers,
            null,
            false
        );

        $counselor = $mail->send(
            $counselor_email,
            'CBP Test — Counselor Notification Email',
            '<h2>Test Counselor Email</h2><p>If you received this, counselor notification emails are working.</p><p>Time: ' . esc_html(current_time('mysql')) . '</p>',
            $headers,
            null,
            false
        );

        return [
            'student'   => $student,
            'counselor' => $counselor,
        ];
    }

    /**
     * Create, verify, and delete a test calendar event.
     */
    public function testCalendarEvent(string $student_email, string $counselor_email): array {
        $counselor = $this->getActiveCounselor();
        if (!$counselor) {
            return ['success' => false, 'error' => 'No active counselor found.'];
        }

        $token_result = $this->resolveAccessToken($counselor);
        if (!$token_result['success']) {
            return ['success' => false, 'error' => $token_result['error']];
        }

        $calendar_id = $counselor->google_calendar_id ?: 'primary';
        $calendar = new GoogleCalendarService($token_result['token'], $calendar_id);
        $counselor_arr = (array) $counselor;

        $create = $calendar->createTestEvent($student_email, $counselor_email, $counselor_arr);
        if (!$create['success']) {
            return [
                'success' => false,
                'error'   => $create['error'] ?? 'Event creation failed.',
                'step'    => 'create',
            ];
        }

        $event_id = $create['id'];
        $verify = $calendar->getEvent($event_id);
        $attendee_emails = [];
        if ($verify['success'] && !empty($verify['data']['attendees'])) {
            $attendee_emails = array_map(fn($a) => $a['email'] ?? '', $verify['data']['attendees']);
        }

        $student_invited = in_array(strtolower($student_email), array_map('strtolower', $attendee_emails), true);
        $counselor_invited = in_array(strtolower($counselor_email), array_map('strtolower', $attendee_emails), true);
        $meet_link = $create['meetLink'] ?? $create['hangoutLink'] ?? null;

        $delete = $calendar->deleteEvent($event_id);

        return [
            'success'           => true,
            'event_id'          => $event_id,
            'html_link'         => $create['htmlLink'] ?? null,
            'meet_link'         => $meet_link,
            'student_invited'   => $student_invited,
            'counselor_invited' => $counselor_invited,
            'attendees'         => $attendee_emails,
            'event_verified'    => $verify['success'],
            'event_deleted'     => $delete['success'],
            'delete_error'      => $delete['success'] ? null : ($delete['error'] ?? 'Delete failed'),
            'create_details'    => $create,
        ];
    }

    private function pass(string $id, string $label, string $message, array $details = []): array {
        return ['id' => $id, 'label' => $label, 'status' => 'PASS', 'message' => $message, 'details' => $details];
    }

    private function fail(string $id, string $label, string $message, array $details = []): array {
        LogService::error("Diagnostic FAIL: {$label} — {$message}", $details);
        return ['id' => $id, 'label' => $label, 'status' => 'FAIL', 'message' => $message, 'details' => $details];
    }

    private function testPhpExtensions(): array {
        $required = ['curl', 'json', 'openssl', 'mbstring'];
        $missing = [];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }
        if ($missing) {
            return $this->fail('php_extensions', 'Required PHP extensions', 'Missing: ' . implode(', ', $missing));
        }
        return $this->pass('php_extensions', 'Required PHP extensions', 'curl, json, openssl, mbstring loaded');
    }

    private function testDatabase(): array {
        global $wpdb;
        $bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cbp_bookings");
        $counselors = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cbp_counselors");
        if ($wpdb->last_error) {
            return $this->fail('database', 'Database connection', $wpdb->last_error);
        }
        return $this->pass('database', 'Database connection', "Connected. {$counselors} counselor(s), {$bookings} booking(s).");
    }

    private function testLogFile(): array {
        $dir = LogService::getLogDir();
        if (!is_dir($dir) && !wp_mkdir_p($dir)) {
            return $this->fail('log_file', 'Plugin log files', 'Cannot create log directory: ' . $dir);
        }
        if (!is_writable($dir)) {
            return $this->fail('log_file', 'Plugin log files', 'Log directory is not writable: ' . $dir);
        }
        foreach (LogService::getAllChannels() as $ch) {
            LogService::info("Diagnostics test — {$ch} channel", [], $ch);
        }
        return $this->pass('log_file', 'Plugin log files', $dir . ' (email, google, api, booking, plugin)');
    }

    private function testFilePermissions(): array {
        $dir = LogService::getLogDir();
        $upload = wp_upload_dir();
        if (!empty($upload['error'])) {
            return $this->fail('file_permissions', 'File permissions', $upload['error']);
        }
        if (!is_writable($upload['basedir'])) {
            return $this->fail('file_permissions', 'File permissions', 'Uploads directory not writable: ' . $upload['basedir']);
        }
        if (!is_writable($dir)) {
            return $this->fail('file_permissions', 'File permissions', 'Log directory not writable: ' . $dir);
        }
        return $this->pass('file_permissions', 'File permissions', 'Uploads and log directory writable');
    }

    private function testWpCron(): array {
        if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) {
            return $this->fail('wp_cron', 'WP Cron', 'DISABLE_WP_CRON is true — scheduled tasks may not run unless external cron triggers wp-cron.php');
        }
        $next = wp_next_scheduled('cbp_process_email_queue');
        if (!$next) {
            wp_schedule_event(time() + 60, 'hourly', 'cbp_process_email_queue');
            $next = wp_next_scheduled('cbp_process_email_queue');
        }
        $msg = $next
            ? 'WP Cron enabled. Email queue hook scheduled for ' . date('Y-m-d H:i:s', $next)
            : 'WP Cron enabled but email queue hook could not be scheduled';
        return $this->pass('wp_cron', 'WP Cron', $msg);
    }

    private function testWordPressMail(): array {
        if (!SmtpSettingsService::isConfigured()) {
            return $this->fail('wp_mail', 'Built-in SMTP mail', 'SMTP not configured in Booking Pro → Settings → Email');
        }
        $to = get_option('admin_email');
        $mail = new MailService();
        $result = $mail->send(
            $to,
            'CBP Diagnostics — SMTP test',
            '<p>Automated mail test from Consultation Booking Pro Diagnostics.</p>',
            MailService::buildHeaders(),
            null,
            false
        );
        if ($result['sent']) {
            return $this->pass('wp_mail', 'Built-in SMTP mail', 'Test email sent to ' . $to);
        }
        return $this->fail('wp_mail', 'Built-in SMTP mail', $result['error'] ?? 'Send failed', $result);
    }

    private function testSmtp(): array {
        $tests = SmtpDiagnosticsService::runDiagnostics();
        foreach ($tests as $t) {
            if ($t['id'] === 'smtp_configured') {
                return $t['status'] === 'PASS'
                    ? $this->pass('smtp', 'SMTP configuration', $t['message'])
                    : $this->fail('smtp', 'SMTP configuration', $t['message']);
            }
        }
        return $this->fail('smtp', 'SMTP configuration', 'Unable to read SMTP settings');
    }

    private function testGoogleClientId(): array {
        $settings = new SettingsService();
        $id = $settings->getGoogleClientId();
        if (!$id) {
            return $this->fail('google_client_id', 'Google Client ID', 'Not configured. Go to Booking Pro → Settings.');
        }
        return $this->pass('google_client_id', 'Google Client ID', 'Configured (' . substr($id, 0, 20) . '…)');
    }

    private function testGoogleClientSecret(): array {
        $settings = new SettingsService();
        $secret = $settings->getGoogleClientSecret();
        if (!$secret) {
            return $this->fail('google_client_secret', 'Google Client Secret', 'Not configured. Go to Booking Pro → Settings.');
        }
        return $this->pass('google_client_secret', 'Google Client Secret', 'Configured (' . strlen($secret) . ' characters)');
    }

    private function testActiveCounselor(): array {
        $c = $this->getActiveCounselor();
        if (!$c) {
            return $this->fail('active_counselor', 'Active counselor', 'No active counselor. Go to Booking Pro → Counselors.');
        }
        return $this->pass('active_counselor', 'Active counselor', $c->name . ' <' . $c->email . '>');
    }

    private function testOAuthConnection(): array {
        $c = $this->getActiveCounselor();
        if (!$c) {
            return $this->fail('oauth_connection', 'OAuth connection', 'No active counselor.');
        }
        if (empty($c->google_refresh_token)) {
            return $this->fail(
                'oauth_connection',
                'OAuth connection',
                'No refresh token stored. Click "Connect Google Calendar" in Settings or Counselors.',
                ['counselor_id' => $c->id]
            );
        }
        return $this->pass('oauth_connection', 'OAuth connection', 'Refresh token stored for counselor #' . $c->id);
    }

    private function testRefreshToken(): array {
        $c = $this->getActiveCounselor();
        if (!$c || empty($c->google_refresh_token)) {
            return $this->fail('refresh_token', 'Refresh token', 'No refresh token available.');
        }
        $settings = new SettingsService();
        $creds = $settings->getGoogleCredentialsForCounselor($c);
        $auth = new GoogleAuthService();
        $result = $auth->refreshToken($c->google_refresh_token, $creds['client_id'], $creds['client_secret']);
        if (!$result['success']) {
            return $this->fail('refresh_token', 'Refresh token', $result['error'] ?? 'Refresh failed', $result);
        }
        return $this->pass('refresh_token', 'Refresh token', 'Token refresh succeeded. Expires in ' . ($result['expires_in'] ?? '?') . 's.');
    }

    private function testAccessToken(): array {
        $c = $this->getActiveCounselor();
        if (!$c) {
            return $this->fail('access_token', 'Access token', 'No active counselor.');
        }
        $result = $this->resolveAccessToken($c);
        if (!$result['success']) {
            return $this->fail('access_token', 'Access token', $result['error']);
        }
        $expiry = $c->google_token_expiry ?? 'unknown';
        return $this->pass('access_token', 'Access token', 'Valid access token obtained. DB expiry: ' . $expiry);
    }

    private function testCalendarId(): array {
        $c = $this->getActiveCounselor();
        if (!$c) {
            return $this->fail('calendar_id', 'Calendar ID', 'No active counselor.');
        }
        $cal = $c->google_calendar_id ?: 'primary';
        return $this->pass('calendar_id', 'Calendar ID', $cal);
    }

    private function testGoogleCalendarApi(): array {
        $c = $this->getActiveCounselor();
        if (!$c) {
            return $this->fail('calendar_api', 'Google Calendar API', 'No active counselor.');
        }
        $token_result = $this->resolveAccessToken($c);
        if (!$token_result['success']) {
            return $this->fail('calendar_api', 'Google Calendar API', $token_result['error']);
        }
        $calendar_id = $c->google_calendar_id ?: 'primary';
        $calendar = new GoogleCalendarService($token_result['token'], $calendar_id);
        $result = $calendar->testConnection();
        if (!$result['success']) {
            return $this->fail('calendar_api', 'Google Calendar API', $result['error'] ?? 'API test failed', $result);
        }
        $name = $result['data']['summary'] ?? 'Unknown';
        $tz = $result['data']['timeZone'] ?? 'UTC';
        return $this->pass('calendar_api', 'Google Calendar API', "Calendar \"{$name}\" accessible (timezone: {$tz})");
    }

    /**
     * @return object|null
     */
    private function getActiveCounselor() {
        global $wpdb;
        return $wpdb->get_row(
            "SELECT * FROM {$wpdb->prefix}cbp_counselors WHERE status = 'active' ORDER BY id ASC LIMIT 1"
        );
    }

    /**
     * @return array{success:bool,token?:string,error?:string}
     */
    public function resolveAccessToken($counselor): array {
        if (
            !empty($counselor->google_access_token)
            && !empty($counselor->google_token_expiry)
            && strtotime($counselor->google_token_expiry) > time() + 300
        ) {
            return ['success' => true, 'token' => $counselor->google_access_token];
        }

        if (empty($counselor->google_refresh_token)) {
            return ['success' => false, 'error' => 'Missing refresh token. Connect Google Calendar in admin.'];
        }

        $settings = new SettingsService();
        $creds = $settings->getGoogleCredentialsForCounselor($counselor);
        if (!$creds['client_id'] || !$creds['client_secret']) {
            return ['success' => false, 'error' => 'Missing Google Client ID or Client Secret in Settings.'];
        }

        $auth = new GoogleAuthService();
        $result = $auth->refreshToken($counselor->google_refresh_token, $creds['client_id'], $creds['client_secret']);
        if (!$result['success']) {
            return ['success' => false, 'error' => $result['error']];
        }

        global $wpdb;
        $expiry = date('Y-m-d H:i:s', time() + ($result['expires_in'] ?? 3600));
        $wpdb->update($wpdb->prefix . 'cbp_counselors', [
            'google_access_token' => $result['access_token'],
            'google_token_expiry' => $expiry,
        ], ['id' => $counselor->id]);

        return ['success' => true, 'token' => $result['access_token']];
    }
}
