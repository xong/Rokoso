#!/bin/sh
# Startet Koopio: Cache aufwärmen, Datenbank migrieren, dann den eigentlichen Befehl (Webserver oder Cron).
set -e

if [ "${RUN_MIGRATIONS:-1}" = "1" ]; then
    # auf die Datenbank warten (max. 60 s)
    i=0
    until php bin/console dbal:run-sql -q "SELECT 1" >/dev/null 2>&1; do
        i=$((i + 1))
        if [ "$i" -ge 30 ]; then
            echo "Datenbank nicht erreichbar" >&2
            exit 1
        fi
        sleep 2
    done
    php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
fi

php bin/console cache:warmup -q
chown -R www-data:www-data var public/uploads 2>/dev/null || true

exec "$@"
