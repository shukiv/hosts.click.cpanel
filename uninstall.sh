#!/usr/bin/env bash
set -euo pipefail

PLUGIN_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CGI_DIR="/usr/local/cpanel/whostmgr/docroot/cgi/hosts-click"
CPANEL_DIR="/usr/local/cpanel/base/frontend/jupiter/hosts-click"
APPS_DIR="/var/cpanel/apps"
TMP_DIR=""

cleanup() {
  if [ -n "${TMP_DIR}" ] && [ -d "${TMP_DIR}" ]; then
    rm -rf "${TMP_DIR}"
  fi
}
trap cleanup EXIT

/usr/local/cpanel/bin/unregister_appconfig "${APPS_DIR}/hosts-click.conf" || \
  /usr/local/cpanel/bin/unregister_appconfig "${PLUGIN_ROOT}/appconfig/hosts-click-whm.conf" || true

if [ -x /usr/local/cpanel/scripts/uninstall_plugin ]; then
  TMP_DIR="$(mktemp -d)"
  cp "${PLUGIN_ROOT}/install.json" "${TMP_DIR}/install.json"
  if [ -f "${PLUGIN_ROOT}/hosts_click.svg" ]; then
    cp "${PLUGIN_ROOT}/hosts_click.svg" "${TMP_DIR}/hosts_click.svg"
  else
    cp "${PLUGIN_ROOT}/cgi/hosts-click/assets/hc_cp.svg" "${TMP_DIR}/hosts_click.svg"
  fi
  tar -czf "${TMP_DIR}/hosts-click-install.tar.gz" -C "${TMP_DIR}" install.json hosts_click.svg
  /usr/local/cpanel/scripts/uninstall_plugin "${TMP_DIR}/hosts-click-install.tar.gz" --theme=jupiter || true
fi

rm -rf "${CGI_DIR}"
rm -rf "${CPANEL_DIR}"
rm -f "${APPS_DIR}/hosts-click.conf" "${APPS_DIR}/hosts-click-whm.conf" "${APPS_DIR}/hosts-click-cpanel.conf" \
  "${APPS_DIR}/Hosts_click.conf" "${APPS_DIR}/Hosts_click_cpanel.conf" "${APPS_DIR}/Hosts-click.conf"

/usr/local/cpanel/bin/update_appconfig_apps || true
/usr/local/cpanel/bin/refresh_plugin_cache || true
/usr/local/cpanel/bin/resetcaches || true

echo "Hosts.click plugin removed."
