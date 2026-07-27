<?php

namespace ConsultationBookingPro\Controllers;

/**
 * Handles WordPress REST API endpoints for the public booking form.
 */
class RestApiController {
    /**
     * Register REST routes
     */
    public static function register() {
        $instance = new self();
        add_action('rest_api_init', [$instance, 'registerRoutes']);
    }

    /**
     * Register Endpoints
     */
    public function registerRoutes() {
        register_rest_route('cbp/v1', '/availability', [
            'methods' => 'GET',
            'callback' => [$this, 'getAvailability'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbp/v1', '/book', [
            'methods' => 'POST',
            'callback' => [$this, 'createBooking'],
            'permission_callback' => '__return_true'
        ]);
        
        register_rest_route('cbp/v1', '/countries', [
            'methods' => 'GET',
            'callback' => [$this, 'getCountries'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbp/v1', '/form-schema', [
            'methods' => 'GET',
            'callback' => [$this, 'getFormSchema'],
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('cbp/v1', '/form-schema', [
            'methods' => 'POST',
            'callback' => [$this, 'saveFormSchema'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
        
        register_rest_route('cbp/v1', '/config', [
            'methods' => 'GET',
            'callback' => [$this, 'getConfig'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * GET /availability?date=YYYY-MM-DD
     */
    public function getAvailability(\WP_REST_Request $request) {
        $date = $request->get_param('date');
        if (!$date) return new \WP_REST_Response(['error' => 'Date is required'], 400);

        try {
            $service = new \ConsultationBookingPro\Services\AvailabilityService();
            $slots = $service->getAvailableSlots($date);
            return new \WP_REST_Response(['slots' => $slots], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * POST /book
     */
    public function createBooking(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        
        // Security: Honeypot check
        if (!empty($params['hp_field'])) {
            return new \WP_REST_Response(['error' => 'Bot detected'], 403);
        }

        try {
            $service = new \ConsultationBookingPro\Services\BookingService();
            $booking = $service->processBooking($params);
            
            return new \WP_REST_Response([
                'success' => true,
                'message' => __('Booking confirmed!', 'consultation-booking-pro'),
                'token'   => $booking->token,
                'google_event_link' => $booking->google_event_link,
            ], 201);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 400);
        }
    }

    /**
     * GET /countries
     */
    public function getCountries() {
        $service = new \ConsultationBookingPro\Services\FormService();
        return new \WP_REST_Response($service->getCountries(), 200);
    }

    /**
     * GET /form-schema
     */
    public function getFormSchema() {
        $service = new \ConsultationBookingPro\Services\FormService();
        return new \WP_REST_Response($service->getSchema(), 200);
    }

    /**
     * POST /form-schema
     */
    public function saveFormSchema(\WP_REST_Request $request) {
        $params = $request->get_json_params();
        if (!is_array($params)) return new \WP_REST_Response(['error' => 'Invalid schema'], 400);

        try {
            $service = new \ConsultationBookingPro\Services\FormService();
            $service->saveSchema($params);
            return new \WP_REST_Response(['success' => true], 200);
        } catch (\Exception $e) {
            return new \WP_REST_Response(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * GET /config
     */
    public function getConfig() {
        $settings = new \ConsultationBookingPro\Services\SettingsService();
        return new \WP_REST_Response([
            'study_levels'      => $settings->getList('form_study_levels', ['Undergraduate', 'Postgraduate', 'PhD', 'Diploma']),
            'interested_fields' => $settings->getList('form_interested_fields', ['Engineering', 'Medicine', 'Business', 'IT', 'Art']),
            'form_title'        => $settings->get('form_title', 'Request a Consultation')
        ], 200);
    }
}
