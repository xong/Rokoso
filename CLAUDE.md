# Projekt: Coop – Kollaborationssoftware Stadtelternvertretung

## Arbeitsweise
- Vor jeder Sitzung `docs/PLAN.md` lesen (Abschnitt „Wo stehen wir?“). Nach jedem abgeschlossenen Schritt Checkboxen und Status dort aktualisieren.
- Neue Entscheidungen in `docs/ENTSCHEIDUNGEN.md` eintragen, Unklarheiten in `docs/OFFENE-FRAGEN.md`.
- `App.md` ist die Ideensammlung des Projektinhabers und wird nur nach Absprache geändert.
- Schrittweise vorgehen; bei Unklarheiten oder möglichen Vereinfachungen konkret nachfragen.

## Stack
- Symfony 8, PHP ≥ 8.4 (lokal 8.5), Doctrine ORM, Twig, Symfony UX (Turbo, Stimulus, Twig Components, UX Icons/Lucide)
- Tailwind 4 über `symfonycasts/tailwind-bundle` + AssetMapper (kein Node); `tailwind:init` ist interaktiv, Version steht in `config/packages/symfonycasts_tailwind.yaml`
- Lokal: MariaDB 11.8 + Mailpit + GreenMail per `docker compose up -d` (DB-Port 3307, Mailpit http://localhost:8025, IMAP 3143/SMTP 3025); Demodaten: `php bin/console app:demo --mails`
- Nur freie Bibliotheken, Lizenz MIT
- Vor Abschluss eines Schritts: `composer check` (CS-Fixer, PHPStan Level 8, PHPUnit)

## Layout
- Seiten mit 3-Spalten-Ansicht erweitern `templates/layout/app.html.twig` (Blöcke `list_title`, `list`, `detail_title`, `detail`; `has_detail` + `back_url` für Mobil).
- Menüpunkte in `src/Twig/Components/Sidebar.php`. Tailwind-Varianten `collapsed:` und `nav-open:` hängen am `shell`-Stimulus-Controller.

## Architektur-Notizen
- E-Mails und interne Nachrichten: eine Entity `Message` (Typ email/internal). Sichtbarkeit zentral in `MessageRepository::visibleQuery()`, Voter `MessageVoter` nutzt sie.
- IMAP: `MailboxReader` (Interface) → `ImapMailboxReader` (webklex, read-only); in Tests `tests/Fake/FakeMailboxReader`. Parser: `MessageParser` (zbateson). Abruf: `MailSynchronizer`, Befehl `app:mail:sync`.
- Versand: `SmtpTransportFactory` (in Tests `MAIL_ACCOUNT_TRANSPORT_OVERRIDE=null://null`), `MailSender`.
- Kalender: `CalendarService` expandiert Wiederholungen (rlanvin/php-rrule) zu `Occurrence`s.
- Twig-Makros für Buttons/Listen: `templates/_partials/ui.html.twig` (in jedem Kind-Template importieren).
- Keine `sed`/`php -r`-Ersetzungen in PHP-Dateien – Edit-Werkzeug nutzen (Escaping-Fallen).

## Konventionen
- UI-Texte ausschließlich über Übersetzungen (`translations/messages.de.*`), Sprache Deutsch.
- Code, Bezeichner und Commit-Messages auf Englisch, Doku auf Deutsch.
- Berechtigungen über Symfony Voter.
