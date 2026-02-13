<?php

require __DIR__ . '/common.php';
require_once '/usr/local/cpanel/php/WHM.php';

$whm = new WHM();
$whm->header('HostsClick');
echo '<style>' . hc_gradient_css() . '</style>';
echo '<script>document.addEventListener("DOMContentLoaded",function(){document.body.classList.add("hc-gradient-bg");});</script>';

$config = hc_load_config();
$message = '';
$error = '';
$linkResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_settings') {
        $config['api_key'] = trim($_POST['api_key'] ?? '');
        $config['license_status'] = $config['api_key'] ? 'unknown' : 'guest';
        $config['last_checked'] = null;

        if (hc_save_config($config)) {
            $message = 'Settings saved.';
        } else {
            $error = 'Failed to save settings.';
        }
    }

    if ($action === 'test_key') {
        $result = hc_test_api_key($config);
        $config['last_checked'] = date('c');
        if (! empty($result['error'])) {
            $config['license_status'] = 'invalid';
            $error = $result['error'];
        } else {
            $config['license_status'] = 'valid';
            $message = 'API key is valid.';
        }
        hc_save_config($config);
    }

    if ($action === 'clear_key') {
        $config['api_key'] = '';
        $config['license_status'] = 'guest';
        $config['last_checked'] = null;
        if (hc_save_config($config)) {
            $message = 'API key cleared. Using guest mode.';
        } else {
            $error = 'Failed to clear API key.';
        }
    }

    if ($action === 'create_link') {
        $domain = trim($_POST['domain'] ?? '');
        $ip = trim($_POST['ip'] ?? '');
        $expiresOption = trim($_POST['expires_option'] ?? '');

        $expiresMinutes = 10;
        if (hc_has_license($config) && $expiresOption !== '') {
            $expiresMinutes = hc_minutes_from_option($expiresOption);
        }

        if ($domain && $ip) {
            if (! hc_is_valid_ip($ip)) {
                $error = 'Invalid IP address.';
            } else {
            $linkResult = hc_create_preview_link($config, $domain, $ip, $expiresMinutes);
            if (! empty($linkResult['error'])) {
                $error = $linkResult['error'];
            } else {
                $message = 'Preview link created.';
                $user = getenv('REMOTE_USER') ?: 'root';
                hc_record_link($user, [
                    'domain' => $domain,
                    'ip' => $ip,
                    'preview_url' => $linkResult['preview_url'] ?? null,
                    'expires_at' => $linkResult['expires_at'] ?? null,
                ]);
            }
            }
        } else {
            $error = 'Domain or IP missing.';
        }
    }
}

$domains = hc_userdatadomains();
$licenseStatus = $config['license_status'] ?? 'guest';
$lastChecked = $config['last_checked'] ?? 'Never';
$currentUser = getenv('REMOTE_USER') ?: 'root';
$hasApiKey = ! empty($config['api_key']);
$userLinks = $hasApiKey ? hc_links_for_user($currentUser) : [];
if (! empty($userLinks)) {
    $nowUtc = time();
    $userLinks = array_values(array_filter($userLinks, function (array $link) use ($nowUtc): bool {
        $expiresAt = $link['expires_at'] ?? null;
        if (! $expiresAt) {
            return true;
        }
        $expiresTs = strtotime($expiresAt);
        if ($expiresTs === false) {
            return true;
        }
        return $expiresTs > $nowUtc;
    }));
}
$cpsessPrefix = '';
if (! empty($_SERVER['REQUEST_URI']) && preg_match('#^(/cpsess[0-9A-Za-z]+)/#', $_SERVER['REQUEST_URI'], $match)) {
    $cpsessPrefix = $match[1];
}
$createEndpoint = $cpsessPrefix . '/cgi/hosts-click/create-whm.php';

?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<div class="container-fluid py-3 hc-surface">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h1 class="h3 mb-1">Preview links - hostsclick.com</h1>
            <div class="text-secondary">Generate preview links for server domains.</div>
        </div>
    </div>

    <div id="hc-alerts">
        <?php if ($message): ?>
            <div class="alert alert-success py-2 mb-3"><?php echo hc_escape($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 mb-3"><?php echo hc_escape($error); ?></div>
        <?php endif; ?>
    </div>

    <ul class="nav nav-tabs mb-3" id="hostsClickTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="preview-tab" data-bs-toggle="tab" data-bs-target="#preview-pane" type="button" role="tab" aria-controls="preview-pane" aria-selected="true">
                Preview links
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="settings-tab" data-bs-toggle="tab" data-bs-target="#settings-pane" type="button" role="tab" aria-controls="settings-pane" aria-selected="false">
                Settings
            </button>
        </li>
    </ul>

    <div class="tab-content" id="hostsClickTabContent">
        <div class="tab-pane show active" id="preview-pane" role="tabpanel" aria-labelledby="preview-tab" tabindex="0" data-create-endpoint="<?php echo hc_escape($createEndpoint); ?>">
            <div class="card">
                <div class="card-header bg-white">
                    <strong>Domains</strong>
                </div>
                <div class="card-body">
                    <div class="text-secondary mb-3">Create preview links for domains on this server.</div>
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="create_link">
                        <div class="col-md-6">
                            <label class="form-label">Domain</label>
                            <input
                                type="text"
                                name="domain"
                                class="form-control"
                                list="hc-domain-list"
                                placeholder="Type a domain or double-click for list"
                                autocomplete="off"
                                required
                            >
                            <datalist id="hc-domain-list">
                                <?php foreach ($domains as $row): ?>
                                    <option value="<?php echo hc_escape($row['domain']); ?>"></option>
                                <?php endforeach; ?>
                            </datalist>
                            <?php if (empty($domains)): ?>
                                <div class="form-text text-secondary">No domains found from the server list. You can still enter a custom domain.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">IP</label>
                            <input name="ip" type="text" value="<?php echo hc_escape(hc_default_ip()); ?>" class="form-control">
                        </div>
                        <div class="col-md-3 d-flex gap-2">
                            <?php if (hc_has_license($config)): ?>
                                <select name="expires_option" class="form-select" style="max-width: 140px;">
                                    <option value="1h">1 hour</option>
                                    <option value="6h">6 hours</option>
                                    <option value="12h">12 hours</option>
                                    <option value="24h">24 hours</option>
                                    <option value="7d">7 days</option>
                                    <option value="30d">30 days</option>
                                </select>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary">Create link</button>
                        </div>
                    </form>

                    <div id="hc-preview-result">
                        <?php if ($linkResult && empty($linkResult['error'])): ?>
                            <div class="alert alert-info mt-3 mb-0">
                                <strong>Preview URL:</strong>
                                <a href="<?php echo hc_escape($linkResult['preview_url'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo hc_escape($linkResult['preview_url'] ?? ''); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($hasApiKey): ?>
                        <div class="mt-4">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <strong>My Preview Links</strong>
                                <span class="text-secondary small"><?php echo hc_escape($currentUser); ?></span>
                            </div>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle" id="hc-links-table">
                                    <thead>
                                        <tr>
                                            <th>Domain</th>
                                            <th style="width: 180px;">IP</th>
                                            <th>Preview URL</th>
                                            <th style="width: 200px;">Created</th>
                                            <th style="width: 200px;">Expires</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                    <?php if (empty($userLinks)): ?>
                                        <tr class="hc-links-empty">
                                            <td colspan="5" class="text-secondary">No links yet.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($userLinks as $link): ?>
                                            <tr>
                                                <td><?php echo hc_escape($link['domain'] ?? ''); ?></td>
                                                <td><?php echo hc_escape($link['ip'] ?? ''); ?></td>
                                                <td>
                                                    <?php if (! empty($link['preview_url'])): ?>
                                                        <a href="<?php echo hc_escape($link['preview_url']); ?>" target="_blank" rel="noopener noreferrer">
                                                            <?php echo hc_escape($link['preview_url']); ?>
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo hc_escape(hc_format_local_time($link['created_at_utc'] ?? '')); ?></td>
                                                <td><?php echo hc_escape(hc_format_local_time($link['expires_at'] ?? '')); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="tab-pane" id="settings-pane" role="tabpanel" aria-labelledby="settings-tab" tabindex="0">
            <div class="card">
                <div class="card-header bg-white">
                    <strong>API License</strong>
                </div>
                <div class="card-body">
                    <form method="post" class="row g-3 align-items-end">
                        <input type="hidden" name="action" value="save_settings">
                        <div class="col-12 col-md-6">
                            <label for="api_key" class="form-label">API Key</label>
                            <input id="api_key" name="api_key" type="password" value="<?php echo hc_escape($config['api_key']); ?>" class="form-control">
                        </div>
                        <div class="col-12 col-md-auto">
                            <button type="submit" class="btn btn-primary">Save</button>
                        </div>
                    </form>
                    <div class="mt-3 d-flex gap-2 align-items-center">
                        <form method="post" class="m-0">
                            <input type="hidden" name="action" value="test_key">
                            <button type="submit" class="btn btn-outline-secondary btn-sm">Test API Key</button>
                        </form>
                        <form method="post" class="m-0">
                            <input type="hidden" name="action" value="clear_key">
                            <button type="submit" class="btn btn-outline-danger btn-sm">Clear API Key</button>
                        </form>
                        <div class="text-secondary small">
                            <strong>Status:</strong> <?php echo hc_escape($licenseStatus); ?> ·
                            <strong>Last checked:</strong> <?php echo hc_escape($lastChecked); ?>
                        </div>
                    </div>
                    <div class="mt-2 text-secondary small">
                        <strong>Plugin version:</strong> <?php echo hc_escape(HC_PLUGIN_VERSION); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>
<?php if ($cpsessPrefix): ?>
    <script src="<?php echo hc_escape($cpsessPrefix); ?>/cgi/hosts-click/assets/whm.js" defer></script>
<?php else: ?>
    <script src="/cgi/hosts-click/assets/whm.js" defer></script>
<?php endif; ?>
<?php $whm->footer(); ?>
