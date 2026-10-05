<?php
return [
    'id' => 'uptime-kuma',
    'name' => 'Uptime Kuma',
    'load' => static function (PDO $pdo): array {
        $baseUrl = rtrim(get_app_setting($pdo, 'plugin_uptime_kuma_url'), '/');
        $slug = trim(get_app_setting($pdo, 'plugin_uptime_kuma_slug'));

        if ($baseUrl === '' || $slug === '') {
            return [
                'status' => 'not-configured',
                'message' => 'Configure the public status page in Admin Settings.',
                'items' => []
            ];
        }

        $encodedSlug = rawurlencode($slug);
        $statusPageFailure = null;
        $heartbeatFailure = null;
        $statusPage = dashboard_plugin_get_json($baseUrl . '/api/status-page/' . $encodedSlug, [], $statusPageFailure);
        $heartbeatData = dashboard_plugin_get_json($baseUrl . '/api/status-page/heartbeat/' . $encodedSlug, [], $heartbeatFailure);
        $statusPageValid = is_array($statusPage) && isset($statusPage['publicGroupList']) && is_array($statusPage['publicGroupList']);
        $heartbeatDataValid = is_array($heartbeatData) && isset($heartbeatData['heartbeatList']) && is_array($heartbeatData['heartbeatList']);
        if (!$statusPageValid || !$heartbeatDataValid) {
            $failure = !$statusPageValid ? $statusPageFailure : $heartbeatFailure;
            $message = match ($failure) {
                'invalid_url' => 'Enter the Uptime Kuma instance base URL, not the status page URL, in Admin Settings.',
                'remote_urls_disabled' => 'PHP is blocking outgoing HTTP requests. Enable allow_url_fopen for the web server or configure the plugin to use cURL.',
                'tls_failed' => 'The PHP server could not verify Uptime Kuma\'s TLS certificate. Check its certificate chain and PHP CA certificates.',
                'connection_failed' => 'The PHP server could not reach Uptime Kuma. Check network routing, firewall rules, and that the service is listening on the configured address and port.',
                'invalid_json' => 'Uptime Kuma returned an invalid API response. Check the instance URL and reverse-proxy configuration.',
                'http_301', 'http_302', 'http_307', 'http_308' => 'Uptime Kuma redirected the API request. Use the final HTTP or HTTPS instance URL in Admin Settings.',
                default => str_starts_with((string) $failure, 'http_')
                    ? sprintf('Uptime Kuma returned HTTP %s. Check the instance URL and public status page slug.', substr((string) $failure, 5))
                    : 'Uptime Kuma returned an unexpected API response. Check the instance URL and public status page slug.'
            };
            return [
                'status' => 'unavailable',
                'message' => $message,
                'items' => [],
                'url' => $baseUrl . '/status/' . $encodedSlug
            ];
        }
        $response = array_merge($statusPage, $heartbeatData);

        $items = [];
        $upCount = 0;
        $downCount = 0;
        $maintenanceCount = 0;
        $unknownCount = 0;

        foreach ($response['publicGroupList'] as $group) {
            foreach (($group['monitorList'] ?? []) as $monitor) {
                if (!is_array($monitor)) {
                    continue;
                }

                $monitorId = (string) ($monitor['id'] ?? '');
                $heartbeats = $response['heartbeatList'][$monitorId] ?? [];
                $latestHeartbeat = is_array($heartbeats) && $heartbeats !== [] ? end($heartbeats) : null;
                $heartbeatStatus = is_array($latestHeartbeat) ? (int) ($latestHeartbeat['status'] ?? -1) : -1;

                if ($heartbeatStatus === 1) {
                    $status = 'online';
                    $upCount++;
                } elseif ($heartbeatStatus === 0) {
                    $status = 'offline';
                    $downCount++;
                } elseif ($heartbeatStatus === 3) {
                    $status = 'maintenance';
                    $maintenanceCount++;
                } else {
                    $status = 'unavailable';
                    $unknownCount++;
                }

                $items[] = [
                    'name' => (string) ($monitor['name'] ?? 'Monitor'),
                    'status' => $status,
                    'detail' => (string) ($group['name'] ?? '')
                ];
            }
        }

        $overallStatus = $downCount > 0 ? 'offline' : ($unknownCount > 0 || $items === [] ? 'unavailable' : ($upCount > 0 ? 'online' : 'maintenance'));
        $message = sprintf('%d up, %d down, %d in maintenance', $upCount, $downCount, $maintenanceCount);
        if ($unknownCount > 0) {
            $message .= sprintf(', %d unknown', $unknownCount);
        }

        return [
            'status' => $overallStatus,
            'message' => $message,
            'items' => $items,
            'url' => $baseUrl . '/status/' . $encodedSlug
        ];
    }
];