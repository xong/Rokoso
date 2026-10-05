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
MAILER_FROM="Rokoso <noreply@example.org>"
DEFAULT_URI=https://rokoso.example.org
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
MAILER_FROM="Rokoso <noreply@example.org>"
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

Für Hosting ohne Docker und ohne passendes PHP auf der Kommandozeile (z. B. KeyHelp bei GN2: Web-PHP 8.5, per SSH nur 8.2) liegt `.github/workflows/deploy.yml` bei. Bei jedem Push auf `main` laufen zuerst `composer check` (mit MariaDB-Dienst), dann der Build auf GitHub (Composer ohne Dev-Pakete, Tailwind, JavaScript, Assets). Auf dem Server wird nur hochgeladen und umgeschaltet:

```
/www/staging.rokoso.de/
├── current -> releases/<zeit>-<commit>   (Document Root: current/public)
├── releases/                             (die letzten 5 Stände)
└── shared/
    ├── .env.local                        (Zugangsdaten, nur hier)
    ├── var/storage, var/log              (Dateien, Anhänge, Logs)
    └── public/uploads                    (Profilbilder, Logos)
```

Ablauf: Upload per rsync in einen neuen Ordner unter `releases/` (unveränderte Dateien als Hardlink), Verlinken von `shared/`, Umschalten von `current`, dann ruft GitHub den **Deploy-Hook** `POST /_deploy` auf. Er führt mit dem Web-PHP die Migrationen und `cache:warmup` aus. Geschützt ist er durch `DEPLOY_TOKEN` (mind. 32 Zeichen, in `.env.local` und als GitHub-Secret; ohne Token ist er aus). Der OPcache des Webservers hält `current/public/index.php` sonst dauerhaft auf dem alten Release fest; daher legt der Workflow vorher kurz eine PHP-Datei mit Zufallsnamen an, die `opcache_reset()` aufruft, und löscht sie wieder. Liefert der Webserver trotzdem noch den alten Stand aus, antwortet der Hook mit 409 und GitHub leert den Cache erneut und versucht es noch einmal.

Einstellungen im GitHub-Repository unter *Settings → Environments → staging*:

| Art | Name | Inhalt |
|---|---|---|
| Secret | `SSH_PRIVATE_KEY` | privater Deploy-Schlüssel (ed25519, ohne Passphrase) |
| Secret | `DEPLOY_TOKEN` | derselbe Wert wie in `shared/.env.local` |
| Variable | `SSH_HOST` | z. B. `robert-rupf.host-011.gn2.hosting` |
| Variable | `SSH_USER` | SSH-Benutzer |
| Variable | `SSH_KNOWN_HOSTS` | Ausgabe von `ssh-keyscan <host>` |
| Variable | `DEPLOY_PATH` | z. B. `/www/staging.rokoso.de` |
| Variable | `APP_URL` | z. B. `https://rokoso.robert-rupf.host-011.gn2.hosting` (Staging) |

**Zurück auf den vorigen Stand:** per SSH `cd /www/staging.rokoso.de && ln -sfn releases/<älterer Ordner> current.new && mv -Tf current.new current`. Migrationen werden dabei nicht zurückgedreht.

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

## Datenschutz-Hinweise

- Rokoso liest Postfächer nur (IMAP read-only); „Papierkorb“ wirkt nur in Rokoso.
- Externe Bilder in HTML-Mails werden standardmäßig blockiert.
- Anhänge liegen außerhalb des Web-Roots und werden nur angemeldeten, berechtigten Mitgliedern ausgeliefert.
- Jede Person kann im Profil unter **Konto und Daten** ihre Daten als JSON herunterladen (Art. 15/20 DSGVO) und ihr Konto löschen (Art. 17). Beim Löschen werden persönliche Daten entfernt; Beiträge in gemeinsamen Bereichen bleiben unter „Gelöschtes Konto“ erhalten. Wer einziger Admin einer Organisation ist, muss vorher die Rolle übergeben.
- **Zwei-Faktor-Anmeldung** (TOTP-App, Ersatzcodes) kann jede Person im Profil unter **Sicherheit** einschalten; dort gibt es auch „Überall abmelden“ und das persönliche Sicherheitsprotokoll. Für Admins einer Organisation wird sie dringend empfohlen.
- Für den Betrieb sind ein Impressum und eine Datenschutzerklärung der betreibenden Stelle nötig (nicht Teil von Rokoso).

## Fehlersuche

- Logs: `var/log/prod.log`, E-Mail-Abruf: `var/log/mail-sync.log`
- Abruf eines einzelnen Kontos testen: `php bin/console app:mail:sync <Konto-ID>`
- Der letzte Abruffehler eines Kontos steht auch in der Oberfläche beim E-Mail-Konto und auf „Heute“ (nur Admins).
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
