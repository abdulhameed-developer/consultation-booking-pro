<?php
/**
 * Consultation Booking Pro - Form Shortcode Template
 */

if (!defined('ABSPATH')) exit;

$rendering_file = CBP_PATH . 'form-files/form-rendering.php';
$config_file = CBP_PATH . 'form-files/form-config.json';

if (file_exists($rendering_file)) {
    require_once $rendering_file;
    $config = [];
    if (file_exists($config_file)) {
        $config = json_decode(file_get_contents($config_file), true);
    }
    render_cbp_booking_form($config);
} else {
    echo '<div style="padding:20px; color:#d63638; border:1px solid #ef4444; border-radius:8px; background:#fef2f2;">Form rendering file missing. Please check Form Builder settings.</div>';
}
