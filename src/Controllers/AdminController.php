<?php

namespace ConsultationBookingPro\Controllers;

if (!defined('ABSPATH')) exit;

class AdminController {
    
    public static function register() {
        $instance = new self();
        add_action('admin_menu', [$instance, 'initMenu']);
        add_action('admin_init', [$instance, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$instance, 'enqueueAssets']);
        add_action('rest_api_init', [$instance, 'registerAdminRoutes']);
        add_action('rest_api_init', [$instance, 'registerPublicRoutes']);
        add_action('admin_post_cbp_save_counselor', [$instance, 'handleSaveCounselor']);
        add_action('admin_post_cbp_delete_counselor', [$instance, 'handleDeleteCounselor']);

        // AJAX Handlers
        add_action('wp_ajax_cbp_save_counselor', [$instance, 'ajaxSaveCounselor']);
        add_action('wp_ajax_cbp_get_available_slots', [$instance, 'ajaxGetAvailableSlots']);
        add_action('wp_ajax_nopriv_cbp_get_available_slots', [$instance, 'ajaxGetAvailableSlots']);
        
        new GoogleCallbackController();
    }

    public function registerSettings() {
        register_setting('cbp_settings_group', 'cbp_google_client_id');
        register_setting('cbp_settings_group', 'cbp_google_client_secret');
        register_setting('cbp_settings_group', 'cbp_google_redirect_uri');
        register_setting('cbp_settings_group', 'cbp_email_from_name');
        register_setting('cbp_settings_group', 'cbp_email_from_email');
        register_setting('cbp_settings_group', 'cbp_email_reply_to', [
            'sanitize_callback' => 'sanitize_email',
        ]);
        register_setting('cbp_settings_group', 'cbp_shortcode');

        register_setting('cbp_settings_group', 'cbp_smtp_host', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('cbp_settings_group', 'cbp_smtp_port', [
            'sanitize_callback' => 'absint',
        ]);
        register_setting('cbp_settings_group', 'cbp_smtp_encryption', [
            'sanitize_callback' => function ($v) {
                return in_array($v, ['none', 'ssl', 'tls'], true) ? $v : 'tls';
            },
        ]);
        register_setting('cbp_settings_group', 'cbp_smtp_username', [
            'sanitize_callback' => 'sanitize_text_field',
        ]);
        register_setting('cbp_settings_group', 'cbp_smtp_password', [
            'sanitize_callback' => [\ConsultationBookingPro\Services\SmtpSettingsService::class, 'sanitizePassword'],
        ]);
        register_setting('cbp_settings_group', 'cbp_smtp_auth', [
            'sanitize_callback' => function ($v) {
                return $v ? 1 : 0;
            },
        ]);
        register_setting('cbp_settings_group', 'cbp_smtp_timeout', [
            'sanitize_callback' => function ($v) {
                $v = absint($v);
                return $v > 0 ? $v : 30;
            },
        ]);
    }

    public function handleDeleteCounselor() {
        check_admin_referer('cbp_delete_counselor');
        if (!current_user_can('manage_options')) wp_die('Forbidden');

        global $wpdb;
        $id = (int)($_GET['id'] ?? 0);
        if ($id) {
            $wpdb->delete($wpdb->prefix . 'cbp_counselors', ['id' => $id]);
        }
        wp_redirect(admin_url('admin.php?page=cbp-counselors'));
        exit;
    }

    public function handleSaveCounselor() {
        check_admin_referer('cbp_save_counselor');
        if (!current_user_can('manage_options')) wp_die('Forbidden');

        $this->saveCounselorData($_POST);

        wp_redirect(admin_url('admin.php?page=cbp-counselors'));
        exit;
    }

    public function ajaxSaveCounselor() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden']);
        }

        try {
            $id = $this->saveCounselorData($_POST);
            
            global $wpdb;
            $counselor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}cbp_counselors WHERE id = %d", $id));
            
            wp_send_json_success([
                'message' => 'Counselor saved successfully.',
                'counselor' => $counselor
            ]);
        } catch (\Exception $e) {
            wp_send_json_error(['message' => $e->getMessage()]);
        }
    }

    private function saveCounselorData($params) {
    global $wpdb;
    $id = (int)($params['id'] ?? 0);
    $status = sanitize_text_field($params['status'] ?? 'active');

    // Validation
    if (empty($params['name'])) throw new \Exception('Name is required.');
    if (empty($params['email']) || !is_email($params['email'])) throw new \Exception('Valid email is required.');

    // Handle schedule - remove any escaping slashes
    $schedule_raw = $params['schedule'] ?? '{}';
    if (is_string($schedule_raw)) {
        // Remove backslashes from POST escaping
        $schedule_raw = stripslashes($schedule_raw);
        // Verify it's valid JSON
        $test = json_decode($schedule_raw, true);
        if (!$test) {
            // If not valid JSON, encode the raw value
            $schedule_raw = json_encode($schedule_raw);
        }
    } else {
        $schedule_raw = json_encode($schedule_raw);
    }

    // Handle custom slots - remove any escaping
    $custom_slots_raw = $params['custom_slots_json'] ?? '{}';
    if (is_string($custom_slots_raw)) {
        $custom_slots_raw = stripslashes($custom_slots_raw);
        $test = json_decode($custom_slots_raw, true);
        if (!$test) {
            $custom_slots_raw = '{}';
        }
    }

    $data = [
        'name'                 => sanitize_text_field($params['name']),
        'email'                => sanitize_email($params['email']),
        'google_calendar_id'   => sanitize_text_field($params['google_calendar_id'] ?: ''),
        'timezone'             => \ConsultationBookingPro\Services\TimezoneHelper::resolve($params['timezone'] ?? ''),
        'availability_mode'    => sanitize_text_field($params['availability_mode'] ?: 'auto'),
        'availability_schedule'=> $schedule_raw,
        'custom_slots'         => $custom_slots_raw,
        'slot_duration'        => (int)($params['slot_duration'] ?? 30),
        'buffer_time'          => (int)($params['buffer_time'] ?? 0),
        'max_bookings_per_day' => (int)($params['max_bookings_per_day'] ?? 10),
        'status'               => $status,
        'updated_at'           => current_time('mysql')
    ];

    // Debug log
    error_log('CBP Save Counselor - Schedule: ' . $data['availability_schedule']);
    error_log('CBP Save Counselor - Custom Slots: ' . $data['custom_slots']);
    error_log('CBP Save Counselor - Mode: ' . $data['availability_mode']);

    // If activating this one, deactivate others
    if ($status === 'active') {
        $wpdb->update($wpdb->prefix . 'cbp_counselors', ['status' => 'inactive'], ['status' => 'active']);
    }

    if ($id) {
        $result = $wpdb->update($wpdb->prefix . 'cbp_counselors', $data, ['id' => $id]);
        error_log('CBP Save Counselor - Update result: ' . var_export($result, true));
    } else {
        $data['created_at'] = current_time('mysql');
        $result = $wpdb->insert($wpdb->prefix . 'cbp_counselors', $data);
        $id = $wpdb->insert_id;
        error_log('CBP Save Counselor - Insert result: ' . var_export($result, true) . ', ID: ' . $id);
    }

    return $id;
}

        public function ajaxGetAvailableSlots() {
        // Use WordPress nonce verification - the nonce is sent as 'nonce' parameter
        $nonce = $_POST['nonce'] ?? $_REQUEST['nonce'] ?? '';
        
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            // Try alternative nonce
            if (!wp_verify_nonce($nonce, 'cbp_booking_nonce')) {
                wp_send_json_error(['message' => 'Security check failed. Please refresh the page.']);
                return;
            }
        }
        
        $date = sanitize_text_field($_POST['date'] ?? '');
        if (!$date) {
            wp_send_json_error(['message' => 'Invalid date provided.']);
            return;
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            wp_send_json_error(['message' => 'Invalid date format. Use YYYY-MM-DD.']);
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'cbp_counselors';
        $counselor = $wpdb->get_row("SELECT * FROM $table WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        
        if (!$counselor) {
            wp_send_json_error(['message' => 'No active counselor found. Please set up a counselor first.']);
            return;
        }

        $availService = new \ConsultationBookingPro\Services\AvailabilityService();
        $slots = $availService->getAvailableSlots($date, $counselor->id);
        $timezone = \ConsultationBookingPro\Services\TimezoneHelper::fromCounselor($counselor);

        wp_send_json_success([
            'slots'    => $slots,
            'timezone' => $timezone,
            'timezone_label' => \ConsultationBookingPro\Services\TimezoneHelper::label($timezone),
        ]);
    }

    public function initMenu() {
        add_menu_page(
            'Booking Pro',
            'Booking Pro',
            'manage_options',
            'cbp-dashboard',
            [$this, 'renderDashboard'],
            'dashicons-calendar-alt',
            25
        );

        add_submenu_page('cbp-dashboard', 'Dashboard', 'Dashboard', 'manage_options', 'cbp-dashboard', [$this, 'renderDashboard']);
        add_submenu_page('cbp-dashboard', 'Bookings', 'Bookings', 'manage_options', 'cbp-bookings', [$this, 'renderBookings']);
        add_submenu_page('cbp-dashboard', 'Counselors', 'Counselors', 'manage_options', 'cbp-counselors', [$this, 'renderCounselors']);
        add_submenu_page('cbp-dashboard', 'Form Builder', 'Form Builder', 'manage_options', 'cbp-form-builder', [$this, 'renderFormBuilder']);
        add_submenu_page('cbp-dashboard', 'Settings', 'Settings', 'manage_options', 'cbp-settings', [$this, 'renderSettings']);
        add_submenu_page('cbp-dashboard', 'Diagnostics', 'Diagnostics', 'manage_options', 'cbp-diagnostics', [$this, 'renderDiagnostics']);
        
        add_submenu_page(null, 'Google Callback', 'Google Callback', 'manage_options', 'cbp-google-callback', '__return_null');
    }

    public function renderBookings() {
        if (isset($_GET['action']) && $_GET['action'] === 'export_csv') {
            $this->handleExportCSV();
        }
        require CBP_PATH . 'templates/admin/bookings.php';
    }

    private function handleExportCSV() {
        check_admin_referer('cbp_export_csv');
        if (!current_user_can('manage_options')) wp_die('Forbidden');

        $repo = new \ConsultationBookingPro\Repositories\BookingRepository();
        $bookings = $repo->getAll();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bookings-export-' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM for Excel
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        fputcsv($output, [
            'Booking ID', 'Date', 'Time', 'Student Name', 'Email', 'Phone', 
            'Country', 'Study Level', 'Field', 'Message', 'Counselor', 'Status', 'Created At'
        ]);

        foreach ($bookings as $b) {
            fputcsv($output, [
                $b->id, $b->booking_date, $b->booking_time, $b->student_name, $b->student_email, 
                $b->student_phone, $b->country, $b->study_level, $b->interested_field,
                $b->message ?? '', $b->counselor_name ?? 'N/A', $b->status, $b->created_at
            ]);
        }
        fclose($output);
        exit;
    }

    public function registerPublicRoutes() {
        register_rest_route('cbp/v1', '/book', [
            'methods' => 'POST',
            'callback' => [$this, 'handlePublicBooking'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbp/v1', '/countries', [
            'methods' => 'GET',
            'callback' => [$this, 'getPublicCountries'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function handlePublicBooking(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        if (!empty($data['hp_field'])) {
            return new \WP_Error('spam', 'Bot detected', ['status' => 403]);
        }

        $bookingService = new \ConsultationBookingPro\Services\BookingService();
        $result = $bookingService->createBooking($data);

        if (is_wp_error($result)) {
            return $result;
        }

        $date_formatted = date('l, F j, Y', strtotime($result['booking_date']));

        return rest_ensure_response([
            'success'              => true,
            'booking_id'           => $result['booking_id'],
            'message'              => 'Booking confirmed.',
            'booking_date'         => $result['booking_date'],
            'booking_time'         => $result['booking_time'],
            'booking_date_formatted' => $date_formatted,
            'booking_time_formatted' => $result['booking_time'],
            'student_email_sent'   => $result['student_email_sent'],
            'counselor_email_sent' => $result['counselor_email_sent'],
            'student_email_error'  => $result['student_email_error'],
            'counselor_email_error'=> $result['counselor_email_error'],
            'calendar_synced'      => $result['calendar_synced'],
            'calendar_error'       => $result['calendar_error'],
            'google_event_link'    => $result['google_event_link'],
            'google_meet_link'     => $result['google_meet_link'],
            'google_event_id'      => $result['google_event_id'],
        ]);
    }

    public function getPublicCountries() {
        $file = CBP_PATH . 'assets/countries.json';
        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true);
            return rest_ensure_response($data);
        }
        return rest_ensure_response([]);
    }

    public function registerAdminRoutes() {
        register_rest_route('cbp/v1', '/form-schema', [
            'methods'  => 'POST',
            'callback' => [$this, 'updateFormSchema'],
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ]);

        register_rest_route('cbp/v1', '/files', [
            'methods' => 'GET',
            'callback' => [$this, 'listFiles'],
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ]);

        register_rest_route('cbp/v1', '/file-content', [
            'methods' => 'GET',
            'callback' => [$this, 'getFileContent'],
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ]);

        register_rest_route('cbp/v1', '/save-file', [
            'methods' => 'POST',
            'callback' => [$this, 'saveFile'],
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ]);

        register_rest_route('cbp/v1', '/toggle-counselor', [
            'methods'  => 'POST',
            'callback' => [$this, 'toggleCounselorStatus'],
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ]);

        register_rest_route('cbp/v1', '/test-calendar', [
            'methods'  => 'POST',
            'callback' => [$this, 'testCalendarConnection'],
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ]);

        register_rest_route('cbp/v1', '/download-files', [
            'methods'  => 'GET',
            'callback' => [$this, 'downloadFilesAsZip'],
            'permission_callback' => function() { return current_user_can('manage_options'); }
        ]);
    }

    public function downloadFilesAsZip() {
        $zip = new \ZipArchive();
        $filename = 'cbp-form-files-' . date('Y-m-d') . '.zip';
        $filepath = sys_get_temp_dir() . '/' . $filename;
        
        if ($zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            $base = CBP_PATH . 'form-files';
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($base));
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($base) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
            $zip->close();
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        unlink($filepath);
        exit;
    }

    public function testCalendarConnection(\WP_REST_Request $request) {
        $calendar_id = $request->get_param('calendar_id');
        if (!$calendar_id) {
            return rest_ensure_response(['success' => false, 'error' => 'Please enter a Calendar ID.']);
        }

        $repo = new \ConsultationBookingPro\Repositories\CounselorRepository();
        $counselor = $repo->getDefault();
        
        if (!$counselor || !$counselor->google_refresh_token) {
            return rest_ensure_response(['success' => false, 'error' => 'Google account not connected. Please connect first.']);
        }

        $availService = new \ConsultationBookingPro\Services\AvailabilityService();
        $accessToken = $availService->getRefreshedToken($counselor);

        if (!$accessToken) {
            return rest_ensure_response(['success' => false, 'error' => 'Could not refresh Google access token. Please reconnect.']);
        }

        $googleService = new \ConsultationBookingPro\Services\GoogleCalendarService($accessToken, $calendar_id);
        
        try {
            $metadata = $googleService->testConnection();
            if (!$metadata['success']) {
                return rest_ensure_response(['success' => false, 'error' => $metadata['error'] ?? 'Connection failed.']);
            }
            return rest_ensure_response([
                'success' => true,
                'name'    => $metadata['data']['summary'] ?? 'Unknown',
                'timezone'=> $metadata['data']['timeZone'] ?? 'UTC'
            ]);
        } catch (\Exception $e) {
            $code = (int) $e->getCode();
            if ($code === 404) {
                return rest_ensure_response(['success' => false, 'error' => 'Calendar not found. Please check the ID.']);
            }
            if ($code === 403) {
                return rest_ensure_response(['success' => false, 'error' => 'Access denied. Ensure the calendar is shared correctly.']);
            }
            if ($code === 401) {
                return rest_ensure_response(['success' => false, 'error' => 'Authentication failed. Please reconnect Google account.']);
            }
            if ($code === 400) {
                return rest_ensure_response(['success' => false, 'error' => 'Invalid Calendar ID format.']);
            }
            return rest_ensure_response(['success' => false, 'error' => 'Connection failed: ' . $e->getMessage()]);
        }
    }

    public function toggleCounselorStatus(\WP_REST_Request $request) {
        $id = (int)$request->get_param('id');
        $status = $request->get_param('status');
        
        global $wpdb;
        $table = $wpdb->prefix . 'cbp_counselors';

        if ($status === 'active') {
            // Deactivate all others
            $wpdb->update($table, ['status' => 'inactive'], ['status' => 'active']);
        }
        
        $wpdb->update($table, ['status' => $status], ['id' => $id]);
        
        return rest_ensure_response(['success' => true]);
    }

    public function listFiles() {
        $base = CBP_PATH . 'form-files';
        if (!is_dir($base)) {
            mkdir($base, 0755, true);
            file_put_contents($base . '/form-schema.json', json_encode((new \ConsultationBookingPro\Services\FormService())->getSchema(), JSON_PRETTY_PRINT));
            file_put_contents($base . '/form-styles.css', "/* Custom Form CSS */\n.cbp-booking-app {\n  --cbp-primary: #d63638;\n}");
            file_put_contents($base . '/form-script.js', "// Custom Form JavaScript\ndocument.addEventListener('cbp_form_loaded', () => {\n  console.log('Form loaded');\n});");
        }
        $files = $this->scanDir($base);
        return rest_ensure_response($files);
    }

    private function scanDir($dir, $relative = '') {
        $result = [];
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $full = $dir . '/' . $item;
            $rel = $relative ? $relative . '/' . $item : $item;
            if (is_dir($full)) {
                $result[] = [
                    'name' => $item,
                    'type' => 'dir',
                    'path' => $rel,
                    'children' => $this->scanDir($full, $rel)
                ];
            } else {
                $result[] = [
                    'name' => $item,
                    'type' => 'file',
                    'path' => $rel
                ];
            }
        }
        return $result;
    }

    public function getFileContent(\WP_REST_Request $request) {
        $path = $request->get_param('path');
        $full = CBP_PATH . 'form-files/' . ltrim($path, '/');
        if (!file_exists($full)) {
            return new \WP_Error('invalid_path', 'Invalid file path');
        }
        return rest_ensure_response(['content' => file_get_contents($full)]);
    }

    public function saveFile(\WP_REST_Request $request) {
        $path = $request->get_param('path');
        $content = $request->get_param('content');
        $full = CBP_PATH . 'form-files/' . ltrim($path, '/');
        
        file_put_contents($full, $content);
        return rest_ensure_response(['success' => true]);
    }

    public function updateFormSchema(\WP_REST_Request $request) {
        $schema = $request->get_json_params();
        $service = new \ConsultationBookingPro\Services\FormService();
        $service->saveSchema($schema);
        return rest_ensure_response(['success' => true]);
    }

    public function enqueueAssets($hook) {
        if (strpos($hook, 'cbp-') === false) return;

        $ver = CBP_VERSION . '.' . filemtime(CBP_PATH . 'assets/admin.css');
        wp_enqueue_style('cbp-admin-css', CBP_URL . 'assets/admin.css', [], $ver);
        
        if (strpos($hook, 'cbp-form-builder') !== false) {
            wp_enqueue_script('sortablejs', 'https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js', [], '1.15.0', true);
        }

        $js_ver = CBP_VERSION . '.' . filemtime(CBP_PATH . 'assets/admin.js');
        wp_enqueue_script('cbp-admin-js', CBP_URL . 'assets/admin.js', ['jquery'], $js_ver, true);

        if (strpos($hook, 'cbp-settings') !== false) {
            $settings_js = CBP_PATH . 'assets/admin-settings.js';
            wp_enqueue_script(
                'cbp-admin-settings',
                CBP_URL . 'assets/admin-settings.js',
                ['jquery', 'cbp-admin-js'],
                CBP_VERSION . '.' . filemtime($settings_js),
                true
            );
        }
        
        $formService = new \ConsultationBookingPro\Services\FormService();
        wp_localize_script('cbp-admin-js', 'cbpData', [
            'apiUrl' => esc_url_raw(rest_url('cbp/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'googleClientId' => (new \ConsultationBookingPro\Services\SettingsService())->getGoogleClientId(),
            'googleRedirectUri' => (new \ConsultationBookingPro\Services\SettingsService())->getGoogleRedirectUri(),
            'disconnectNonce' => wp_create_nonce('cbp_disconnect_google'),
            'adminUrl' => admin_url('admin.php'),
        ]);
        
        wp_localize_script('cbp-admin-js', 'cbpInitialSchema', $formService->getSchema());
    }

    public function renderDashboard() { require CBP_PATH . 'templates/admin/dashboard.php'; }
    public function renderCounselors() { 
        global $wpdb;
        if (isset($_GET['action']) && $_GET['action'] === 'disconnect' && isset($_GET['id'])) {
            check_admin_referer('cbp_disconnect_google');
            $wpdb->update($wpdb->prefix . 'cbp_counselors', ['google_refresh_token' => null, 'google_access_token' => null, 'google_token_expiry' => null], ['id' => (int)$_GET['id']]);
            wp_redirect(admin_url('admin.php?page=cbp-counselors')); exit;
        }
        require CBP_PATH . 'templates/admin/counselors.php'; 
    }
    public function renderFormBuilder() { require CBP_PATH . 'templates/admin/form-builder.php'; }
    public function renderSettings() {
        require CBP_PATH . 'templates/admin/settings.php';
    }

    public function renderDiagnostics() {
        require CBP_PATH . 'templates/admin/diagnostics.php';
    }
	
    public function __construct() {
        add_action('wp_ajax_cbp_get_booking_details', [$this, 'ajaxGetBookingDetails']);
        add_action('wp_ajax_cbp_delete_booking', [$this, 'ajaxDeleteBooking']);
        add_action('wp_ajax_cbp_test_email', [$this, 'ajaxTestEmail']);
        add_action('wp_ajax_cbp_run_diagnostics', [$this, 'ajaxRunDiagnostics']);
        add_action('wp_ajax_cbp_test_booking_emails', [$this, 'ajaxTestBookingEmails']);
        add_action('wp_ajax_cbp_test_calendar_event', [$this, 'ajaxTestCalendarEvent']);
        add_action('wp_ajax_cbp_clear_plugin_log', [$this, 'ajaxClearPluginLog']);
        add_action('wp_ajax_cbp_run_smtp_diagnostics', [$this, 'ajaxRunSmtpDiagnostics']);
        add_action('wp_ajax_cbp_retry_email_queue', [$this, 'ajaxRetryEmailQueue']);
        add_action('wp_ajax_cbp_retry_all_email_queue', [$this, 'ajaxRetryAllEmailQueue']);
        add_action('wp_ajax_cbp_clear_email_log', [$this, 'ajaxClearEmailLog']);
        add_action('wp_ajax_cbp_test_google_connection', [$this, 'ajaxTestGoogleConnection']);
    }

    public function ajaxGetBookingDetails() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);
        
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        if (!$booking_id) wp_send_json_error(['message' => 'Invalid booking ID']);
        
        global $wpdb;
        $booking = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cbp_bookings WHERE id = %d", $booking_id
        ));
        
        if (!$booking) wp_send_json_error(['message' => 'Booking not found']);
        
        $custom_data = json_decode($booking->custom_data, true);
        $booking->message = $custom_data['message'] ?? '';
        
        wp_send_json_success($booking);
    }

    public function ajaxDeleteBooking() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);
        
        $booking_id = (int)($_POST['booking_id'] ?? 0);
        if (!$booking_id) wp_send_json_error(['message' => 'Invalid booking ID']);
        
        global $wpdb;
        $wpdb->delete($wpdb->prefix . 'cbp_bookings', ['id' => $booking_id]);
        
        wp_send_json_success(['message' => 'Booking deleted']);
    }

    public function ajaxTestEmail() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);

        $test_email = sanitize_email($_POST['email'] ?? get_option('admin_email'));
        $result = \ConsultationBookingPro\Services\SmtpDiagnosticsService::testSend($test_email);

        if ($result['success']) {
            wp_send_json_success([
                'message' => 'Email sent successfully to ' . $test_email,
                'steps'   => $result['steps'],
            ]);
        }
        wp_send_json_error([
            'message' => 'Email failed to send',
            'steps'   => $result['steps'] ?? [],
            'details' => $result['details'] ?? [],
        ]);
    }

    public function ajaxRunSmtpDiagnostics() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);
        wp_send_json_success(['tests' => \ConsultationBookingPro\Services\SmtpDiagnosticsService::runDiagnostics()]);
    }

    public function ajaxRetryEmailQueue() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);
        $id = (int) ($_POST['queue_id'] ?? 0);
        if (!$id) wp_send_json_error(['message' => 'Invalid queue ID']);
        $result = \ConsultationBookingPro\Services\EmailQueueService::retry($id);
        if ($result['sent']) {
            wp_send_json_success(['message' => 'Email sent successfully']);
        }
        wp_send_json_error(['message' => $result['error'] ?? 'Retry failed']);
    }

    public function ajaxRetryAllEmailQueue() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);
        $results = \ConsultationBookingPro\Services\EmailQueueService::retryAll();
        wp_send_json_success($results);
    }

    public function ajaxClearEmailLog() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);
        \ConsultationBookingPro\Services\EmailLogService::clear();
        wp_send_json_success(['message' => 'Email log cleared']);
    }

    public function ajaxRunDiagnostics() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);

        $service = new \ConsultationBookingPro\Services\DiagnosticsService();
        wp_send_json_success(['tests' => $service->runAll()]);
    }

    public function ajaxTestBookingEmails() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);

        $student = sanitize_email($_POST['student_email'] ?? '');
        $counselor = sanitize_email($_POST['counselor_email'] ?? get_option('admin_email'));

        if (!$student || !$counselor) {
            wp_send_json_error(['message' => 'Both student and counselor email addresses are required.']);
        }

        $service = new \ConsultationBookingPro\Services\DiagnosticsService();
        $results = $service->testBookingEmails($student, $counselor);
        wp_send_json_success($results);
    }

    public function ajaxTestCalendarEvent() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);

        $student = sanitize_email($_POST['student_email'] ?? '');
        $counselor = sanitize_email($_POST['counselor_email'] ?? '');

        if (!$student || !$counselor) {
            wp_send_json_error(['message' => 'Both student and counselor email addresses are required.']);
        }

        $service = new \ConsultationBookingPro\Services\DiagnosticsService();
        $result = $service->testCalendarEvent($student, $counselor);

        if (!$result['success']) {
            wp_send_json_error($result);
        }
        wp_send_json_success($result);
    }

    public function ajaxClearPluginLog() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);
        $channel = sanitize_key($_POST['channel'] ?? 'plugin');
        if (!in_array($channel, \ConsultationBookingPro\Services\LogService::getAllChannels(), true)) {
            $channel = 'plugin';
        }
        \ConsultationBookingPro\Services\LogService::clear($channel);
        wp_send_json_success(['message' => ucfirst($channel) . ' log cleared.']);
    }

    public function ajaxTestGoogleConnection() {
        check_ajax_referer('wp_rest', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Forbidden']);

        global $wpdb;
        $counselor = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cbp_counselors WHERE status = 'active' LIMIT 1");
        if (!$counselor || empty($counselor->google_refresh_token)) {
            wp_send_json_error(['message' => 'Google Calendar not connected. Click Connect first.']);
        }

        $diag = new \ConsultationBookingPro\Services\DiagnosticsService();
        $tok = $diag->resolveAccessToken($counselor);
        if (!$tok['success']) {
            wp_send_json_error(['message' => $tok['error']]);
        }

        $cal = new \ConsultationBookingPro\Services\GoogleCalendarService($tok['token'], $counselor->google_calendar_id ?: 'primary');
        $result = $cal->testConnection();
        if (!$result['success']) {
            wp_send_json_error(['message' => $result['error'] ?? 'API test failed']);
        }

        $name = $result['data']['summary'] ?? 'Unknown';
        $tz = $result['data']['timeZone'] ?? 'UTC';
        wp_send_json_success(['message' => "Calendar \"{$name}\" accessible (timezone: {$tz})"]);
    }
}
