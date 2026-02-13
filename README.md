# Hosts.click cPanel + WHM Plugin

This plugin adds Hosts.click preview link generation inside **WHM** and **cPanel** (Jupiter).

## Features

- **WHM page**: configure API key + validate license, list all domains, create preview links using domain IPs.
- **cPanel page**: list user domains with **Temporary URL** buttons (local server IP), plus an advanced form for custom IP + domain.
- **Domains page injection**: adds a **Temporary URL** button alongside existing actions for each domain (no system files modified).
- **Licensed servers**: expiry options (1h, 6h, 12h, 24h, 7d, 30d).
- **Guest mode**: default expiry of 10 minutes (uses guest API endpoint).

## Requirements

- cPanel/WHM (Jupiter theme)
- PHP available in cpsrvd
- Hosts.click API base URL: `https://hostsclick.com` (HTTPS only)

## Install

```bash
sudo /path/to/cpanel-plugin/install.sh
```

This will:
- Copy plugin files into `/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/`
- Register the appconfig for **WHM**
- Register the **cPanel** menu entry using `install.json` + `/usr/local/cpanel/scripts/install_plugin`
- Create `/var/cpanel/hosts-click/config.json`
- Registers the cPanel menu entry using `install.json` + `/usr/local/cpanel/scripts/install_plugin`

## Uninstall

```bash
sudo /path/to/cpanel-plugin/uninstall.sh
```

## Configuration

Open **WHM → Hosts.click** and paste your API key. Use **Test API Key** to validate. The key is stored in:

```
/var/cpanel/hosts-click/config.json
```

## Guest vs Licensed

- **Guest mode** (no valid API key): uses `POST /api/guest-preview-links` with a fixed **10-minute** expiry.
- **Licensed mode** (valid API key): uses `POST /api/preview-links` and allows expiry options.

### Required API endpoints

The plugin expects these endpoints:

- **Licensed**
  - `POST /api/preview-links` (header `X-API-Key`)
  - `DELETE /api/preview-links/{token}` (used for API key testing)

- **Guest**
  - `POST /api/guest-preview-links` (no auth, 10-minute expiry enforced)

If `guest-preview-links` is not implemented yet, guest mode will fail.

## Notes

- Domains are read from `/etc/userdatadomains`.
- IP resolution is based on the `ip=` field in that file.
- No Jupiter customizations are required for the plugin menu entry or icon.

## Production fixes (Feb 3, 2026)

This section documents the exact changes applied on the cPanel server (`devcp`)
to make the plugin appear in WHM/cPanel and stop 500/404 errors.

### Files and locations

- WHM page: `/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/whm.php`
- cPanel page (Jupiter): `/usr/local/cpanel/base/frontend/jupiter/hosts-click/index.live.php`
- Shared logic: `/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/common.php`
- Appconfigs:
  - `/var/cpanel/apps/hosts-click.conf`
  - `/var/cpanel/apps/hosts-click-cpanel.conf`
- Config file: `/var/cpanel/hosts-click/config.json`
- cPanel icon is provided via `install.json` (Jupiter spritemap).

### Required appconfig contents

`/var/cpanel/apps/hosts-click.conf`:
```
name=hosts-click
displayname=HostsClick
service=whostmgr
url=/cgi/hosts-click
entryurl=hosts-click/whm.php
acls=all
icon=/cgi/hosts-click/assets/hc_cp.svg
order=50
user=root
target=_self
group=plugins
```

For cPanel, use `install.json` and `/usr/local/cpanel/scripts/install_plugin`.

### Fixes applied

- cPanel page renamed to **.live.php** (required for `CPANEL_PHPCONNECT_SOCKET`).
- cPanel page embeds inside Jupiter via `$cpanel->header()`/`$cpanel->footer()`.
- Public IP detection added for private IPs in `/etc/userdatadomains`.
- Permissions fixed so cPanel users can read required files:
  - `chmod 755 /var/cpanel/hosts-click`
  - `chmod 644 /var/cpanel/hosts-click/config.json`
  - `chmod 644 /etc/userdatadomains`
- Feature manager support is handled by `install_plugin` via `install.json`.
- cPanel icon installed at the Jupiter application icon path above.
- Duplicate appconfig **removed** (breaks UI):
  - Delete `/var/cpanel/apps/Hosts_click.conf` or `/var/cpanel/apps/Hosts-click.conf` if they appear.

### Cache refresh commands

```
/usr/local/cpanel/bin/register_appconfig /var/cpanel/apps/hosts-click.conf
/usr/local/cpanel/bin/register_appconfig /var/cpanel/apps/hosts-click-cpanel.conf
/usr/local/cpanel/bin/update_appconfig_apps
/usr/local/cpanel/bin/refresh_plugin_cache
/usr/local/cpanel/bin/resetcaches
```

### Sanity checks

- WHM URL: `/cgi/hosts-click/whm.php`
- cPanel URL: `/frontend/jupiter/hosts-click/index.live.php`
- Run syntax checks after changes:
  - `php -l /usr/local/cpanel/base/frontend/jupiter/hosts-click/index.live.php`
  - `php -l /usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/whm.php`
  - `php -l /usr/local/cpanel/whostmgr/docroot/cgi/hosts-click/common.php`
