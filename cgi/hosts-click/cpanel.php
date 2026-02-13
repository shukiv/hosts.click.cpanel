<?php

require __DIR__ . '/common.php';

$config = hc_load_config();
$message = '';
$error = '';
$linkResult = null;

$user = getenv('REMOTE_USER') ?: '';
$domains = $user ? hc_domains_for_user($user) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_link') {
        $domain = trim($_POST['domain'] ?? '');
        $ip = trim($_POST['ip'] ?? '');
        $expiresOption = trim($_POST['expires_option'] ?? '');

        $expiresMinutes = 10;
        if (hc_has_license($config) && $expiresOption !== '') {
            $expiresMinutes = hc_minutes_from_option($expiresOption);
        }

        if ($domain && $ip) {
            $linkResult = hc_create_preview_link($config, $domain, $ip, $expiresMinutes);
            if (! empty($linkResult['error'])) {
                $error = $linkResult['error'];
            } else {
                $message = 'Preview link created.';
            }
        } else {
            $error = 'Domain or IP missing.';
        }
    }

    if ($action === 'create_custom') {
        $domain = trim($_POST['custom_domain'] ?? '');
        $ip = trim($_POST['custom_ip'] ?? '');
        $expiresOption = trim($_POST['expires_option'] ?? '');

        $expiresMinutes = 10;
        if (hc_has_license($config) && $expiresOption !== '') {
            $expiresMinutes = hc_minutes_from_option($expiresOption);
        }

        if ($domain && $ip) {
            $linkResult = hc_create_preview_link($config, $domain, $ip, $expiresMinutes);
            if (! empty($linkResult['error'])) {
                $error = $linkResult['error'];
            } else {
                $message = 'Preview link created.';
            }
        } else {
            $error = 'Domain or IP missing.';
        }
    }
}

$licenseStatus = $config['license_status'] ?? 'guest';

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Preview links - hostsclick.com</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" href="/cgi/hosts-click/assets/hc_cp.svg" type="image/svg+xml">
    <style><?php echo hc_gradient_css(); ?></style>
</head>
<body class="hc-gradient-bg" style="font-family: Arial, sans-serif; padding: 24px; min-height: 100vh;">
<div class="hc-surface">
    <h1>Preview links - hostsclick.com</h1>
    <p>Logged in as: <?php echo hc_escape($user ?: 'unknown'); ?></p>
    <p>Status: <?php echo hc_escape($licenseStatus); ?></p>

    <?php if ($message): ?>
        <div style="color: #0f5132; margin-bottom: 12px;"><?php echo hc_escape($message); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div style="color: #842029; margin-bottom: 12px;"><?php echo hc_escape($error); ?></div>
    <?php endif; ?>

    <section style="border: 1px solid #ddd; padding: 16px; margin-bottom: 24px;">
        <h2>Your Domains</h2>
        <p>Create a temporary preview URL for each domain using the local server IP.</p>
        <table style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th style="text-align: left; border-bottom: 1px solid #ccc; padding: 8px;">Domain</th>
                    <th style="text-align: left; border-bottom: 1px solid #ccc; padding: 8px;">IP</th>
                    <th style="text-align: left; border-bottom: 1px solid #ccc; padding: 8px;">Action</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($domains)): ?>
                <tr><td colspan="3" style="padding: 8px;">No domains found.</td></tr>
            <?php else: ?>
                <?php foreach ($domains as $row): ?>
                    <tr>
                        <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo hc_escape($row['domain']); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid #eee;"><?php echo hc_escape($row['ip'] ?: 'N/A'); ?></td>
                        <td style="padding: 8px; border-bottom: 1px solid #eee;">
                            <form method="post" style="margin: 0;">
                                <input type="hidden" name="action" value="create_link">
                                <input type="hidden" name="domain" value="<?php echo hc_escape($row['domain']); ?>">
                                <input type="hidden" name="ip" value="<?php echo hc_escape($row['ip'] ?? ''); ?>">
                                <?php if (hc_has_license($config)): ?>
                                    <select name="expires_option">
                                        <option value="1h">1 hour</option>
                                        <option value="6h">6 hours</option>
                                        <option value="12h">12 hours</option>
                                        <option value="24h">24 hours</option>
                                        <option value="7d">7 days</option>
                                        <option value="30d">30 days</option>
                                    </select>
                                <?php endif; ?>
                                <button type="submit">Temporary URL</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </section>

    <section style="border: 1px solid #ddd; padding: 16px;">
        <h2>Create a Preview Link</h2>
        <p>Create preview links for other IPs and domains.</p>
        <form method="post">
            <input type="hidden" name="action" value="create_custom">
            <div style="margin-bottom: 8px;">
                <label for="custom_domain">Domain</label><br>
                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <input
                        id="custom_domain"
                        name="custom_domain"
                        type="text"
                        style="width: 420px; max-width: 100%;"
                        placeholder="Type a domain or pick from list"
                        autocomplete="off"
                    >
                    <select id="domain_picker" style="min-width: 220px;">
                        <option value="">Select a server domain</option>
                        <?php foreach ($domains as $row): ?>
                            <option value="<?php echo hc_escape($row['domain']); ?>"><?php echo hc_escape($row['domain']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if (empty($domains)): ?>
                    <div style="color: #6c757d; font-size: 12px; margin-top: 4px;">No domains found from the server list. You can still enter a custom domain.</div>
                <?php endif; ?>
            </div>
            <div style="margin-bottom: 8px;">
                <label for="custom_ip">IP Address</label><br>
                <input id="custom_ip" name="custom_ip" type="text" style="width: 420px; max-width: 100%;">
            </div>
            <?php if (hc_has_license($config)): ?>
                <div style="margin-bottom: 8px;">
                    <label for="expires_option">Expiry</label><br>
                    <select id="expires_option" name="expires_option">
                        <option value="1h">1 hour</option>
                        <option value="6h">6 hours</option>
                        <option value="12h">12 hours</option>
                        <option value="24h">24 hours</option>
                        <option value="7d">7 days</option>
                        <option value="30d">30 days</option>
                    </select>
                </div>
            <?php endif; ?>
            <button type="submit">Create link</button>
        </form>
    </section>

    <?php if ($linkResult && empty($linkResult['error'])): ?>
        <div style="margin-top: 16px;">
            <strong>Preview URL:</strong>
            <a href="<?php echo hc_escape($linkResult['preview_url'] ?? '#'); ?>" target="_blank" rel="noopener noreferrer">
                <?php echo hc_escape($linkResult['preview_url'] ?? ''); ?>
            </a>
        </div>
    <?php endif; ?>
</div>
<script>
  (function () {
    var input = document.getElementById('custom_domain');
    var picker = document.getElementById('domain_picker');
    if (!input || !picker) return;
    picker.addEventListener('change', function () {
      if (picker.value) {
        input.value = picker.value;
      }
    });
    input.addEventListener('input', function () {
      if (!input.value) {
        picker.value = '';
      }
    });
  })();
</script>
</body>
</html>
