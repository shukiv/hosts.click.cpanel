<?php

define('HC_CONFIG_PATH', '/var/cpanel/hosts-click/config.json');
define('HC_LINKS_PATH', '/var/cpanel/hosts-click/links.json');
define('HC_PLUGIN_VERSION', 'v2.1');

define('HC_DEFAULT_CONFIG', [
    'api_base_url' => 'https://hostsclick.com',
    'api_key' => '',
    'license_status' => 'unknown',
    'last_checked' => null,
]);

function hc_load_config(): array
{
    if (! file_exists(HC_CONFIG_PATH)) {
        return HC_DEFAULT_CONFIG;
    }

    $raw = file_get_contents(HC_CONFIG_PATH);
    if ($raw === false) {
        return HC_DEFAULT_CONFIG;
    }

    $decoded = json_decode($raw, true);
    if (! is_array($decoded)) {
        return HC_DEFAULT_CONFIG;
    }

    return array_merge(HC_DEFAULT_CONFIG, $decoded);
}

function hc_save_config(array $config): bool
{
    $dir = dirname(HC_CONFIG_PATH);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $payload = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    return file_put_contents(HC_CONFIG_PATH, $payload) !== false;
}

function hc_escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function hc_gradient_css(): string
{
    return <<<CSS
body.hc-gradient-bg, .hc-gradient-bg {
    background-image: linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
    background-size: 400% 400%;
    animation: hc-gradient 15s ease infinite;
}

@keyframes hc-gradient {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.hc-surface {
    background: rgba(255, 255, 255, 0.92);
    border-radius: 12px;
    padding: 24px;
}
CSS;
}

function hc_load_links(): array
{
    if (! file_exists(HC_LINKS_PATH)) {
        return [];
    }

    $raw = file_get_contents(HC_LINKS_PATH);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

function hc_save_links(array $links): bool
{
    $dir = dirname(HC_LINKS_PATH);
    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    $payload = json_encode($links, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    if ($payload === false) {
        return false;
    }

    $result = file_put_contents(HC_LINKS_PATH, $payload, LOCK_EX) !== false;
    if ($result) {
        @chmod(HC_LINKS_PATH, 0664);
    }
    return $result;
}

function hc_record_link(string $user, array $payload): void
{
    $links = hc_load_links();
    $links[] = array_merge([
        'user' => $user,
        'created_at_utc' => gmdate('c'),
    ], $payload);

    if (count($links) > 500) {
        $links = array_slice($links, -500);
    }

    hc_save_links($links);
}

function hc_links_for_user(string $user): array
{
    $links = hc_load_links();
    $filtered = [];
    foreach ($links as $row) {
        if (($row['user'] ?? '') === $user) {
            $filtered[] = $row;
        }
    }

    return array_reverse($filtered);
}

function hc_format_local_time(?string $utc): string
{
    if (! $utc) {
        return '';
    }

    try {
        $dt = new DateTime($utc, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone(date_default_timezone_get()));
        return $dt->format('Y-m-d H:i:s T');
    } catch (Exception $e) {
        return $utc;
    }
}

function hc_default_ip(): string
{
    $wwwacct = '/etc/wwwacct.conf';
    if (file_exists($wwwacct)) {
        $lines = file($wwwacct, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                if (strpos($line, 'ADDR ') === 0) {
                    $parts = preg_split('/\s+/', trim($line));
                    if (isset($parts[1]) && filter_var($parts[1], FILTER_VALIDATE_IP)) {
                        return $parts[1];
                    }
                }
            }
        }
    }

    $ipsFile = '/etc/ips';
    if (file_exists($ipsFile)) {
        $lines = file($ipsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if (is_array($lines)) {
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $parts = explode(':', $line, 2);
                $candidate = trim($parts[0]);
                if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                    return $candidate;
                }
            }
        }
    }

    return '';
}

function hc_is_valid_ip(string $ip): bool
{
    return filter_var($ip, FILTER_VALIDATE_IP) !== false;
}

function hc_post_json(string $url, array $payload, array $headers = []): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['error' => 'Unable to initialize HTTP client.'];
    }

    $body = http_build_query($payload);
    $headerLines = array_merge([
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json',
    ], $headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['error' => $error ?: 'Request failed.'];
    }

    $decoded = json_decode($response, true);
    if (! is_array($decoded)) {
        return ['error' => 'Invalid response from API.', 'status' => $status, 'raw' => $response];
    }

    $decoded['status'] = $status;
    return $decoded;
}

function hc_delete_request(string $url, array $headers = []): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        return ['error' => 'Unable to initialize HTTP client.'];
    }

    $headerLines = array_merge([
        'Accept: application/json',
    ], $headers);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_HTTPHEADER => $headerLines,
        CURLOPT_TIMEOUT => 15,
    ]);

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['error' => $error ?: 'Request failed.'];
    }

    $decoded = json_decode($response, true);
    if (! is_array($decoded)) {
        return ['error' => 'Invalid response from API.', 'status' => $status, 'raw' => $response];
    }

    $decoded['status'] = $status;
    return $decoded;
}

function hc_userdatadomains(): array
{
    $path = '/etc/userdatadomains';
    if (! file_exists($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (! is_array($lines)) {
        return [];
    }

    $domains = [];
    $defaultIp = hc_default_ip();

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, ':') === false) {
            continue;
        }

        [$domain, $rest] = explode(':', $line, 2);
        $domain = trim($domain);
        $rest = trim($rest);

        $user = null;
        $ip = null;

        if (preg_match('/\buser=([^\s]+)/', $rest, $match)) {
            $user = $match[1];
        }

        if (preg_match('/\bip=([^\s]+)/', $rest, $match)) {
            $ip = $match[1];
        }

        if (! $domain) {
            continue;
        }

        $domains[] = [
            'domain' => $domain,
            'user' => $user,
            'ip' => $ip ?: $defaultIp,
        ];
    }

    return $domains;
}

function hc_domains_for_user(string $user): array
{
    $domains = [];
    $userDataDomains = hc_userdatadomains();
    if (! empty($userDataDomains)) {
        foreach ($userDataDomains as $row) {
            if ($row['user'] === $user) {
                $domains[] = $row;
            }
        }
        return $domains;
    }

    return hc_domains_from_userdata($user);
}

function hc_domains_from_userdata(string $user): array
{
    $path = "/var/cpanel/userdata/{$user}/main";
    if (! is_readable($path)) {
        return [];
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (! is_array($lines)) {
        return [];
    }

    $defaultIp = hc_default_ip();
    $domains = [];
    $section = null;

    foreach ($lines as $line) {
        $line = rtrim($line);

        if (preg_match('/^\s*main_domain:\s*(\S+)/', $line, $match)) {
            $domains[] = $match[1];
            $section = null;
            continue;
        }

        if (preg_match('/^\s*(sub_domains|parked_domains|addon_domains):\s*(\{\}|\[\])?\s*$/', $line, $match)) {
            $section = $match[1];
            continue;
        }

        if ($section && preg_match('/^\s*-\s*(\S+)/', $line, $match)) {
            $domains[] = $match[1];
            continue;
        }

        if ($section === 'addon_domains' && preg_match('/^\s+([^\s:]+):\s*$/', $line, $match)) {
            $domains[] = $match[1];
            continue;
        }
    }

    $domains = array_values(array_unique(array_filter($domains)));
    $rows = [];
    foreach ($domains as $domain) {
        $rows[] = [
            'domain' => $domain,
            'user' => $user,
            'ip' => $defaultIp,
        ];
    }

    return $rows;
}

function hc_create_preview_link(array $config, string $domain, string $ip, int $expiresMinutes): array
{
    $payload = [
        'hostname' => $domain,
        'ip' => $ip,
        'expires_in' => $expiresMinutes,
    ];

    $headers = [];
    $endpoint = rtrim($config['api_base_url'], '/') . '/api/preview-links';

    if (! empty($config['api_key'])) {
        $headers[] = 'X-API-Key: ' . $config['api_key'];
    } else {
        $endpoint = rtrim($config['api_base_url'], '/') . '/api/guest-preview-links';
    }

    return hc_post_json($endpoint, $payload, $headers);
}

function hc_test_api_key(array $config): array
{
    if (empty($config['api_key'])) {
        return ['error' => 'API key is empty.'];
    }

    $response = hc_post_json(rtrim($config['api_base_url'], '/') . '/api/preview-links', [
        'hostname' => 'example.com',
        'ip' => '1.1.1.1',
        'expires_in' => 1,
    ], ['X-API-Key: ' . $config['api_key']]);

    if (! empty($response['error'])) {
        return $response;
    }

    $previewUrl = $response['preview_url'] ?? null;
    if ($previewUrl) {
        $host = parse_url($previewUrl, PHP_URL_HOST);
        if ($host) {
            $token = explode('.', $host)[0];
            if ($token) {
                hc_delete_request(rtrim($config['api_base_url'], '/') . '/api/preview-links/' . $token, [
                    'X-API-Key: ' . $config['api_key'],
                ]);
            }
        }
    }

    return ['status' => 200];
}

function hc_minutes_from_option(string $option): int
{
    $map = [
        '1h' => 60,
        '6h' => 360,
        '12h' => 720,
        '24h' => 1440,
        '7d' => 10080,
        '30d' => 43200,
    ];

    return $map[$option] ?? 10;
}

function hc_has_license(array $config): bool
{
    return ! empty($config['api_key']) && ($config['license_status'] === 'valid');
}
