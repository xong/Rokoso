# Entscheidungen

Kurzprotokoll getroffener Architektur- und Produktentscheidungen. Neueste unten.

| # | Datum | Entscheidung | Begründung |
|---|---|---|---|
| 1 | 2026-10-02 | **Symfony 8 auf PHP 8.4** | Aktuelle Major-Version, Wunsch des Projektinhabers. |
| 2 | 2026-10-02 | **Serverseitiges Rendering: Twig + Symfony UX (Turbo, Stimulus, Twig Components)**, kein SPA | Eine Codebasis, wenig JavaScript; das 3-Spalten-Layout wird mit Turbo Frames umgesetzt. |
| 3 | 2026-10-02 | **Mobil als PWA**, keine native App | Installierbar auf Android/iOS, Web-Push möglich. Eine native Hülle (Hotwire Native/Capacitor) bleibt später möglich. |
| 4 | 2026-10-02 | **Tailwind über symfonycasts/tailwind-bundle + AssetMapper** | Kein Node-Build nötig, nutzt die Tailwind-Standalone-Binary. |
| 5 | 2026-10-02 | **E-Mails werden per IMAP in die Datenbank synchronisiert** | Kommentare, Verantwortliche, Projekte, Filter und interne Nachrichten brauchen lokale Daten. Live-IMAP wäre langsam und komplex. |
| 6 | 2026-10-02 | **E-Mails und interne Nachrichten teilen sich ein Datenmodell** (`Message` mit Typ) | Anforderung 35: „genau wie E-Mails behandelt“. Dadurch gibt es Liste, Filter, Kommentare usw. nur einmal. |
| 7 | 2026-10-02 | **Hosting offen → portabel bleiben**: Doctrine (MariaDB/MySQL/PostgreSQL), Sync per Cron-Befehl statt Dauer-Worker | Läuft auch auf einfachem Hosting. |
| 8 | 2026-10-02 | **Lizenz MIT**; nur freie Bibliotheken | Projektanforderung. |
| 9 | 2026-10-02 | **UI nur Deutsch**, Texte dennoch in Übersetzungsdateien | Spätere Sprachen ohne Umbau möglich. |
| 10 | 2026-10-02 | **Lokale Dienste per Docker**: MariaDB 11.8 (Port 3307) + Mailpit | MariaDB 10.4 aus XAMPP ist veraltet; Mailpit fängt alle Systemmails ab. |
| 11 | 2026-10-02 | **Icons: Lucide** (ISC-Lizenz) über Symfony UX Icons, lokal in `assets/icons/` gespeichert | Frei, konsistent, keine Laufzeit-Abhängigkeit von externen Diensten. |
| 12 | 2026-10-02 | **Navigation als Twig-Component** `Sidebar` (`src/Twig/Components/Sidebar.php`) | Neue Menüpunkte an einer Stelle ergänzen. |
| 13 | 2026-10-02 | **Mobil: eine Spalte zur Zeit**; Liste oder Detail entscheidet der Server (`has_detail`), Navigation als Off-Canvas | Kein JS-Zustand nötig, funktioniert mit Turbo. |
| 14 | 2026-10-02 | **Name: „Coop“** (vorläufig) | Vorgabe des Projektinhabers. Ordner/Repo heißt noch `stev`. |
| 15 | 2026-10-02 | **Offene Selbstregistrierung mit E-Mail-Bestätigung**; jeder Benutzer darf Organisationen anlegen und wird deren Administrator | Vorgabe des Projektinhabers. Die Bestätigung verhindert Tippfehler und Fremdregistrierungen. |
| 16 | 2026-10-02 | **E-Mails der Org-Konten sehen alle Mitglieder**; Gelesen/Ungelesen wird **pro Benutzer** gespeichert | Antwort des Projektinhabers. |
| 17 | 2026-10-02 | **Papierkorb nur in Coop** (Mail bleibt auf dem IMAP-Server, wiederherstellbar) | Antwort des Projektinhabers; einfach und ohne Datenverlust. |
| 18 | 2026-10-02 | **Kontakte und Kalendereinträge gehören einer Organisation** (optional Projekt); ohne Organisation nur für den Ersteller sichtbar | Antwort des Projektinhabers. |
| 19 | 2026-10-02 | **E-Mails schreiben nur als Text** (Plaintext-Versand) | Antwort des Projektinhabers; Rich-Text später möglich. |
| 20 | 2026-10-02 | **Projekte einer Organisation** sehen alle Mitglieder, verwalten nur deren Administratoren; **Projekte ohne Organisation** sind persönlich (nur Ersteller) | Beantwortet die offene Frage aus Phase 3 mit der einfachsten sicheren Regel. |
| 21 | 2026-10-02 | **Passwörter der E-Mail-Konten mit libsodium verschlüsselt**, Schlüssel aus `APP_SECRET` abgeleitet | Kein Klartext in der DB; Achtung: `APP_SECRET` darf sich im Betrieb nicht ändern. |
| 22 | 2026-10-02 | **IMAP read-only (EXAMINE)**, Rohdaten mit webklex/php-imap, Zerlegung mit zbateson/mail-mime-parser (BSD-2) | Coop verändert nichts auf dem Server (kein „gelesen“-Flag); der Parser ist robust und ohne IMAP testbar. |
| 23 | 2026-10-02 | **Lokales Test-Postfach GreenMail** (`docker compose`, IMAP 3143, SMTP 3025, jeder Login wird akzeptiert) | E-Mail-Funktionen ohne echtes Postfach ausprobieren. |
| 24 | 2026-10-02 | **HTML-Mails** werden bereinigt (symfony/html-sanitizer) und im **Sandbox-iframe mit strenger CSP** angezeigt; **externe Bilder standardmäßig blockiert** („Bilder laden“ pro Mail) | Schutz vor Skripten, Tracking-Pixeln und Phishing-Formularen. |
| 25 | 2026-10-02 | **Abruf: nur der Posteingang** (Ordner einstellbar), beim ersten Abruf die letzten 90 Tage (einstellbar); danach ab der letzten UID. Abruf per Cron (`app:mail:sync`) oder Button | Einfach und schnell; gesendete Mails entstehen in Coop selbst. |
| 26 | 2026-10-02 | **Anhänge** liegen außerhalb des Web-Roots (`var/storage`) und werden immer als Download ausgeliefert | Keine fremden Inhalte unter der eigenen Domain. |
| 27 | 2026-10-02 | **Gesendete Mails** werden nur in Coop (Ausgang) abgelegt, nicht zusätzlich per IMAP in den „Gesendet“-Ordner des Servers kopiert | Einfacher; kann später ergänzt werden (siehe Ideen-Parkplatz). |
| 28 | 2026-10-02 | **Kontakte einer Organisation** dürfen alle Mitglieder sehen und bearbeiten; private Kontakte nur der Ersteller. vCard-Import/-Export noch nicht umgesetzt | Gemeinsames Adressbuch ohne Rechteverwaltung; vCard steht im Ideen-Parkplatz. |
| 29 | 2026-10-02 | **Kalendereinträge**: Sichtbarkeit wie Kontakte (Organisation bzw. persönlich), ein Projekt legt die Organisation fest. **Wiederholungen** einfach (täglich/wöchentlich/monatlich/jährlich, Intervall, Enddatum) über rlanvin/php-rrule; „erledigt“ und Löschen gelten für die ganze Serie | Deckt die üblichen Fälle ab; Ausnahmen einzelner Termine sind ein späterer Ausbau. |
| 30 | 2026-10-02 | **Kalender (48):** mittlere Spalte = kompakter Monatskalender mit KW und Punkten für Tage mit Einträgen, darunter „Demnächst“; Detail = Woche (Standard, Klick auf KW) oder Tag (Klick auf Tag). Gleich hohe Stunden-Slots 0–24 Uhr, Start-Scroll ca. 7 Uhr | Antwort des Projektinhabers. |
| 31 | 2026-10-02 | **Dateien (41, 42):** Ordner-Hierarchie; oberste Ordner gehören Pflicht einer Organisation (optional Projekt), Unterordner erben. Alle Mitglieder sehen und laden hoch; löschen dürfen Ersteller und Org-Admins. Dateien haben Kommentare und optional ein Projekt | Antwort des Projektinhabers. |
| 32 | 2026-10-02 | **Persönliche Ablage (44):** jede Person hat eine eigene Ablage mit Verweisen auf Dateien (aus „Dateien“ oder E-Mail-Anhängen, ohne Kopie); beim Schreiben einer E-Mail als Anhang wählbar | Antwort des Projektinhabers. |
| 33 | 2026-10-02 | **Forum (45):** Bereiche (hierarchisch) → Themen → Beiträge. Oberste Bereiche gehören Pflicht einer Organisation (optional Projekt), Themen können zusätzlich einem Projekt zugeordnet werden. Beiträge in **Markdown** (league/commonmark, ohne rohes HTML) mit Bildern und Dateianhängen | Antwort des Projektinhabers. |
| 34 | 2026-10-02 | **Projekt-Nachrichten (46, 47):** Nachrichten mit Projekt erscheinen nicht mehr im Eingang, sondern unter „Alle E-Mails“ und beim Projekt | Anforderung 46/47. |
| 35 | 2026-10-02 | **Kommentare** werden für Nachrichten, Projekte und Dateien in einer gemeinsamen Entity `Comment` mit jeweils einer optionalen Beziehung gespeichert | Eine Kommentar-Oberfläche für alle Bereiche. |
| 36 | 2026-10-02 | **Dateispeicher** `var/storage/files/JJJJ/MM/<zufällig>` außerhalb des Web-Roots, max. 50 MB pro Datei. Download standardmäßig als Anhang; nur PNG/JPEG/GIF/WebP/PDF dürfen inline (Vorschau) ausgeliefert werden, nie SVG | Keine fremden Skripte unter der eigenen Domain; `var/storage` muss ins Backup. |
