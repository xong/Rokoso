# Projekt: Rokoso – Kollaborationssoftware Stadtelternvertretung

## Arbeitsweise
- Vor jeder Sitzung `docs/PLAN.md` lesen (Abschnitt „Wo stehen wir?“). Nach jedem abgeschlossenen Schritt Checkboxen und Status dort aktualisieren.
- Neue Entscheidungen in `docs/ENTSCHEIDUNGEN.md` eintragen, Unklarheiten in `docs/OFFENE-FRAGEN.md`.
- `App.md` ist die Ideensammlung des Projektinhabers und wird nur nach Absprache geändert.
- Schrittweise vorgehen; bei Unklarheiten oder möglichen Vereinfachungen konkret nachfragen.

## Stack
- Symfony 8, PHP ≥ 8.4 (lokal 8.5), Doctrine ORM, Twig, Symfony UX (Turbo, Stimulus, Twig Components, UX Icons/Lucide)
- Tailwind 4 über `symfonycasts/tailwind-bundle` + AssetMapper (kein Node); `tailwind:init` ist interaktiv, Version steht in `config/packages/symfonycasts_tailwind.yaml`
- Lokal: MariaDB 11.8 + Mailpit + GreenMail per `docker compose up -d` (DB-Port 3307, Mailpit http://localhost:8025, IMAP 3143/SMTP 3025); Demodaten: `php bin/console app:demo --mails`
- JavaScript: ein Bündel per esbuild-Binary (kein Node): `php bin/console app:js:build [--watch|--minify]` → `var/js/build/app.js`, ausgeliefert als `asset('build/app.js')` mit Hash. Neue Stimulus-Controller brauchen einen Neubau (bzw. Neustart von `--watch`); `importmap.php` ist nur noch die Liste der Fremdpakete (`importmap:require`/`importmap:install`). Dateien mit festem Pfad in `public/` über `public_file('/…')` (hängt `?v=<Hash>` an)
- Nur freie Bibliotheken, Lizenz MIT
- Vor Abschluss eines Schritts: `composer check` (CS-Fixer, PHPStan Level 8, PHPUnit); stürzt PHPStan unter Windows ab, vorher `cache:clear` + `cache:warmup`

## Layout
- Seiten mit 3-Spalten-Ansicht erweitern `templates/layout/app.html.twig` (Blöcke `list_title`, `list`, `detail_title`, `detail`; `has_detail` + `back_url` für Mobil).
- Dunkelmodus über umgedrehte Farbvariablen (`assets/styles/app.css`): Flächen mit `bg-surface` statt `bg-white`; `bg-white` nur, wo es immer weiß bleiben soll.
- Menüpunkte in `src/Twig/Components/Sidebar.php` (`GROUPS`, aktiv = längstes `match`-Präfix, auch bei Unterpunkten; Neues anlegen über `COMPOSE` = Menü „Verfassen“). Tailwind-Varianten `collapsed:` und `nav-open:` hängen am `shell`-Stimulus-Controller. Flyouts der eingeklappten Navigation sind `fixed` (Liste bleibt scrollbar) und werden von `nav_flyout_controller.js` positioniert.

## Architektur-Notizen
- E-Mails und interne Nachrichten: eine Entity `Message` (Typ email/internal). Sichtbarkeit zentral in `MessageRepository::visibleQuery()`, Voter `MessageVoter` nutzt sie.
- IMAP: `MailboxReader` (Interface) → `ImapMailboxReader` (webklex, read-only); in Tests `tests/Fake/FakeMailboxReader`. Parser: `MessageParser` (zbateson). Abruf: `MailSynchronizer`, Befehl `app:mail:sync`.
- Versand: `SmtpTransportFactory` (in Tests `MAIL_ACCOUNT_TRANSPORT_OVERRIDE=null://null`), `MailSender` (verschickt einen `Draft`). Senden rückgängig: `Outbox` mit `MAIL_SEND_DELAY` (Tests: 0 = sofort), Versand nach der Antwort (`OutboxListener`) bzw. Cron `app:mail:outbox`. Gesendet-Kopie per `SentFolderWriter` (Tests: `FakeSentFolderWriter`).
- Kontakte: Verteiler `ContactGroup` (pro Organisation); Rundschreiben = `ComposeData::$circular` → `MailSender` schickt je „An“-Adresse eine E-Mail; vCard über `Service\VCard` (eigener Code). In Tests zählt `assertEmailCount` auch Konto-Mails (Override-Transport mit Dispatcher).
- Kalender: `CalendarService` expandiert Wiederholungen (rlanvin/php-rrule) zu `Occurrence`s. Einzeltermine einer Serie: `CalendarException` je ursprünglichem Tag, Links mit `date: o.day` (nicht `o.start`). .ics immer über `App\Calendar\Ics`; Abo-Feed und Gäste-Einladung in `CalendarInvitation`.
- Zugriff: `Organization::getMembership()` liefert nur Vollmitglieder (kein Gast, Amtszeit läuft), `findMembership()` jede. In Queries `Membership::fullDql()` bzw. `guestDql()` für Gäste mit freigegebenen Projekten; Voter nutzen `canSeeProject()`/`isVisibleTo()`.
- Öffentliche Seiten: `PublicController` unter `/p/{slug}` (Layout `templates/public/_layout.html.twig`, eigene Formulare in `src/Form/PublicForm`; Organisationsfarbe über `.org-theme` + `--org-color`, das die `brand-*`-Variablen umfärbt). Spamschutz `PublicGuard::addFields()`/`check()`, Verarbeitung und Bestätigungen `PublicSubmissionHandler`; in Tests Feld `started` per `PublicGuard::stamp(time() - 30)` setzen.
- Turbo-Morphing global (`base.html.twig`): nach POST einfach auf dieselbe Seite umleiten, keine eigenen Streams nötig. Wischgesten: `swipe`-Controller mit Formular-Targets `left`/`right`.
- Direktnachrichten nur an Personen; Altbestand an Organisationen per `app:messages:convert-group` → Forum (`GroupMessageConverter`). „Erste Schritte“: `Service\SetupChecklist`.
- Passwortfelder: Formular-Theme `password_widget` bringt „Passwort anzeigen“ mit (`password-reveal`-Controller); handgeschriebene Felder nutzen `_partials/password_toggle.html.twig`.
- Verschiebbare Spalten: `panel_resize`-Controller + `_partials/panel_handle.html.twig`, Grenzen und Cookie `panel_<name>` in `PanelWidthExtension` (`panel_width()`), Breite über `--panel-width`.
- Twig-Makros für Buttons/Listen (`ui.empty(text, icon, cta_url, cta_label)` für Leer-Zustände mit Knopf): `templates/_partials/ui.html.twig` (in jedem Kind-Template importieren).
- Dateien: `StoredFile::replaceWith()` legt eine `FileVersion` an (Löschen über `getAllStoragePaths()`); Freigabelinks `FileShare` unter `/s/{token}` (öffentlich); „Merken“ = `ShelfItem::for()` (Datei, Anhang, Nachricht, Forenthema), Zugriff in `Shelf::isAccessible()`; PWA-Teilen-Ziel `ShareTargetController` mit Zwischenablage `FileStorage::storeIncoming()`.
- Startseite „Heute“: `HomeController`; globale Suche: `App\Search\GlobalSearch` (nutzt die Repository-Sichtbarkeit – neue Bereiche dort ergänzen); Befehlspalette/Kürzel: `templates/_partials/palette.html.twig` + `palette_controller.js`; Live-Badges: `[data-live-count]` + `CountsController` + `live_counts_controller.js`.
- Hilfe: Markdown-Artikel in `help/NN-slug.md` (Front Matter `routes` = Routen-Präfixe, optional `feature`, `group: guide`; `# Titel`, erster Absatz = Kurzfassung, Links als `/help/<slug>`), gelesen von `App\Help\HelpCenter`, Seiten `/help`; „?“-Link im Layout über `help_article()`, auch in Suche und Palette. Neuer Bereich oder geänderter Ablauf = Hilfeartikel ergänzen (`HelpCenterTest` prüft Routen und Links). „Erste Schritte“ (`SetupChecklist`) für Admins/ohne Organisation bzw. für Mitglieder.
- Sicherheit: `SecurityLog::record()` für sicherheitsrelevante Ereignisse (Typen in `security_log.type.*` übersetzen); `User::renewSessionStamp()` beendet alle Sitzungen; Konto löschen über `AccountDeleter` (neue personenbezogene Tabellen dort ergänzen, ebenso in `AccountExport`); Löschfristen in `RetentionCleaner` (läuft in `app:notify`). Plattform-Admin (`ROLE_PLATFORM_ADMIN`, `app:user:promote`) unter `/admin` (`PlatformAdminController`); Menüpunkte mit `role` werden nur bei passendem Recht gezeigt.
- Bereiche: Enum `Feature` (Routen-Präfixe in `routePrefixes()`), pro Organisation `hasFeature()`; `Features::isAvailable()` (Menü/Palette) und `Features::dql()` in den `visibleQuery`-Methoden der Repositories, dazu eine Prüfung im jeweiligen Voter (affirmative Strategie). `FeatureListener` gibt 404 für Routen ohne nutzende Organisation und für Controller-Argumente einer Organisation ohne den Bereich. Twig: `has_feature()`, `route_available()`, `org.hasFeature()`; Auswahl beim Anlegen über `OrganizationRepository::findForUser($user, Feature::X)`; öffentliche Seite über `PublicSettings::offers()`. Neuer Bereich = Enum-Fall + Präfixe + Übersetzung `feature.*`.
- Abruf beim Öffnen: `SyncOnOpenListener` (Routen-Liste) → `SyncOnOpen` (Drossel `MAIL_SYNC_INTERVAL`, in Tests 0 = aus); Abrufprobleme über `MailAccount::getSyncProblem()`.
- Browser-Tests: `tests/Browser` (Panther, `composer test:browser`, eigene `phpunit.browser.xml` ohne DAMA – Daten werden geschrieben, daher eindeutige E-Mails); `AccessibilityTest` prüft mit axe (neue Hauptseiten in `PAGES` ergänzen). Nicht Teil von `composer check`.
- Vertraulicher Kontakt: `App\Confidential\ConfidentialInbox` (öffnen, antworten, entschlüsseln) + `ConfidentialCrypto` (Schlüssel pro Gespräch, aus `APP_SECRET` abgeleitet – Wechsel macht Gespräche unlesbar; Code nur als HMAC). Zugriff nur Vertrauenspersonen (`Membership::$confidant`, `ConfidentialCaseVoter`, `ConfidentialCaseRepository::visibleQuery()`), bewusst nicht in Suche/Export. Öffentlich `PublicController::confidential*` (Code nie in URLs, `confidentialHeaders()`).
- Spam: `Message::$spamAt` (Header `X-Spam-Flag`/`X-Spam-Status` in `MessageParser`, gesperrte Absender `BlockedSender` in `MailSynchronizer`); Ordner `spam` in `MessageRepository::findForList()`, alle anderen Abfragen schließen Spam aus (neue Nachrichten-Abfragen ebenso). Aktionen `spam`/`not_spam`/`block` in `MessageActions`.
- Versionen: `CHANGELOG.md` (oberster Abschnitt = laufende Version, Text für Nutzer:innen auf Deutsch) über `Service\Changelog`; vor einem Deployment mit sichtbaren Änderungen dort einen Abschnitt ergänzen. Seite `/profile/changelog`, Hinweis auf „Heute“ bis gelesen (`User::$seenVersion`).
- Deployment: `.github/workflows/deploy.yml` (Push auf `main` → Staging bei GN2, Release-Ordner + `shared/`), Migrationen über den Deploy-Hook `DeployController` (`/_deploy`, `DEPLOY_TOKEN`), weil per SSH nur PHP 8.2 läuft; ebenso Cron per URL `/_cron/<CRON_TOKEN>` (`CronController` → `CronRunner`, Abstände in `CronRunner::TASKS` – neue Cron-Befehle dort und in `docker/cron.sh` ergänzen); Ablauf in `docs/BETRIEB.md`. Ohne Actions: `bin/deploy` (Git Bash, `.env.deploy.local`), gleiche Schritte per tar über SSH. Persistente Pfade neu? Dann im Workflow und in `bin/deploy` verlinken.
- Docker: `Dockerfile` (FrankenPHP), `docker/entrypoint.sh` (Migrationen), `docker/cron.sh`, Beispiel `compose.prod.yaml`.
- UX Icons nur aus `assets/icons` (Iconify-Download nur in dev, legt die Datei dort ab – mit committen; `IconsTest` prüft das, der Webhoster kann zur Laufzeit nichts laden).
- Logo/Icons: nie von Hand ändern, sondern `python docs/logo/generate.py` (schreibt SVG, PNG, ICO und `templates/_partials/logo.html.twig`; App-Icons in `public/app-icons`, nie `/icons/` – Apache-Alias). Markenfarbe `brand-600` (#417230, Moosgrün) für Flächen mit weißer Schrift, Text auf hell `brand-700`; Neutraltöne grau.
- Doctrine: Sortierrichtung als `\SortDirection::Ascending`/`Descending` (QueryBuilder und `#[ORM\OrderBy]`), Joins auf Entity-Klassen mit `ON` statt `WITH` – sonst Deprecations im Log.
- Keine `sed`/`php -r`-Ersetzungen in PHP-Dateien – Edit-Werkzeug nutzen (Escaping-Fallen).

## Konventionen
- UI-Texte ausschließlich über Übersetzungen (`translations/messages.de.*`), Sprache Deutsch.
- Code, Bezeichner und Commit-Messages auf Englisch, Doku auf Deutsch.
- Berechtigungen über Symfony Voter.
