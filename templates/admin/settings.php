<?php
/**
 * Consultation Booking Pro — Settings (v1.2+)
 * Tabbed: Google Calendar | Email & SMTP | General
 */
if (!defined('ABSPATH')) exit;

global $wpdb;
$active = $wpdb->get_row("SELECT * FROM {$wpdb->prefix}cbp_counselors WHERE status = 'active' LIMIT 1");
$settings_svc = new \ConsultationBookingPro\Services\SettingsService();
$auth_svc = new \ConsultationBookingPro\Services\GoogleAuthService();
$smtp = \ConsultationBookingPro\Services\SmtpSettingsService::getSettings();
$has_smtp_pw = (bool) get_option('cbp_smtp_password', '');
$queue = \ConsultationBookingPro\Services\EmailQueueService::getAll(30);
$email_log = \ConsultationBookingPro\Services\EmailLogService::getRecentLines(60);
$nonce = wp_create_nonce('wp_rest');

$client_id = get_option('cbp_google_client_id', '');
$client_secret = get_option('cbp_google_client_secret', '');
$redirect_uri = get_option('cbp_google_redirect_uri', admin_url('admin.php?page=cbp-google-callback'));
$g_connected = $active && !empty($active->google_refresh_token);
$auth_url = ($client_id && $active) ? $auth_svc->getAuthUrl($client_id, $settings_svc->getGoogleRedirectUri(), (string) $active->id) : '';
$cal_name = '';
if ($g_connected && $active) {
    try {
        $diag = new \ConsultationBookingPro\Services\DiagnosticsService();
        $tok = $diag->resolveAccessToken($active);
        if ($tok['success']) {
            $cal = new \ConsultationBookingPro\Services\GoogleCalendarService($tok['token'], $active->google_calendar_id ?: 'primary');
            $meta = $cal->testConnection();
            if ($meta['success']) {
                $cal_name = $meta['data']['summary'] ?? '';
            }
        }
    } catch (\Throwable $e) { /* ignore on settings load */ }
}
?>
<div class="cbp-app cbp-settings-app">
    <header class="cbp-page-header">
        <div class="cbp-page-header__left">
            <h1>Settings</h1>
            <p class="cbp-page-header__sub">Configure Google Calendar, built-in SMTP, and plugin options</p>
        </div>
        <div class="cbp-page-header__right">
            <span class="cbp-version-badge">v<?php echo esc_html(CBP_VERSION); ?></span>
            <span class="cbp-build-badge"><?php echo esc_html(CBP_BUILD_ID); ?></span>
        </div>
    </header>

    <nav class="cbp-tab-nav" role="tablist">
        <button type="button" class="cbp-tab-btn is-active" data-tab="google" role="tab">
            <span class="dashicons dashicons-google"></span> Google Calendar
        </button>
        <button type="button" class="cbp-tab-btn" data-tab="email" role="tab">
            <span class="dashicons dashicons-email-alt"></span> Email &amp; SMTP
        </button>
        <button type="button" class="cbp-tab-btn" data-tab="general" role="tab">
            <span class="dashicons dashicons-admin-settings"></span> General
        </button>
    </nav>

    <form method="post" action="options.php" id="cbp-settings-form">
        <?php settings_fields('cbp_settings_group'); ?>

        <!-- ===== GOOGLE TAB ===== -->
        <div class="cbp-tab-panel is-active" id="cbp-tab-google" role="tabpanel">
            <?php if ($active): ?>
            <div class="cbp-card cbp-google-dashboard">
                <div class="cbp-card__header">
                    <h2>Google Calendar Dashboard</h2>
                    <?php echo $g_connected
                        ? '<span class="cbp-badge cbp-badge--success">Connected</span>'
                        : '<span class="cbp-badge cbp-badge--error">Not Connected</span>'; ?>
                </div>
                <div class="cbp-status-grid">
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">Counselor</span>
                        <span class="cbp-status-item__value"><?php echo esc_html($active->name); ?></span>
                    </div>
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">Connected Email</span>
                        <span class="cbp-status-item__value"><?php echo esc_html($active->email); ?></span>
                    </div>
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">Calendar Name</span>
                        <span class="cbp-status-item__value"><?php echo esc_html($cal_name ?: '—'); ?></span>
                    </div>
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">Calendar ID</span>
                        <span class="cbp-status-item__value"><code><?php echo esc_html($active->google_calendar_id ?: 'primary'); ?></code></span>
                    </div>
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">OAuth Status</span>
                        <span class="cbp-status-item__value"><?php echo $g_connected ? '✔ Refresh token stored' : '✖ Not authorized'; ?></span>
                    </div>
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">Counselor Timezone</span>
                        <span class="cbp-status-item__value"><code><?php echo esc_html($active->timezone ?: 'UTC'); ?></code></span>
                    </div>
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">Token Expiration</span>
                        <span class="cbp-status-item__value"><?php echo esc_html($active->google_token_expiry ?: 'N/A'); ?></span>
                    </div>
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">Refresh Token</span>
                        <span class="cbp-status-item__value"><?php echo $g_connected ? '✔ Present' : '✖ Missing'; ?></span>
                    </div>
                    <div class="cbp-status-item">
                        <span class="cbp-status-item__label">API Status</span>
                        <span class="cbp-status-item__value" id="cbp-google-api-status"><?php echo $cal_name ? '✔ Calendar accessible' : ($g_connected ? 'Run test below' : 'Connect first'); ?></span>
                    </div>
                </div>
                <div class="cbp-btn-row">
                    <?php if ($auth_url && !$g_connected): ?>
                        <a href="<?php echo esc_url($auth_url); ?>" class="cbp-btn cbp-btn--primary" target="_blank" rel="noopener">Connect Google Calendar</a>
                    <?php endif; ?>
                    <?php if ($g_connected && $auth_url): ?>
                        <a href="<?php echo esc_url($auth_url); ?>" class="cbp-btn cbp-btn--secondary" target="_blank" rel="noopener">Reconnect</a>
                        <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cbp-counselors&action=disconnect&id=' . $active->id), 'cbp_disconnect_google')); ?>" class="cbp-btn cbp-btn--ghost">Disconnect</a>
                    <?php endif; ?>
                    <button type="button" class="cbp-btn cbp-btn--secondary" id="cbp-test-google-connection">Test Connection</button>
                    <button type="button" class="cbp-btn cbp-btn--secondary" id="cbp-test-google-event">Create Test Event</button>
                </div>
                <div id="cbp-google-test-result" class="cbp-alert-area"></div>
            </div>

            <div class="cbp-card">
                <div class="cbp-card__header"><h2>How time &amp; calendar sync works</h2></div>
                <ol style="margin:0; padding-left:20px; color:#334155; line-height:1.7; font-size:14px;">
                    <li><strong>Counselor timezone</strong> (set under Counselors) is the single clock for slots, emails, and Google events.</li>
                    <li>When a student picks <em>09:00 AM</em>, the plugin creates a Google event at <em>09:00 AM in that timezone</em> — not shifted to UTC or the server clock.</li>
                    <li><strong>Manual Google Calendar events</strong> marked Busy (default) block those slots for students automatically via FreeBusy + events sync.</li>
                    <li>Events marked Free / Transparent do not block slots (Google Calendar behaviour).</li>
                    <li>Reconnect Google Calendar to auto-import the calendar’s timezone into the counselor profile.</li>
                </ol>
                <p class="cbp-field-hint" style="margin-top:12px;">Edit timezone under <a href="<?php echo esc_url(admin_url('admin.php?page=cbp-counselors')); ?>">Counselors → Edit → General Info</a>.</p>
            </div>
            <?php else: ?>
            <div class="cbp-alert cbp-alert--warning">No active counselor. <a href="<?php echo admin_url('admin.php?page=cbp-counselors'); ?>">Add a counselor first</a>.</div>
            <?php endif; ?>

            <div class="cbp-card">
                <div class="cbp-card__header"><h2>Google OAuth Credentials</h2></div>
                <div class="cbp-form-grid cbp-form-grid--2">
                    <div class="cbp-field">
                        <label>Client ID</label>
                        <input type="text" name="cbp_google_client_id" value="<?php echo esc_attr($client_id); ?>" class="cbp-input" placeholder="Google Cloud OAuth Client ID">
                    </div>
                    <div class="cbp-field">
                        <label>Client Secret</label>
                        <div class="cbp-input-group">
                            <input type="password" name="cbp_google_client_secret" value="<?php echo esc_attr($client_secret); ?>" class="cbp-input" id="cbp-google-secret">
                            <button type="button" class="cbp-btn cbp-btn--icon cbp-toggle-pw" data-target="cbp-google-secret" title="Show/hide">👁</button>
                        </div>
                    </div>
                </div>
                <div class="cbp-field">
                    <label>Authorized Redirect URI</label>
                    <div class="cbp-input-group">
                        <input type="text" name="cbp_google_redirect_uri" value="<?php echo esc_attr($redirect_uri); ?>" class="cbp-input" id="cbp-redirect-uri" readonly>
                        <button type="button" class="cbp-btn cbp-btn--secondary cbp-copy-btn" data-copy="cbp-redirect-uri">Copy</button>
                    </div>
                    <p class="cbp-field-hint cbp-field-hint--warn">Must match exactly in Google Cloud Console → Credentials</p>
                </div>
            </div>
        </div>

        <!-- ===== EMAIL & SMTP TAB ===== -->
        <div class="cbp-tab-panel" id="cbp-tab-email" role="tabpanel">
            <div class="cbp-alert cbp-alert--info">
                <strong>Built-in SMTP</strong> — This plugin sends all booking emails through its own SMTP mailer. No WP Mail SMTP or third-party plugin required.
            </div>

            <div class="cbp-card">
                <div class="cbp-card__header">
                    <h2>SMTP Configuration</h2>
                    <?php echo \ConsultationBookingPro\Services\SmtpSettingsService::isConfigured()
                        ? '<span class="cbp-badge cbp-badge--success">Configured</span>'
                        : '<span class="cbp-badge cbp-badge--warning">Not Configured</span>'; ?>
                </div>

                <div class="cbp-form-grid cbp-form-grid--2">
                    <div class="cbp-field">
                        <label>SMTP Host <span class="req">*</span></label>
                        <input type="text" name="cbp_smtp_host" value="<?php echo esc_attr($smtp['host']); ?>" class="cbp-input" placeholder="smtp.gmail.com">
                    </div>
                    <div class="cbp-field">
                        <label>SMTP Port <span class="req">*</span></label>
                        <input type="number" name="cbp_smtp_port" value="<?php echo esc_attr($smtp['port']); ?>" class="cbp-input" placeholder="587">
                    </div>
                </div>

                <div class="cbp-form-grid cbp-form-grid--3">
                    <div class="cbp-field">
                        <label>Encryption</label>
                        <select name="cbp_smtp_encryption" class="cbp-input">
                            <option value="tls" <?php selected($smtp['encryption'], 'tls'); ?>>TLS (port 587)</option>
                            <option value="ssl" <?php selected($smtp['encryption'], 'ssl'); ?>>SSL (port 465)</option>
                            <option value="none" <?php selected($smtp['encryption'], 'none'); ?>>None</option>
                        </select>
                    </div>
                    <div class="cbp-field">
                        <label>Connection Timeout (seconds)</label>
                        <input type="number" name="cbp_smtp_timeout" value="<?php echo esc_attr($smtp['timeout']); ?>" class="cbp-input" min="5" max="120">
                    </div>
                    <div class="cbp-field cbp-field--toggle">
                        <label>SMTP Authentication</label>
                        <label class="cbp-switch">
                            <input type="hidden" name="cbp_smtp_auth" value="0">
                            <input type="checkbox" name="cbp_smtp_auth" value="1" <?php checked($smtp['auth'], true); ?>>
                            <span class="cbp-switch__slider"></span>
                        </label>
                    </div>
                </div>

                <div class="cbp-form-grid cbp-form-grid--2">
                    <div class="cbp-field">
                        <label>SMTP Username</label>
                        <input type="text" name="cbp_smtp_username" value="<?php echo esc_attr($smtp['username']); ?>" class="cbp-input" autocomplete="off">
                    </div>
                    <div class="cbp-field">
                        <label>SMTP Password</label>
                        <div class="cbp-input-group">
                            <input type="password" name="cbp_smtp_password" value="<?php echo $has_smtp_pw ? '********' : ''; ?>" class="cbp-input" id="cbp-smtp-password" autocomplete="new-password" placeholder="<?php echo $has_smtp_pw ? 'Leave blank to keep current' : 'Enter password'; ?>">
                            <button type="button" class="cbp-btn cbp-btn--icon cbp-toggle-pw" data-target="cbp-smtp-password">👁</button>
                        </div>
                        <?php if ($has_smtp_pw): ?><p class="cbp-field-hint">Encrypted in database. Leave blank to keep current password.</p><?php endif; ?>
                    </div>
                </div>

                <div class="cbp-form-grid cbp-form-grid--3">
                    <div class="cbp-field">
                        <label>From Name <span class="req">*</span></label>
                        <input type="text" name="cbp_email_from_name" value="<?php echo esc_attr($smtp['from_name']); ?>" class="cbp-input">
                    </div>
                    <div class="cbp-field">
                        <label>From Email <span class="req">*</span></label>
                        <input type="email" name="cbp_email_from_email" value="<?php echo esc_attr($smtp['from_email']); ?>" class="cbp-input">
                    </div>
                    <div class="cbp-field">
                        <label>Reply-To (optional)</label>
                        <input type="email" name="cbp_email_reply_to" value="<?php echo esc_attr($smtp['reply_to']); ?>" class="cbp-input" placeholder="Defaults to counselor email">
                    </div>
                </div>
            </div>

            <!-- Test Email (outside save requirement but on same tab) -->
            <div class="cbp-card">
                <div class="cbp-card__header"><h2>SMTP Test</h2></div>
                <p class="cbp-card__desc">Save settings first, then run a test to verify connection, authentication, TLS, and delivery.</p>
                <div class="cbp-form-grid cbp-form-grid--2 cbp-form-grid--align-end">
                    <div class="cbp-field">
                        <label>Test recipient</label>
                        <input type="email" id="cbp-test-email-address" value="<?php echo esc_attr(get_option('admin_email')); ?>" class="cbp-input">
                    </div>
                    <div class="cbp-btn-row">
                        <button type="button" id="cbp-send-test-email" class="cbp-btn cbp-btn--primary">Send Test Email</button>
                        <button type="button" id="cbp-run-smtp-diagnostics" class="cbp-btn cbp-btn--secondary">Run SMTP Diagnostics</button>
                    </div>
                </div>
                <div id="cbp-test-progress" class="cbp-progress-steps" style="display:none;"></div>
                <div id="cbp-test-result" class="cbp-alert-area"></div>
                <div id="cbp-smtp-diagnostics" class="cbp-diagnostics-table-wrap"></div>
            </div>

            <!-- Email Queue -->
            <div class="cbp-card">
                <div class="cbp-card__header">
                    <h2>Email Queue</h2>
                    <button type="button" id="cbp-retry-all-queue" class="cbp-btn cbp-btn--secondary cbp-btn--sm">Retry All Pending</button>
                </div>
                <?php if (empty($queue)): ?>
                    <p class="cbp-empty-state">No queued emails. Failed sends appear here for retry.</p>
                <?php else: ?>
                    <div class="cbp-table-wrap">
                        <table class="cbp-table">
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
                                <?php foreach ($queue as $item):
                                    $badge = $item->status === 'sent' ? 'success' : ($item->status === 'failed' ? 'error' : 'warning');
                                ?>
                                <tr>
                                    <td><?php echo esc_html($item->recipient); ?></td>
                                    <td><?php echo esc_html(wp_trim_words($item->subject, 8)); ?></td>
                                    <td><span class="cbp-badge cbp-badge--<?php echo esc_attr($badge); ?>"><?php echo esc_html($item->status); ?></span></td>
                                    <td><?php echo (int)$item->retry_count; ?>/<?php echo (int)$item->max_retries; ?></td>
                                    <td class="cbp-table__error"><?php echo esc_html(wp_trim_words($item->failure_reason ?? '', 15)); ?></td>
                                    <td><?php if ($item->status !== 'sent'): ?><button type="button" class="cbp-btn cbp-btn--sm cbp-retry-queue" data-id="<?php echo (int)$item->id; ?>">Retry</button><?php endif; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Email Log -->
            <div class="cbp-card">
                <div class="cbp-card__header">
                    <h2>SMTP Session Log</h2>
                    <button type="button" id="cbp-clear-email-log" class="cbp-btn cbp-btn--ghost cbp-btn--sm">Clear</button>
                </div>
                <p class="cbp-field-hint"><code><?php echo esc_html(\ConsultationBookingPro\Services\EmailLogService::getLogFile()); ?></code></p>
                <pre class="cbp-log-viewer" id="cbp-email-log-viewer"><?php echo esc_html($email_log ? implode("\n", $email_log) : '(No log entries yet — send a test email.)'); ?></pre>
            </div>
        </div>

        <!-- ===== GENERAL TAB ===== -->
        <div class="cbp-tab-panel" id="cbp-tab-general" role="tabpanel">
            <div class="cbp-card">
                <div class="cbp-card__header"><h2>Shortcode</h2></div>
                <div class="cbp-field">
                    <label>Booking Form Shortcode</label>
                    <input type="text" name="cbp_shortcode" value="<?php echo esc_attr(get_option('cbp_shortcode', 'consultation_booking')); ?>" class="cbp-input">
                    <p class="cbp-field-hint">Default: <code>[consultation_booking]</code></p>
                </div>
            </div>
            <div class="cbp-card">
                <div class="cbp-card__header"><h2>Timezone checklist</h2></div>
                <ul style="margin:0; padding-left:18px; color:#334155; line-height:1.7; font-size:14px;">
                    <li>Set counselor timezone under <strong>Counselors</strong> (or reconnect Google to auto-sync it).</li>
                    <li>Student form times = counselor wall-clock times (shown on the booking form).</li>
                    <li>Google Calendar events are created in that same timezone — no UTC shift.</li>
                    <li>Manual Busy events on the connected calendar block those slots automatically.</li>
                </ul>
            </div>
        </div>

        <div class="cbp-sticky-save">
            <button type="submit" class="cbp-btn cbp-btn--primary cbp-btn--lg">Save All Settings</button>
        </div>
    </form>
</div>

<script>window.cbpSettingsNonce = <?php echo wp_json_encode($nonce); ?>;</script>
