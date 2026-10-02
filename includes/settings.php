<?php

function get_app_setting(PDO $pdo, string $key, string $default = ''): string
{
    static $settings = null;

    if ($settings === null) {
        $settings = [];
        $stmt = $pdo->query('SELECT setting_key, setting_value FROM settings');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $setting) {
            $settings[$setting['setting_key']] = $setting['setting_value'];
        }
    }

    return $settings[$key] ?? $default;
}

function save_app_setting(PDO $pdo, string $key, string $value): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) '
        . 'ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)'
    );
    $stmt->execute([$key, $value]);
}