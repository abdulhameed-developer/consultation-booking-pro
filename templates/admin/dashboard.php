<?php 
if (!defined('ABSPATH')) exit; 
global $wpdb;

// Stats
$total_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cbp_bookings");
$confirmed_bookings = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cbp_bookings WHERE status = 'confirmed'");
$today_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cbp_bookings WHERE booking_date = CURDATE()");
$active_counselor = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cbp_counselors WHERE status = 'active' LIMIT 1");
?>

<div class="wrap cbp-admin-wrap">
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:30px;">
        <div>
            <h1 style="font-weight:800; font-size:28px; margin:0;"><?php _e('Booking Dashboard', 'consultation-booking-pro'); ?></h1>
            <p style="color:#64748b; margin-top:5px;"><?php echo date('l, F j, Y'); ?></p>
        </div>
        <div style="display:flex; gap:10px;">
            <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cbp-bookings&action=export_csv'), 'cbp_export_csv'); ?>" class="button button-primary button-large">
                <span class="dashicons dashicons-download" style="margin-top:4px;"></span> <?php _e('Export to CSV', 'consultation-booking-pro'); ?>
            </a>
            <a href="<?php echo admin_url('admin.php?page=cbp-counselors'); ?>" class="button button-secondary button-large"><?php _e('Manage Counselors', 'consultation-booking-pro'); ?></a>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="cbp-dashboard-grid" style="display:grid; grid-template-columns: repeat(4, 1fr); gap:20px; margin-bottom:40px;">
        <div class="cbp-stat-card" style="background:#fff; padding:25px; border-radius:16px; border:1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
            <div style="color:#64748b; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;"><?php _e('Total Bookings', 'consultation-booking-pro'); ?></div>
            <div style="font-size:32px; font-weight:800; margin-top:10px;"><?php echo number_format($total_bookings); ?></div>
        </div>
        <div class="cbp-stat-card" style="background:#fff; padding:25px; border-radius:16px; border:1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
            <div style="color:#10b981; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;"><?php _e('Confirmed', 'consultation-booking-pro'); ?></div>
            <div style="font-size:32px; font-weight:800; margin-top:10px;"><?php echo number_format($confirmed_bookings); ?></div>
        </div>
        <div class="cbp-stat-card" style="background:#fff; padding:25px; border-radius:16px; border:1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);">
            <div style="color:#6366f1; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;"><?php _e('Today\'s Appointments', 'consultation-booking-pro'); ?></div>
            <div style="font-size:32px; font-weight:800; margin-top:10px;"><?php echo number_format($today_count); ?></div>
        </div>
        <div class="cbp-stat-card" style="background:#f8fafc; padding:25px; border-radius:16px; border:1px solid #e2e8f0;">
            <div style="color:#64748b; font-size:12px; font-weight:700; text-transform:uppercase; letter-spacing:0.05em;"><?php _e('Active Counselor', 'consultation-booking-pro'); ?></div>
            <div style="font-size:18px; font-weight:700; margin-top:10px; display:flex; align-items:center; gap:8px;">
                <span class="dashicons dashicons-admin-users" style="color:#d63638"></span>
                <?php echo $active_counselor ? esc_html($active_counselor->name) : 'None'; ?>
            </div>
        </div>
    </div>

    <div style="display:grid; grid-template-columns: 2fr 1fr; gap:30px;">
        <!-- Left: Recent Appointments -->
        <div style="background:#fff; border-radius:16px; border:1px solid #e2e8f0; overflow:hidden;">
            <div style="padding:20px; border-bottom:1px solid #e2e8f0; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="margin:0; font-size:16px; font-weight:700;"><?php _e('Today’s Schedule', 'consultation-booking-pro'); ?></h3>
                <a href="<?php echo admin_url('admin.php?page=cbp-bookings'); ?>" style="font-size:12px; color:#d63638; font-weight:600; text-decoration:none;"><?php _e('View All Bookings', 'consultation-booking-pro'); ?> →</a>
            </div>
            <div style="padding:0;">
                <table class="wp-list-table widefat fixed striped" style="border:none; box-shadow:none;">
                    <thead>
                        <tr>
                            <th style="padding:15px;"><?php _e('Time', 'consultation-booking-pro'); ?></th>
                            <th style="padding:15px;"><?php _e('Student', 'consultation-booking-pro'); ?></th>
                            <th style="padding:15px;"><?php _e('Contact', 'consultation-booking-pro'); ?></th>
                            <th style="padding:15px; text-align:right;"><?php _e('Actions', 'consultation-booking-pro'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $today_bookings = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}cbp_bookings WHERE booking_date = CURDATE() ORDER BY booking_time ASC");
                        if ($today_bookings):
                            foreach ($today_bookings as $b):
                        ?>
                            <tr>
                                <td style="padding:15px;"><strong><?php echo date('h:i A', strtotime($b->booking_time)); ?></strong></td>
                                <td style="padding:15px;"><?php echo esc_html($b->student_name); ?></td>
                                <td style="padding:15px; font-size:12px; color:#64748b;"><?php echo esc_html($b->student_email); ?></td>
                                <td style="padding:15px; text-align:right;">
                                    <button class="button button-small view-booking-details" data-id="<?php echo $b->id; ?>"><?php _e('Details', 'consultation-booking-pro'); ?></button>
                                </td>
                            </tr>
                        <?php 
                            endforeach;
                        else:
                        ?>
                            <tr>
                                <td colspan="4" style="padding:60px; text-align:center; color:#94a3b8;">
                                    <span class="dashicons dashicons-calendar-alt" style="font-size:48px; width:48px; height:48px; margin-bottom:10px; opacity:0.3;"></span>
                                    <p><?php _e('No appointments for today.', 'consultation-booking-pro'); ?></p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Right: Activity Feed / Tips -->
        <div>
            <div style="background:#fff; border-radius:16px; border:1px solid #e2e8f0; padding:20px; margin-bottom:20px;">
                <h3 style="margin:0 0 15px 0; font-size:16px; font-weight:700;"><?php _e('Quick Tips', 'consultation-booking-pro'); ?></h3>
                <div style="display:flex; flex-direction:column; gap:15px;">
                    <div style="display:flex; gap:12px;">
                        <span class="dashicons dashicons-info" style="color:#6366f1;"></span>
                        <div style="font-size:13px; line-height:1.4;">
                            <strong>Google Meet:</strong> Make sure your calendar integration is active to automatically generate meeting links.
                        </div>
                    </div>
                    <div style="display:flex; gap:12px;">
                        <span class="dashicons dashicons-clock" style="color:#10b981;"></span>
                        <div style="font-size:13px; line-height:1.4;">
                            <strong>Buffer Time:</strong> You can adjust slot duration in the counselor settings to allow for breaks.
                        </div>
                    </div>
                </div>
            </div>

            <div style="background:var(--cbp-primary, #d63638); border-radius:16px; padding:25px; color:#fff; position:relative; overflow:hidden;">
                <div style="font-size:18px; font-weight:800; margin-bottom:10px;">Get More Bookings?</div>
                <p style="font-size:13px; margin:0; opacity:0.9;">Place the shortcode <code>[consultation_booking]</code> on any page to start accepting bookings.</p>
                <div class="dashicons dashicons-testimonial" style="position:absolute; right:-10px; bottom:-10px; font-size:100px; width:100px; height:100px; opacity:0.1;"></div>
            </div>
        </div>
    </div>
</div>
