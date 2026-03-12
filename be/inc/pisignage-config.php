<?php
if (!defined('WCR_PISIGNAGE_CONFIG')) {
    define('WCR_PISIGNAGE_CONFIG', true);

    define('WCR_PISIGNAGE_BASE_URL', 'https://your-account.pisignage.com');
    define('WCR_PISIGNAGE_CONFIG_FILE', __DIR__ . '/../../data/pisignage.json');
}

function wcr_pisignage_default_config(): array {
    return [
        'base_url' => WCR_PISIGNAGE_BASE_URL,
        'api_token' => '',
        'email' => '',
        'password' => '',
        'last_updated' => null,
    ];
}

function wcr_pisignage_load_config(): array {
    $file = WCR_PISIGNAGE_CONFIG_FILE;

    if (!file_exists($file)) {
        return wcr_pisignage_default_config();
    }

    $json = file_get_contents($file);
    $data = json_decode($json, true);

    if (!is_array($data)) {
        return wcr_pisignage_default_config();
    }

    return array_merge(wcr_pisignage_default_config(), $data);
}

function wcr_pisignage_save_config(array $config): bool {
    $file = WCR_PISIGNAGE_CONFIG_FILE;
    $dir = dirname($file);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $config['last_updated'] = date('c');

    return (bool) file_put_contents(
        $file,
        json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
    );
}

function wcr_pisignage_mask_token(string $token): string {
    $len = strlen($token);
    if ($len <= 8) {
        return str_repeat('*', $len);
    }
    return substr($token, 0, 4) . str_repeat('*', max(4, $len - 8)) . substr($token, -4);
}

