#!/usr/bin/env bash
set -euo pipefail

if [ "$(id -u)" -ne 0 ]; then
  echo "This installer must be run as root." >&2
  exit 1
fi

PLUGIN_URL_DEFAULT="https://hostsclick.com/downloads/hosts-click-cpanel-plugin.tar.gz"
PLUGIN_URL="${HC_PLUGIN_URL:-$PLUGIN_URL_DEFAULT}"
API_BASE_URL="${HC_API_BASE_URL:-}"

TMP_DIR="$(mktemp -d)"
cleanup() {
  rm -rf "$TMP_DIR"
}
trap cleanup EXIT

echo "Downloading Hosts.click cPanel plugin..."
if ! curl -fsSL "$PLUGIN_URL" -o "$TMP_DIR/plugin.tar.gz"; then
  echo "Download failed: $PLUGIN_URL" >&2
  exit 1
fi

tar -xzf "$TMP_DIR/plugin.tar.gz" -C "$TMP_DIR"

if [ ! -f "$TMP_DIR/cpanel-plugin/install.sh" ]; then
  echo "Installer not found inside archive." >&2
  exit 1
fi

bash "$TMP_DIR/cpanel-plugin/install.sh"

if [ -n "$API_BASE_URL" ] && [ -f /var/cpanel/hosts-click/config.json ]; then
  sed -i "s#\"api_base_url\": \".*\"#\"api_base_url\": \"${API_BASE_URL}\"#" /var/cpanel/hosts-click/config.json
fi

echo "Done. Configure the API key in WHM → Hosts.click."
