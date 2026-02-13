#!/usr/bin/env bash
set -euo pipefail

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CGI_DIR="/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click"
CPANEL_DIR="/usr/local/cpanel/base/frontend/jupiter/hosts-click"
INSTALL_ICON_SVG="${PLUGIN_ROOT}/hosts_click.svg"
CONFIG_DIR="/var/cpanel/hosts-click"
CONFIG_FILE="${CONFIG_DIR}/config.json"
APPS_DIR="/var/cpanel/apps"
TMP_DIR=""

if [ "$(id -u)" -ne 0 ]; then
  echo "This installer must be run as root." >&2
  exit 1
fi

if ! getent group plugins >/dev/null 2>&1; then
  groupadd -r plugins
fi

cleanup() {
  if [ -n "${TMP_DIR}" ] && [ -d "${TMP_DIR}" ]; then
    rm -rf "${TMP_DIR}"
  fi
}
trap cleanup EXIT

mkdir -p "${CGI_DIR}"
cp -R "${PLUGIN_ROOT}/cgi/hosts-click/"* "${CGI_DIR}/"
chmod 0755 "${CGI_DIR}"/*.php
chmod 0644 "${CGI_DIR}"/assets/*

mkdir -p "${CPANEL_DIR}"
cp -R "${PLUGIN_ROOT}/jupiter/hosts-click/"* "${CPANEL_DIR}/"
chmod 0644 "${CPANEL_DIR}"/*.php

mkdir -p "${CONFIG_DIR}"
if [ ! -f "${CONFIG_FILE}" ]; then
  cat <<'JSON' > "${CONFIG_FILE}"
{
  "api_base_url": "https://hostsclick.com",
  "api_key": "",
  "license_status": "guest",
  "last_checked": null
}
JSON
  chmod 0640 "${CONFIG_FILE}"
fi
chown -R cpanel:cpanel "${CONFIG_DIR}" || true
chmod 0775 "${CONFIG_DIR}" || true
chmod 0664 "${CONFIG_FILE}" || true

mkdir -p "${APPS_DIR}"
rm -f "${APPS_DIR}/Hosts_click.conf" "${APPS_DIR}/Hosts_click_cpanel.conf" "${APPS_DIR}/Hosts-click.conf" "${APPS_DIR}/hosts-click-cpanel.conf" "${APPS_DIR}/hosts-click-whm.conf"
cp "${PLUGIN_ROOT}/appconfig/hosts-click-whm.conf" "${APPS_DIR}/hosts-click.conf"

/usr/local/cpanel/bin/register_appconfig "${APPS_DIR}/hosts-click.conf"
if [ -x /usr/local/cpanel/scripts/install_plugin ]; then
  TMP_DIR="$(mktemp -d)"
  cp "${PLUGIN_ROOT}/install.json" "${TMP_DIR}/install.json"
  if [ -f "${INSTALL_ICON_SVG}" ]; then
    cp "${INSTALL_ICON_SVG}" "${TMP_DIR}/hosts_click.svg"
  else
    cp "${PLUGIN_ROOT}/cgi/hosts-click/assets/hc_cp.svg" "${TMP_DIR}/hosts_click.svg"
  fi
  tar -czf "${TMP_DIR}/hosts-click-install.tar.gz" -C "${TMP_DIR}" install.json hosts_click.svg
  /usr/local/cpanel/scripts/install_plugin "${TMP_DIR}/hosts-click-install.tar.gz" --theme=jupiter
  if [ -f /usr/local/cpanel/base/frontend/jupiter/assets/application_icons/hosts_click.svg ]; then
    chmod 0644 /usr/local/cpanel/base/frontend/jupiter/assets/application_icons/hosts_click.svg || true
  fi
fi

/usr/local/cpanel/bin/update_appconfig_apps || true
/usr/local/cpanel/bin/refresh_plugin_cache || true
/usr/local/cpanel/bin/resetcaches || true

echo "Hosts.click plugin installed."
