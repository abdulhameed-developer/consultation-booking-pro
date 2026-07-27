<?php

namespace ConsultationBookingPro\Models;

/**
 * Booking Model
 */
class Booking {
    public $id;
    public $counselor_id;
    public $student_name;
    public $student_email;
    public $student_phone;
    public $country;
    public $study_level;
    public $interested_field;
    public $booking_date;
    public $booking_time;
    public $status;
    public $google_event_id;
    public $token;
    public $created_at;
    public $updated_at;

    public function __construct(array $data = []) {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }
}
