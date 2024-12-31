#!/bin/bash

if [ "$EUID" -ne 0 ]; then
    echo "You must run this installation script as root."
    exit 1
fi

INSTALL_DIR="/usr/local/cpanel/base/frontend/jupiter"

if [ ! -d "$INSTALL_DIR" ]; then
    echo "Installation dir not found: $INSTALL_DIR"
    exit 1
fi

echo "Starting hosts.click cPanel Plugin installation..."

mkdir -p $INSTALL_DIR/hosts.click/
cp hostsclick.svg index.live.php install.json $INSTALL_DIR/hosts.click/
/usr/local/cpanel/scripts/install_plugin $INSTALL_DIR/hosts.click/
