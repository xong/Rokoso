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
