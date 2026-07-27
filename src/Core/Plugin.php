<?php

namespace ConsultationBookingPro\Core;

if (!defined('ABSPATH')) exit;

/**
 * Main Plugin Controller (Singleton)
 */
class Plugin {
    private static $instance = null;

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->init();
    }

    private function init() {
        register_activation_hook(CBP_BASENAME, [Database::class, 'init']);

        add_action('plugins_loaded', [$this, 'bootstrap']);
    }

    public function bootstrap() {
        Database::maybeUpgrade();
        \ConsultationBookingPro\Services\MailService::init();
        \ConsultationBookingPro\Services\LogService::info('Plugin booted', ['version' => CBP_VERSION, 'build' => CBP_BUILD_ID]);
        $this->registerCron();
        $this->initControllers();
    }

    private function registerCron(): void {
        add_action('cbp_process_email_queue', [\ConsultationBookingPro\Services\EmailQueueService::class, 'processPending']);
        if (!wp_next_scheduled('cbp_process_email_queue')) {
            wp_schedule_event(time() + 300, 'hourly', 'cbp_process_email_queue');
        }
    }

    private function initControllers() {
        \ConsultationBookingPro\Controllers\AdminController::register();
        \ConsultationBookingPro\Controllers\PublicController::register();
    }
}
