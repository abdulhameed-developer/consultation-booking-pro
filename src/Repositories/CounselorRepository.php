<?php

namespace ConsultationBookingPro\Repositories;

if (!defined('ABSPATH')) exit;

class CounselorRepository {
    private string $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'cbp_counselors';
    }

    public function find(int $id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id));
    }

    public function first() {
        global $wpdb;
        return $wpdb->get_row("SELECT * FROM {$this->table} LIMIT 1");
    }

    public function getDefault() {
        global $wpdb;
        $counselor = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$this->table} WHERE status = 'active' LIMIT 1"));
        return $counselor ?: $this->first();
    }

    public function all() {
        global $wpdb;
        return $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY created_at DESC");
    }

    public function update(int $id, array $data) {
        global $wpdb;
        $data['updated_at'] = current_time('mysql');
        return $wpdb->update($this->table, $data, ['id' => $id]);
    }

    public function create(array $data) {
        global $wpdb;
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        $wpdb->insert($this->table, $data);
        return $wpdb->insert_id;
    }

    public function delete(int $id) {
        global $wpdb;
        return $wpdb->delete($this->table, ['id' => $id]);
    }
}
