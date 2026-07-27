<?php

namespace ConsultationBookingPro\Repositories;

/**
 * Base Repository 
 */
abstract class RepositoryBase {
    protected $wpdb;
    protected $table_name;

    public function __construct() {
        global $wpdb;
        $this->wpdb = $wpdb;
        $this->table_name = $this->getTableName();
    }

    abstract protected function getTableName(): string;

    public function find(int $id) {
        return $this->wpdb->get_row($this->wpdb->prepare(
            "SELECT * FROM {$this->table_name} WHERE id = %d",
            $id
        ));
    }

    public function create(array $data) {
        $inserted = $this->wpdb->insert($this->table_name, $data);
        return $inserted ? $this->wpdb->insert_id : false;
    }

    public function update(int $id, array $data) {
        return $this->wpdb->update($this->table_name, $data, ['id' => $id]);
    }

    public function delete(int $id) {
        return $this->wpdb->delete($this->table_name, ['id' => $id]);
    }
}
