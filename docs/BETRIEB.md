# Betrieb – Rokoso installieren, aktualisieren, sichern

Diese Anleitung richtet sich an die Person, die Rokoso für eine Stadtelternvertretung betreibt.

## Voraussetzungen

- Webserver mit **PHP ≥ 8.4** und den Erweiterungen `ctype, iconv, curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, zip`
- **MariaDB ≥ 10.11 / MySQL ≥ 8** (PostgreSQL funktioniert über Doctrine ebenfalls; die Migrationen sind für MySQL/MariaDB erzeugt)
- **Composer** und SSH-Zugang (für Installation und Updates)
- Möglichkeit für **Cronjobs** (E-Mail-Abruf, Versand, Erinnerungen, Löschfristen)
- Ein SMTP-Zugang für die Systemmails (Bestätigung, Passwort, Einladungen)
- HTTPS (Pflicht für die installierbare App und sichere Cookies)

Node.js wird nicht benötigt.

## Installation

```bash
git clone <repository> rokoso && cd rokoso
composer install --no-dev --optimize-autoloader
```

`.env.local` anlegen (wird nicht eingecheckt):

```dotenv
APP_ENV=prod
APP_SECRET=<64 zufällige Hex-Zeichen, z. B. php -r "echo bin2hex(random_bytes(32));">
DATABASE_URL="mysql://rokoso:PASSWORT@127.0.0.1:3306/rokoso?serverVersion=11.8.0-MariaDB&charset=utf8mb4"
MAILER_DSN=smtp://benutzer:passwort@smtp.example.org:465
MAILER_FROM="Rokoso <system@example.org>"
DEFAULT_URI=https://rokoso.example.org
# Fehlerlog als Datei (Standard: stderr, passend für Docker)
LOG_PATH="%kernel.logs_dir%/%kernel.environment%.log"
```

> **Wichtig:** Aus `APP_SECRET` wird der Schlüssel abgeleitet, mit dem die Passwörter der E-Mail-Konten verschlüsselt sind.
> Wird `APP_SECRET` geändert, müssen alle E-Mail-Konto-Passwörter neu eingegeben werden. `APP_SECRET` also sicher aufbewahren.

Datenbank, Assets und Cache vorbereiten:

```bash
php bin/console doctrine:database:create --if-not-exists
php bin/console doctrine:migrations:migrate -n
php bin/console app:js:build --minify
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear
```

Der **Document Root** des Webservers zeigt auf `public/`. Für Apache liefert `composer require symfony/apache-pack` eine passende `.htaccess`; für nginx siehe die Symfony-Dokumentation („Configuring a Web Server“).

Schreibrechte für den Webserver-Benutzer: `var/` (Cache, Logs, **Anhänge, Dateien und Forum-Uploads in `var/storage`**) und `public/uploads/` (Profilbilder, Logos, Fotos).

Die Zeitzone ist auf `Europe/Berlin` ausgelegt: in der `php.ini` `date.timezone = Europe/Berlin` setzen.

Dateien (Menü „Dateien“, Forum-Anhänge) dürfen bis zu **50 MB** groß sein. Damit das klappt, in der `php.ini` z. B. `upload_max_filesize = 50M`, `post_max_size = 200M` und `max_file_uploads = 20` setzen (bei nginx zusätzlich `client_max_body_size 200m;`).

## Alternative: Betrieb mit Docker

Statt eines eigenen Webservers kann Rokoso als Docker-Image laufen. Das Image basiert auf **FrankenPHP** (Caddy + PHP 8.4) und holt sich für die eingetragene Domain automatisch ein HTTPS-Zertifikat (Let's Encrypt). Ein Beispiel mit Datenbank liegt in `compose.prod.yaml`:

| Dienst | Aufgabe |
|---|---|
| `app` | Webserver; führt beim Start die Datenbank-Migrationen aus |
| `cron` | Hintergrundaufgaben ohne System-Cron (Ausgang jede Minute, Abruf alle 5 Min., `app:notify` alle 15 Min., Passwort-Links täglich) |
| `db` | MariaDB 11.8 |

`.env.prod` neben `compose.prod.yaml` anlegen (nicht einchecken):

```dotenv
SERVER_NAME=rokoso.example.org
DEFAULT_URI=https://rokoso.example.org
APP_SECRET=<64 zufällige Hex-Zeichen>
DB_PASSWORD=<zufällig>
DB_ROOT_PASSWORD=<zufällig>
MAILER_DSN=smtp://benutzer:passwort@smtp.example.org:465
MAILER_FROM="Rokoso <system@example.org>"
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
```

```bash
docker compose -f compose.prod.yaml --env-file .env.prod up -d --build
docker compose -f compose.prod.yaml --env-file .env.prod exec app php bin/console app:user:promote <E-Mail>
```

Läuft ein anderer Reverse-Proxy davor, `SERVER_NAME=:80` setzen und nur Port 80 freigeben. **Update:** `git pull` und denselben `up -d --build`-Befehl erneut ausführen. **Sicherung:** die Volumes `db`, `storage` (= `var/storage`) und `uploads` (= `public/uploads`) sowie `.env.prod`.

## Cronjobs

```cron
# Neue E-Mails aller aktiven Konten abrufen (alle 5 Minuten)
*/5 * * * * cd /pfad/zu/rokoso && php bin/console app:mail:sync --no-interaction >> var/log/mail-sync.log 2>&1

# Ausgang: verzögert gesendete E-Mails („Senden rückgängig“) verschicken (jede Minute)
* * * * * cd /pfad/zu/rokoso && php bin/console app:mail:outbox --no-interaction >> var/log/outbox.log 2>&1

# Erinnerungen, Tageszusammenfassung, Aufräumen und Löschfristen (alle 15 Minuten)
*/15 * * * * cd /pfad/zu/rokoso && php bin/console app:notify --no-interaction >> var/log/notify.log 2>&1

# Abgelaufene Passwort-Links aufräumen (täglich)
15 3 * * * cd /pfad/zu/rokoso && php bin/console reset-password:remove-expired --no-interaction
```

Ein Dauer-Worker (Messenger) ist **nicht** nötig: Systemmails werden direkt verschickt.

**Cron per URL (ohne passendes PHP auf der Kommandozeile, z. B. KeyHelp bei GN2):** In `shared/.env.local` bzw. `.env.local` ein `CRON_TOKEN` mit mindestens 32 Zeichen setzen (z. B. `openssl rand -hex 24`) und im Hosting-Panel **eine** Aufgabe anlegen, die jede Minute die URL `https://<domain>/_cron/<CRON_TOKEN>` aufruft (KeyHelp: *Geplante Aufgaben* → Typ „Befehl“ mit `curl -sS --fail-with-body https://<domain>/_cron/<CRON_TOKEN>`; bei curl älter als 7.76 `-fsS` statt `-sS --fail-with-body`). Rokoso führt mit dem PHP des Webservers die fälligen Aufgaben aus – in denselben Abständen wie oben (Ausgang jede Minute, Abruf alle 5 Minuten, `app:notify` alle 15 Minuten, Passwort-Links täglich). **Ist alles in Ordnung, kommt eine leere Antwort (204)** – curl gibt nichts aus, der Cron-Dienst schickt keine Mail. Schlägt eine Aufgabe fehl (auch ein E-Mail-Konto, das sich nicht abrufen lässt), antwortet Rokoso mit 500 und der Ausgabe der fehlgeschlagenen Aufgaben; curl gibt sie aus und der Cron-Dienst mailt sie. Die letzten Läufe stehen in `var/cache/prod/cron.json`, das gleichzeitig als Sperre dient (nach einem Deployment laufen alle Aufgaben einmal sofort; läuft der vorige Aufruf noch, passiert nichts). Fehler stehen zusätzlich im Fehlerlog; ein falscher Token ergibt 404 (mit curl `-f`/`--fail-with-body` ebenfalls eine Fehlermeldung). Ohne `CRON_TOKEN` ist der Aufruf abgeschaltet. Den Token wie ein Passwort behandeln – er steht im Zugriffslog des Webservers, erlaubt aber nur, die Aufgaben früher anzustoßen.

**Abruf beim Öffnen:** Zusätzlich ruft Rokoso die Postfächer ab, wenn jemand „Heute“ oder den Posteingang öffnet und der letzte Abruf länger als `MAIL_SYNC_INTERVAL` Minuten (Standard 5, `0` = aus) zurückliegt – nach dem Ausliefern der Seite, also ohne Wartezeit. Der Cronjob bleibt trotzdem empfohlen. Hängt der Abruf eines Kontos länger als eine Stunde oder ist er fehlgeschlagen, sehen die Admins der Organisation einen Hinweis auf „Heute“ und in der Organisation.

`app:notify` setzt auch die **Löschfristen** um (einstellbar je Organisation unter „Organisation bearbeiten“):

| Was | Standard | Wirkung |
|---|---|---|
| Papierkorb | 30 Tage | Nachrichten im Papierkorb werden samt Anhängen endgültig gelöscht |
| Nachrichten | unbegrenzt | E-Mails und interne Nachrichten älter als X Jahre werden gelöscht (auf dem IMAP-Server bleiben sie) |
| Öffentliche Einsendungen | 2 Jahre | Umfrage-Antworten und Veranstaltungs-Anmeldungen |
| Sicherheitsprotokoll | 1 Jahr | fest, IP-Adressen werden gekürzt gespeichert |
| Unbestätigte Anfragen der öffentlichen Seite | 7 Tage | fest |

## Erste Schritte nach der Installation

1. Unter `/register` ein Konto anlegen und die E-Mail-Adresse bestätigen.
2. Unter **Organisationen** die Stadtelternvertretung anlegen – wer sie anlegt, ist Administrator.
3. In der Organisation ein **E-Mail-Konto** hinzufügen (IMAP + SMTP) und „Verbindung testen“.
4. Mitglieder per E-Mail **einladen**.
5. Das eigene Konto zum **Plattform-Admin** machen: `php bin/console app:user:promote <E-Mail>` (Rücknahme mit `--revoke`). Plattform-Admins sehen unter `/admin` alle Konten (sperren, löschen), die Organisationen (ohne deren Inhalte) und das Sicherheitsprotokoll.

## Deployment mit GitHub Actions (Shared Hosting)

Für Hosting ohne Docker und ohne passendes PHP auf der Kommandozeile (z. B. KeyHelp bei GN2: Web-PHP 8.5, per SSH nur 8.2) liegt `.github/workflows/deploy.yml` bei. Bei jedem Push auf `main` laufen zuerst `composer check` (mit MariaDB-Dienst), dann der Build auf GitHub (Composer ohne Dev-Pakete, Tailwind, JavaScript, Assets). Auf dem Server wird nur hochgeladen und umgeschaltet.

**Instanzen:** Jede Domain ist eine eigene Instanz mit eigenem Ordner, eigener Datenbank und eigener `shared/.env.local` (eigene `APP_SECRET`, `DEFAULT_URI`, `DEPLOY_TOKEN`, `CRON_TOKEN`, VAPID-Schlüssel, eigene KeyHelp-Aufgabe für den Cron). Der Code ist überall derselbe.

| Ziel (GitHub-Environment, `bin/deploy`) | Domain | Art | Wann |
|---|---|---|---|
| `stage` | stage.rokoso.de | Staging, passwortgeschützt | automatisch bei jedem Push auf `main` |
| `rokoso` | rokoso.de | live | von Hand |
| `stev-halle` | rokoso.stev-halle.de | live | von Hand |

Live-Instanzen werden unter *Actions → Test & Deploy → Run workflow* mit dem passenden Ziel ausgeliefert (`live` = beide gleichzeitig), sobald der Stand auf Staging geprüft ist. Lokal entsprechend `bin/deploy rokoso stev-halle` (siehe unten).

**Passwortschutz (Staging):** In der `shared/.env.local` der Instanz `BASIC_AUTH="benutzer:passwort"` setzen. Rokoso fragt dann vor jeder Seite per Browser-Dialog nach Benutzer und Passwort (HTTP Basic Auth, nur über HTTPS sinnvoll). Der Schutz steckt in der App statt im Webserver, damit er Deployments übersteht; Deploy-Hook (`/_deploy`) und Cron-URL (`/_cron/…`) bleiben erreichbar, weil sie eigene Tokens haben. Leer = aus.

**Push-Benachrichtigungen (VAPID):** Das Schlüsselpaar lokal erzeugen – es ist an keinen Server gebunden, auf dem Server ist also kein PHP auf der Kommandozeile nötig: `php bin/console app:vapid-keys` (unter Windows/XAMPP vorher in PowerShell `$env:OPENSSL_CONF = 'C:\xampp\php\extras\ssl\openssl.cnf'` bzw. in Git Bash `OPENSSL_CONF='C:\xampp\php\extras\ssl\openssl.cnf'` vor den Befehl setzen, sonst „Unable to create the key“). Die beiden ausgegebenen Zeilen `VAPID_PUBLIC_KEY=…` und `VAPID_PRIVATE_KEY=…` in die `shared/.env.local` der Instanz eintragen (z. B. mit dem Datei-Editor von KeyHelp); sie wirken ohne neues Deployment. Je Instanz ein eigenes Paar, den privaten Schlüssel wie ein Passwort behandeln (nicht committen). **Später nicht mehr wechseln:** Die Push-Abos der Geräte hängen am öffentlichen Schlüssel – nach einem Wechsel kommt nichts mehr an, bis alle Push auf ihren Geräten aus- und wieder einschalten. Ohne Schlüssel zeigt das Profil „nicht eingerichtet“.

Aufbau auf dem Server (je Instanz):

```
/www/stage.rokoso.de/
├── current -> releases/<zeit>-<commit>   (Document Root: current/public)
├── releases/                             (die letzten 5 Stände)
└── shared/
    ├── .env.local                        (Zugangsdaten, nur hier)
    ├── var/storage, var/log              (Dateien, Anhänge, Fehlerlog prod.log über LOG_PATH)
    └── public/uploads                    (Profilbilder, Logos)
```

Ablauf: Upload per rsync in einen neuen Ordner unter `releases/` (unveränderte Dateien als Hardlink), Verlinken von `shared/`, Umschalten von `current`, dann ruft GitHub den **Deploy-Hook** `POST /_deploy` auf. Er führt mit dem Web-PHP die Migrationen und `cache:warmup` aus. Geschützt ist er durch `DEPLOY_TOKEN` (mind. 32 Zeichen, in `.env.local` und als GitHub-Secret; ohne Token ist er aus). Der OPcache des Webservers hält `current/public/index.php` sonst dauerhaft auf dem alten Release fest; daher legt der Workflow vorher kurz eine PHP-Datei mit Zufallsnamen an, die `opcache_reset()` aufruft, und löscht sie wieder. Liefert der Webserver trotzdem noch den alten Stand aus, antwortet der Hook mit 409 und GitHub leert den Cache erneut und versucht es noch einmal.

Einstellungen im GitHub-Repository unter *Settings → Environments*, je ein Environment `stage`, `rokoso` und `stev-halle` (für die Live-Instanzen am besten mit *Deployment branches* = `main`):

| Art | Name | Inhalt |
|---|---|---|
| Secret | `SSH_PRIVATE_KEY` | privater Deploy-Schlüssel (ed25519, ohne Passphrase) |
| Secret | `DEPLOY_TOKEN` | derselbe Wert wie in `shared/.env.local` |
| Variable | `SSH_HOST` | z. B. `robert-rupf.host-011.gn2.hosting` |
| Variable | `SSH_USER` | SSH-Benutzer |
| Variable | `SSH_KNOWN_HOSTS` | Ausgabe von `ssh-keyscan <host>` |
| Variable | `DEPLOY_PATH` | z. B. `/www/stage.rokoso.de` |
| Variable | `APP_URL` | z. B. `https://stage.rokoso.de` |

**Zurück auf den vorigen Stand:** per SSH `cd /www/<instanz> && ln -sfn releases/<älterer Ordner> current.new && mv -Tf current.new current`. Migrationen werden dabei nicht zurückgedreht.

### Deployment von lokal (ohne GitHub Actions)

Läuft GitHub Actions nicht, macht `bin/deploy` dieselben Schritte vom eigenen Rechner aus (unter Windows in der Git Bash). Gebaut wird der eingecheckte Stand (`HEAD`) einmal in einem temporären Ordner; Tailwind- und esbuild-Binary sowie `assets/vendor` werden aus dem Projekt übernommen. Hochgeladen wird für jedes Ziel per `tar` über SSH (kein rsync nötig, dafür ohne Hardlinks – jeder Stand belegt den vollen Platz, aufgeräumt wird wie im Workflow auf 5 Stände).

Einstellungen je Ziel in `.env.deploy.<ziel>.local` im Projektordner (nicht eingecheckt), z. B. `.env.deploy.stage.local`:

```
SSH_TARGET=<ssh-benutzer>@robert-rupf.host-011.gn2.hosting
SSH_KEY=~/.ssh/deploy_rokoso
DEPLOY_PATH=/www/stage.rokoso.de
APP_URL=https://stage.rokoso.de
# DEPLOY_TOKEN=…   (optional; sonst aus shared/.env.local auf dem Server gelesen)
```

Aufruf:

- `bin/deploy stage` – `composer check`, Build, Upload, Umschalten, Deploy-Hook (Migrationen), Aufräumen
- `bin/deploy rokoso stev-halle` – einmal bauen, nacheinander auf beide Live-Instanzen (bricht beim ersten Fehler ab)
- `bin/deploy --skip-check <ziel …>` – ohne `composer check`
- `bin/deploy --build-only` – nur bauen (zum Ausprobieren, lädt nichts hoch)

Schlägt der Deploy-Hook fehl, zeigt `current` schon auf den neuen Stand; zurück wie oben beschrieben.

Die Zeitzone kommt aus der `php.ini` (siehe oben); die Tests in GitHub Actions laufen deshalb ebenfalls mit `date.timezone=Europe/Berlin`.

Apache braucht die mitgelieferte `public/.htaccess` (Weiterleitung auf `index.php`).

## Updates

```bash
git pull
composer install --no-dev --optimize-autoloader
php bin/console doctrine:migrations:migrate -n
php bin/console app:js:build --minify
php bin/console tailwind:build --minify
php bin/console asset-map:compile
php bin/console cache:clear
```

## Datensicherung

Zu sichern sind:

- die **Datenbank** (z. B. täglich `mysqldump --single-transaction rokoso > rokoso-$(date +%F).sql`)
- **`var/storage/`** – Anhänge von E-Mails und Nachrichten, Dateien (`files/`) und Forum-Uploads
- **`public/uploads/`** – Profilbilder, Logos, Kontaktfotos
- **`.env.local`** – insbesondere `APP_SECRET` (siehe oben)

E-Mails liegen zusätzlich weiterhin auf dem IMAP-Server; Rokoso verändert dort nichts.

## Systemmails zuverlässig zustellen

Rokoso verschickt Bestätigungen, Passwort-Links, Einladungen und Benachrichtigungen über `MAILER_DSN` mit dem Absender `MAILER_FROM`.
Über den SMTP-Server eines Webhosters klappt das technisch, aber dessen IP teilen sich viele Kunden. Vor allem **Microsoft 365** nimmt solche Mails oft an und legt sie dann **ohne Rückläufer in die Quarantäne**.
Für Systemmails daher einen Dienst für Transaktionsmails nutzen. Wegen der personenbezogenen Daten einen EU-Anbieter mit Auftragsverarbeitungsvertrag (AVV) wählen, z. B. Mailjet, Brevo oder Scaleway.
Die Postfächer (z. B. `system@…` für Antworten und Rückläufer) bleiben beim Webhoster.

**Prüfen:** Unter **Plattform → E-Mail-Versand** (`/admin/mail`, nur Plattform-Admins) stehen Absender und Server. Dort lässt sich eine Testmail schicken.
Rokoso zeigt dann die Fehlermeldung des Servers oder die Kennung, unter der er die Mail angenommen hat. Mit der Kennung kann man beim Anbieter nachfragen.
Ob Absender, SPF, DKIM und DMARC stimmen, zeigt eine Testmail an die Adresse von https://www.mail-tester.com.
Fehlgeschlagene Einladungen, Benachrichtigungen und Rundschreiben stehen als Fehler im Log.

**Beispiel Mailjet:**

Vorher Datenschutzerklärung und Impressum unter `/admin/legal` eintragen. Mailjet prüft neue Konten und hält den Versand an, wenn die Website keine öffentliche Datenschutzerklärung hat oder die Mails nicht darauf verlinken. Fragt Mailjet nach den Mailarten: Rokoso verschickt nur Mails an einzelne Personen, ausgelöst durch deren eigene Aktion (Registrierung, Passwort, Adressänderung, Formular auf der öffentlichen Seite) oder durch eine Aktion anderer Mitglieder, die sie direkt betrifft (Einladung, Sitzungseinladung, Benachrichtigung). Rundschreiben und E-Mails der Organisationen gehen über deren eigene Postfächer, nicht über den Versanddienst.

1. Konto anlegen und unter „Absender & Domains“ die Domain hinzufügen (z. B. `rokoso.de`). Die Domain bestätigen, entweder mit einer Datei bzw. einem TXT-Eintrag oder mit einer Mail an die Absenderadresse.
2. **DKIM:** den angezeigten TXT-Eintrag `mailjet._domainkey` im DNS anlegen.
3. **SPF:** den vorhandenen Eintrag ergänzen, nicht ersetzen, z. B. `v=spf1 a mx include:spf.mailjet.com -all`. Auch das Webmail beim Hoster soll weiter senden dürfen.
4. In Mailjet unter „SMTP-Einstellungen“ API-Schlüssel und Secret Key ablesen und in `shared/.env.local` jeder Instanz eintragen:

   ```dotenv
   MAILER_DSN="smtp://API-SCHLUESSEL:SECRET-KEY@in-v3.mailjet.com:587"
   MAILER_FROM="Rokoso <system@rokoso.de>"
   ```

   Sonderzeichen in Benutzername und Passwort URL-kodieren: `@` → `%40`, `&` → `%26`, `#` → `%23`, `/` → `%2F`, `:` → `%3A`. Ein Neustart ist nicht nötig.
5. Testmail unter `/admin/mail` schicken, auch an eine Microsoft-365-Adresse. Im Mailjet-Dashboard steht danach für jede Mail, ob sie zugestellt, abgelehnt oder als Spam markiert wurde.
6. Unter Kontoeinstellungen → Tracking das Zählen von Öffnungen und Klicks abschalten. Rokoso schickt dafür zusätzlich die Header `X-Mailjet-TrackOpen: 0` und `X-Mailjet-TrackClick: 0` mit (andere Server ignorieren sie). Sonst baut Mailjet ein Zählpixel ein und leitet Links, auch Passwort-Links, über eigene Server um.

**DKIM-Eintrag prüfen:** `nslookup -type=TXT mailjet._domainkey.<domain>` muss den ganzen Wert `k=rsa; p=…` zeigen. Steht dort nur `k=rsa`, hat das DNS-Panel den Wert am `;` abgeschnitten (Kommentarzeichen in Zonendateien). Dann den Wert in Anführungszeichen eintragen und, weil er länger als 255 Zeichen ist, auf zwei Zeichenketten aufteilen: `"k=rsa; p=…erster Teil" "zweiter Teil"`.

**Mails angenommen, aber nicht zugestellt:** Meldet `/admin/mail` „angenommen“ und erscheint die Mail trotzdem nicht in Mailjet, zeigt die API die zuletzt verarbeiteten Mails: `curl -s -u "API_KEY:SECRET_KEY" "https://api.mailjet.com/v3/REST/message?Limit=10&ShowSubject=true&Sort=ArrivedAt+DESC"` (im eigenen Terminal ausführen). Fehlen die neuen Mails dort, hält Mailjet sie zurück, etwa weil das Konto geprüft oder angehalten wird. Dann mit der Kennung aus `/admin/mail` beim Mailjet-Support nachfragen.

**DMARC-Berichte:** Den TXT-Eintrag `_dmarc.<domain>` auf `v=DMARC1; p=none; rua=mailto:dmarc@<domain>` setzen. Die großen Anbieter schicken dann täglich Berichte, wer im Namen der Domain sendet und ob es durchkommt. Wenn alles passt, auf `p=quarantine` verschärfen.

Benachrichtigungsmails haben die Header `List-Unsubscribe` und `List-Unsubscribe-Post` (Abbestellen mit einem Klick, RFC 8058). Darauf achten Gmail und Yahoo. Der signierte Link `/unsubscribe/<id>` schaltet nur die E-Mails ab und gilt nur für die aktuelle Adresse des Kontos.

## Datenschutz-Hinweise

- Rokoso liest Postfächer nur (IMAP read-only); „Papierkorb“ wirkt nur in Rokoso.
- Externe Bilder in HTML-Mails werden standardmäßig blockiert.
- Anhänge liegen außerhalb des Web-Roots und werden nur angemeldeten, berechtigten Mitgliedern ausgeliefert.
- Jede Person kann im Profil unter **Konto und Daten** ihre Daten als JSON herunterladen (Art. 15/20 DSGVO) und ihr Konto löschen (Art. 17). Beim Löschen werden persönliche Daten entfernt; Beiträge in gemeinsamen Bereichen bleiben unter „Gelöschtes Konto“ erhalten. Wer einziger Admin einer Organisation ist, muss vorher die Rolle übergeben.
- **Zwei-Faktor-Anmeldung** (TOTP-App, Ersatzcodes) kann jede Person im Profil unter **Sicherheit** einschalten; dort gibt es auch „Überall abmelden“ und das persönliche Sicherheitsprotokoll. Für Admins einer Organisation wird sie dringend empfohlen.
- **Vertraulicher Kontakt:** Betreff, Nachrichten und die optionale E-Mail-Adresse liegen verschlüsselt in der Datenbank; der Schlüssel hängt an `APP_SECRET`. **`APP_SECRET` nach dem Einrichten nie ändern** – sonst sind alle vertraulichen Gespräche unlesbar (und die Zugangscodes ungültig). Rokoso speichert dafür keine IP-Adressen und keine Uhrzeiten; die **Zugriffsprotokolle des Webservers** enthalten aber IP-Adressen – sie sollten kurz aufbewahrt bzw. gekürzt werden (beim Hoster einstellbar), wenn ihr Anonymität zusagt.
- **KI-Agenten** (MCP unter `https://<domain>/mcp`) sind pro Organisation standardmäßig **aus**. Wer sie einschaltet, sollte das in der Datenschutzerklärung erwähnen: Was ein Agent liest, geht an den jeweiligen KI-Anbieter (z. B. Anthropic). Zugänge lassen sich im Profil widerrufen, „Überall abmelden“ beendet alle; abgelaufene Zugänge löscht `app:notify`. Ein zusätzlicher Cronjob ist nicht nötig. Damit Agenten durchkommen, muss der Webserver den `Authorization`-Header an PHP weitergeben (`public/.htaccess` erledigt das bei Apache); ein `BASIC_AUTH`-Passwortschutz gilt für `/mcp`, `/oauth/token`, `/oauth/register` und `/.well-known/` nicht.
- **Auftragsverarbeitung (Art. 28 DSGVO):** Hoster und Versanddienst verarbeiten personenbezogene Daten im Auftrag, mit beiden ist ein Vertrag zur Auftragsverarbeitung (AVV) nötig. Der Versanddienst bekommt nur die Systemmails (Bestätigungen, Passwort-Links, Einladungen, Benachrichtigungen); E-Mails der Organisationen laufen über deren eigene Postfächer, den AVV mit diesem E-Mail-Anbieter schließt die jeweilige Organisation. Viele Anbieter machen ihn zum Teil ihrer AGB, bei anderen muss man ihn im Kundenbereich abschließen oder anfordern – je Anbieter prüfen und mit Datum im Verarbeitungsverzeichnis festhalten. Betreibt eine Einzelperson die Installation für eine Elternvertretung, braucht es zusätzlich einen AVV zwischen Elternvertretung (Verantwortliche) und dieser Person; Hoster und Versanddienst sind dann Unterauftragsverarbeiter.
- Für den Betrieb sind ein Impressum und eine Datenschutzerklärung der betreibenden Stelle nötig. Beides pflegt ein Plattform-Admin unter `/admin/legal` (Markdown); veröffentlicht unter `/privacy` und `/imprint`, verlinkt auf den Anmeldeseiten, im Profil und im Fuß jeder Systemmail. Leere Texte werden nicht veröffentlicht. Muster: siehe unten. Versanddienste wie Mailjet verlangen eine öffentliche Datenschutzerklärung, auf die die Mails verlinken.

## Fehlersuche

- Logs: `var/log/prod.log`, E-Mail-Abruf: `var/log/mail-sync.log`
- Abruf eines einzelnen Kontos testen: `php bin/console app:mail:sync <Konto-ID>`
- Der letzte Abruffehler eines Kontos steht auch in der Oberfläche beim E-Mail-Konto und auf „Heute“ (nur Admins).
- Systemmails kommen nicht an: Testmail unter `/admin/mail` (siehe „Systemmails zuverlässig zustellen“); nimmt der Server sie an, liegt es an der Zustellung (Spam-Ordner, Quarantäne bei Microsoft 365, Rückläufer im Absender-Postfach).
- Docker: `docker compose -f compose.prod.yaml logs -f app cron`

## Für Entwickler: Browser- und Barrierefreiheitstests

`composer check` deckt Code-Stil, PHPStan und die PHPUnit-Tests ab. Zusätzlich gibt es Tests in einem echten Chrome (Symfony Panther):

```bash
vendor/bin/bdi detect drivers   # passenden chromedriver nach drivers/ laden (einmalig, Chrome muss installiert sein)
composer test:browser
```

Sie prüfen Anmeldung, Befehlspalette/Tastenkürzel, Turbo-Aktionen im Posteingang sowie mit **axe-core** die wichtigsten Seiten in hellem und dunklem Design auf WCAG-2.2-AA-Verstöße (Stufe „serious“/„critical“). axe-core (MPL-2.0) wird nur zur Testzeit nach `var/` geladen und ist nicht Teil von Rokoso. Die Tests schreiben echte Daten in die Testdatenbank (`rokoso_test`). Eine Stichprobe mit einem Screenreader (NVDA) ersetzen sie nicht.

## Muster: Verzeichnis der Verarbeitungstätigkeiten (Art. 30 DSGVO)

Vorlage für die betreibende Stelle – Angaben in eckigen Klammern ergänzen. Die Verantwortung liegt bei der Stadtelternvertretung bzw. dem Träger, nicht bei den Entwicklern von Rokoso.

| Feld | Angabe |
|---|---|
| **Verantwortliche Stelle** | [Name der Stadtelternvertretung / des Trägervereins, Anschrift, Kontakt] |
| **Datenschutzbeauftragte*r** | [falls vorhanden, sonst „nicht benannt (nicht erforderlich)“] |
| **Bezeichnung** | Kollaborationsplattform „Rokoso“ für die Gremienarbeit der Elternvertretung |
| **Zwecke** | Kommunikation der Mitglieder (E-Mail-Postfach, interne Nachrichten, Forum), Organisation von Sitzungen, Beschlüssen, Abstimmungen, Terminen und Aufgaben, Ablage von Dokumenten, Kontakt zu Eltern und Institutionen, Beteiligung der Öffentlichkeit (Kontaktformular, Umfragen, Anmeldungen, Verteiler) |
| **Rechtsgrundlagen** | Art. 6 Abs. 1 lit. e DSGVO i. V. m. [Landesschulgesetz, § … Elternvertretung] für die Gremienarbeit; Art. 6 Abs. 1 lit. a (Einwilligung) für Verteiler-Abos und freiwillige Angaben in Umfragen; Art. 6 Abs. 1 lit. f für Sicherheitsprotokoll und Spamschutz |
| **Betroffene** | Mitglieder und Gäste der Organisation; Eltern, Lehrkräfte, Schulleitungen, Verwaltung und weitere Absender*innen von E-Mails; Teilnehmende an Umfragen, Anmeldungen und Verteilern |
| **Datenkategorien** | Mitglieder: Name, E-Mail, Profilbild, Funktion/Amtszeit, Inhalte (Nachrichten, Beiträge, Kommentare, Dateien), Anmeldeprotokoll mit gekürzter IP. Externe: Name, E-Mail-Adresse, Inhalt der Nachricht samt Anhängen, ggf. Kontaktdaten (Institution, Funktion, Telefon). Öffentlichkeit: Formulareingaben, ggf. Name/E-Mail |
| **Besondere Kategorien (Art. 9)** | Nicht vorgesehen; in Elternanfragen können jedoch Gesundheits- oder Sozialdaten von Kindern enthalten sein → sparsam weitergeben, Löschfristen nutzen |
| **Empfänger** | Mitglieder der jeweiligen Organisation (rollenbasiert, Gäste nur freigegebene Projekte); Hoster [Name, AV-Vertrag vom …]; E-Mail-Anbieter [Name, AV-Vertrag vom …] |
| **Drittlandübermittlung** | Keine [bei Hosting in der EU]; Web-Push läuft über den Push-Dienst des jeweiligen Browsers (nur Titel und Link der Benachrichtigung) |
| **Löschfristen** | Papierkorb [30] Tage; Nachrichten [unbegrenzt / X Jahre]; öffentliche Einsendungen [2] Jahre; Sicherheitsprotokoll 1 Jahr; unbestätigte Anfragen 7 Tage; Konten auf Wunsch sofort (Inhalte in gemeinsamen Bereichen werden anonymisiert); Mitgliedschaften nach Ende der Amtszeit ohne Zugriff, [Löschung nach … ] |
| **Technische und organisatorische Maßnahmen** | HTTPS; Passwörter mit modernem Hash (bcrypt/argon2); optionale Zwei-Faktor-Anmeldung; Sperre nach wiederholten Fehlversuchen; „Überall abmelden“; Postfach-Passwörter verschlüsselt (aus `APP_SECRET`); Anhänge außerhalb des Web-Roots, Auslieferung nur nach Rechteprüfung; externe Bilder in E-Mails blockiert; Sicherheitsprotokoll; tägliche Datensicherung [Ort, Aufbewahrung]; Updates [Rhythmus] |

## Muster: Datenschutzerklärung

Vorlage zum Einfügen unter `/admin/legal` – Angaben in eckigen Klammern ergänzen, Nicht-Zutreffendes streichen. Keine Rechtsberatung; die Verantwortung liegt bei der betreibenden Stelle. Den Satz zur Auftragsverarbeitung unter „Empfänger“ erst stehen lassen, wenn die Verträge abgeschlossen sind (siehe „Datenschutz-Hinweise“).

```markdown
## Verantwortliche Stelle

[Name der Stadtelternvertretung / des Trägervereins]
[Anschrift]
E-Mail: [Kontaktadresse]

## Worum es geht

Rokoso ist die Arbeitsplattform der [Stadtelternvertretung …]. Mitglieder organisieren hier E-Mails, Sitzungen, Beschlüsse, Termine, Aufgaben und Dateien. Ein Konto bekommt nur, wer sich registriert oder eingeladen wird.

## Welche Daten wir verarbeiten

- **Konto:** Name, E-Mail-Adresse, Passwort (nur als Hash), optional Profilbild und Zwei-Faktor-Schlüssel.
- **Mitarbeit:** Mitgliedschaften mit Funktion und Amtszeit sowie alles, was ihr in Rokoso schreibt oder hochladet.
- **E-Mails der Organisation:** Die Postfächer der Organisationen werden abgerufen und für die Mitglieder angezeigt, einschließlich Absender, Inhalt und Anhängen.
- **Öffentliche Seiten und Formulare:** Eingaben in Kontaktformulare, Umfragen, Anmeldungen und Verteiler, bei Verteilern mit Bestätigung per E-Mail (Double-Opt-in).
- **Sicherheitsprotokoll:** Anmeldungen und sicherheitsrelevante Änderungen mit gekürzter IP-Adresse, ein Jahr lang.
- **Server-Logs:** Der Webserver protokolliert Zugriffe mit IP-Adresse; [Aufbewahrung beim Hoster, z. B. 7 Tage].

## Zwecke und Rechtsgrundlagen

- Gremienarbeit der Elternvertretung: Art. 6 Abs. 1 lit. e DSGVO i. V. m. [Landesschulgesetz, § …].
- Konto und Nutzung der Plattform: Art. 6 Abs. 1 lit. b DSGVO.
- Verteiler-Abos und freiwillige Angaben: Einwilligung, Art. 6 Abs. 1 lit. a DSGVO, jederzeit widerrufbar.
- Sicherheitsprotokoll und Spamschutz: berechtigtes Interesse, Art. 6 Abs. 1 lit. f DSGVO.

## E-Mails von Rokoso

Rokoso schickt nur E-Mails, die eine Person direkt betreffen: Bestätigung der Adresse, Passwort zurücksetzen, Einladungen, Sitzungseinladungen und Benachrichtigungen (einzeln oder als tägliche Zusammenfassung). Benachrichtigungsmails lassen sich im Profil oder mit einem Klick in der Mail abbestellen. Die Mails gehen über [Versanddienst, z. B. Mailjet SAS, Paris] als Auftragsverarbeiter; Öffnungen und Klicks werden nicht gezählt.

## Cookies und Speicher im Browser

Rokoso verwendet nur Cookies und Browser-Speicher, die für den Betrieb nötig sind oder eine Einstellung speichern, die ihr selbst vornehmt. Eine Einwilligung ist dafür nicht erforderlich (§ 25 Abs. 2 Nr. 2 TDDDG). Es gibt keine Analyse-, Werbe- oder Tracking-Cookies und keine eingebundenen Inhalte Dritter; die Cookies werden nicht an andere weitergegeben.

| Name | Zweck | Speicherdauer |
|---|---|---|
| `PHPSESSID` | Sitzung: hält euch nach der Anmeldung angemeldet, auch beim Absenden von Formularen | bis zum Schließen des Browsers bzw. zur Abmeldung |
| `REMEMBERME` | nur wenn ihr „Angemeldet bleiben“ wählt | 30 Tage, „Überall abmelden“ macht es ungültig |
| `__Host-csrf-token_…` | Schutz vor gefälschten Formularen (CSRF) | wird nach dem Absenden gelöscht |
| `nav_collapsed` | ob die Navigation eingeklappt ist | 1 Jahr |
| `panel_…` | von euch eingestellte Spaltenbreite je Seite | 1 Jahr |

Außerdem nutzt Rokoso den Speicher eures Browsers:

- **Formular-Sicherung (Local Storage):** Was ihr in ein Formular schreibt, wird bis zum Absenden auf eurem Gerät zwischengespeichert, damit nichts verloren geht. Die Sicherung wird beim Absenden gelöscht und verfällt nach 7 Tagen. Auf den öffentlichen Seiten und beim vertraulichen Kontakt ist sie abgeschaltet.
- **Sitzungsspeicher (Session Storage):** merkt sich kurzzeitig, welches Suchfeld den Fokus hatte; wird beim Schließen des Tabs gelöscht.
- **App-Cache:** Wer Rokoso als App installiert, bekommt eine Offline-Seite und das App-Symbol im Cache des Browsers, keine Inhalte.

Ihr könnt Cookies und Browser-Speicher jederzeit in den Einstellungen eures Browsers löschen; danach müsst ihr euch neu anmelden.

## Push-Benachrichtigungen

Wer Push-Benachrichtigungen einschaltet, bekommt sie über den Push-Dienst des eigenen Browsers (z. B. Google, Mozilla, Apple). Dabei werden nur Titel und Link der Benachrichtigung übertragen, verschlüsselt.

## Empfänger

Inhalte sehen nur die Mitglieder der jeweiligen Organisation, je nach Rolle; Gäste nur freigegebene Projekte. Technische Dienstleister: [Hoster, Sitz] für den Betrieb der Plattform und [Versanddienst, Sitz] nur für die Systemmails von Rokoso (siehe „E-Mails von Rokoso“); mit beiden besteht ein Vertrag zur Auftragsverarbeitung nach Art. 28 DSGVO. Die E-Mails der Elternvertretungen selbst – Korrespondenz, Antworten, Rundschreiben – laufen nicht über den Versanddienst, sondern über deren eigene Postfächer bei [E-Mail-Anbieter der Organisation]; Rokoso ruft sie dort ab und versendet über deren Postausgangsserver. Eine Übermittlung in Drittländer findet nicht statt[, außer …].

## Speicherdauer

Gelöschte Inhalte bleiben [30] Tage im Papierkorb. Einsendungen über öffentliche Formulare werden nach [2] Jahren gelöscht, unbestätigte Anfragen nach 7 Tagen, das Sicherheitsprotokoll nach einem Jahr. Ein Konto kann jederzeit im Profil gelöscht werden; Beiträge in gemeinsamen Bereichen bleiben dann unter „Gelöschtes Konto“ erhalten.

## Eure Rechte

Ihr habt das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch sowie auf Widerruf einer Einwilligung. Im Profil unter „Konto und Daten“ könnt ihr eure Daten herunterladen und das Konto löschen. Außerdem könnt ihr euch bei einer Datenschutz-Aufsichtsbehörde beschweren, z. B. [Landesbeauftragte*r für den Datenschutz …].
```
