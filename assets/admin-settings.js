/**
 * Consultation Booking Pro — Settings page (v1.2+)
 */
(function($) {
    'use strict';

    const nonce = window.cbpSettingsNonce || (window.cbpData && cbpData.nonce) || '';

    /* ---- Tabs ---- */
    $('.cbp-tab-btn').on('click', function() {
        const tab = $(this).data('tab');
        $('.cbp-tab-btn').removeClass('is-active');
        $(this).addClass('is-active');
        $('.cbp-tab-panel').removeClass('is-active');
        $('#cbp-tab-' + tab).addClass('is-active');
    });

    /* ---- Password toggle ---- */
    $(document).on('click', '.cbp-toggle-pw', function() {
        const id = $(this).data('target');
        const $f = $('#' + id);
        $f.attr('type', $f.attr('type') === 'password' ? 'text' : 'password');
    });

    /* ---- Copy button ---- */
    $(document).on('click', '.cbp-copy-btn', function() {
        const id = $(this).data('copy');
        const el = document.getElementById(id);
        if (el) {
            navigator.clipboard.writeText(el.value).then(() => {
                $(this).text('Copied!');
                setTimeout(() => $(this).text('Copy'), 2000);
            });
        }
    });

    function showAlert($el, type, html) {
        $el.html('<div class="cbp-alert cbp-alert--' + type + '">' + html + '</div>');
    }

    function renderSteps($container, steps) {
        if (!steps || !steps.length) return;
        let html = '<div class="cbp-progress-steps">';
        steps.forEach(function(s, i) {
            const cls = s.ok ? 'is-ok' : 'is-fail';
            const icon = s.ok ? '✔' : '✖';
            html += '<div class="cbp-progress-step ' + cls + '"><span class="cbp-progress-step__num">' + (i+1) + '</span><div><strong>' + s.step + '</strong><p>' + s.message + '</p></div></div>';
        });
        html += '</div>';
        $container.html(html).show();
    }

    function renderDiagTable($el, tests) {
        if (!tests) return;
        let html = '<table class="cbp-table"><thead><tr><th>Check</th><th>Status</th><th>Result</th></tr></thead><tbody>';
        tests.forEach(function(t) {
            const badge = t.status === 'PASS' ? 'success' : (t.status === 'FAIL' ? 'error' : 'warning');
            html += '<tr><td>' + t.label + '</td><td><span class="cbp-badge cbp-badge--' + badge + '">' + t.status + '</span></td><td>' + $('<div>').text(t.message).html() + '</td></tr>';
        });
        html += '</tbody></table>';
        $el.html(html);
    }

    /* ---- Test Email ---- */
    $('#cbp-send-test-email').on('click', function() {
        const btn = $(this);
        const email = $('#cbp-test-email-address').val();
        btn.prop('disabled', true).addClass('is-loading');
        $('#cbp-test-progress').hide();
        $('#cbp-test-result').html('<div class="cbp-alert cbp-alert--info">Testing SMTP…</div>');

        $.post(ajaxurl, { action: 'cbp_test_email', email: email, nonce: nonce }, function(res) {
            btn.prop('disabled', false).removeClass('is-loading');
            if (res.success) {
                renderSteps($('#cbp-test-progress'), res.data.steps);
                showAlert($('#cbp-test-result'), 'success', '✔ ' + res.data.message);
            } else {
                renderSteps($('#cbp-test-progress'), res.data?.steps);
                showAlert($('#cbp-test-result'), 'error', '✖ ' + (res.data?.message || 'Send failed'));
            }
        }).fail(function() {
            btn.prop('disabled', false).removeClass('is-loading');
            showAlert($('#cbp-test-result'), 'error', '✖ AJAX request failed');
        });
    });

    /* ---- SMTP Diagnostics ---- */
    $('#cbp-run-smtp-diagnostics').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true).addClass('is-loading');
        $('#cbp-smtp-diagnostics').html('<div class="cbp-alert cbp-alert--info">Running diagnostics…</div>');
        $.post(ajaxurl, { action: 'cbp_run_smtp_diagnostics', nonce: nonce }, function(res) {
            btn.prop('disabled', false).removeClass('is-loading');
            if (res.success) renderDiagTable($('#cbp-smtp-diagnostics'), res.data.tests);
        });
    });

    /* ---- Email Queue ---- */
    $(document).on('click', '.cbp-retry-queue', function() {
        const id = $(this).data('id');
        const btn = $(this);
        btn.prop('disabled', true).text('…');
        $.post(ajaxurl, { action: 'cbp_retry_email_queue', queue_id: id, nonce: nonce }, function(res) {
            location.reload();
        });
    });

    $('#cbp-retry-all-queue').on('click', function() {
        $(this).prop('disabled', true);
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

    /* ---- Google tests ---- */
    $('#cbp-test-google-connection').on('click', function() {
        const btn = $(this);
        btn.prop('disabled', true);
        $('#cbp-google-test-result').html('<div class="cbp-alert cbp-alert--info">Testing Google Calendar API…</div>');
        $.post(ajaxurl, { action: 'cbp_test_google_connection', nonce: nonce }, function(res) {
            btn.prop('disabled', false);
            if (res.success) {
                showAlert($('#cbp-google-test-result'), 'success', '✔ ' + res.data.message);
                $('#cbp-google-api-status').text('✔ ' + res.data.message);
            } else {
                showAlert($('#cbp-google-test-result'), 'error', '✖ ' + (res.data?.message || res.data?.error || 'Failed'));
            }
        });
    });

    $('#cbp-test-google-event').on('click', function() {
        const btn = $(this);
        const student = prompt('Student test email:', $('#cbp-test-email-address').val() || '');
        const counselor = prompt('Counselor test email:', '');
        if (!student || !counselor) return;
        btn.prop('disabled', true);
        $('#cbp-google-test-result').html('<div class="cbp-alert cbp-alert--info">Creating test event…</div>');
        $.post(ajaxurl, {
            action: 'cbp_test_calendar_event', nonce: nonce,
            student_email: student, counselor_email: counselor
        }, function(res) {
            btn.prop('disabled', false);
            if (res.success) {
                const d = res.data;
                let msg = '✔ Event created: ' + d.event_id;
                if (d.meet_link) msg += '<br>Meet: <a href="' + d.meet_link + '" target="_blank">' + d.meet_link + '</a>';
                msg += '<br>Student invited: ' + (d.student_invited ? 'Yes' : 'No');
                msg += '<br>Counselor invited: ' + (d.counselor_invited ? 'Yes' : 'No');
                msg += '<br>Test event deleted: ' + (d.event_deleted ? 'Yes' : 'No');
                showAlert($('#cbp-google-test-result'), 'success', msg);
            } else {
                showAlert($('#cbp-google-test-result'), 'error', '✖ ' + (res.data?.error || res.data?.message || 'Failed'));
            }
        });
    });

})(jQuery);
