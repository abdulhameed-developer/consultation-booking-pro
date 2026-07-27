<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <?php if (isset($_GET['sync']) && $_GET['sync'] === 'success'): ?>
        <div class="notice notice-success is-dismissible"><p><?php _e('Google Calendar connected successfully!', 'consultation-booking-pro'); ?></p></div>
    <?php endif; ?>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
        <h1 style="font-weight:700; font-size:24px;"><?php _e('Manage Counselors', 'consultation-booking-pro'); ?></h1>
        <button id="cbp-add-counselor-btn" class="button button-primary button-large"><?php _e('Add New Counselor', 'consultation-booking-pro'); ?></button>
    </div>

    <div class="cbp-counselors-table-container" style="background:#fff; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; box-shadow:0 1px 3px rgba(0,0,0,0.05);">
        <table class="wp-list-table widefat fixed striped" style="border:none;">
            <thead>
                <tr style="background:#f9fafb;">
                    <th style="padding:15px; font-weight:700;"><?php _e('Counselor Name', 'consultation-booking-pro'); ?></th>
                    <th style="padding:15px; font-weight:700;"><?php _e('Email Address', 'consultation-booking-pro'); ?></th>
                    <th style="padding:15px; font-weight:700;"><?php _e('Google Calendar ID', 'consultation-booking-pro'); ?></th>
                    <th style="padding:15px; font-weight:700;"><?php _e('Status', 'consultation-booking-pro'); ?></th>
                    <th style="padding:15px; font-weight:700; text-align:right;"><?php _e('Actions', 'consultation-booking-pro'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php 
                global $wpdb;
                $counselors = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cbp_counselors ORDER BY id DESC");
                $total_counselors = count($counselors);
                foreach ($counselors as $c):
                    $cal_id = $c->google_calendar_id ?: 'Empty (manual entry required)';
                    $truncated_cal = strlen($cal_id) > 25 ? substr($cal_id, 0, 22) . '...' : $cal_id;
                    $is_active = $c->status === 'active';
                ?>
                    <tr data-counselor='<?php echo json_encode($c); ?>'>
                        <td style="padding:15px;"><strong><?php echo esc_html($c->name); ?></strong></td>
                        <td style="padding:15px;"><?php echo esc_html($c->email); ?></td>
                        <td style="padding:15px;">
                            <code title="<?php echo esc_attr($cal_id); ?>"><?php echo esc_html($truncated_cal); ?></code>
                        </td>
                        <td style="padding:15px;">
                            <label class="cbp-switch">
                                <input type="checkbox" class="cbp-status-toggle" data-id="<?php echo $c->id; ?>" <?php checked($is_active); ?>>
                                <span class="cbp-slider round"></span>
                            </label>
                            <span class="status-label" style="font-size:11px; margin-left:5px; color:#64748b; vertical-align:middle;"><?php echo ucfirst($c->status); ?></span>
                        </td>
                        <td style="padding:15px; text-align:right;">
                            <button class="button button-secondary cbp-counselor-edit" style="margin-right:5px; border-radius:8px; padding:4px 10px;">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4L18.5 2.5z"></path></svg>
                            </button>
                            <?php if ($total_counselors > 1): ?>
                                <a href="<?php echo wp_nonce_url(admin_url('admin-post.php?action=cbp_delete_counselor&id=' . $c->id), 'cbp_delete_counselor'); ?>" 
                                   class="button button-link-delete" 
                                   style="border-radius:8px; padding:4px 10px; border:1px solid #fee2e2; background:#fff; color:#dc2626;"
                                   onclick="return confirm('<?php _e('Are you sure you want to delete this counselor?', 'consultation-booking-pro'); ?>')">
                                   <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>
                                </a>
                            <?php else: ?>
                                <span style="color:#94a3b8; font-size:11px; cursor:not-allowed;" title="Cannot delete the only counselor">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal -->
<div id="cbp-counselor-modal" class="cbp-modal" style="display:none;">
    <div class="cbp-modal-content" style="max-width:800px; padding:0;">
        <div class="cbp-modal-header" style="padding:20px 25px; border-bottom:1px solid #e5e7eb; display:flex; justify-content:space-between; align-items:center;">
            <h3 id="modal-title" style="margin:0; font-weight:800; font-size:18px; color:#1e293b;"><?php _e('Add New Counselor', 'consultation-booking-pro'); ?></h3>
            <button type="button" class="cbp-modal-close" style="background:none; border:none; padding:5px; cursor:pointer; color:#94a3b8; transition:color 0.2s;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
            </button>
        </div>
        
        <form id="cbp-counselor-form" method="POST" action="<?php echo admin_url('admin-post.php'); ?>">
            <?php wp_nonce_field('cbp_save_counselor'); ?>
            <input type="hidden" name="action" value="cbp_save_counselor">
            <input type="hidden" name="id" id="c-id">
            
            <div class="step-indicator" style="display:flex; padding:15px 25px; background:#f8fafc; border-bottom:1px solid #e2e8f0; gap:30px;">
                <div class="step active" data-step="1" style="cursor:pointer; display:flex; align-items:center; gap:8px; font-weight:700; font-size:13px; color:#64748b;">
                    <span class="num" style="width:24px; height:24px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px;">1</span> General Info
                </div>
                <div class="step" data-step="2" style="cursor:pointer; display:flex; align-items:center; gap:8px; font-weight:700; font-size:13px; color:#64748b;">
                    <span class="num" style="width:24px; height:24px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px;">2</span> Calendar Sync
                </div>
                <div class="step" data-step="3" style="cursor:pointer; display:flex; align-items:center; gap:8px; font-weight:700; font-size:13px; color:#64748b;">
                    <span class="num" style="width:24px; height:24px; background:#e2e8f0; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px;">3</span> Availability
                </div>
            </div>

            <div style="padding:25px; max-height:60vh; overflow-y:auto;">
                <!-- Step 1: General Info -->
                <div id="section-step-1" class="modal-section">
                    <div style="display:grid; grid-template-columns: 1fr 1fr; gap:20px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px;">Full Name <span style="color:red">*</span></label>
                            <input type="text" name="name" id="c-name" class="widefat" required>
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px;">Email Address <span style="color:red">*</span></label>
                            <input type="email" name="email" id="c-email" class="widefat" required>
                        </div>
                    </div>
                    <div style="display:grid; grid-template-columns: 1fr; gap:12px; margin-bottom:20px;">
                        <div>
                            <label style="display:block; font-weight:600; margin-bottom:5px;">Counselor Timezone <span style="color:red">*</span></label>
                            <select name="timezone" id="c-timezone" class="widefat" required>
                                <optgroup label="Common">
                                <?php
                                $common = \ConsultationBookingPro\Services\TimezoneHelper::commonList();
                                $wp_tz = wp_timezone_string() ?: 'UTC';
                                foreach ($common as $tz) {
                                    $sel = selected($tz, $wp_tz, false);
                                    echo '<option value="' . esc_attr($tz) . '" ' . $sel . '>' . esc_html($tz) . '</option>';
                                }
                                ?>
                                </optgroup>
                                <optgroup label="All timezones">
                                <?php
                                foreach (timezone_identifiers_list() as $tz) {
                                    if (in_array($tz, $common, true)) continue;
                                    echo '<option value="' . esc_attr($tz) . '">' . esc_html($tz) . '</option>';
                                }
                                ?>
                                </optgroup>
                            </select>
                            <p class="description" style="margin-top:8px; color:#475569;">
                                <strong>Critical:</strong> All student booking slots and Google Calendar events use this timezone as wall-clock time.
                                Example: if set to <code>Asia/Karachi</code> and student picks <strong>09:00 AM</strong>, the calendar event is created at 09:00 AM Karachi time — not UTC.
                                When you connect Google Calendar, this is auto-synced from the calendar’s timezone.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Step 2: Calendar Sync -->
                <div id="section-step-2" class="modal-section" style="display:none;">
                    <div id="cbp-google-oauth-panel" style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px;">
                        <h4 style="margin:0 0 8px; font-weight:700; font-size:14px;">Google Account Authorization</h4>
                        <p class="description" style="margin-bottom:15px;">Authorize this counselor's Google account to automatically create calendar events and send invitations.</p>
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; border-radius:8px; padding:12px 14px; margin-bottom:15px; font-size:13px; color:#1e3a8a; line-height:1.5;">
                            <strong>Two-way slot blocking:</strong> Any event already on this Google Calendar (manual or from another app) that is marked <em>Busy</em> will hide that time from students.
                            Plugin bookings also create Busy events, so the same slot cannot be booked twice.
                        </div>
                        <div id="cbp-google-status-connected" style="display:none; align-items:center; gap:12px;">
                            <span style="color:#059669; font-weight:700;">● Google Calendar Connected</span>
                            <a id="cbp-disconnect-google" href="#" class="button">Disconnect Google</a>
                        </div>
                        <div id="cbp-google-status-disconnected" style="display:none;">
                            <a id="cbp-connect-google" href="#" class="button button-primary" target="_blank" rel="noopener">Connect Google Calendar</a>
                            <p class="description" style="margin-top:10px;" id="cbp-google-connect-hint">Save the counselor first, then connect Google Calendar.</p>
                        </div>
                        <div id="cbp-google-status-missing-config" style="display:none; color:#dc2626; font-weight:600;">
                            Configure Google Client ID and Secret in <a href="<?php echo admin_url('admin.php?page=cbp-settings'); ?>">Settings</a> before connecting.
                        </div>
                    </div>
                    <div style="background:#f1f5f9; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px;">
                        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:15px;">
                            <div style="flex:1;">
                                <label style="display:block; font-weight:600; margin-bottom:5px;">Google Calendar ID <span style="color:red">*</span></label>
                                <input type="text" name="google_calendar_id" id="c-calendar-id" class="widefat" placeholder="your-email@gmail.com or primary" required>
                            </div>
                            <button type="button" id="cbp-test-calendar" class="button" style="margin-left:10px; margin-top:24px;">Test Connection</button>
                        </div>
                        <p class="description">Make sure you have authorized the app in Settings first. Use "primary" or your email address.</p>
                    </div>
                </div>

                <!-- Step 3: Availability -->
                <div id="section-step-3" class="modal-section" style="display:none;">
                    <div class="cbp-availability-header" style="margin-bottom:24px; border-bottom:1px solid #e5e7eb; padding-bottom:16px;">
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <div>
                                <h4 style="margin:0; font-size:16px; font-weight:700;">Availability Settings</h4>
                                <p style="margin:4px 0 0; color:#6b7280; font-size:12px;">Manage how your booking slots are generated.</p>
                            </div>
                            <div class="cbp-mode-toggle-group" style="display:flex; background:#f1f5f9; padding:4px; border-radius:10px;">
                                <label class="cbp-mode-btn active" style="padding:6px 12px; border-radius:8px; cursor:pointer; font-size:12px; font-weight:600;">
                                    <input type="radio" name="availability_mode" value="auto" checked style="display:none;"> Auto-Generate
                                </label>
                                <label class="cbp-mode-btn" style="padding:6px 12px; border-radius:8px; cursor:pointer; font-size:12px; font-weight:600; color:#64748b;">
                                    <input type="radio" name="availability_mode" value="custom" style="display:none;"> Custom Slots
                                </label>
                            </div>
                        </div>
                    </div>

                    <!-- Auto Mode UI -->
                    <div id="cbp-mode-auto" class="availability-mode-section">
                        <table class="cbp-schedule-table" style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                            <thead>
                                <tr style="text-align:left; color:#94a3b8; font-size:10px; text-transform:uppercase; letter-spacing:0.05em; border-bottom:1px solid #f1f5f9;">
                                    <th style="padding:8px 12px; font-weight:600;">Day</th>
                                    <th style="padding:8px 12px; font-weight:600;">Status</th>
                                    <th style="padding:8px 12px; font-weight:600;">Start Time</th>
                                    <th style="padding:8px 12px; font-weight:600;">End Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
                                foreach ($days as $day): ?>
                                <tr class="cbp-day-row" data-day="<?php echo $day; ?>" style="border-bottom:1px solid #f8fafc;">
                                    <td style="padding:12px; font-weight:600; text-transform:capitalize; font-size:13px;"><?php echo $day; ?></td>
                                    <td style="padding:12px;">
                                        <label class="cbp-switch-container" style="display:flex; align-items:center;">
                                            <input type="checkbox" name="schedule[<?php echo $day; ?>][enabled]" value="1" class="cbp-day-toggle" checked>
                                            <span class="cbp-switch-slider"></span>
                                        </label>
                                    </td>
                                    <td style="padding:12px;">
                                        <input type="time" name="schedule[<?php echo $day; ?>][start]" value="09:00" class="cbp-admin-input" style="padding:6px; font-size:12px; width:110px;">
                                    </td>
                                    <td style="padding:12px;">
                                        <input type="time" name="schedule[<?php echo $day; ?>][end]" value="17:00" class="cbp-admin-input" style="padding:6px; font-size:12px; width:110px;">
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <div style="padding:16px; background:#f8fafc; border-radius:12px; border:1px solid #f1f5f9; display:grid; grid-template-columns:1fr 1fr; gap:16px;">
                            <div class="cbp-admin-field" style="margin:0;">
                                <label style="font-size:11px; margin-bottom:4px;">Slot Duration (min)</label>
                                <input type="number" name="slot_duration" value="30" class="cbp-admin-input" style="padding:8px;">
                            </div>
                            <div class="cbp-admin-field" style="margin:0;">
                                <label style="font-size:11px; margin-bottom:4px;">Buffer Time (min)</label>
                                <input type="number" name="buffer_time" value="0" class="cbp-admin-input" style="padding:8px;">
                            </div>
                        </div>
                    </div>

                    <!-- Custom Mode UI -->
                    <div id="cbp-mode-custom" class="availability-mode-section" style="display:none;">
                        <div class="cbp-custom-accordion">
                            <?php foreach ($days as $day): ?>
                            <div class="cbp-acc-item" data-day="<?php echo $day; ?>" style="border:1px solid #f1f5f9; border-radius:8px; margin-bottom:8px; overflow:hidden;">
                                <div class="cbp-acc-header" style="background:#fff; padding:12px 16px; cursor:pointer; display:flex; justify-content:space-between; align-items:center; transition:background 0.2s;">
                                    <span style="font-weight:700; text-transform:capitalize; font-size:13px; color:#1e293b;"><?php echo $day; ?></span>
                                    <div style="display:flex; align-items:center; gap:10px;">
                                        <span class="cbp-slot-count" style="font-size:11px; color:#94a3b8; font-weight:600; background:#f8fafc; padding:2px 8px; border-radius:4px;">0 slots</span>
                                        <svg class="cbp-icon-chevron" viewBox="0 0 24 24" width="14" height="14" style="color:#94a3b8; transition:transform 0.2s;"><path d="M6 9l6 6 6-6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                    </div>
                                </div>
                                <div class="cbp-acc-content" style="display:none; padding:16px; background:#fcfdfe; border-top:1px solid #f1f5f9;">
                                    <div class="cbp-custom-slots-container" style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:16px;"></div>
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <input type="time" class="cbp-new-slot-input cbp-admin-input" style="width:115px; padding:6px; font-size:12px;">
                                        <button type="button" class="cbp-add-slot-btn button" style="padding:2px 12px; font-size:12px;">Add Slot</button>
                                        <button type="button" class="cbp-gen-slots-btn button" style="margin-left:auto; font-size:11px; opacity:0.7;">Gen from Auto Hours</button>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="custom_slots_json" id="cbp-custom-slots-json">
                    </div>

                    <div style="margin-top:20px; padding:12px; border-top:1px dashed #e2e8f0;">
                         <div class="cbp-admin-field" style="margin:0;">
                            <label style="font-size:11px; margin-bottom:4px; color:#64748b;">Maximum Bookings per Day</label>
                            <input type="number" name="max_bookings_per_day" value="15" class="cbp-admin-input" style="max-width:120px; padding:8px;">
                        </div>
                    </div>
                </div>
            </div>

            <div class="cbp-modal-footer" style="padding:20px; border-top:1px solid #e5e7eb; display:flex; justify-content:space-between; background:#f8fafc; border-radius: 0 0 16px 16px;">
                <button type="button" id="cbp-prev-step" class="button button-large" style="display:none;"><?php _e('← Previous', 'consultation-booking-pro'); ?></button>
                <div style="flex:1;"></div>
                <button type="button" id="cbp-next-step" class="button button-primary button-large"><?php _e('Next Step →', 'consultation-booking-pro'); ?></button>
                <button type="submit" id="cbp-save-counselor" class="button button-primary button-large" style="display:none; background:#059669; border-color:#059669;"><?php _e('Save Counselor', 'consultation-booking-pro'); ?></button>
            </div>
        </form>
    </div>
</div>