<?php

namespace ConsultationBookingPro\Core;

if (!defined('ABSPATH')) exit;

class Database {
    
    public static function init() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // 1. Counselors Table
        $table_counselors = $wpdb->prefix . 'cbp_counselors';
        $sql1 = "CREATE TABLE $table_counselors (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            name varchar(255) NOT NULL,
            email varchar(255) NOT NULL,
            google_client_id varchar(255) DEFAULT NULL,
            google_client_secret varchar(255) DEFAULT NULL,
            google_calendar_id varchar(255) DEFAULT '',
            google_access_token text DEFAULT NULL,
            google_refresh_token text DEFAULT NULL,
            google_token_expiry datetime DEFAULT NULL,
            timezone varchar(100) DEFAULT 'UTC',
            availability_mode varchar(20) DEFAULT 'auto',
            availability_schedule text DEFAULT NULL,
            custom_slots text DEFAULT NULL,
            slot_duration int DEFAULT 30,
            buffer_time int DEFAULT 0,
            max_bookings_per_day int DEFAULT 10,
            status varchar(20) DEFAULT 'active',
            is_default tinyint(1) DEFAULT 0,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            updated_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 2. Bookings Table
        $table_bookings = $wpdb->prefix . 'cbp_bookings';
        $sql2 = "CREATE TABLE $table_bookings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            counselor_id mediumint(9) NOT NULL,
            student_name varchar(255) NOT NULL,
            student_email varchar(255) NOT NULL,
            student_phone varchar(50) DEFAULT '',
            student_country_code varchar(10) DEFAULT '',
            country varchar(100) DEFAULT '',
            study_level varchar(100) DEFAULT '',
            interested_field varchar(100) DEFAULT '',
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            status varchar(50) DEFAULT 'confirmed',
            google_event_id varchar(255) DEFAULT NULL,
            google_event_link varchar(512) DEFAULT NULL,
            google_meet_link varchar(512) DEFAULT NULL,
            token varchar(64) DEFAULT NULL,
            custom_data text DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        // 3. Settings Table
        $table_settings = $wpdb->prefix . 'cbp_settings';
        $sql3 = "CREATE TABLE $table_settings (
            setting_key varchar(100) NOT NULL,
            setting_value text NOT NULL,
            PRIMARY KEY  (setting_key)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql1);
        dbDelta($sql2);
        dbDelta($sql3);

        // 4. Email Queue Table
        $table_queue = $wpdb->prefix . 'cbp_email_queue';
        $sql4 = "CREATE TABLE $table_queue (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            recipient varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            body longtext NOT NULL,
            headers text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            retry_count int DEFAULT 0,
            max_retries int DEFAULT 3,
            failure_reason text DEFAULT NULL,
            booking_id mediumint(9) DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            last_attempt_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql4);

        self::maybeUpgrade();

        // Seed default counselor if empty
        if ($wpdb->get_var("SELECT COUNT(*) FROM $table_counselors") == 0) {
            $now = current_time('mysql');
            $wpdb->insert($table_counselors, [
                'name'       => 'Senior Counselor',
                'email'      => get_option('admin_email'),
                'status'     => 'active',
                'is_default' => 1,
                'timezone' => wp_timezone_string() ?: 'UTC',
                'created_at' => $now,
                'updated_at' => $now
            ]);
        }
    }

    /**
     * Run schema upgrades on existing installations without re-activation.
     */
    public static function maybeUpgrade() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $table_bookings = $wpdb->prefix . 'cbp_bookings';

        $sql = "CREATE TABLE $table_bookings (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            counselor_id mediumint(9) NOT NULL,
            student_name varchar(255) NOT NULL,
            student_email varchar(255) NOT NULL,
            student_phone varchar(50) DEFAULT '',
            student_country_code varchar(10) DEFAULT '',
            country varchar(100) DEFAULT '',
            study_level varchar(100) DEFAULT '',
            interested_field varchar(100) DEFAULT '',
            booking_date date NOT NULL,
            booking_time time NOT NULL,
            status varchar(50) DEFAULT 'confirmed',
            google_event_id varchar(255) DEFAULT NULL,
            google_event_link varchar(512) DEFAULT NULL,
            google_meet_link varchar(512) DEFAULT NULL,
            token varchar(64) DEFAULT NULL,
            custom_data text DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        $table_queue = $wpdb->prefix . 'cbp_email_queue';
        $sql_queue = "CREATE TABLE $table_queue (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            recipient varchar(255) NOT NULL,
            subject varchar(500) NOT NULL,
            body longtext NOT NULL,
            headers text DEFAULT NULL,
            status varchar(20) DEFAULT 'pending',
            retry_count int DEFAULT 0,
            max_retries int DEFAULT 3,
            failure_reason text DEFAULT NULL,
            booking_id mediumint(9) DEFAULT NULL,
            created_at datetime DEFAULT '0000-00-00 00:00:00' NOT NULL,
            last_attempt_at datetime DEFAULT NULL,
            sent_at datetime DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY status (status)
        ) $charset_collate;";
        dbDelta($sql_queue);
    }
}
