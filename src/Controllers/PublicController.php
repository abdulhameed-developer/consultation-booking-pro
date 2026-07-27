<?php

namespace ConsultationBookingPro\Controllers;

if (!defined('ABSPATH')) exit;

use ConsultationBookingPro\Services\FormService;
use ConsultationBookingPro\Services\AvailabilityService;
use ConsultationBookingPro\Services\BookingService;
use ConsultationBookingPro\Core\Countries;

class PublicController {
    
    public static function register() {
        $instance = new self();
        add_shortcode('consultation_booking', [$instance, 'renderBookingForm']);
        add_action('rest_api_init', [$instance, 'registerRoutes']);
        add_action('wp_enqueue_scripts', [$instance, 'enqueueAssets']);
        
        // Preview handling
        add_action('template_redirect', function() {
            if (isset($_GET['cbp_preview']) && current_user_can('manage_options')) {
                echo '<!DOCTYPE html><html><head>';
                wp_head();
                echo '</head><body style="background:#fff;">';
                echo do_shortcode('[consultation_booking]');
                wp_footer();
                echo '</body></html>';
                exit;
            }
        });
    }

    public function enqueueAssets() {
        wp_enqueue_style('dashicons');
        
        $form_files_url = CBP_URL . 'form-files/';
        $form_files_path = CBP_PATH . 'form-files/';

        // Load custom styles if exist, else fallback
        if (file_exists($form_files_path . 'form-styles.css')) {
            $ver = filemtime($form_files_path . 'form-styles.css');
            wp_enqueue_style('cbp-custom-styles', $form_files_url . 'form-styles.css', [], $ver);
        } else {
            $css_ver = CBP_VERSION . '.' . (file_exists(CBP_PATH . 'assets/public.css') ? filemtime(CBP_PATH . 'assets/public.css') : '1.0.0');
            wp_enqueue_style('cbp-public-css', CBP_URL . 'assets/public.css', [], $css_ver);
        }
        
        // Load custom script if exists, else fallback
        if (file_exists($form_files_path . 'form-script.js')) {
            $ver = filemtime($form_files_path . 'form-script.js');
            wp_enqueue_script('cbp-custom-script', $form_files_url . 'form-script.js', [], $ver, true);
        } else {
            $js_ver = CBP_VERSION . '.' . (file_exists(CBP_PATH . 'assets/public.js') ? filemtime(CBP_PATH . 'assets/public.js') : '1.0.0');
            wp_enqueue_script('cbp-public-js', CBP_URL . 'assets/public.js', [], $js_ver, true);
        }
    }

    public function registerRoutes() {
        register_rest_route('cbp/v1', '/form-schema', [
            'methods' => 'GET',
            'callback' => [$this, 'getFormSchema'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbp/v1', '/available-slots', [
            'methods' => 'GET',
            'callback' => [$this, 'getAvailableSlots'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbp/v1', '/countries', [
            'methods' => 'GET',
            'callback' => function() { return rest_ensure_response(Countries::get()); },
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbp/v1', '/book', [
            'methods' => 'POST',
            'callback' => [$this, 'handleBooking'],
            'permission_callback' => '__return_true'
        ]);
    }

    public function getFormSchema() {
        $service = new FormService();
        return rest_ensure_response($service->getSchema());
    }

    public function getAvailableSlots(\WP_REST_Request $request) {
        $date = $request->get_param('date');
        if (!$date) return new \WP_Error('missing_params', 'Date is required');

        $service = new AvailabilityService();
        $slots = $service->getSlotsForDate($date);

        global $wpdb;
        $counselor = $wpdb->get_row("SELECT timezone FROM {$wpdb->prefix}cbp_counselors WHERE status = 'active' ORDER BY id ASC LIMIT 1");
        $timezone = \ConsultationBookingPro\Services\TimezoneHelper::resolve($counselor->timezone ?? '');

        return rest_ensure_response([
            'slots' => array_values($slots),
            'timezone' => $timezone,
            'timezone_label' => \ConsultationBookingPro\Services\TimezoneHelper::label($timezone),
        ]);
    }

    public function handleBooking(\WP_REST_Request $request) {
        $data = $request->get_json_params();
        if (!empty($data['hp_field'])) {
            return new \WP_Error('spam', 'Bot detected', ['status' => 403]);
        }

        $service = new BookingService();
        $result = $service->createBooking($data);
        if (is_wp_error($result)) {
            return $result;
        }

        return rest_ensure_response($this->formatBookingResponse($result));
    }

    /**
     * Build a consistent booking API response with notification status.
     */
    private function formatBookingResponse(array $result): array {
        $date_formatted = date('l, F j, Y', strtotime($result['booking_date']));
        $time_formatted = $result['booking_time'];

        global $wpdb;
        $counselor = $wpdb->get_row($wpdb->prepare(
            "SELECT timezone FROM {$wpdb->prefix}cbp_counselors WHERE id = (SELECT counselor_id FROM {$wpdb->prefix}cbp_bookings WHERE id = %d)",
            $result['booking_id']
        ));
        $timezone = \ConsultationBookingPro\Services\TimezoneHelper::resolve($counselor->timezone ?? '');
        if (is_string($result['booking_time'])) {
            $time_formatted = \ConsultationBookingPro\Services\TimezoneHelper::toDisplay($result['booking_time'], $timezone);
        }

        return [
            'success'              => true,
            'booking_id'           => $result['booking_id'],
            'message'              => 'Booking confirmed.',
            'booking_date'         => $result['booking_date'],
            'booking_time'         => $result['booking_time'],
            'booking_date_formatted' => $date_formatted,
            'booking_time_formatted' => $time_formatted . ' (' . $timezone . ')',
            'timezone'             => $timezone,
            'student_email_sent'   => $result['student_email_sent'],
            'counselor_email_sent' => $result['counselor_email_sent'],
            'student_email_error'  => $result['student_email_error'],
            'counselor_email_error'=> $result['counselor_email_error'],
            'calendar_synced'      => $result['calendar_synced'],
            'calendar_error'       => $result['calendar_error'],
            'google_event_link'    => $result['google_event_link'],
            'google_meet_link'     => $result['google_meet_link'],
            'google_event_id'      => $result['google_event_id'],
        ];
    }

    public function renderBookingForm() {
        ob_start();
        require CBP_PATH . 'templates/public/booking-form.php';
        return ob_get_clean();
    }
}
