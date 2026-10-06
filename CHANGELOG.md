# Neuigkeiten in Rokoso

Was sich von Version zu Version ändert – in der App unter Profil → „Neuigkeiten“.
Neue Version: oben einen Abschnitt `## <Version> – <JJJJ-MM-TT>` ergänzen (Nummerierung nach [SemVer](https://semver.org/lang/de/)).
Der oberste Abschnitt ist die aktuelle Version; der Text richtet sich an die Nutzerinnen und Nutzer.

## 0.14.5 – 2026-10-06

### Verbessert

- **Anhänge:** stehen jetzt kompakt im Kopf der E-Mail (unter Absender und Empfängern) statt erst unter dem Text.

## 0.14.4 – 2026-10-06

### Behoben

- **App installieren:** Rokoso lässt sich wieder als App auf dem Handy installieren (Chrome: „Zum Startbildschirm hinzufügen“ bzw. „App installieren“). Die App-Symbole waren auf dem Server nicht erreichbar; dadurch fehlte auch das Logo in den E-Mails von Rokoso.

## 0.14.3 – 2026-10-06

### Behoben

- **Umlaute in der HTML-Ansicht:** Viele E-Mails (z. B. aus Outlook) zeigten in der HTML-Ansicht „Ã¼“ statt „ü“, obwohl die Textansicht stimmte. Das ist behoben – auch für bereits abgerufene E-Mails.

## 0.14.2 – 2026-10-06

### Behoben

- **Umlaute in E-Mails:** E-Mails, deren Absender den Zeichensatz nicht angibt, zeigten Umlaute als „Ã¼“ o. Ä. Solche Nachrichten werden beim Abruf jetzt richtig gelesen.

## 0.14.1 – 2026-10-06

### Verbessert

- **Menü „Verfassen“:** Wieder kompakt – nur noch die Namen der Einträge. Die Erklärungen stehen jetzt ausführlicher oben auf der jeweiligen Seite zum Anlegen: wofür eine E-Mail, ein Forenthema, ein Termin, eine Aufgabe, eine Sitzung, eine Abstimmung oder eine Umfrage gedacht ist und was danach passiert.

## 0.14.0 – 2026-10-06

### Neu

- **Hilfe:** Unter „Verwaltung → Hilfe“ erklärt Rokoso jetzt jeden Bereich kurz und gibt Schritt-für-Schritt-Anleitungen für typische Abläufe – z. B. jemanden einladen, ein E-Mail-Konto verbinden, ein Rundschreiben verschicken, einen Umlaufbeschluss fassen, einen Termin finden, eine Sitzung vorbereiten oder eine Umfrage durchführen. Das Fragezeichen oben in jedem Bereich führt direkt zum passenden Artikel; die Hilfe ist auch über die Suche und die Befehlspalette (Strg+K) zu finden.
- **Erste Schritte für Mitglieder:** Wer einer Organisation beitritt, sieht auf „Heute“ jetzt eigene erste Schritte: Profilbild hochladen, Kalender abonnieren und die Zwei-Faktor-Anmeldung einrichten.

### Verbessert

- **Menü „Verfassen“:** Jeder Eintrag sagt in einer Zeile, wofür er gedacht ist. Sitzung, Abstimmung und Umfrage lassen sich jetzt ebenfalls direkt dort anlegen.
- **Einleitungen:** E-Mails, Abstimmungen und Umfragen erklären auf ihrer Übersichtsseite, wofür sie da sind – und worin sich Abstimmungen (für Mitglieder) und Umfragen (für Außenstehende) unterscheiden.
- **Einheitliche Begriffe:** Überall heißt es jetzt „Organisation“ (statt teils „Gremium“), „E-Mail-Konto“ (statt „Postfach“), „Mitglied“ (statt „Nutzer“) und „Handbuch“ (statt „Wiki“ oder „Wissen“).

### Behoben

- **Verwaltung:** Der Einleitungstext der Verwaltungsseite fehlte.

## 0.13.3 – 2026-10-05

### Verbessert

- **Neue Umfrage:** Beim Anlegen steht jetzt dabei, dass Fragen und Antwortmöglichkeiten im nächsten Schritt folgen; der Knopf heißt „Weiter zu den Fragen“.

## 0.13.2 – 2026-10-05

### Behoben

- **Dunkelmodus:** Der Hinweis mit den Ersatzcodes der Zwei-Faktor-Anmeldung (und der beim Löschen des Kontos) war kaum lesbar. Auch das Abzeichen „Erledigt“ bei Aufgaben, „Aktiv“ bei der Zwei-Faktor-Anmeldung und einige grüne und blaue Texte haben jetzt genug Kontrast.

## 0.13.1 – 2026-10-05

### Verbessert

- **Einladungslink kopieren:** Bei den offenen Einladungen einer Organisation gibt es jetzt „Link kopieren“ – praktisch, um eine Einladung z. B. per Messenger weiterzugeben. Kann die Einladungs-E-Mail nicht verschickt werden, bleibt die Einladung trotzdem bestehen und du bekommst einen Hinweis statt einer Fehlerseite.

## 0.13.0 – 2026-10-05

### Neu

- **Einladungen für bestehende Konten:** Wer schon ein Konto hat, findet eine Einladung in eine Organisation jetzt auch unter den Benachrichtigungen (Glocke) und kann sie dort mit einem Klick annehmen. Die E-Mail sagt dazu, dass man sich einfach anmelden kann. Wird die Einladung zurückgezogen, verschwindet auch die Benachrichtigung.

### Verbessert

- **Einladungen gelten nur für die eingeladene Adresse:** Ein weitergeleiteter Link lässt sich mit einem anderen Konto nicht mehr annehmen. Die Einladungsseite zeigt, für welche Adresse sie gilt, und bietet bei falschem Konto das Abmelden an.

## 0.12.1 – 2026-10-05

### Verbessert

- **Wiederholung im Abstand:** statt „Intervall“ steht jetzt „Wiederholen alle [2] Wochen“ – die Einheit passt sich an, und das Feld erscheint nur bei wiederholten Terminen. Beim Termin und in Einladungen steht entsprechend z. B. „Alle 2 Wochen am Donnerstag“.

## 0.12.0 – 2026-10-05

### Neu

- **Termine wie „jeden 2. Donnerstag im Monat“:** bei „Wiederholen“ gibt es jetzt „Monatlich am selben Wochentag“ und „Monatlich am letzten Wochentag“. Die Auswahl richtet sich nach dem Beginn – trag den ersten Termin ein, dann steht dort z. B. „Monatlich am 2. Donnerstag“. Das klappt auch im Kalender-Abo auf dem Handy und in Einladungen.

### Behoben

- Bei langen Listen ließ sich die Seite über ihr Ende hinaus in einen leeren Bereich scrollen.
- Der Hinweis „Neue Nachrichten sind eingegangen“ war im Dunkelmodus kaum lesbar.

## 0.11.0 – 2026-10-05

### Neu

- **Vertraulicher Kontakt:** über die öffentliche Seite können Eltern und andere euch anonym etwas mitteilen. Sie bekommen einen Zugangscode, mit dem sie eure Antworten lesen und weiterschreiben – ganz ohne Konto. Lesen und antworten können nur Mitglieder, die in der Mitgliederverwaltung als **Vertrauensperson** markiert sind; die Inhalte werden verschlüsselt gespeichert und nach einer einstellbaren Frist gelöscht. Einschalten unter Verwaltung → Öffentliche Seite.

### Verbessert

- **Verteiler-Abo:** der Link „Verteiler abonnieren“ erscheint auf der öffentlichen Seite nur noch, wenn mindestens ein Verteiler öffentlich abonnierbar ist – vorher führte er sonst auf eine leere Seite. In den Einstellungen siehst du, wie viele Verteiler das gerade sind.

## 0.10.0 – 2026-10-05

### Neu

- **Spaltenbreite anpassen:** die Liste und die Kommentarspalte lassen sich am Rand mit der Maus (oder per Tab und Pfeiltasten) breiter oder schmaler ziehen. Die Breite merkt sich dein Gerät, ein Doppelklick auf den Rand stellt sie zurück.

## 0.9.0 – 2026-10-05

Die erste Testversion von Rokoso.

### Neu

- **Heute:** die Startseite zeigt auf einen Blick, was ansteht – Nachrichten, Aufgaben, Termine, Abstimmungen und neue Forenbeiträge.
- **Postfach:** gemeinsame E-Mail-Postfächer der Organisation mit Zuständigkeit, Status, Wiedervorlage und Regeln; Senden lässt sich kurz rückgängig machen.
- **Kontakte und Verteiler:** Rundschreiben gehen einzeln adressiert an alle Empfänger eines Verteilers.
- **Forum, Kalender und Aufgaben:** Themen mit @-Erwähnungen, Termine mit Wiederholung und Einladungen, Aufgaben als Liste oder Board.
- **Sitzungen:** Tagesordnung, Einladung, Anwesenheit, Protokoll und Beschlussliste.
- **Abstimmungen:** offen oder geheim, auch als Umlaufbeschluss oder Terminfindung.
- **Dateien und Handbuch:** Ablage mit Versionen und Freigabelinks, Handbuch für das Wissen des Gremiums.
- **Öffentliche Seite:** Kontaktformular, Umfragen, Anmeldungen und Verteiler-Abo für Eltern.
- **Sicherheit:** Zwei-Faktor-Anmeldung, Datenexport und Konto löschen im Profil.
- **Spam:** vom Mailserver gekennzeichnete E-Mails und E-Mails gesperrter Absender landen im Ordner „Spam“ statt im Eingang – mit „Kein Spam“ holst du sie zurück.
- **Neuigkeiten:** nach einem Update steht auf „Heute“, was sich geändert hat.
