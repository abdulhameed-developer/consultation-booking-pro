<?php

namespace ConsultationBookingPro\Services;

/**
 * Handles Dashboard Statistics and Reports
 */
class AnalyticsService {
    private $wpdb;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
    }

    /**
     * Get summary counts
     */
    public function getSummary() {
        $table = $this->wpdb->prefix . 'cbp_bookings';
        
        $stats = $this->wpdb->get_row("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as created_today
            FROM $table
        ", ARRAY_A);

        return $stats;
    }

    /**
     * Get bookings for today
     */
    public function getTodayBookings() {
        $table = $this->wpdb->prefix . 'cbp_bookings';
        return $this->wpdb->get_results("
            SELECT * FROM $table 
            WHERE booking_date = CURDATE() 
            ORDER BY booking_time ASC
        ");
    }
}
