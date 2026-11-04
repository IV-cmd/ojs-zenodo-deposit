#!/bin/sh
set -eu

CONFIG_FILE=/var/www/ojs-config/config.inc.php

if [ ! -f "$CONFIG_FILE" ]; then
    cp /var/www/html/config.inc.php "$CONFIG_FILE"
    sed -i 's|^base_url = .*|base_url = "http://localhost:8080"|' "$CONFIG_FILE"
fi

chown -R www-data:www-data /var/www/ojs-config /var/www/files /var/www/html/public
# PKP startup runs sed -i; PKP_CONF must point at the volume file, not its webroot symlink.
ln -sfn "$CONFIG_FILE" /var/www/html/config.inc.php
chown -h www-data:www-data /var/www/html/config.inc.php

exec /usr/local/bin/pkp-start
