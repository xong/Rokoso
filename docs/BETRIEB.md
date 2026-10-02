# Betrieb – Coop installieren, aktualisieren, sichern

Diese Anleitung richtet sich an die Person, die Coop für eine Stadtelternvertretung betreibt.

## Voraussetzungen

- Webserver mit **PHP ≥ 8.4** und den Erweiterungen `ctype, iconv, curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, zip`
- **MariaDB ≥ 10.11 / MySQL ≥ 8** (PostgreSQL funktioniert über Doctrine ebenfalls; die Migrationen sind für MySQL/MariaDB erzeugt)
- **Composer** und SSH-Zugang (für Installation und Updates)
- Möglichkeit für **Cronjobs** (E-Mail-Abruf)
- Ein SMTP-Zugang für die Systemmails (Bestätigung, Passwort, Einladungen)
- HTTPS (Pflicht für die installierbare App und sichere Cookies)

Node.js wird nicht benötigt.

## Installation

```bash
git clone <repository> coop && cd coop
composer install --no-dev --optimize-autoloader
```

`.env.local` anlegen (wird nicht eingecheckt):

```dotenv
APP_ENV=prod
APP_SECRET=<64 zufällige Hex-Zeichen, z. B. php -r "echo bin2hex(random_bytes(32));">
DATABASE_URL="mysql://coop:PASSWORT@127.0.0.1:3306/coop?serverVersion=11.8.0-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://benutzer:passwort@smtp.example.org:465
MAILER_FROM="Coop <noreply@example.org>"
DEFAULT_URI=https://coop.example.org
```

> **Wichtig:** Aus `APP_SECRET` wird der Schlüssel abgeleitet, mit dem die Passwörter der E-Mail-Konten verschlüsselt sind.
> Wird `APP_SECRET` geändert, müssen alle E-Mail-Konto-Passwörter neu eingegeben werden. `APP_SECRET` also sicher aufbewahren.

Datenbank, Assets und Cache vorbereiten:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear
```

Der **Document Root** des Webservers zeigt auf `public/`. Für Apache liefert `composer require symfony/apache-pack` eine passende `.htaccess`; für nginx siehe die Symfony-Dokumentation („Configuring a Web Server“).

Schreibrechte für den Webserver-Benutzer: `var/` (Cache, Logs, **Anhänge in `var/storage`**) und `public/uploads/` (Profilbilder, Logos, Fotos).

Die Zeitzone ist auf `Europe/Berlin` ausgelegt: in der `php.ini` `date.timezone = Europe/Berlin` setzen.

## Cronjobs

```cron
# Neue E-Mails aller aktiven Konten abrufen (alle 5 Minuten)
*/5 * * * * cd /pfad/zu/coop && php bin/console app:mail:sync --no-interaction >> var/log/mail-sync.log 2>&1

# Abgelaufene Passwort-Links aufräumen (täglich)
15 3 * * * cd /pfad/zu/coop && php bin/console reset-password:remove-expired --no-interaction
```

Ein Dauer-Worker (Messenger) ist **nicht** nötig: Systemmails werden direkt verschickt.

## Erste Schritte nach der Installation

1. Unter `/register` ein Konto anlegen und die E-Mail-Adresse bestätigen.
2. Unter **Organisationen** die Stadtelternvertretung anlegen – wer sie anlegt, ist Administrator.
3. In der Organisation ein **E-Mail-Konto** hinzufügen (IMAP + SMTP) und „Verbindung testen“.
4. Mitglieder per E-Mail **einladen**.

## Updates

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear
```

## Datensicherung

Zu sichern sind:

- die **Datenbank** (z. B. täglich `mysqldump --single-transaction coop > coop-$(date +%F).sql`)
- **`var/storage/`** – Anhänge von E-Mails und Nachrichten
- **`public/uploads/`** – Profilbilder, Logos, Kontaktfotos
- **`.env.local`** – insbesondere `APP_SECRET` (siehe oben)

E-Mails liegen zusätzlich weiterhin auf dem IMAP-Server; Coop verändert dort nichts.

## Datenschutz-Hinweise

- Coop liest Postfächer nur (IMAP read-only); „Papierkorb“ wirkt nur in Coop.
- Externe Bilder in HTML-Mails werden standardmäßig blockiert.
- Anhänge liegen außerhalb des Web-Roots und werden nur angemeldeten, berechtigten Mitgliedern ausgeliefert.
- Für den Betrieb sind ein Impressum und eine Datenschutzerklärung der betreibenden Stelle nötig (nicht Teil von Coop).

## Fehlersuche

- Logs: `var/log/prod.log`, E-Mail-Abruf: `var/log/mail-sync.log`
- Abruf eines einzelnen Kontos testen: `php bin/console app:mail:sync <Konto-ID>`
- Der letzte Abruffehler eines Kontos steht auch in der Oberfläche beim E-Mail-Konto.
