<?php if (!defined('ABSPATH')) exit; ?>

<div class="wrap">
    <h1 class="wp-heading-inline"><?php _e('Consultation Bookings', 'consultation-booking-pro'); ?></h1>
    <hr class="wp-header-end">

    <div class="cbp-bookings-filter-bar" style="background:#fff; padding:15px; margin:20px 0; border:1px solid #e5e7eb; border-radius:8px; display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
        <div>
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;"><?php _e('Search', 'consultation-booking-pro'); ?></label>
            <input type="text" id="cbp-booking-search" placeholder="<?php _e('Name or Email...', 'consultation-booking-pro'); ?>" class="regular-text">
        </div>
        <div>
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;"><?php _e('Date Range', 'consultation-booking-pro'); ?></label>
            <div style="display:flex; gap:8px; align-items:center;">
                <input type="date" id="cbp-filter-start" style="width:140px;"> 
                <span style="color:#64748b;">to</span> 
                <input type="date" id="cbp-filter-end" style="width:140px;">
            </div>
        </div>
        <div>
            <label style="display:block; font-size:12px; font-weight:600; margin-bottom:5px;">&nbsp;</label>
            <div style="display:flex; gap:8px;">
                <button id="cbp-apply-filters" class="button button-primary"><?php _e('Apply Filters', 'consultation-booking-pro'); ?></button>
                <button id="cbp-reset-filters" class="button"><?php _e('Reset', 'consultation-booking-pro'); ?></button>
                <a href="<?php echo wp_nonce_url(admin_url('admin.php?page=cbp-bookings&action=export_csv'), 'cbp_export_csv'); ?>" class="button button-secondary"><?php _e('Export to CSV', 'consultation-booking-pro'); ?></a>
            </div>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped table-view-list bookings">
        <thead>
            <tr>
                <td id="cb" class="manage-column column-cb check-column"><input type="checkbox"></td>
                <th class="manage-column column-id" style="width:50px;">ID</th>
                <th class="manage-column">Student</th>
                <th class="manage-column">Contact</th>
                <th class="manage-column">Date & Time</th>
                <th class="manage-column">Counselor</th>
                <th class="manage-column">Status</th>
                <th class="manage-column" style="text-align:right;">Actions</th>
            </tr>
        </thead>
        <tbody id="cbp-bookings-table-body">
            <?php 
            global $wpdb;
            $bookings = $wpdb->get_results("SELECT b.*, c.name as counselor_name FROM {$wpdb->prefix}cbp_bookings b LEFT JOIN {$wpdb->prefix}cbp_counselors c ON b.counselor_id = c.id ORDER BY b.created_at DESC LIMIT 50");
            
            if ($bookings):
                foreach ($bookings as $b):
                    $status_class = strtolower($b->status);
            ?>
                <tr data-date="<?php echo esc_attr($b->booking_date); ?>">
                    <th scope="row" class="check-column"><input type="checkbox"></th>
                    <td>#<?php echo $b->id; ?></td>
                    <td><strong><?php echo esc_html($b->student_name); ?></strong></td>
                    <td>
                        <div style="font-size:12px;"><?php echo esc_html($b->student_email); ?></div>
                        <div style="font-size:11px; color:#666;"><?php echo esc_html($b->student_phone); ?> (<?php echo esc_html($b->country); ?>)</div>
                    </td>
                    <td>
                        <div><?php echo date('M d, Y', strtotime($b->booking_date)); ?></div>
                        <div style="font-size:12px; color:#666;"><?php echo date('h:i A', strtotime($b->booking_time)); ?></div>
                    </td>
                    <td><?php echo esc_html($b->counselor_name ?: 'Unknown'); ?></td>
                    <td>
                        <span class="cbp-badge cbp-badge-<?php echo $status_class; ?>">
                            <?php echo ucfirst($b->status); ?>
                        </span>
                    </td>
                    <td style="text-align:right;">
                        <button class="button button-small view-booking-details" data-id="<?php echo $b->id; ?>">View</button>
                        <button class="button button-small delete-booking" data-id="<?php echo $b->id; ?>" style="color:#d63638;">Delete</button>
                    </td>
                </tr>
            <?php 
                endforeach;
            else:
            ?>
                <tr><td colspan="8" style="text-align:center; padding:40px;">No bookings found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
    
    <div id="cbp-no-results" style="display:none; text-align:center; padding:40px; color:#64748b;">
        <p style="font-size:16px;">No bookings match your filters.</p>
        <p style="font-size:13px;">Try adjusting your search or date range.</p>
    </div>
</div>

<!-- Booking Details Modal -->
<div id="cbp-booking-modal" class="cbp-modal" style="display:none;">
    <div class="cbp-modal-content" style="max-width:600px;">
        <div class="cbp-modal-header">
            <h3>Booking Details</h3>
            <span class="cbp-modal-close">&times;</span>
        </div>
        <div id="cbp-booking-details-content" style="padding:20px;">
            <div style="text-align:center; padding:20px;"><span class="cbp-spinner"></span> Loading...</div>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // View Booking Details
    $(document).on('click', '.view-booking-details', function() {
        var bookingId = $(this).data('id');
        $('#cbp-booking-details-content').html('<div style="text-align:center; padding:20px;"><span class="spinner is-active"></span> Loading...</div>');
        $('#cbp-booking-modal').css('display', 'flex').hide().fadeIn(200);
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cbp_get_booking_details',
                booking_id: bookingId,
                nonce: '<?php echo wp_create_nonce("wp_rest"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    var b = response.data;
                    var html = '<table class="widefat fixed" style="border:none;">';
                    html += '<tr><td style="padding:10px; font-weight:700;">Student</td><td>' + b.student_name + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Email</td><td>' + b.student_email + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Phone</td><td>' + (b.student_country_code || '') + ' ' + (b.student_phone || '') + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Country</td><td>' + (b.country || '') + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Study Level</td><td>' + (b.study_level || '') + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Field</td><td>' + (b.interested_field || '') + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Date</td><td>' + b.booking_date + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Time</td><td>' + b.booking_time + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Status</td><td>' + b.status + '</td></tr>';
                    html += '<tr><td style="padding:10px; font-weight:700;">Message</td><td>' + (b.message || 'N/A') + '</td></tr>';
                    html += '</table>';
                    $('#cbp-booking-details-content').html(html);
                } else {
                    $('#cbp-booking-details-content').html('<p style="color:red;">Failed to load details.</p>');
                }
            },
            error: function() {
                $('#cbp-booking-details-content').html('<p style="color:red;">Failed to load details.</p>');
            }
        });
    });

    // Delete Booking
    $(document).on('click', '.delete-booking', function() {
        if (!confirm('Are you sure you want to delete this booking? This cannot be undone.')) return;
        var bookingId = $(this).data('id');
        var row = $(this).closest('tr');
        
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'cbp_delete_booking',
                booking_id: bookingId,
                nonce: '<?php echo wp_create_nonce("wp_rest"); ?>'
            },
            success: function(response) {
                if (response.success) {
                    row.fadeOut(300, function() { 
                        $(this).remove(); 
                        checkEmptyTable();
                    });
                } else {
                    alert('Error: ' + (response.data?.message || 'Failed to delete'));
                }
            }
        });
    });

    // Check if table is empty after filtering
    function checkEmptyTable() {
        var visibleRows = $('#cbp-bookings-table-body tr:visible').length;
        if (visibleRows === 0) {
            $('#cbp-no-results').show();
        } else {
            $('#cbp-no-results').hide();
        }
    }

    // Search & Filter - FIXED
    $('#cbp-apply-filters').on('click', function() {
        var search = $('#cbp-booking-search').val().toLowerCase().trim();
        var startDate = $('#cbp-filter-start').val();
        var endDate = $('#cbp-filter-end').val();
        
        $('#cbp-bookings-table-body tr').each(function() {
            var row = $(this);
            var text = row.text().toLowerCase();
            var rowDate = row.data('date') || '';
            
            var show = true;
            
            // Search filter
            if (search && !text.includes(search)) {
                show = false;
            }
            
            // Date range filter
            if (show && (startDate || endDate)) {
                if (rowDate) {
                    if (startDate && rowDate < startDate) show = false;
                    if (endDate && rowDate > endDate) show = false;
                } else {
                    show = false;
                }
            }
            
            if (show) {
                row.show();
            } else {
                row.hide();
            }
        });
        
        checkEmptyTable();
    });
    
    // Reset filters
    $('#cbp-reset-filters').on('click', function() {
        $('#cbp-booking-search').val('');
        $('#cbp-filter-start').val('');
        $('#cbp-filter-end').val('');
        $('#cbp-bookings-table-body tr').show();
        $('#cbp-no-results').hide();
    });
    
    // Real-time search on Enter key
    $('#cbp-booking-search').on('keyup', function(e) {
        if (e.key === 'Enter') {
            $('#cbp-apply-filters').click();
        }
    });

    // Modal close
    $(document).on('click', '.cbp-modal-close', function() {
        $('#cbp-booking-modal').fadeOut(200);
    });
    
    $(document).on('click', '#cbp-booking-modal', function(e) {
        if (e.target === this) $(this).fadeOut(200);
    });
});
</script>

<style>
.cbp-badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 700; color: #fff; display:inline-block; }
.cbp-badge-confirmed { background: #10b981; }
.cbp-badge-pending { background: #f59e0b; }
.cbp-badge-cancelled { background: #ef4444; }
.cbp-bookings-filter-bar input[type="date"] { padding:4px 8px; border-radius:4px; border:1px solid #8c8f94; }
</style>