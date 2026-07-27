<?php if (!defined('ABSPATH')) exit;

$nonce = wp_create_nonce('wp_rest');
$email_log = \ConsultationBookingPro\Services\EmailLogService::getRecentLines(80);
$email_log_file = \ConsultationBookingPro\Services\EmailLogService::getLogFile();
$queue = \ConsultationBookingPro\Services\EmailQueueService::getAll(50);
?>

<div style="max-width:800px; margin-top:10px;">
    <!-- Test Email -->
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:25px; margin-bottom:24px;">
        <h3 style="margin:0 0 16px; font-size:16px; font-weight:800;">Send Test Email</h3>
        <p class="description">Save SMTP settings first, then send a test to verify connection, authentication, and delivery.</p>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap; margin-top:12px;">
            <input type="email" id="cbp-test-email-address" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="regular-text">
            <button type="button" id="cbp-send-test-email" class="button button-primary">Send Test Email</button>
            <button type="button" id="cbp-run-smtp-diagnostics" class="button">Run SMTP Diagnostics</button>
        </div>
        <div id="cbp-test-result" style="margin-top:16px;"></div>
        <div id="cbp-smtp-diagnostics" style="margin-top:16px;"></div>
    </div>

    <!-- Email Queue -->
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:25px; margin-bottom:24px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:16px;">
            <h3 style="margin:0; font-size:16px; font-weight:800;">Email Queue</h3>
            <button type="button" id="cbp-retry-all-queue" class="button">Retry All Pending</button>
        </div>
        <?php if (empty($queue)): ?>
            <p style="color:#64748b; margin:0;">No queued emails.</p>
        <?php else: ?>
            <table class="widefat striped" style="border:none;">
                <thead>
                    <tr>
                        <th>Recipient</th>
                        <th>Subject</th>
                        <th>Status</th>
                        <th>Retries</th>
                        <th>Failure Reason</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($queue as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item->recipient); ?></td>
                        <td><?php echo esc_html(wp_trim_words($item->subject, 6)); ?></td>
                        <td><code><?php echo esc_html($item->status); ?></code></td>
                        <td><?php echo (int) $item->retry_count; ?> / <?php echo (int) $item->max_retries; ?></td>
                        <td style="font-size:12px; max-width:200px;"><?php echo esc_html(wp_trim_words($item->failure_reason ?? '', 12)); ?></td>
                        <td>
                            <?php if ($item->status !== 'sent'): ?>
                            <button type="button" class="button button-small cbp-retry-queue" data-id="<?php echo (int) $item->id; ?>">Retry</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <!-- Email Log -->
    <div style="background:#fff; border:1px solid #e2e8f0; border-radius:16px; padding:25px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:12px;">
            <h3 style="margin:0; font-size:16px; font-weight:800;">SMTP Session Log</h3>
            <button type="button" id="cbp-clear-email-log" class="button">Clear Log</button>
        </div>
        <p style="color:#64748b; font-size:13px;"><code><?php echo esc_html($email_log_file); ?></code></p>
        <pre id="cbp-email-log-viewer" style="background:#0f172a; color:#e2e8f0; padding:16px; border-radius:8px; max-height:350px; overflow:auto; font-size:11px; line-height:1.5; margin:0;"><?php
            echo esc_html($email_log ? implode("\n", $email_log) : '(No SMTP log entries yet.)');
        ?></pre>
    </div>
</div>

<script>
jQuery(function($) {
    const nonce = <?php echo wp_json_encode($nonce); ?>;

    function renderSteps(steps, container) {
        if (!steps || !steps.length) return;
        let html = '<ul style="margin:8px 0 0; padding-left:0; list-style:none;">';
        steps.forEach(function(s) {
            const color = s.ok ? '#059669' : '#dc2626';
            const icon = s.ok ? '✔' : '✖';
            html += '<li style="margin-bottom:6px; color:' + color + ';">' + icon + ' <strong>' + s.step + ':</strong> ' + $('<div>').text(s.message).html() + '</li>';
        });
        html += '</ul>';
        $(container).html(html);
    }

    function renderDiagTests(tests) {
        if (!tests) return;
        let html = '<table class="widefat striped" style="margin-top:8px;"><thead><tr><th>Check</th><th>Status</th><th>Result</th></tr></thead><tbody>';
        tests.forEach(function(t) {
            const color = t.status === 'PASS' ? '#059669' : (t.status === 'FAIL' ? '#dc2626' : '#64748b');
            html += '<tr><td>' + t.label + '</td><td style="color:' + color + '; font-weight:700;">' + t.status + '</td><td style="font-size:12px;">' + $('<div>').text(t.message).html() + '</td></tr>';
        });
        html += '</tbody></table>';
        $('#cbp-smtp-diagnostics').html(html);
    }

    $('#cbp-send-test-email').on('click', function() {
        const btn = $(this);
        const email = $('#cbp-test-email-address').val();
        btn.prop('disabled', true).text('Sending…');
        $('#cbp-test-result').html('<p>Testing SMTP connection and sending…</p>');
        $.post(ajaxurl, { action: 'cbp_test_email', email: email, nonce: nonce }, function(res) {
            btn.prop('disabled', false).text('Send Test Email');
            if (res.success) {
                $('#cbp-test-result').html('<p style="color:#059669; font-weight:700;">✔ ' + res.data.message + '</p>');
                renderSteps(res.data.steps, '#cbp-test-result');
            } else {
                $('#cbp-test-result').html('<p style="color:#dc2626; font-weight:700;">✖ ' + (res.data?.message || 'Failed') + '</p>');
                renderSteps(res.data?.steps, '#cbp-test-result');
            }
        });
    });

    $('#cbp-run-smtp-diagnostics').on('click', function() {
        $('#cbp-smtp-diagnostics').html('<p>Running…</p>');
        $.post(ajaxurl, { action: 'cbp_run_smtp_diagnostics', nonce: nonce }, function(res) {
            if (res.success) renderDiagTests(res.data.tests);
        });
    });

    $(document).on('click', '.cbp-retry-queue', function() {
        const id = $(this).data('id');
        const btn = $(this);
        btn.prop('disabled', true).text('…');
        $.post(ajaxurl, { action: 'cbp_retry_email_queue', queue_id: id, nonce: nonce }, function(res) {
            if (res.success) location.reload();
            else { alert(res.data?.message || 'Retry failed'); btn.prop('disabled', false).text('Retry'); }
        });
    });

    $('#cbp-retry-all-queue').on('click', function() {
        $.post(ajaxurl, { action: 'cbp_retry_all_email_queue', nonce: nonce }, function(res) {
            if (res.success) {
                alert('Sent: ' + res.data.sent + ', Failed: ' + res.data.failed);
                location.reload();
            }
        });
    });

    $('#cbp-clear-email-log').on('click', function() {
        $.post(ajaxurl, { action: 'cbp_clear_email_log', nonce: nonce }, function() {
            $('#cbp-email-log-viewer').text('(Log cleared.)');
        });
    });
});
</script>
