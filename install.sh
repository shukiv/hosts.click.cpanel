#!/bin/bash
mkdir -p /usr/local/cpanel/base/frontend/jupiter/hosts.click/

cp hostsclick.svg index.live.php install.json /usr/local/cpanel/base/frontend/jupiter/hosts.click/

/usr/local/cpanel/scripts/install_plugin /usr/local/cpanel/base/frontend/jupiter/hosts.click/
