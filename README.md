# Rokoso – Kollaborationssoftware für Stadtelternvertretungen

Gemeinsames Postfach, interne Nachrichten, Projekte, Kalender und Kontakte für ehrenamtliche Gremien.
Open Source unter [MIT-Lizenz](LICENSE).

> Stand und Fahrplan: [`docs/PLAN.md`](docs/PLAN.md) · Betrieb: [`docs/BETRIEB.md`](docs/BETRIEB.md)

## Funktionen

- **Konten:** Registrierung mit E-Mail-Bestätigung, Anmeldung („angemeldet bleiben“), Passwort vergessen, Profil mit Bild
- **Organisationen:** Name, Logo, Farbe, Beschreibung; Rollen Administrator/Nutzer; Einladungen per E-Mail
- **E-Mail:** IMAP-Konten je Organisation, gemeinsamer Eingang mit Ausgang und Papierkorb, Gruppierung nach Datum,
  Filter (ohne Verantwortliche, mir zugeordnet, ungelesen, Projekt, Konto, Suche), Text-/HTML-Ansicht (sicher im Sandbox-iframe),
  Anhänge, Kommentare, Verantwortliche, Projektzuordnung, Hover-Funktionsleiste, Schreiben/Antworten/Weiterleiten per SMTP
- **Interne Nachrichten** an Personen, Organisationen oder Projekte – in derselben Liste wie E-Mails
- **Projekte** mit Farbe und Bild, optional einer Organisation zugeordnet
- **Kalender:** Monats-, Wochen- und Tagesansicht, Termine und Aufgaben, Verantwortliche/Teilnehmende, Wiederholungen
- **Kontakte** mit Foto; bekannte Absender erscheinen in der Nachrichtenliste mit Foto bzw. Initialen
- **Installierbar** als App (PWA) auf Smartphone und Desktop

## Voraussetzungen

- PHP ≥ 8.4 mit den Erweiterungen `curl, fileinfo, gd, intl, mbstring, openssl, pdo_mysql, sodium, zip`
- Composer
- Docker (für MariaDB, Mailpit und das Test-Postfach in der Entwicklung)

Node wird **nicht** benötigt: Tailwind läuft als Standalone-Binary, JavaScript über AssetMapper/Importmap.

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
