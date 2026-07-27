<?php
/**
 * Plugin Name: Consultation Booking
 * Plugin URI: https://github.com/abdulhameed-developer/consultation-booking-pro
 * Description: Enterprise-level consultation booking system with native Google Calendar sync.
 * Version: 1.2.0
 * Author: Abdul Hameed
 * Author URI: https://www.linkedin.com/in/abdul-hameed-website-developer
 * Text Domain: consultation-booking-pro
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('CBP_VERSION', '1.3.0');
define('CBP_BUILD_ID', '20260718-tz-freebusy');
define('CBP_PATH', plugin_dir_path(__FILE__));
define('CBP_URL', plugin_dir_url(__FILE__));
define('CBP_BASENAME', plugin_basename(__FILE__));

/**
 * Custom Autoloader
 */
require_once CBP_PATH . 'src/Core/Autoloader.php';

use ConsultationBookingPro\Core\Autoloader;
use ConsultationBookingPro\Core\Plugin;

// Register the autoloader for the namespace
Autoloader::register('ConsultationBookingPro', CBP_PATH . 'src/');

/**
 * Initialize the plugin
 */
function cbp_init() {
    return Plugin::getInstance();
}

// Global accessor for the plugin instance
$GLOBALS['cbp'] = cbp_init();
