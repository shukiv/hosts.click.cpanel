<?php

require __DIR__ . '/common.php';

header('Content-Type: application/json');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$domain = trim($_POST['domain'] ?? '');
$ip = trim($_POST['ip'] ?? '');
$expiresOption = trim($_POST['expires_option'] ?? '');

if ($domain === '' || $ip === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Domain or IP missing']);
    exit;
}

if (! hc_is_valid_ip($ip)) {
    http_response_code(422);
    echo json_encode(['error' => 'Invalid IP address']);
    exit;
}

$config = hc_load_config();
$expiresMinutes = 10;
if (hc_has_license($config) && $expiresOption !== '') {
    $expiresMinutes = hc_minutes_from_option($expiresOption);
}

$result = hc_create_preview_link($config, $domain, $ip, $expiresMinutes);
if (! empty($result['error'])) {
    http_response_code(422);
    echo json_encode(['error' => $result['error']]);
    exit;
}

$user = getenv('REMOTE_USER') ?: 'root';
hc_record_link($user, [
    'domain' => $domain,
    'ip' => $ip,
    'preview_url' => $result['preview_url'] ?? null,
    'expires_at' => $result['expires_at'] ?? null,
]);

http_response_code(201);
echo json_encode([
    'preview_url' => $result['preview_url'] ?? null,
    'subdomain' => $result['subdomain'] ?? null,
    'expires_at' => $result['expires_at'] ?? null,
]);
