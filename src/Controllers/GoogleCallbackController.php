<?php

namespace ConsultationBookingPro\Controllers;

if (!defined('ABSPATH')) exit;

class GoogleCallbackController {

    public function __construct() {
        add_action('admin_init', [$this, 'handleCallback']);
    }

    /**
     * Intercept the OAuth callback
     */
    public function handleCallback() {
        if (!isset($_GET['page']) || $_GET['page'] !== 'cbp-google-callback') return;
        if (!current_user_can('manage_options')) return;

        $code = $_GET['code'] ?? '';
        $counselor_id = (int)($_GET['state'] ?? 0);

        if (!$code || !$counselor_id) {
            wp_die('Invalid OAuth state or missing code.');
        }

        try {
            $repo = new \ConsultationBookingPro\Repositories\CounselorRepository();
            $counselor = $repo->find($counselor_id);
            if (!$counselor) throw new \Exception('Counselor not found.');

            $settings = new \ConsultationBookingPro\Services\SettingsService();
            $creds = $settings->getGoogleCredentialsForCounselor($counselor);

            if (!$creds['client_id'] || !$creds['client_secret']) {
                throw new \Exception('Google Client ID/Secret missing. Configure them in Settings first.');
            }

            $authService = new \ConsultationBookingPro\Services\GoogleAuthService();
            $tokens = $authService->exchangeCode(
                $code,
                $creds['client_id'],
                $creds['client_secret'],
                $creds['redirect_uri']
            );

            if (!$tokens['success'] || empty($tokens['access_token'])) {
                throw new \Exception($tokens['error'] ?? 'Token exchange failed. Please try connecting again.');
            }

            $expiry = date('Y-m-d H:i:s', time() + ($tokens['expires_in'] ?? 3600));

            $update = [
                'google_access_token' => $tokens['access_token'],
                'google_token_expiry' => $expiry,
            ];

            if (!empty($tokens['refresh_token'])) {
                $update['google_refresh_token'] = $tokens['refresh_token'];
            }

            // Sync calendar id + timezone from Google so event times match the counselor calendar.
            $calendar_id = $counselor->google_calendar_id ?: 'primary';
            $cal = new \ConsultationBookingPro\Services\GoogleCalendarService($tokens['access_token'], $calendar_id);
            $meta = $cal->testConnection();
            if (!empty($meta['success']) && is_array($meta['data'] ?? null)) {
                $g_tz = $meta['data']['timeZone'] ?? '';
                if ($g_tz && \ConsultationBookingPro\Services\TimezoneHelper::isValid($g_tz)) {
                    $update['timezone'] = $g_tz;
                }
                if (empty($counselor->google_calendar_id)) {
                    $update['google_calendar_id'] = 'primary';
                }
            }

            $repo->update($counselor_id, $update);

            wp_redirect(admin_url('admin.php?page=cbp-counselors&sync=success'));
            exit;
        } catch (\Exception $e) {
            wp_die('Google Sync Error: ' . esc_html($e->getMessage()));
        }
    }
}
