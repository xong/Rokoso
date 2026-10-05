---
group: guide
feature: mail
---
# Gemeinsames E-Mail-Konto verbinden

Damit E-Mails an eure Adresse (z. B. info@…) im gemeinsamen Eingang landen, verbindet ein Administrator das Postfach mit Rokoso. Das Postfach selbst bleibt bei eurem E-Mail-Anbieter; Rokoso ruft es regelmäßig ab und verschickt darüber.

## Das brauchst du

Die Zugangsdaten für **IMAP** (Empfang) und **SMTP** (Versand): Server, Port, Benutzername und Passwort. Sie stehen in der Hilfe eures Anbieters, meist unter „E-Mail-Programm einrichten“.

## So geht's

1. **Verwaltung → E-Mail-Konten → E-Mail-Konto hinzufügen**.
2. **Allgemein**: Bezeichnung (z. B. „Postfach SEV“), E-Mail-Adresse und Absendername.
3. **Empfang (IMAP)**: Server, Port, Verschlüsselung, Benutzername und Passwort. Unter **Erster Abruf** legst du fest, wie viele Tage zurück vorhandene E-Mails übernommen werden.
4. **Versand (SMTP)**: Server, Port und Verschlüsselung. Benutzername und Passwort kannst du leer lassen, wenn sie dieselben wie bei IMAP sind.
5. Optional: **Ordner für gesendete E-Mails** (z. B. „Sent“ oder „Gesendet“), damit Antworten auch im Postfach beim Anbieter landen.
6. Speichern und **Verbindung testen**. Klappt beides, holt **Jetzt abrufen** die ersten E-Mails.

Rokoso liest das Postfach nur und löscht dort nichts. Entfernst du das Konto in Rokoso, bleibt beim Anbieter alles erhalten.

**Abruf gestört?** Auf **Heute** und beim Konto steht dann ein Hinweis mit der Fehlermeldung. Häufige Ursache ist ein geändertes Passwort.
