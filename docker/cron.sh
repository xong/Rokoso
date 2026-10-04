#!/bin/sh
# Hintergrundaufgaben ohne System-Cron: Postausgang jede Minute, Mail-Abruf alle 5 Minuten,
# Erinnerungen/Aufräumen alle 15 Minuten, abgelaufene Passwort-Links täglich.
minute=0
while true; do
    php bin/console app:mail:outbox -q || true
    if [ $((minute % 5)) -eq 0 ]; then
        php bin/console app:mail:sync -q || true
    fi
    if [ $((minute % 15)) -eq 0 ]; then
        php bin/console app:notify -q || true
    fi
    if [ $((minute % 1440)) -eq 0 ]; then
        php bin/console reset-password:remove-expired --no-interaction -q || true
    fi
    minute=$((minute + 1))
    sleep 60
done
