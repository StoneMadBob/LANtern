<?php
require_once __DIR__ . '/settings.php';

function dashboard_plugin_get_json(string $url, array $headers = [], ?string &$failure = null): ?array
{
    $failure = null;
    $parts = parse_url($url);
    if (!is_array($parts) || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        $failure = 'invalid_url';
        return null;
    }

    foreach ($headers as $header) {
        if (str_contains($header, "\r") || str_contains($header, "\n")) {
            $failure = 'invalid_headers';
            return null;
        }
    }

    $requestHeaders = array_merge(['Accept: application/json'], $headers);
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $requestHeaders)
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true
        ]
    ]);

    $requestWarning = null;
    set_error_handler(static function (int $severity, string $message) use (&$requestWarning): bool {
        $requestWarning = $message;
        return true;
    });
    try {
        $response = file_get_contents($url, false, $context);
    } finally {
        restore_error_handler();
    }
    $statusCode = 0;
    foreach ($http_response_header ?? [] as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d{3})/', $header, $matches)) {
            $statusCode = (int) $matches[1];
        }
    }
    if (!is_string($response)) {
        $failure = str_contains((string) $requestWarning, 'allow_url_fopen') || str_contains((string) $requestWarning, 'wrapper is disabled')
            ? 'remote_urls_disabled'
            : (str_contains(strtolower((string) $requestWarning), 'ssl') || str_contains(strtolower((string) $requestWarning), 'certificate')
                ? 'tls_failed'
                : 'connection_failed');
        return null;
    }
    if ($statusCode < 200 || $statusCode >= 300) {
        $failure = 'http_' . $statusCode;
        return null;
    }

    $data = json_decode($response, true);
    if (!is_array($data)) {
        $failure = 'invalid_json';
        return null;
    }
    return is_array($data) ? $data : null;
}

function get_dashboard_plugin_widgets(PDO $pdo): array
{
    $widgets = [];
    $pluginFiles = glob(__DIR__ . '/../plugins/*/plugin.php') ?: [];

    foreach ($pluginFiles as $pluginFile) {
        try {
            $plugin = require $pluginFile;
            if (!is_array($plugin) || empty($plugin['id']) || empty($plugin['name']) || !is_callable($plugin['load'] ?? null)) {
                continue;
            }

            $widget = [
                'id' => (string) $plugin['id'],
                'name' => (string) $plugin['name'],
                'status' => 'unavailable',
                'message' => 'Status could not be loaded.',
                'items' => [],
                'url' => ''
            ];

            try {
                $data = ($plugin['load'])($pdo);
                if (is_array($data)) {
                    $widget = array_merge($widget, $data);
                }
            } catch (Throwable $exception) {
                error_log('Dashboard plugin failed: ' . $plugin['id'] . ' (' . $exception->getMessage() . ')');
            }

            $widgets[] = $widget;
        } catch (Throwable $exception) {
            error_log('Dashboard plugin could not be loaded: ' . basename(dirname($pluginFile)));
        }
    }

    return $widgets;
}