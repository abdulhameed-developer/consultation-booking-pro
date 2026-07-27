<?php
/**
 * Consultation Booking Pro - Form Rendering Engine
 * Production-Ready Implementation - FULLY FIXED
 */

if (!defined('ABSPATH')) exit;

function render_cbp_booking_form($config) {
    $nonce = wp_create_nonce('wp_rest');
    $api_url = esc_url_raw(rest_url('cbp/v1'));

    global $wpdb;
    $counselor = $wpdb->get_row("SELECT timezone FROM {$wpdb->prefix}cbp_counselors WHERE status = 'active' ORDER BY id ASC LIMIT 1");
    $timezone = \ConsultationBookingPro\Services\TimezoneHelper::resolve($counselor->timezone ?? '');
    $tz_label = \ConsultationBookingPro\Services\TimezoneHelper::label($timezone);
    ?>
    <div id="cbp-booking-app"
         data-nonce="<?php echo esc_attr($nonce); ?>"
         data-api-url="<?php echo esc_attr($api_url); ?>"
         data-ajax-url="<?php echo esc_url(admin_url('admin-ajax.php')); ?>"
         data-timezone="<?php echo esc_attr($timezone); ?>"
         data-timezone-label="<?php echo esc_attr($tz_label); ?>">
        
        <div class="cbp-header">
<!--             <h1>Request a Consultation</h1> -->
            <p>Fill in your details, choose a date, and select the best available time.</p>
            <p style="font-size:13px; color:#64748b; margin-top:12px;">All times are in <strong><?php echo esc_html($timezone); ?></strong> (counselor timezone).</p>
            <p style="font-size:13px; color:#94a3b8; margin-top:12px;"><span style="color:#ec1e28;">*</span> indicates required fields</p>
        </div>

        <div id="cbp-error-alert" class="cbp-alert cbp-alert-error"></div>

        <form id="cbp-main-form" novalidate>
            <div class="cbp-grid">
                <!-- Left Column -->
                <div class="cbp-col-left">
                    
                    <!-- Row 1: Name + Email -->
                    <div class="cbp-field-row">
                        <div class="cbp-field">
                            <label class="cbp-label">Full Name <span class="req">*</span></label>
                            <div class="cbp-input-wrapper">
                                <input type="text" name="student_name" placeholder="Enter your full name" required>
                            </div>
                        </div>
                        <div class="cbp-field">
                            <label class="cbp-label">Email Address <span class="req">*</span></label>
                            <div class="cbp-input-wrapper">
                                <input type="email" name="student_email" placeholder="Enter your email address" required>
                            </div>
                        </div>
                    </div>

                    <!-- Row 2: Country + WhatsApp -->
                    <div class="cbp-field-row">
                        <div class="cbp-field">
                            <label class="cbp-label">Select country <span class="req">*</span></label>
                            <div class="cbp-custom-select" data-id="country">
                                <div class="cbp-select-trigger">
                                    <span class="val">Select your country</span>
                                    <svg class="cbp-icon-chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div class="cbp-select-panel">
                                    <div class="cbp-panel-search">
                                        <svg class="cbp-icon-search" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        <input type="text" placeholder="Search country...">
                                        <div class="cbp-search-clear">
                                            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </div>
                                    </div>
                                    <div class="cbp-option-list" data-type="country">
                                        <!-- Populated via JS -->
                                    </div>
                                </div>
                                <input type="hidden" name="country" required>
                            </div>
                        </div>
                        <div class="cbp-field">
                            <label class="cbp-label">WhatsApp Number <span class="req">*</span></label>
                            <div class="cbp-whatsapp-group">
                                <div class="cbp-country-trigger">
                                    <span class="flag">🇵🇰</span>
                                    <span class="dial">+92</span>
                                    <svg class="cbp-icon-chevron" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div class="cbp-country-panel">
                                    <div class="cbp-panel-search">
                                        <svg class="cbp-icon-search" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        <input type="text" placeholder="Search country...">
                                        <div class="cbp-search-clear">
                                            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </div>
                                    </div>
                                    <div class="cbp-country-list">
                                        <!-- Populated by JS -->
                                    </div>
                                </div>
                                <input type="tel" name="student_phone" class="cbp-phone-field" placeholder="300 0000000" required>
                                <input type="hidden" name="student_country_code" id="cbp-dial-code" value="+92">
                            </div>
                        </div>
                    </div>

                    <!-- Row 3: Study Level -->
                    <div class="cbp-field-row">
                        <div class="cbp-field">
                            <label class="cbp-label">Preferred Study Level <span class="req">*</span></label>
                            <div class="cbp-custom-select" data-id="study_level">
                                <div class="cbp-select-trigger">
                                    <span class="val">Select study level</span>
                                    <svg class="cbp-icon-chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div class="cbp-select-panel">
                                    <div class="cbp-option-list">
                                        <?php 
                                        $levels = [
                                            'English Language Program',
                                            'Foundation',
                                            'Diploma',
                                            'Bachelors',
                                            'Masters',
                                            'PhD'
                                        ];
                                        foreach ($levels as $l): ?>
                                            <div class="cbp-option" data-value="<?php echo esc_attr($l); ?>">
                                                <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                                    <span><?php echo esc_html($l); ?></span>
                                                    <svg class="cbp-check-icon" style="display:none; color:#ec1e28;" viewBox="0 0 24 24" width="14" height="14"><path d="M5 13l4 4L19 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="study_level" required>
                            </div>
                        </div>
                    </div>

                    <!-- Row 4: Interested Field - ALL 25 OPTIONS -->
                    <div class="cbp-field-row">
                        <div class="cbp-field">
                            <label class="cbp-label">Interested Field <span class="req">*</span></label>
                            <div class="cbp-custom-select" data-id="interested_field">
                                <div class="cbp-select-trigger">
                                    <span class="val">Select your field of interest</span>
                                    <svg class="cbp-icon-chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div class="cbp-select-panel">
                                    <div class="cbp-panel-search">
                                        <svg class="cbp-icon-search" viewBox="0 0 24 24" width="14" height="14" aria-hidden="true"><circle cx="11" cy="11" r="8" fill="none" stroke="currentColor" stroke-width="2"/><path d="M21 21l-4.35-4.35" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                                        <input type="text" placeholder="Search fields...">
                                        <div class="cbp-search-clear">
                                            <svg viewBox="0 0 24 24" width="14" height="14"><path d="M18 6L6 18M6 6l12 12" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        </div>
                                    </div>
                                    <div class="cbp-option-list">
                                        <?php 
                                        $fields = [
                                            'Accounting',
                                            'Artificial Intelligence (AI)',
                                            'Banking and Finance',
                                            'Biomedical Engineering / Biomedical Equipment Engineering',
                                            'Business Analytics',
                                            'Chemical Engineering',
                                            'Civil Engineering',
                                            'Computer Science',
                                            'Cyber Security',
                                            'Data Science',
                                            'E-Commerce',
                                            'Economics',
                                            'Fintech',
                                            'Industrial Engineering',
                                            'Information Technology (IT)',
                                            'International Business',
                                            'Logistics',
                                            'Marketing',
                                            'Mechatronics Engineering',
                                            'Petroleum Engineering',
                                            'Robotics Engineering',
                                            'Software Engineering',
                                            'Telecommunication Engineering',
                                            'Tourism and Hospitality',
                                            'Other'
                                        ];
                                        foreach ($fields as $f): ?>
                                            <div class="cbp-option" data-value="<?php echo esc_attr($f); ?>">
                                                <div style="display:flex; justify-content:space-between; align-items:center; width:100%;">
                                                    <span><?php echo esc_html($f); ?></span>
                                                    <svg class="cbp-check-icon" style="display:none; color:#ec1e28;" viewBox="0 0 24 24" width="14" height="14"><path d="M5 13l4 4L19 7" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <input type="hidden" name="interested_field" required>
                            </div>
                        </div>
                    </div>

                    <!-- Row 5: Message -->
                    <div class="cbp-field-row">
                        <div class="cbp-field">
                            <label class="cbp-label">Tell us what you need help with <span class="req">*</span></label>
                            <div class="cbp-input-wrapper">
                                <textarea name="message" id="cbp-fld-message" placeholder="Tell us about your goals...0 *" required rows="4" maxlength="500"></textarea>
                                <div class="cbp-counter">0/500 characters</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="cbp-col-right">
                    <div class="cbp-booking-card">
                        <div class="cbp-group-title">Choose a Date</div>
                        <div id="cbp-date-cards" class="cbp-date-grid">
                            <!-- Populated by JS -->
                        </div>
                        
                        <div class="cbp-input-wrapper">
                            <label class="cbp-label">Or select from calendar</label>
                            <input type="date" id="cbp-date-picker" min="<?php echo date('Y-m-d', strtotime('+1 day')); ?>" max="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                            <div class="cbp-calendar-icon-overlay">
                                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2" ry="2" fill="none" stroke="currentColor" stroke-width="2"/><path d="M16 2v4M8 2v4M3 10h18" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                            </div>
                        </div>

                        <p style="font-size:13px; color:#64748b; font-style:italic; margin:15px 0;">
                            Students can either choose a highlighted available date or select any preferred date from the calendar.
                        </p>

                        <div class="cbp-group-title">Choose a Time</div>
                        <div id="cbp-time-grid" class="cbp-time-grid">
                            <!-- Populated by JS -->
                        </div>

                        <div class="cbp-field">
                            <div class="cbp-custom-select" data-id="booking_time">
                                <div class="cbp-select-trigger">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <svg class="cbp-icon-clock" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="2"/><path d="M12 6v6l4 2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                        <span class="val">Select a time</span>
                                    </div>
                                    <svg class="cbp-icon-chevron" viewBox="0 0 24 24" width="16" height="16" aria-hidden="true"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                </div>
                                <div class="cbp-select-panel" id="cbp-time-panel">
                                    <div class="cbp-option-list" id="cbp-time-option-list">
                                        <!-- Populated via JS -->
                                    </div>
                                </div>
                                <input type="hidden" name="booking_time" required>
                            </div>
                        </div>
                        
                        <!-- Honeypot - hidden from users -->
                        <div style="position:absolute; left:-9999px; top:-9999px;" aria-hidden="true">
                            <input type="text" name="hp_field" tabindex="-1" autocomplete="off">
                        </div>
                    </div>
                    
                    <!-- Submit Button - centered below both columns -->
                    <div style="text-align:center; margin-top:30px;">
                        <button type="submit" id="cbp-submit-btn" class="cbp-btn-submit">Submit Booking</button>
                    </div>
                </div>
            </div>
        </form>

        <!-- Success View -->
        <div id="cbp-success-view" class="cbp-alert cbp-alert-success" style="display:none;">
            <div style="text-align:center; padding:20px;">
                <div style="margin-bottom:20px; color:#059669;">
                    <svg viewBox="0 0 24 24" width="64" height="64" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"/>
                        <path d="M8 12l3 3 5-5"/>
                    </svg>
                </div>
                <h2 style="font-size:24px; font-weight:800; margin-bottom:10px; color:#111827;">Booking Confirmed!</h2>
                <p style="color:#64748b; font-size:16px;">
                    Your appointment was successfully scheduled for:<br>
                    <strong id="res-date" style="color:#111827;">...</strong> at <strong id="res-time" style="color:#111827;">...</strong>
                </p>
                <div style="background:#f9fafb; border:1px solid #e2e8f0; border-radius:16px; padding:25px; max-width:400px; margin:20px auto; text-align:left;">
                    <p style="margin-top:0; font-weight:600;">What's next?</p>
                    <ul id="cbp-success-next-steps" style="padding-left:20px; color:#64748b; list-style:none; margin:0; padding-left:0;">
                        <li id="res-email-student" style="display:none; margin-bottom:8px;"></li>
                        <li id="res-email-counselor" style="display:none; margin-bottom:8px;"></li>
                        <li id="res-calendar" style="display:none; margin-bottom:8px;"></li>
                        <li id="res-email-failed" style="display:none; margin-bottom:8px; color:#dc2626;"></li>
                        <li id="res-calendar-failed" style="display:none; margin-bottom:8px; color:#dc2626;"></li>
                        <li style="margin-bottom:8px;">We will contact you via WhatsApp if needed.</li>
                    </ul>
                </div>
                <button onclick="window.location.reload()" class="cbp-btn-submit" style="margin:20px auto; max-width:250px; background:#059669;">Book Another Consultation</button>
            </div>
        </div>
    </div>
    <?php
}