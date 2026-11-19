# Rokoso – Kollaborationssoftware für Elternvertretungen

Rokoso bündelt, was ehrenamtliche Elternvertretungen (Stadt-, Kreis-, Schul- und Kita-Elternvertretungen) für ihre
Arbeit brauchen – Postfach, Sitzungen, Abstimmungen, Termine, Aufgaben und Dateien an einem Ort statt verstreut über
private E-Mail-Adressen und Messenger-Gruppen. Was die Organisation weiß, gehört ihr und nicht einzelnen Personen:
Beim jährlichen Amtswechsel finden neue Mitglieder alles vor.

Open Source unter [MIT-Lizenz](LICENSE).

> Stand und Fahrplan: [`docs/PLAN.md`](docs/PLAN.md) · Betrieb: [`docs/BETRIEB.md`](docs/BETRIEB.md) · Hilfe für Nutzer:innen: [`help/`](help/)

## Funktionen

- **Gemeinsames Postfach:** die IMAP-Konten der Organisation für das ganze Team – zuweisen, kommentieren, Wiedervorlage,
  „Erledigt“ für mich oder für alle, Regeln, Spam-Ordner, Projekten zuordnen; Schreiben mit Entwürfen, Signaturen,
  Textbausteinen und „Senden rückgängig“, Versand über den eigenen SMTP-Server samt Kopie im Gesendet-Ordner
- **Rundschreiben** an Verteiler, einzeln adressiert, mit Fehlerprotokoll
- **Forum und Direktnachrichten** mit @-Erwähnungen, Beobachten und Abstimmungen in Themen
- **Sitzungen:** Tagesordnung mit Vorschlägen, Einladung mit .ics, Anwesenheit und Beschlussfähigkeit, Protokoll mit
  Freigabe, Beschlussliste, Aufgaben aus Tagesordnungspunkten
- **Abstimmungen und Umfragen:** offen oder geheim, Umlaufbeschluss, Terminfindung; Umfragen auch öffentlich mit Auswertung
- **Kalender** mit Wiederholungen, Einzelterminen einer Serie, Erinnerungen, Abo-Link und Gäste-Einladung
- **Aufgaben und Projekte:** Liste und Board, Zuständige, Fristen; Projekte bündeln Mails, Termine, Aufgaben und Dateien,
  Gastzugang pro Projekt
- **Dateien und Handbuch:** Ablage mit Vorschau, Versionen und Freigabelinks; Handbuch als Seitenbaum mit Versionen
- **Kontakte und Einrichtungen:** Kontakte mit Verlauf und Notizen, Verteiler, vCard-Import/-Export; Schulen und Kitas mit
  Vertretung und Stellvertretung
- **Öffentliche Seite** pro Organisation: Kontaktformular in den Eingang, Termine, Beschlüsse, Veranstaltungs-Anmeldung,
  Verteiler-Abo mit Double-Opt-in, vertraulicher Kontakt (verschlüsselt, ohne Konto)
- **Organisationen und Rechte:** Rollen, Funktionen und Amtszeiten, Übergabe beim Amtswechsel, Bereiche pro Organisation
  ein- und ausschaltbar
- **Startseite „Heute“,** globale Suche, Befehlspalette (Strg+K), Benachrichtigungen per Glocke, E-Mail und Push
- **App (PWA)** für Handy und Computer, Dunkelmodus, barrierearm (axe-geprüft)
- **Sicherheit und Datenschutz:** Zwei-Faktor-Anmeldung, „Überall abmelden“, Datenexport, Konto löschen, Löschfristen,
  Sicherheitsprotokoll; kein Tracking, keine Inhalte von Dritten
- **KI-Agenten (optional):** MCP-Schnittstelle mit persönlichen Tokens bzw. OAuth, pro Organisation abschaltbar
- **Betrieb:** Plattform-Verwaltung unter `/admin` mit Begrüßungstext der Anmeldeseite, Datenschutzerklärung und Impressum;
  läuft beim normalen Webhoster (Cron und Migrationen per URL) oder im Docker-Container

## Voraussetzungen

- PHP ≥ 8.4 mit den Erweiterungen `curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, zip`
- Composer
- MariaDB (getestet mit 11.8)
- für die Entwicklung **optional Docker:** `compose.yaml` startet MariaDB, Mailpit (fängt Systemmails ab) und GreenMail (Test-Postfach) mit passenden Einstellungen – praktisch, aber nicht nötig

Node wird **nicht** benötigt: Tailwind und esbuild laufen als Standalone-Binaries.

## Lokale Entwicklung

```bash
composer install
docker compose up -d                       # MariaDB (3307), Mailpit (http://localhost:8025), GreenMail (IMAP 3143 / SMTP 3025)
php bin/console doctrine:migrations:migrate -n
php bin/console app:demo --mails           # Demodaten: demo@rokoso.test / demo-passwort, Postfach mit Beispiel-Mails
php bin/console tailwind:build --watch     # in eigenem Terminal
php bin/console app:js:build --watch       # in eigenem Terminal (JavaScript-Bündel)
php -S 127.0.0.1:8000 -t public public/index.php   # oder: symfony serve
```

Systemmails (Bestätigung, Passwort, Einladungen) landen in **Mailpit**: http://localhost:8025.
Das Demo-Postfach `sev@rokoso.test` liegt in **GreenMail**; Mails dorthin (SMTP `127.0.0.1:3025`) erscheinen nach „Neue E-Mails abrufen“ bzw. `php bin/console app:mail:sync`.

Eigene Einstellungen (z. B. `APP_SECRET`) gehören in `.env.local`.

### Ohne Docker

Statt `docker compose up -d` eine eigene MariaDB nutzen (z. B. aus XAMPP) und in `.env.local` eintragen:

```dotenv
DATABASE_URL="mysql://benutzer:passwort@127.0.0.1:3306/rokoso?serverVersion=11.8.0-MariaDB&charset=utf8mb4"
MAILER_DSN=null://null   # Systemmails verwerfen – oder ein echtes SMTP-Postfach
```

Die Datenbank legt `php bin/console doctrine:database:create` an; für `composer check` braucht der Benutzer auch Rechte auf `rokoso_test`. Demodaten dann ohne Beispiel-Postfach: `php bin/console app:demo` (ohne `--mails`, das braucht GreenMail; das Demo-Postfach meldet dann Abrufprobleme).

## Qualität

```bash
composer check   # Codestil, PHPStan (Level 8), PHPUnit
composer fix     # Codestil automatisch korrigieren
```

Die Tests legen die Datenbank `rokoso_test` selbst an (Rechte: `docker/mariadb/test-db.sql`).

## Dokumentation

- [`App.md`](App.md) – Ideensammlung / Anforderungen
- [`docs/PLAN.md`](docs/PLAN.md) – Phasen und Fortschritt
- [`docs/ENTSCHEIDUNGEN.md`](docs/ENTSCHEIDUNGEN.md) – Architektur- und Produktentscheidungen
- [`docs/OFFENE-FRAGEN.md`](docs/OFFENE-FRAGEN.md) – noch zu klären
- [`docs/BETRIEB.md`](docs/BETRIEB.md) – Installation, Cron, Updates, Datensicherung
