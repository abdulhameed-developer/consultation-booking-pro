<?php

namespace ConsultationBookingPro\Services;

/**
 * Handles retrieval and saving of plugin settings.
 * Reads Google OAuth credentials from wp_options (admin UI) with fallback to cbp_settings table.
 */
class SettingsService {
    private $wpdb;
    private $table;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table = $wpdb->prefix . 'cbp_settings';
    }

    public function get(string $key, $default = '') {
        $val = $this->wpdb->get_var($this->wpdb->prepare(
            "SELECT setting_value FROM {$this->table} WHERE setting_key = %s",
            $key
        ));
        return $val !== null ? $val : $default;
    }

    public function getList(string $key, array $default = []) {
        $val = $this->get($key);
        if (!$val) return $default;

        $lines = explode("\n", $val);
        return array_values(array_filter(array_map('trim', $lines)));
    }

    public function update(string $key, string $value) {
        return $this->wpdb->replace($this->table, [
            'setting_key'   => $key,
            'setting_value' => $value
        ]);
    }

    /**
     * Google Client ID — primary source is wp_options (Settings page).
     */
    public function getGoogleClientId(): string {
        $from_options = get_option('cbp_google_client_id', '');
        if ($from_options) {
            return $from_options;
        }
        return $this->get('google_client_id', '');
    }

    /**
     * Google Client Secret — primary source is wp_options (Settings page).
     */
    public function getGoogleClientSecret(): string {
        $from_options = get_option('cbp_google_client_secret', '');
        if ($from_options) {
            return $from_options;
        }
        return $this->get('google_client_secret', '');
    }

    /**
     * Authorized OAuth redirect URI.
     */
    public function getGoogleRedirectUri(): string {
        $uri = get_option('cbp_google_redirect_uri', '');
        if ($uri) {
            return $uri;
        }
        return admin_url('admin.php?page=cbp-google-callback');
    }

    /**
     * Resolve Google credentials for a counselor (per-counselor override or global).
     */
    public function getGoogleCredentialsForCounselor($counselor): array {
        $client_id = ($counselor->google_client_id ?? '') ?: $this->getGoogleClientId();
        $client_secret = ($counselor->google_client_secret ?? '') ?: $this->getGoogleClientSecret();
        return [
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'redirect_uri'  => $this->getGoogleRedirectUri(),
        ];
    }
}
