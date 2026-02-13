<?php

require_once '/usr/local/cpanel/php/cpanel.php';
require_once '/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/common.php';

$cpanel = new CPANEL();

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

echo $cpanel->header('Preview links', 'Preview links');
echo '<style>' . hc_gradient_css() . '</style>';
echo '<script>document.addEventListener("DOMContentLoaded",function(){document.body.classList.add("hc-gradient-bg");});</script>';
?>

<div class="hc-surface" style="font-family: Arial, sans-serif; padding: 24px; margin: 24px;">
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
        <h2>Create a Preview Link</h2>
        <p>Create preview links for other IPs and domains.</p>
        <form method="post">
            <input type="hidden" name="action" value="create_custom">
            <div style="margin-bottom: 8px;">
                <label for="custom_domain">Domain</label><br>
                <input
                    id="custom_domain"
                    name="custom_domain"
                    type="text"
                    style="width: 420px; max-width: 100%;"
                    placeholder="Type a domain or pick from list"
                    autocomplete="off"
                    list="hc-domain-list"
                >
                <datalist id="hc-domain-list">
                    <?php foreach ($domains as $row): ?>
                        <option value="<?php echo hc_escape($row['domain']); ?>"></option>
                    <?php endforeach; ?>
                </datalist>
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

<?php
echo $cpanel->footer();
