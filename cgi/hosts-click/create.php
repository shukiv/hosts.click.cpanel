<?php

require __DIR__ . '/common.php';

header('Content-Type: application/json');

$user = getenv('REMOTE_USER') ?: '';
if ($user === '') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

$domain = trim($_GET['domain'] ?? '');
if ($domain === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Domain missing']);
    exit;
}

$domains = hc_domains_for_user($user);
$match = null;
foreach ($domains as $row) {
    if (strcasecmp($row['domain'], $domain) === 0) {
        $match = $row;
        break;
    }
}

if (! $match) {
    http_response_code(404);
    echo json_encode(['error' => 'Domain not found']);
    exit;
}

$ip = $match['ip'] ?? '';
if ($ip === '') {
    http_response_code(422);
    echo json_encode(['error' => 'Domain IP not available']);
    exit;
}

$config = hc_load_config();
$expiresMinutes = 10;

$result = hc_create_preview_link($config, $domain, $ip, $expiresMinutes);
if (! empty($result['error'])) {
    http_response_code(422);
    echo json_encode(['error' => $result['error']]);
    exit;
}

http_response_code(201);
echo json_encode([
    'preview_url' => $result['preview_url'] ?? null,
    'subdomain' => $result['subdomain'] ?? null,
    'expires_at' => $result['expires_at'] ?? null,
]);
