<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

class EmailQueueService {

    public static function enqueue(string $to, string $subject, string $body, array $headers = [], ?int $booking_id = null, string $reason = ''): int {
        global $wpdb;
        $table = $wpdb->prefix . 'cbp_email_queue';

        $wpdb->insert($table, [
            'recipient'      => $to,
            'subject'        => $subject,
            'body'           => $body,
            'headers'        => wp_json_encode($headers),
            'status'         => 'pending',
            'retry_count'    => 0,
            'max_retries'    => 3,
            'failure_reason' => $reason,
            'booking_id'     => $booking_id,
            'created_at'     => current_time('mysql'),
        ]);

        $id = (int) $wpdb->insert_id;
        EmailLogService::log('Email queued for retry', ['queue_id' => $id, 'to' => $to, 'reason' => $reason]);
        return $id;
    }

    public static function getPending(int $limit = 50): array {
        global $wpdb;
        $table = $wpdb->prefix . 'cbp_email_queue';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE status IN ('pending','failed') AND retry_count < max_retries ORDER BY created_at ASC LIMIT %d",
            $limit
        )) ?: [];
    }

    public static function getAll(int $limit = 100): array {
        global $wpdb;
        $table = $wpdb->prefix . 'cbp_email_queue';
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d",
            $limit
        )) ?: [];
    }

    public static function markSent(int $id): void {
        global $wpdb;
        $wpdb->update($wpdb->prefix . 'cbp_email_queue', [
            'status'          => 'sent',
            'sent_at'         => current_time('mysql'),
            'last_attempt_at' => current_time('mysql'),
        ], ['id' => $id]);
    }

    public static function markFailed(int $id, string $reason): void {
        global $wpdb;
        $table = $wpdb->prefix . 'cbp_email_queue';
        $row = $wpdb->get_row($wpdb->prepare("SELECT retry_count, max_retries FROM {$table} WHERE id = %d", $id));
        $retries = ($row->retry_count ?? 0) + 1;
        $status = $retries >= ($row->max_retries ?? 3) ? 'failed' : 'pending';

        $wpdb->update($table, [
            'status'          => $status,
            'retry_count'     => $retries,
            'failure_reason'  => $reason,
            'last_attempt_at' => current_time('mysql'),
        ], ['id' => $id]);
    }

    /**
     * Retry a single queued email.
     */
    public static function retry(int $id): array {
        global $wpdb;
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cbp_email_queue WHERE id = %d",
            $id
        ));

        if (!$row) {
            return ['sent' => false, 'error' => 'Queue item not found.'];
        }

        $headers = json_decode($row->headers, true) ?: [];
        $mail = new MailService();
        $result = $mail->send($row->recipient, $row->subject, $row->body, $headers, (int) $row->booking_id, false);

        if ($result['sent']) {
            self::markSent($id);
        } else {
            self::markFailed($id, $result['error'] ?? 'Unknown error');
        }

        return $result;
    }

    /**
     * WP Cron handler — retry pending queue items.
     */
    public static function processPending(): array {
        LogService::info('Email queue cron processing', [], 'email');
        return self::retryAll();
    }

    /**
     * Retry all pending emails.
     */
    public static function retryAll(): array {
        $pending = self::getPending(50);
        $results = ['sent' => 0, 'failed' => 0, 'errors' => []];
        foreach ($pending as $row) {
            $r = self::retry((int) $row->id);
            if ($r['sent']) {
                $results['sent']++;
            } else {
                $results['failed']++;
                $results['errors'][] = $row->recipient . ': ' . ($r['error'] ?? 'failed');
            }
        }
        return $results;
    }
}
