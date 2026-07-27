<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

class FormService {
    private string $option_name = 'cbp_form_schema';

    /**
     * Get the form schema as array
     */
    public function getSchema() {
        $schema = get_option($this->option_name);
        if (empty($schema)) {
            return $this->getDefaultSchema();
        }
        return is_array($schema) ? $schema : json_decode($schema, true);
    }

    /**
     * Save form schema
     */
    public function saveSchema(array $schema) {
        return update_option($this->option_name, $schema);
    }

    /**
     * Get list of countries with flags and dial codes
     */
    public function getCountries() {
        return [
            ['name' => 'Pakistan', 'code' => '+92', 'flag' => '🇵🇰'],
            ['name' => 'United Kingdom', 'code' => '+44', 'flag' => '🇬🇧'],
            ['name' => 'United States', 'code' => '+1', 'flag' => '🇺🇸'],
            ['name' => 'Canada', 'code' => '+1', 'flag' => '🇨🇦'],
            ['name' => 'Australia', 'code' => '+61', 'flag' => '🇦🇺'],
            ['name' => 'Germany', 'code' => '+49', 'flag' => '🇩🇪'],
            ['name' => 'France', 'code' => '+33', 'flag' => '🇫🇷'],
            ['name' => 'United Arab Emirates', 'code' => '+971', 'flag' => '🇦🇪'],
            ['name' => 'Saudi Arabia', 'code' => '+966', 'flag' => '🇸🇦'],
            ['name' => 'Turkey', 'code' => '+90', 'flag' => '🇹🇷'],
            ['name' => 'China', 'code' => '+86', 'flag' => '🇨🇳'],
            ['name' => 'India', 'code' => '+91', 'flag' => '🇮🇳'],
            ['name' => 'Malaysia', 'code' => '+60', 'flag' => '🇲🇾'],
            ['name' => 'Singapore', 'code' => '+65', 'flag' => '🇸🇬'],
            ['name' => 'Nigeria', 'code' => '+234', 'flag' => '🇳🇬'],
            ['name' => 'Kenya', 'code' => '+254', 'flag' => '🇰🇪'],
            ['name' => 'South Africa', 'code' => '+27', 'flag' => '🇿🇦'],
            ['name' => 'Brazil', 'code' => '+55', 'flag' => '🇧🇷'],
            ['name' => 'Japan', 'code' => '+81', 'flag' => '🇯🇵'],
            ['name' => 'South Korea', 'code' => '+82', 'flag' => '🇰🇷']
        ];
    }

    /**
     * Default schema for new installations
     */
    private function getDefaultSchema() {
        return [
            [
                'id' => 'f_name',
                'type' => 'text',
                'key' => 'full_name',
                'label' => 'Full Name',
                'placeholder' => 'Enter your full name',
                'required' => true,
                'width' => '50',
                'styling' => ['radius' => 12, 'fontSize' => 14, 'color' => '#111827', 'spacing' => 24]
            ],
            [
                'id' => 'f_email',
                'type' => 'email',
                'key' => 'email',
                'label' => 'Email Address',
                'placeholder' => 'Enter your email',
                'required' => true,
                'width' => '50',
                'styling' => ['radius' => 12, 'fontSize' => 14, 'color' => '#111827', 'spacing' => 24]
            ],
            [
                'id' => 'f_country',
                'type' => 'country',
                'key' => 'country',
                'label' => 'Select country',
                'placeholder' => 'Select country',
                'required' => true,
                'width' => '50',
                'styling' => ['radius' => 12, 'fontSize' => 14, 'color' => '#111827', 'spacing' => 24]
            ],
            [
                'id' => 'f_whatsapp',
                'type' => 'tel',
                'key' => 'whatsapp',
                'label' => 'WhatsApp Number',
                'placeholder' => '(000) 000-0000',
                'required' => true,
                'width' => '50',
                'styling' => ['radius' => 12, 'fontSize' => 14, 'color' => '#111827', 'spacing' => 24]
            ],
            [
                'id' => 'f_level',
                'type' => 'select',
                'key' => 'study_level',
                'label' => 'Preferred Study Level',
                'placeholder' => 'Select study level',
                'options' => "English Language Program, Foundation, Diploma, Bachelors, Masters, PhD",
                'required' => true,
                'width' => '50',
                'styling' => ['radius' => 12, 'fontSize' => 14, 'color' => '#111827', 'spacing' => 24]
            ],
            [
                'id' => 'f_field',
                'type' => 'select',
                'key' => 'interested_field',
                'label' => 'Interested Field',
                'placeholder' => 'Select your field of interest',
                'options' => "Accounting, Artificial Intelligence (AI), Banking and Finance, Biomedical Engineering / Biomedical Equipment Engineering, Business Analytics, Chemical Engineering, Civil Engineering, Computer Science, Cyber Security, Data Science, E-Commerce, Economics, Fintech, Industrial Engineering, Information Technology (IT), International Business, Logistics, Marketing, Mechatronics Engineering, Petroleum Engineering, Robotics Engineering, Software Engineering, Telecommunication Engineering, Tourism and Hospitality, Other",
                'required' => true,
                'width' => '50',
                'styling' => ['radius' => 12, 'fontSize' => 14, 'color' => '#111827', 'spacing' => 24]
            ],
            [
                'id' => 'f_message',
                'type' => 'textarea',
                'key' => 'message',
                'label' => 'Tell us what you need help with',
                'placeholder' => 'Tell us about your goals...*',
                'required' => true,
                'width' => '100',
                'styling' => ['radius' => 12, 'fontSize' => 14, 'color' => '#111827', 'spacing' => 24]
            ]
        ];
    }
}
