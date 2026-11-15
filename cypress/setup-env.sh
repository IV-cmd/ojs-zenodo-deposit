#!/bin/sh
# Boot the OJS dev stack and seed it for the Cypress specs.
# Idempotent: skips steps already done. Used by CI; works locally too.
set -eu

COMPOSE="docker compose -f $(dirname "$0")/../docker-compose.yml"
OJS_URL="http://localhost:8080"
API_KEY="cypress-dev-apikey"
API_SECRET="dev-api-secret"
DB="$COMPOSE exec -T db mysql -u ojs -pojs_dev_password ojs"

$COMPOSE up -d --wait

# Wait for Apache and MySQL (the mysql client can race the socket on fresh boots).
until curl -sf -o /dev/null "$OJS_URL"; do sleep 2; done
until $DB -e 'select 1' >/dev/null 2>&1; do sleep 2; done

# Fresh volume -> run the CLI installer.
if [ "$($DB -N -B -e 'select count(*) from information_schema.tables where table_schema="ojs" and table_name="users"' 2>/dev/null)" = "0" ]; then
    printf 'en\n\n/var/www/files\nadmin\nadminadmin\nadminadmin\nadmin@localhost\nmysqli\ndb\nojs\nojs_dev_password\nojs\nojs\nN\nY\n' \
        | $COMPOSE exec -T ojs php tools/install.php
    # The CLI installer leaves allowed_hosts = [""] (rejects all requests) and api_key_secret empty.
    $COMPOSE exec -T ojs sed -i \
        -e 's|^allowed_hosts = .*|allowed_hosts = \x27["localhost"]\x27|' \
        -e "s|^api_key_secret = .*|api_key_secret = \"$API_SECRET\"|" \
        /var/www/ojs-config/config.inc.php
fi

# Admin API key (raw value in user_settings; requests send it JWT-encoded).
$DB -e "insert ignore into user_settings (user_id, locale, setting_name, setting_value) values (1, '', 'apiKey', '$API_KEY'), (1, '', 'apiKeyEnabled', '1')"
JWT=$($COMPOSE exec -T ojs php -r "require '/var/www/html/lib/pkp/lib/vendor/firebase/php-jwt/src/JWT.php'; require '/var/www/html/lib/pkp/lib/vendor/firebase/php-jwt/src/Key.php'; echo Firebase\\JWT\\JWT::encode(['$API_KEY'], '$API_SECRET', 'HS256');")

# Journal (contexts API seeds default sections and user groups).
# Apache strips the Authorization header under this mod_php setup, so the token
# goes in the apiToken query param, which OJS also accepts.
if [ "$($DB -N -B -e 'select count(*) from journals')" = "0" ]; then
    curl -sf -X POST "$OJS_URL/index.php/index/api/v1/contexts?apiToken=$JWT" \
        -H "Content-Type: application/json" \
        -d '{"name":{"en":"Zenodo Dev Journal"},"acronym":{"en":"zdj"},"urlPath":"zdj","supportedLocales":["en"],"supportedFormLocales":["en"],"supportedSubmissionLocales":["en"]}' -o /dev/null
    MANAGER_GROUP=$($DB -N -B -e 'select user_group_id from user_groups where context_id=1 and role_id=16 limit 1')
    $DB -e "insert ignore into user_user_groups (user_group_id, user_id) values ($MANAGER_GROUP, 1)"
fi

# Register + enable the plugin (same rows the UI writes).
$DB -e "insert ignore into versions (major,minor,revision,build,date_installed,\`current\`,product_type,product,product_class_name,lazy_load,sitewide) values (0,2,0,0,now(),1,'plugins.generic','zenodoDeposit','ZenodoDepositPlugin',1,0)"
$DB -e "insert ignore into plugin_settings (plugin_name,context_id,setting_name,setting_value,setting_type) values ('zenododepositplugin',1,'enabled','1','bool')"

echo "setup-env: OJS ready at $OJS_URL"
