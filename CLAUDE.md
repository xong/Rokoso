# Projekt: Koopio – Kollaborationssoftware Stadtelternvertretung

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
- Vor Abschluss eines Schritts: `composer check` (CS-Fixer, PHPStan Level 8, PHPUnit); stürzt PHPStan unter Windows ab, vorher `cache:clear` + `cache:warmup`

## Layout
- Seiten mit 3-Spalten-Ansicht erweitern `templates/layout/app.html.twig` (Blöcke `list_title`, `list`, `detail_title`, `detail`; `has_detail` + `back_url` für Mobil).
- Dunkelmodus über umgedrehte Farbvariablen (`assets/styles/app.css`): Flächen mit `bg-surface` statt `bg-white`; `bg-white` nur, wo es immer weiß bleiben soll.
- Menüpunkte in `src/Twig/Components/Sidebar.php` (`GROUPS`, aktiv = längstes `match`-Präfix; Neues anlegen über `COMPOSE` = Menü „Verfassen“). Tailwind-Varianten `collapsed:` und `nav-open:` hängen am `shell`-Stimulus-Controller. Flyouts der eingeklappten Navigation sind `fixed` (Liste bleibt scrollbar) und werden von `nav_flyout_controller.js` positioniert.

## Architektur-Notizen
- E-Mails und interne Nachrichten: eine Entity `Message` (Typ email/internal). Sichtbarkeit zentral in `MessageRepository::visibleQuery()`, Voter `MessageVoter` nutzt sie.
- IMAP: `MailboxReader` (Interface) → `ImapMailboxReader` (webklex, read-only); in Tests `tests/Fake/FakeMailboxReader`. Parser: `MessageParser` (zbateson). Abruf: `MailSynchronizer`, Befehl `app:mail:sync`.
- Versand: `SmtpTransportFactory` (in Tests `MAIL_ACCOUNT_TRANSPORT_OVERRIDE=null://null`), `MailSender` (verschickt einen `Draft`). Senden rückgängig: `Outbox` mit `MAIL_SEND_DELAY` (Tests: 0 = sofort), Versand nach der Antwort (`OutboxListener`) bzw. Cron `app:mail:outbox`. Gesendet-Kopie per `SentFolderWriter` (Tests: `FakeSentFolderWriter`).
- Kontakte: Verteiler `ContactGroup` (pro Organisation); Rundschreiben = `ComposeData::$circular` → `MailSender` schickt je „An“-Adresse eine E-Mail; vCard über `Service\VCard` (eigener Code). In Tests zählt `assertEmailCount` auch Konto-Mails (Override-Transport mit Dispatcher).
- Kalender: `CalendarService` expandiert Wiederholungen (rlanvin/php-rrule) zu `Occurrence`s. Einzeltermine einer Serie: `CalendarException` je ursprünglichem Tag, Links mit `date: o.day` (nicht `o.start`). .ics immer über `App\Calendar\Ics`; Abo-Feed und Gäste-Einladung in `CalendarInvitation`.
- Zugriff: `Organization::getMembership()` liefert nur Vollmitglieder (kein Gast, Amtszeit läuft), `findMembership()` jede. In Queries `Membership::fullDql()` bzw. `guestDql()` für Gäste mit freigegebenen Projekten; Voter nutzen `canSeeProject()`/`isVisibleTo()`.
- Öffentliche Seiten: `PublicController` unter `/p/{slug}` (Layout `templates/public/_layout.html.twig`, eigene Formulare in `src/Form/PublicForm`). Spamschutz `PublicGuard::addFields()`/`check()`, Verarbeitung und Bestätigungen `PublicSubmissionHandler`; in Tests Feld `started` per `PublicGuard::stamp(time() - 30)` setzen.
- Turbo-Morphing global (`base.html.twig`): nach POST einfach auf dieselbe Seite umleiten, keine eigenen Streams nötig. Wischgesten: `swipe`-Controller mit Formular-Targets `left`/`right`.
- Direktnachrichten nur an Personen; Altbestand an Organisationen per `app:messages:convert-group` → Forum (`GroupMessageConverter`). „Erste Schritte“: `Service\SetupChecklist`.
- Passwortfelder: Formular-Theme `password_widget` bringt „Passwort anzeigen“ mit (`password-reveal`-Controller); handgeschriebene Felder nutzen `_partials/password_toggle.html.twig`.
- Twig-Makros für Buttons/Listen (`ui.empty(text, icon, cta_url, cta_label)` für Leer-Zustände mit Knopf): `templates/_partials/ui.html.twig` (in jedem Kind-Template importieren).
- Dateien: `StoredFile::replaceWith()` legt eine `FileVersion` an (Löschen über `getAllStoragePaths()`); Freigabelinks `FileShare` unter `/s/{token}` (öffentlich); „Merken“ = `ShelfItem::for()` (Datei, Anhang, Nachricht, Forenthema), Zugriff in `Shelf::isAccessible()`; PWA-Teilen-Ziel `ShareTargetController` mit Zwischenablage `FileStorage::storeIncoming()`.
- Startseite „Heute“: `HomeController`; globale Suche: `App\Search\GlobalSearch` (nutzt die Repository-Sichtbarkeit – neue Bereiche dort ergänzen); Befehlspalette/Kürzel: `templates/_partials/palette.html.twig` + `palette_controller.js`; Live-Badges: `[data-live-count]` + `CountsController` + `live_counts_controller.js`.
- Sicherheit: `SecurityLog::record()` für sicherheitsrelevante Ereignisse (Typen in `security_log.type.*` übersetzen); `User::renewSessionStamp()` beendet alle Sitzungen; Konto löschen über `AccountDeleter` (neue personenbezogene Tabellen dort ergänzen, ebenso in `AccountExport`); Löschfristen in `RetentionCleaner` (läuft in `app:notify`). Plattform-Admin (`ROLE_PLATFORM_ADMIN`, `app:user:promote`) unter `/admin` (`PlatformAdminController`); Menüpunkte mit `role` werden nur bei passendem Recht gezeigt.
- Abruf beim Öffnen: `SyncOnOpenListener` (Routen-Liste) → `SyncOnOpen` (Drossel `MAIL_SYNC_INTERVAL`, in Tests 0 = aus); Abrufprobleme über `MailAccount::getSyncProblem()`.
- Browser-Tests: `tests/Browser` (Panther, `composer test:browser`, eigene `phpunit.browser.xml` ohne DAMA – Daten werden geschrieben, daher eindeutige E-Mails); `AccessibilityTest` prüft mit axe (neue Hauptseiten in `PAGES` ergänzen). Nicht Teil von `composer check`.
- Docker: `Dockerfile` (FrankenPHP), `docker/entrypoint.sh` (Migrationen), `docker/cron.sh`, Beispiel `compose.prod.yaml`.
- Logo/Icons: nie von Hand ändern, sondern `python docs/logo/generate.py` (schreibt SVG, PNG, ICO und `templates/_partials/logo.html.twig`). Markenfarbe `brand-500` (#84c232) nur mit dunkler Schrift (`text-brand-950`), Text auf hell `brand-700`; Neutraltöne grau.
- Keine `sed`/`php -r`-Ersetzungen in PHP-Dateien – Edit-Werkzeug nutzen (Escaping-Fallen).

## Konventionen
- UI-Texte ausschließlich über Übersetzungen (`translations/messages.de.*`), Sprache Deutsch.
- Code, Bezeichner und Commit-Messages auf Englisch, Doku auf Deutsch.
- Berechtigungen über Symfony Voter.
