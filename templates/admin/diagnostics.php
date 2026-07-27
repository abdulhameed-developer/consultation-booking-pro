<?php if (!defined('ABSPATH')) exit;

global $wpdb;
$active = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cbp_counselors WHERE status = 'active' LIMIT 1");
$log_dir = \ConsultationBookingPro\Services\LogService::getLogDir();
$channels = \ConsultationBookingPro\Services\LogService::getAllChannels();
$nonce = wp_create_nonce('wp_rest');

$quick_checks = [
    ['id' => 'database', 'label' => 'Database', 'icon' => 'dashicons-database'],
    ['id' => 'smtp', 'label' => 'SMTP', 'icon' => 'dashicons-email-alt'],
    ['id' => 'google_oauth', 'label' => 'Google OAuth', 'icon' => 'dashicons-google'],
    ['id' => 'calendar_api', 'label' => 'Google Calendar API', 'icon' => 'dashicons-calendar-alt'],
    ['id' => 'event_creation', 'label' => 'Event Creation', 'icon' => 'dashicons-plus-alt'],
    ['id' => 'email_sending', 'label' => 'Email Sending', 'icon' => 'dashicons-email'],
    ['id' => 'php_extensions', 'label' => 'PHP Extensions', 'icon' => 'dashicons-admin-tools'],
    ['id' => 'wp_cron', 'label' => 'WP Cron', 'icon' => 'dashicons-clock'],
    ['id' => 'file_permissions', 'label' => 'File Permissions', 'icon' => 'dashicons-lock'],
    ['id' => 'logs', 'label' => 'Logs', 'icon' => 'dashicons-media-text'],
];
?>
<div class="cbp-app cbp-diagnostics-app">
    <header class="cbp-page-header">
        <div class="cbp-page-header__left">
            <h1>Diagnostics</h1>
            <p class="cbp-page-header__sub">Real-time status for database, SMTP, Google Calendar, email, and system health</p>
        </div>
        <div class="cbp-page-header__right">
            <span class="cbp-version-badge">v<?php echo esc_html(CBP_VERSION); ?></span>
            <span class="cbp-build-badge"><?php echo esc_html(CBP_BUILD_ID); ?></span>
        </div>
    </header>

    <div class="cbp-card">
        <div class="cbp-card__header">
            <h2>System Status</h2>
            <button type="button" id="cbp-run-diagnostics" class="cbp-btn cbp-btn--primary">Run All Tests</button>
        </div>
        <div id="cbp-diagnostics-cards" class="cbp-diagnostics-grid">
            <?php foreach ($quick_checks as $check): ?>
            <div class="cbp-diag-card" id="cbp-diag-card-<?php echo esc_attr($check['id']); ?>" data-test-id="<?php echo esc_attr($check['id']); ?>">
                <div class="cbp-diag-card__title">
                    <span class="dashicons <?php echo esc_attr($check['icon']); ?>"></span>
                    <?php echo esc_html($check['label']); ?>
                </div>
                <div class="cbp-diag-card__status cbp-badge cbp-badge--warning">Pending</div>
                <p class="cbp-diag-card__msg">Click "Run All Tests" to check</p>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="cbp-diagnostics-results"></div>
    </div>

    <div class="cbp-form-grid cbp-form-grid--2">
        <div class="cbp-card">
            <div class="cbp-card__header"><h2>Manual Integration Tests</h2></div>
            <div class="cbp-field">
                <label>Student test email</label>
                <input type="email" id="cbp-diag-student-email" class="cbp-input" placeholder="student@example.com">
            </div>
            <div class="cbp-field">
                <label>Counselor test email</label>
                <input type="email" id="cbp-diag-counselor-email" class="cbp-input" value="<?php echo esc_attr($active ? $active->email : get_option('admin_email')); ?>">
            </div>
            <div class="cbp-btn-row">
                <button type="button" id="cbp-test-booking-emails" class="cbp-btn cbp-btn--secondary">Test Emails</button>
                <button type="button" id="cbp-test-calendar-event" class="cbp-btn cbp-btn--secondary">Test Calendar Event</button>
            </div>
            <div id="cbp-manual-test-results" class="cbp-alert-area"></div>
        </div>

        <div class="cbp-card">
            <div class="cbp-card__header"><h2>Active Counselor</h2></div>
            <?php if ($active): ?>
            <div class="cbp-status-grid">
                <div class="cbp-status-item">
                    <span class="cbp-status-item__label">Name</span>
                    <span class="cbp-status-item__value"><?php echo esc_html($active->name); ?></span>
                </div>
                <div class="cbp-status-item">
                    <span class="cbp-status-item__label">Email</span>
                    <span class="cbp-status-item__value"><?php echo esc_html($active->email); ?></span>
                </div>
                <div class="cbp-status-item">
                    <span class="cbp-status-item__label">Calendar ID</span>
                    <span class="cbp-status-item__value"><code><?php echo esc_html($active->google_calendar_id ?: 'primary'); ?></code></span>
                </div>
                <div class="cbp-status-item">
                    <span class="cbp-status-item__label">OAuth</span>
                    <span class="cbp-status-item__value"><?php echo $active->google_refresh_token ? '✔ Connected' : '✖ Not connected'; ?></span>
                </div>
                <div class="cbp-status-item">
                    <span class="cbp-status-item__label">Token Expiry</span>
                    <span class="cbp-status-item__value"><?php echo esc_html($active->google_token_expiry ?: 'N/A'); ?></span>
                </div>
            </div>
            <?php if (!$active->google_refresh_token): ?>
            <div class="cbp-alert cbp-alert--warning">Connect Google Calendar in <a href="<?php echo esc_url(admin_url('admin.php?page=cbp-settings')); ?>">Settings</a>.</div>
            <?php endif; ?>
            <?php else: ?>
            <div class="cbp-alert cbp-alert--error">No active counselor. <a href="<?php echo esc_url(admin_url('admin.php?page=cbp-counselors')); ?>">Add one</a>.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="cbp-card">
        <div class="cbp-card__header">
            <h2>Log Files</h2>
            <div class="cbp-btn-row">
                <button type="button" id="cbp-refresh-log" class="cbp-btn cbp-btn--ghost cbp-btn--sm">Refresh</button>
                <button type="button" id="cbp-clear-log" class="cbp-btn cbp-btn--ghost cbp-btn--sm">Clear Active Log</button>
            </div>
        </div>
        <p class="cbp-field-hint"><code><?php echo esc_html($log_dir); ?>/</code></p>
        <div class="cbp-log-tabs" role="tablist">
            <?php foreach ($channels as $i => $ch): ?>
            <button type="button" class="cbp-log-tab<?php echo $i === 0 ? ' is-active' : ''; ?>" data-channel="<?php echo esc_attr($ch); ?>"><?php echo esc_html($ch); ?>.log</button>
            <?php endforeach; ?>
        </div>
        <?php foreach ($channels as $i => $ch):
            $lines = \ConsultationBookingPro\Services\LogService::getRecentLines($ch, 80);
        ?>
        <pre class="cbp-log-viewer cbp-log-panel" id="cbp-log-<?php echo esc_attr($ch); ?>" data-channel="<?php echo esc_attr($ch); ?>" style="<?php echo $i === 0 ? '' : 'display:none;'; ?>"><?php
            echo esc_html($lines ? implode("\n", $lines) : '(Empty — activity will appear here.)');
        ?></pre>
        <?php endforeach; ?>
    </div>
</div>

<script>
jQuery(function($) {
    const nonce = <?php echo wp_json_encode($nonce); ?>;
    let activeLogChannel = 'plugin';

    const testIdMap = {
        'database': 'database',
        'smtp': 'smtp',
        'wp_mail': 'email_sending',
        'oauth_connection': 'google_oauth',
        'refresh_token': 'google_oauth',
        'access_token': 'google_oauth',
        'calendar_api': 'calendar_api',
        'event_creation': 'event_creation',
        'php_extensions': 'php_extensions',
        'wp_cron': 'wp_cron',
        'file_permissions': 'file_permissions',
        'log_file': 'logs'
    };

    function updateCard(testId, status, message) {
        const cardId = testIdMap[testId] || testId;
        const $card = $('#cbp-diag-card-' + cardId);
        if (!$card.length) return;
        const pass = status === 'PASS';
        $card.removeClass('is-pass is-fail').addClass(pass ? 'is-pass' : 'is-fail');
        $card.find('.cbp-diag-card__status')
            .removeClass('cbp-badge--success cbp-badge--error cbp-badge--warning')
            .addClass(pass ? 'cbp-badge--success' : 'cbp-badge--error')
            .text(status);
        $card.find('.cbp-diag-card__msg').text(message);
    }

    function renderDetailTable(tests) {
        if (!tests || !tests.length) return;
        let html = '<table class="cbp-table" style="margin-top:20px;"><thead><tr><th>Test</th><th>Status</th><th>Result</th></tr></thead><tbody>';
        tests.forEach(function(t) {
            const badge = t.status === 'PASS' ? 'success' : (t.status === 'FAIL' ? 'error' : 'warning');
            html += '<tr><td><strong>' + t.label + '</strong></td>';
            html += '<td><span class="cbp-badge cbp-badge--' + badge + '">' + t.status + '</span></td>';
            html += '<td>' + $('<div>').text(t.message).html() + '</td></tr>';
            updateCard(t.id, t.status, t.message);
        });
        html += '</tbody></table>';
        $('#cbp-diagnostics-results').html(html);
    }

    $('#cbp-run-diagnostics').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).addClass('is-loading').text('Running…');
        $('.cbp-diag-card').removeClass('is-pass is-fail');
        $('.cbp-diag-card__status').removeClass('cbp-badge--success cbp-badge--error').addClass('cbp-badge--warning').text('Running…');
        $.post(ajaxurl, { action: 'cbp_run_diagnostics', nonce: nonce }, function(res) {
            btn.prop('disabled', false).removeClass('is-loading').text('Run All Tests');
            if (res.success) renderDetailTable(res.data.tests);
            else $('#cbp-diagnostics-results').html('<div class="cbp-alert cbp-alert--error">' + (res.data?.message || 'Failed') + '</div>');
        }).fail(function() {
            btn.prop('disabled', false).removeClass('is-loading').text('Run All Tests');
        });
    });

    $('.cbp-log-tab').on('click', function() {
        const ch = $(this).data('channel');
        activeLogChannel = ch;
        $('.cbp-log-tab').removeClass('is-active');
        $(this).addClass('is-active');
        $('.cbp-log-panel').hide();
        $('#cbp-log-' + ch).show();
    });

    $('#cbp-test-booking-emails').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).addClass('is-loading');
        $('#cbp-manual-test-results').html('<div class="cbp-alert cbp-alert--info">Sending test emails…</div>');
        $.post(ajaxurl, {
            action: 'cbp_test_booking_emails', nonce: nonce,
            student_email: $('#cbp-diag-student-email').val(),
            counselor_email: $('#cbp-diag-counselor-email').val()
        }, function(res) {
            btn.prop('disabled', false).removeClass('is-loading');
            if (!res.success) {
                $('#cbp-manual-test-results').html('<div class="cbp-alert cbp-alert--error">✖ ' + (res.data?.message || 'Failed') + '</div>');
                return;
            }
            const s = res.data.student, c = res.data.counselor;
            let html = '<div class="cbp-alert cbp-alert--' + (s.sent && c.sent ? 'success' : 'error') + '"><ul style="margin:0;padding-left:18px;">';
            html += '<li>Student: ' + (s.sent ? '✔ Sent' : '✖ ' + (s.error || 'Failed')) + '</li>';
            html += '<li>Counselor: ' + (c.sent ? '✔ Sent' : '✖ ' + (c.error || 'Failed')) + '</li></ul></div>';
            $('#cbp-manual-test-results').html(html);
        });
    });

    $('#cbp-test-calendar-event').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).addClass('is-loading');
        $('#cbp-manual-test-results').html('<div class="cbp-alert cbp-alert--info">Creating test event…</div>');
        $.post(ajaxurl, {
            action: 'cbp_test_calendar_event', nonce: nonce,
            student_email: $('#cbp-diag-student-email').val(),
            counselor_email: $('#cbp-diag-counselor-email').val()
        }, function(res) {
            btn.prop('disabled', false).removeClass('is-loading');
            if (!res.success) {
                $('#cbp-manual-test-results').html('<div class="cbp-alert cbp-alert--error">✖ ' + (res.data?.error || res.data?.message || 'Failed') + '</div>');
                return;
            }
            const d = res.data;
            let msg = '✔ Event ' + d.event_id + ' — Student: ' + (d.student_invited ? '✔' : '✖') + ', Counselor: ' + (d.counselor_invited ? '✔' : '✖');
            if (d.meet_link) msg += '<br>Meet: <a href="' + d.meet_link + '" target="_blank">' + d.meet_link + '</a>';
            msg += '<br>Deleted: ' + (d.event_deleted ? '✔' : '✖');
            $('#cbp-manual-test-results').html('<div class="cbp-alert cbp-alert--success">' + msg + '</div>');
        });
    });

    $('#cbp-clear-log').on('click', function() {
        $.post(ajaxurl, { action: 'cbp_clear_plugin_log', nonce: nonce, channel: activeLogChannel }, function() {
            $('#cbp-log-' + activeLogChannel).text('(Log cleared.)');
        });
    });

    $('#cbp-refresh-log').on('click', function() { location.reload(); });
});
</script>
