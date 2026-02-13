# Hosts.click cPanel + WHM Plugin

Hosts.click adds **temporary HTTPS preview links** for your hosted domains. This plugin brings Hosts.click into:

- **WHM** (root/admin)
- **cPanel** (Jupiter theme)

It also injects a “Temporary URL” action in the Domains UI (without editing core cPanel theme files).

## What You Get

- WHM UI to configure an API key, validate license, list domains, and create preview links using each domain’s IP.
- cPanel UI to generate preview links for the account’s domains.
- One-click “Temporary URL” on the Domains page (injection script; reversible).
- Guest mode (no key) and Licensed mode (key) flows.

## Requirements

- cPanel/WHM
- cPanel **Jupiter** theme (this repo includes the Jupiter frontend page)
- Root shell access
- Hosts.click API base URL: `https://hostsclick.com` (HTTPS only)

## Install (Recommended)

1. Clone this repo or upload it to your server.
2. Run the installer as root:

```bash
cd /path/to/hosts.click.cpanel
sudo bash install.sh
```

The installer will:

- Copy WHM CGI files into:
  - `/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/`
- Copy the Jupiter UI into:
  - `/usr/local/cpanel/base/frontend/jupiter/hosts-click/`
- Register the WHM appconfig:
  - `/var/cpanel/apps/hosts-click.conf`
- Install/register the cPanel plugin via:
  - `install.json` + `/usr/local/cpanel/scripts/install_plugin` (when available)
- Create the plugin config file:
  - `/var/cpanel/hosts-click/config.json`
- Refresh cPanel/WHM caches.

## Uninstall

```bash
cd /path/to/hosts.click.cpanel
sudo bash uninstall.sh
```

## Configuration

1. Open: **WHM → Hosts.click**
2. Paste your API key and click **Test API Key**.

Config is stored at:

```text
/var/cpanel/hosts-click/config.json
```

### Permissions Note

This plugin runs in both WHM and cPanel contexts, so the installer sets permissions so cPanel can read the config directory/file.
If you need to tighten permissions, do it carefully and re-test both WHM and cPanel pages.

## Guest vs Licensed Mode

- Guest mode (no valid key): uses `POST /api/guest-preview-links` with a fixed **10-minute** expiry.
- Licensed mode (valid key): uses `POST /api/preview-links` (header `X-API-Key`) and supports expiry options.

### Required API Endpoints

- Licensed:
  - `POST /api/preview-links` (`X-API-Key`)
  - `DELETE /api/preview-links/{token}` (used for API key testing)
- Guest:
  - `POST /api/guest-preview-links` (no auth)

If `guest-preview-links` is not available on your API base URL, guest mode will fail.

## Files and Locations (On the cPanel Server)

- WHM page:
  - `/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/whm.php`
- cPanel page (Jupiter):
  - `/usr/local/cpanel/base/frontend/jupiter/hosts-click/index.live.php`
- Shared logic:
  - `/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/common.php`
- WHM appconfig:
  - `/var/cpanel/apps/hosts-click.conf`
- Config file:
  - `/var/cpanel/hosts-click/config.json`

## How Domain/IP Detection Works

- Domains are read from:
  - `/etc/userdatadomains`
- IP resolution uses the `ip=` field from that file.

## Troubleshooting

- Plugin does not appear in WHM/cPanel:
  - Re-run `install.sh` and then run:

```bash
/usr/local/cpanel/bin/update_appconfig_apps || true
/usr/local/cpanel/bin/refresh_plugin_cache || true
/usr/local/cpanel/bin/resetcaches || true
```

- 500 errors / blank page:
  - Run syntax checks:

```bash
php -l /usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/whm.php
php -l /usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/common.php
php -l /usr/local/cpanel/base/frontend/jupiter/hosts-click/index.live.php
```

- cPanel page must be `*.live.php`:
  - The Jupiter page is named `index.live.php` to satisfy cPanel’s socket bootstrap expectations.

## Updating

To update, pull new changes and re-run the installer:

```bash
cd /path/to/hosts.click.cpanel
git pull
sudo bash install.sh
```

## Security Notes

- Your API key is stored in a JSON config file on the server.
- Prefer limiting access to the config directory to the minimum required for WHM and cPanel to function.

## License / Support

Issues and PRs are welcome. Include:

- cPanel version
- Theme (Jupiter)
- PHP error output (if any)
- The exact install/uninstall commands you ran
