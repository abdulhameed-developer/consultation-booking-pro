<?php

namespace ConsultationBookingPro\Services;

if (!defined('ABSPATH')) exit;

class GoogleAuthService {

    /**
     * Exchange an authorization code for access and refresh tokens.
     *
     * @return array{success:bool,access_token?:string,refresh_token?:string,expires_in?:int,error?:string,http_code?:int}
     */
    public function exchangeCode(string $code, string $client_id, string $client_secret, string $redirect_uri): array {
        if (!$code || !$client_id || !$client_secret) {
            return ['success' => false, 'error' => 'Missing OAuth code, client ID, or client secret.'];
        }

        LogService::info('OAuth token exchange request', ['redirect_uri' => $redirect_uri], 'google');

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 30,
            'body'    => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code',
            ],
        ]);

        return $this->parseTokenResponse($response, 'OAuth token exchange');
    }

    /**
     * Refresh Google Access Token.
     *
     * @return array{success:bool,access_token?:string,expires_in?:int,error?:string,http_code?:int,error_code?:string}
     */
    public function refreshToken(string $refresh_token, string $client_id, string $client_secret): array {
        if (!$refresh_token) {
            return ['success' => false, 'error' => 'Missing refresh token. Connect Google Calendar in admin.'];
        }
        if (!$client_id || !$client_secret) {
            return ['success' => false, 'error' => 'Missing Google Client ID or Client Secret in Settings.'];
        }

        LogService::info('OAuth token refresh request', [], 'google');

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'timeout' => 30,
            'body' => [
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'refresh_token' => $refresh_token,
                'grant_type'    => 'refresh_token',
            ],
        ]);

        return $this->parseTokenResponse($response, 'OAuth token refresh');
    }

    /**
     * @return array{success:bool,access_token?:string,refresh_token?:string,expires_in?:int,error?:string,http_code?:int,error_code?:string}
     */
    private function parseTokenResponse($response, string $context): array {
        if (is_wp_error($response)) {
            $msg = $context . ' network error: ' . $response->get_error_message();
            LogService::error($msg, [], 'google');
            return ['success' => false, 'error' => $msg];
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $body_raw = wp_remote_retrieve_body($response);
        $data = json_decode($body_raw, true) ?: [];

        LogService::debug($context . ' response', [
            'http_code' => $http_code,
            'body'      => $data,
        ], 'google');

        if ($http_code !== 200 || empty($data['access_token'])) {
            $error_code = $data['error'] ?? 'unknown_error';
            $error_desc = $data['error_description'] ?? 'No access token returned';
            $msg = sprintf(
                '%s failed (HTTP %d): %s — %s',
                $context,
                $http_code,
                $error_code,
                $error_desc
            );
            LogService::error($msg, [], 'google');
            return [
                'success'    => false,
                'error'      => $msg,
                'http_code'  => $http_code,
                'error_code' => $error_code,
            ];
        }

        LogService::info($context . ' succeeded', [], 'google');
        return array_merge(['success' => true], $data);
    }

    /**
     * Build Google OAuth authorization URL.
     */
    public function getAuthUrl(string $client_id, string $redirect_uri, string $state = ''): string {
        $scopes = [
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events',
        ];

        $params = [
            'client_id'     => $client_id,
            'redirect_uri'  => $redirect_uri,
            'scope'         => implode(' ', $scopes),
            'response_type' => 'code',
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ];

        if ($state) {
            $params['state'] = $state;
        }

        return 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);
    }
}
