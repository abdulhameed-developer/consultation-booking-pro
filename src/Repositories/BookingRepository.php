<?php

namespace ConsultationBookingPro\Repositories;

/**
 * Repository for Bookings
 */
class BookingRepository extends RepositoryBase {
    protected function getTableName(): string {
        return $this->wpdb->prefix . 'cbp_bookings';
    }

    public function getAll(int $limit = 100) {
        $c_table = $this->wpdb->prefix . 'cbp_counselors';
        return $this->wpdb->get_results(
            $this->wpdb->prepare(
                "SELECT b.*, c.name as counselor_name FROM {$this->table_name} b 
                LEFT JOIN {$c_table} c ON b.counselor_id = c.id 
                ORDER BY b.created_at DESC LIMIT %d",
                $limit
            )
        );
    }

    /**
     * Find bookings by date for a counselor
     */
    public function getBookingsByDate(string $date, ?int $counselor_id = null) {
        $query = "SELECT * FROM {$this->table_name} WHERE booking_date = %s";
        $params = [$date];

        if ($counselor_id) {
            $query .= " AND counselor_id = %d";
            $params[] = $counselor_id;
        }

        $query .= " AND status IN ('confirmed', 'pending')";

        return $this->wpdb->get_results($this->wpdb->prepare($query, ...$params));
    }
}
