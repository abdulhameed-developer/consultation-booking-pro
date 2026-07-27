<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

class GoogleCalendarService {
    private string $access_token;
    private string $calendar_id;
    private ?array $last_error = null;

    public function __construct(string $access_token, string $calendar_id = 'primary') {
        $this->access_token = $access_token;
        $this->calendar_id  = $calendar_id ?: 'primary';
    }

    public function getLastError(): ?array {
        return $this->last_error;
    }

    private function calendarUrlSegment(): string {
        return rawurlencode($this->calendar_id);
    }

    /**
     * @return array{success:bool,id?:string,htmlLink?:string,hangoutLink?:string,meetLink?:string,attendees?:array,error?:string,http_code?:int}
     */
    public function createEvent(array $booking, array $counselor, bool $with_conference = true): array {
        $this->last_error = null;
        $body = $this->buildEventBody($booking, $counselor, $with_conference);

        $query = 'conferenceDataVersion=1&sendUpdates=all';
        if (!$with_conference) {
            $query = 'sendUpdates=all';
        }

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . $this->calendarUrlSegment()
            . '/events?' . $query;

        LogService::info('Google Calendar createEvent request', [
            'calendar_id' => $this->calendar_id,
            'booking_id'  => $booking['id'] ?? null,
            'with_meet'   => $with_conference,
            'attendees'   => array_column($body['attendees'] ?? [], 'email'),
        ], 'google');

        $result = $this->apiRequest('POST', $url, $body);

        // Retry without Meet if conference creation failed.
        if (!$result['success'] && $with_conference && $this->isConferenceError($result)) {
            LogService::info('Retrying event creation without Google Meet conference', [], 'google');
            return $this->createEvent($booking, $counselor, false);
        }

        if (!$result['success']) {
            $this->last_error = $result;
            return $result;
        }

        $data = $result['data'];
        $meet = $this->extractMeetLink($data);

        LogService::info('Google Calendar event created', [
            'event_id'   => $data['id'],
            'htmlLink'   => $data['htmlLink'] ?? null,
            'meetLink'   => $meet,
            'attendees'  => $data['attendees'] ?? [],
        ], 'google');

        return [
            'success'     => true,
            'id'          => $data['id'],
            'htmlLink'    => $data['htmlLink'] ?? null,
            'hangoutLink' => $data['hangoutLink'] ?? null,
            'meetLink'    => $meet,
            'attendees'   => $data['attendees'] ?? [],
            'raw'         => $data,
        ];
    }

    /**
     * Create a diagnostic test event (does not require booking record).
     *
     * @return array{success:bool,id?:string,error?:string}
     */
    public function createTestEvent(string $student_email, string $counselor_email, array $counselor): array {
        $timezone = TimezoneHelper::fromCounselor($counselor);
        $start = new \DateTimeImmutable('+2 days 10:00:00', TimezoneHelper::zone($timezone));

        $fake_booking = [
            'id'                   => 'test',
            'student_name'         => 'CBP Test Student',
            'student_email'        => $student_email,
            'student_phone'        => '0000000000',
            'student_country_code' => '+1',
            'country'              => 'Test',
            'study_level'          => 'Test',
            'interested_field'     => 'Test',
            'booking_date'         => $start->format('Y-m-d'),
            'booking_time'         => $start->format('H:i:s'),
            'custom_data'          => wp_json_encode(['message' => 'Automated diagnostics test event — safe to ignore.']),
        ];

        return $this->createEvent($fake_booking, array_merge($counselor, ['email' => $counselor_email]), true);
    }

    /**
     * @return array{success:bool,data?:array,error?:string,http_code?:int}
     */
    public function getEvent(string $event_id): array {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . $this->calendarUrlSegment()
            . '/events/' . rawurlencode($event_id);
        return $this->apiRequest('GET', $url);
    }

    /**
     * @return array{success:bool,error?:string,http_code?:int}
     */
    public function deleteEvent(string $event_id): array {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . $this->calendarUrlSegment()
            . '/events/' . rawurlencode($event_id) . '?sendUpdates=all';

        LogService::info('Google Calendar deleteEvent', ['event_id' => $event_id], 'google');
        return $this->apiRequest('DELETE', $url);
    }

    /**
     * @return array{success:bool,data?:array,error?:string,http_code?:int}
     */
    public function testConnection(): array {
        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . $this->calendarUrlSegment();
        return $this->apiRequest('GET', $url);
    }

    /**
     * Google FreeBusy — returns busy blocks for ANY opaque event (plugin or manual).
     * Times are absolute (UTC); compare in AvailabilityService with counselor-local slots.
     *
     * @return array<int, array{start:string,end:string}>
     */
    public function getFreeBusy(string $time_min, string $time_max, ?string $timezone = null): array {
        $url = 'https://www.googleapis.com/calendar/v3/freeBusy';
        $body = [
            'timeMin'  => $time_min,
            'timeMax'  => $time_max,
            'items'    => [['id' => $this->calendar_id]],
        ];
        if ($timezone) {
            $body['timeZone'] = TimezoneHelper::resolve($timezone);
        }

        $result = $this->apiRequest('POST', $url, $body);
        if (!$result['success']) {
            LogService::error('FreeBusy request failed', ['error' => $result['error'] ?? ''], 'google');
            return [];
        }

        $calendars = $result['data']['calendars'] ?? [];
        $entry = $calendars[$this->calendar_id] ?? null;
        if ($entry === null && !empty($calendars)) {
            $entry = reset($calendars);
        }
        if (!is_array($entry)) {
            return [];
        }
        if (!empty($entry['errors'])) {
            LogService::error('FreeBusy calendar errors', ['errors' => $entry['errors'], 'calendar_id' => $this->calendar_id], 'google');
        }

        $busy = $entry['busy'] ?? [];
        LogService::info('FreeBusy periods', ['count' => count($busy), 'calendar_id' => $this->calendar_id], 'google');
        return is_array($busy) ? $busy : [];
    }

    /**
     * List opaque (busy) events in a window — backup for FreeBusy so manually added
     * Google Calendar events always block student slots.
     *
     * @return array<int, array{start:string,end:string}>
     */
    public function listBusyEvents(string $time_min, string $time_max, ?string $timezone = null): array {
        $query = http_build_query([
            'timeMin'      => $time_min,
            'timeMax'      => $time_max,
            'singleEvents' => 'true',
            'orderBy'      => 'startTime',
            'maxResults'   => 250,
            'timeZone'     => TimezoneHelper::resolve($timezone),
        ]);

        $url = 'https://www.googleapis.com/calendar/v3/calendars/' . $this->calendarUrlSegment()
            . '/events?' . $query;

        $result = $this->apiRequest('GET', $url);
        if (!$result['success']) {
            LogService::error('listBusyEvents failed', ['error' => $result['error'] ?? ''], 'google');
            return [];
        }

        $busy = [];
        foreach ($result['data']['items'] ?? [] as $event) {
            // Skip cancelled / free ("transparent") events — they should not block slots.
            if (($event['status'] ?? '') === 'cancelled') {
                continue;
            }
            if (($event['transparency'] ?? 'opaque') === 'transparent') {
                continue;
            }

            $start = $event['start']['dateTime'] ?? null;
            $end = $event['end']['dateTime'] ?? null;

            // All-day events use date (YYYY-MM-DD) — treat as full-day busy in calendar TZ.
            if (!$start && !empty($event['start']['date'])) {
                $start = $event['start']['date'] . 'T00:00:00';
                $end = ($event['end']['date'] ?? $event['start']['date']) . 'T00:00:00';
                try {
                    $tz = TimezoneHelper::zone($timezone);
                    $start_dt = new \DateTimeImmutable($start, $tz);
                    $end_dt = new \DateTimeImmutable($end, $tz);
                    $busy[] = [
                        'start' => $start_dt->format(\DateTimeInterface::RFC3339),
                        'end'   => $end_dt->format(\DateTimeInterface::RFC3339),
                    ];
                } catch (\Exception $e) {
                    continue;
                }
                continue;
            }

            if ($start && $end) {
                $busy[] = ['start' => $start, 'end' => $end];
            }
        }

        LogService::info('listBusyEvents periods', ['count' => count($busy), 'calendar_id' => $this->calendar_id], 'google');
        return $busy;
    }

    /**
     * @return array{success:bool,data?:array,error?:string,http_code?:int,error_reason?:string}
     */
    private function apiRequest(string $method, string $url, ?array $body = null): array {
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type'  => 'application/json',
            ],
        ];

        if ($body !== null && $method !== 'GET') {
            $args['body'] = wp_json_encode($body);
        }

        LogService::debug('Google Calendar API request', [
            'method' => $method,
            'url'    => $url,
            'body'   => $body,
        ], 'api');

        $response = ($method === 'GET')
            ? wp_remote_get($url, $args)
            : wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            $msg = 'Google Calendar network error: ' . $response->get_error_message();
            LogService::error($msg, [], 'api');
            return ['success' => false, 'error' => $msg];
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);
        $data = json_decode($raw_body, true);

        LogService::debug('Google Calendar API response', [
            'http_code' => $http_code,
            'body'      => $data ?: $raw_body,
        ], 'api');

        if ($http_code >= 200 && $http_code < 300) {
            return ['success' => true, 'data' => is_array($data) ? $data : [], 'http_code' => $http_code];
        }

        $api_message = $data['error']['message'] ?? 'Unknown Google API error';
        $api_reason  = $data['error']['errors'][0]['reason'] ?? ($data['error']['status'] ?? '');
        $msg = sprintf('Google Calendar API %d: %s', $http_code, $api_message);
        if ($api_reason) {
            $msg .= ' (reason: ' . $api_reason . ')';
        }

        LogService::error($msg, ['http_code' => $http_code, 'response' => $data], 'api');

        return [
            'success'       => false,
            'error'         => $msg,
            'http_code'     => $http_code,
            'error_reason'  => $api_reason,
            'response'      => $data,
        ];
    }

    private function buildEventBody(array $booking, array $counselor, bool $with_conference): array {
        $duration = max(1, (int) ($counselor['slot_duration'] ?? 30));
        $timezone = TimezoneHelper::fromCounselor($counselor);

        // Wall-clock time the student picked, interpreted ONLY in counselor timezone.
        $start_dt = TimezoneHelper::bookingDateTime(
            (string) $booking['booking_date'],
            (string) $booking['booking_time'],
            $timezone
        );
        $end_dt = $start_dt->modify('+' . $duration . ' minutes');

        $custom_data = json_decode($booking['custom_data'] ?? '{}', true);
        $message = is_array($custom_data) ? ($custom_data['message'] ?? '') : '';

        $description = "CONSULTATION BOOKING DETAILS\n";
        $description .= "Name: " . $booking['student_name'] . "\n";
        $description .= "Email: " . $booking['student_email'] . "\n";
        $description .= "Phone: " . ($booking['student_country_code'] ?? '') . ' ' . ($booking['student_phone'] ?? '') . "\n";
        $description .= "Timezone: " . TimezoneHelper::label($timezone) . "\n";
        if ($message) {
            $description .= "Message: " . $message . "\n";
        }
        $description .= "Booking ID: #" . ($booking['id'] ?? 'N/A') . "\n";

        LogService::info('Building calendar event times', [
            'booking_date' => $booking['booking_date'] ?? null,
            'booking_time' => $booking['booking_time'] ?? null,
            'timezone'     => $timezone,
            'start_local'  => $start_dt->format('Y-m-d H:i:s'),
            'end_local'    => $end_dt->format('Y-m-d H:i:s'),
            'start_rfc'    => $start_dt->format(\DateTimeInterface::RFC3339),
        ], 'google');

        $body = [
            'summary'     => 'Consultation: ' . $booking['student_name'],
            'location'    => 'Online / Google Meet',
            'description' => $description,
            // Local wall-clock + timeZone = Google shows the exact selected time in counselor TZ.
            'start'       => [
                'dateTime' => $start_dt->format('Y-m-d\TH:i:s'),
                'timeZone' => $timezone,
            ],
            'end' => [
                'dateTime' => $end_dt->format('Y-m-d\TH:i:s'),
                'timeZone' => $timezone,
            ],
            'attendees' => [
                ['email' => $booking['student_email'], 'responseStatus' => 'needsAction'],
                ['email' => $counselor['email'], 'responseStatus' => 'needsAction'],
            ],
            'guestsCanSeeOtherGuests' => true,
            'transparency' => 'opaque',
            'reminders' => [
                'useDefault' => false,
                'overrides'  => [
                    ['method' => 'email', 'minutes' => 60],
                    ['method' => 'popup', 'minutes' => 15],
                ],
            ],
        ];

        if ($with_conference) {
            $body['conferenceData'] = [
                'createRequest' => [
                    'requestId'             => 'cbp-' . ($booking['id'] ?? uniqid('', true)),
                    'conferenceSolutionKey' => ['type' => 'hangoutsMeet'],
                ],
            ];
        }

        return $body;
    }

    private function extractMeetLink(array $data): ?string {
        if (!empty($data['hangoutLink'])) {
            return $data['hangoutLink'];
        }
        if (!empty($data['conferenceData']['entryPoints'])) {
            foreach ($data['conferenceData']['entryPoints'] as $ep) {
                if (($ep['entryPointType'] ?? '') === 'video') {
                    return $ep['uri'] ?? null;
                }
            }
        }
        return null;
    }

    private function isConferenceError(array $result): bool {
        $msg = strtolower($result['error'] ?? '');
        return str_contains($msg, 'conference') || str_contains($msg, 'meet') || ($result['http_code'] ?? 0) === 400;
    }
}
