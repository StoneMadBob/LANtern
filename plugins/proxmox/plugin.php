<?php
return [
    'id' => 'proxmox',
    'name' => 'Proxmox',
    'load' => static function (PDO $pdo): array {
        $baseUrl = rtrim(get_app_setting($pdo, 'plugin_proxmox_url'), '/');
        $tokenId = trim(get_app_setting($pdo, 'plugin_proxmox_token_id'));
        $tokenSecret = get_app_setting($pdo, 'plugin_proxmox_token_secret');

        if ($baseUrl === '' || $tokenId === '' || $tokenSecret === '') {
            return [
                'status' => 'not-configured',
                'message' => 'Configure the Proxmox API connection in Admin Settings.',
                'items' => []
            ];
        }

        $response = dashboard_plugin_get_json(
            $baseUrl . '/api2/json/nodes',
            ['Authorization: PVEAPIToken=' . $tokenId . '=' . $tokenSecret]
        );
        if (!is_array($response) || !isset($response['data']) || !is_array($response['data'])) {
            return [
                'status' => 'unavailable',
                'message' => 'Could not read Proxmox node status. Check the URL, token, and API permissions.',
                'items' => [],
                'url' => $baseUrl
            ];
        }

        $items = [];
        $onlineCount = 0;
        foreach ($response['data'] as $node) {
            if (!is_array($node)) {
                continue;
            }

            $nodeStatus = strtolower((string) ($node['status'] ?? 'unknown'));
            $status = $nodeStatus === 'online' ? 'online' : 'offline';
            $onlineCount += $status === 'online' ? 1 : 0;
            $items[] = [
                'name' => (string) ($node['node'] ?? 'Node'),
                'status' => $status,
                'detail' => $nodeStatus === 'online' ? 'Online' : ucfirst($nodeStatus)
            ];
        }

        $nodeCount = count($items);
        $offlineCount = $nodeCount - $onlineCount;
        return [
            'status' => $nodeCount === 0 ? 'unavailable' : ($offlineCount > 0 ? 'offline' : 'online'),
            'message' => sprintf('%d of %d nodes online', $onlineCount, $nodeCount),
            'items' => $items,
            'url' => $baseUrl
        ];
    }
];