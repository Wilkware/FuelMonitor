# ⛽ Spritmonitor (Fuel Monitor)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg?style=flat-square)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-8.1-blue.svg?style=flat-square)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-2.0.20260730-orange.svg?style=flat-square)](https://github.com/Wilkware/FuelMonitor)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg?style=flat-square)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://img.shields.io/github/actions/workflow/status/wilkware/FuelMonitor/ci.yml?branch=main&label=CI&style=flat-square)](https://github.com/Wilkware/FuelMonitor/actions)

Der Spritmonitor berechnet den Spritverbrauch und Kosten von einem Fahrzeugs und hilft bei der Serviceverwaltung.

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Darstellungen](#user-content-5-statusvariablen-und-darstellungen)
6. [Visualisierung](#user-content-6-visualisierung)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

Der Spritmonitor ist eigentlich schon etwas mehr als ein reiner Verbrauchsmonitor:

- berechnet den Durchschnitsverbrauch und die dazugehörenden Kosten
- verwaltet die algemeinen Fahrzeugdaten
- verwaltet und informiert bei fälligen Service und TÜV-Terminen
- stellt alles zentral über TileVisu zur Verfügung

### 2. Voraussetzungen

* IP-Symcon ab Version 8.1

### 3. Installation

* Über den Modul Store das Modul _Sprintmonitor_ installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/FuelMonitor` oder `git://github.com/Wilkware/FuelMonitor.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter 'Instanz hinzufügen' ist das _Sprintmonitor_-Modul (Alias: _Benzinverbrauchsrechner_, _Benzinkostenrechner_) unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> 🚗 Fahrzeugdaten ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Marke / Modell                                   | Dient nur zur besseren Fahrzeugverwaltung
Kennzeichen                                      | Nummernschild
Erstzulassung                                    | Datum der Erstzulassung (berechnet automatisch ersten TÜV-Termin)
Kilometerstand                                   | Dient zur einfachen Erstinitialisierung
Fahrzeugbild                                     | wenn vorhanden, dient als Hintergrundbild in der Visu

> 🛠️ Service & TÜV ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
TÜV-Überwachung aktivieren                       | Aktiviert Meldung bei TÜV-Fälligkeit
Erinnere X Tage vor Fälligkeit                   | Einstellung wieviel Tage vorher erinnert werden soll (Termin machen)
Serviceerinnerung aktivieren                     | Aktiviert Meldung für Service-Fälligkeit
Nach Kilometerleistung verfolgen                 | Entscheidung, ob nach gefahrenen Kilometern informiert werden soll
Erinnere X km vor Fälligkeit                     | Wird durch Tankkilometer gegengerechnet
Nach Datum verfolgen                             | Entscheidung, ob nach festem Intervall (Datum) informiert werden soll
Erinnere X Tage vor Fälligkeit                   | Einstellung wieviel Tage vor Service erinnert werden soll (Termin machen)

> ✨ Visualisierung ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Marken- und Modellbezeichnung anzeigen?          | Wird vor Nummernschild angezeigt und kann aber auch weggelassen werden (meistens heißt die Darstellung schon so)
Farbe (Normal)                                   | Darstellungsfarbe in Visu für Zustand "Alles easy"
Farbe (Warnung)                                  | Darstellungsfarbe in Visu für Zustand "Wird eng"
Farbe (Kritisch)                                 | Darstellungsfarbe in Visu für Zustand "Jetzt aber schnell"

> ⚙️ Erweiterte Einstellungen ...

Name                                       | Beschreibung
------------------------------------------ | -----------------------------------
Zeige Fehlermeldung wenn Datum in der Zukunft liegt! | Nur Rückwirkend wenn kein Zwischendatum ist!
Zeige Fehlermeldung wenn Kilometerstand abnimmt! | Bitte keine Manipulationen :)

### 5. Statusvariablen und Darstellungen

Die Statusvariablen werden automatisch angelegt. Das Löschen einzelner zerstört die komplette Funktionalität!!!

#### Statusvariablen

Name                            | Typ       | Beschreibung
--------------------------------| --------- | ----------------
Kilometer                       | Integer   | gefahrene Kilometer (ob aktuell, abhängig vom Tankdatum)
Liter                           | Float     | getankten Liter (ob aktuell, abhängig vom Tankdatum)
Preis                           | Float     | getankten Liter (ob aktuell, abhängig vom Tankdatum)
Durchschnittsverbrauch          | Flaot     | verbrauchte Liter je gefahrene 100 Kilometer
Kosten                          | Flaot     | Kosten je 100 Kilometer
TÜV-Fälligkeitstermin           | Integer   | nächser TÜV-Termin (wenn aktiviert)
Wartungsdatum                   | Integer   | nächster Servicetermin (wenn aktiviert)
Wartungsintervall               | Integer   | nächster Serviceintervall bei Kilometer X (wenn aktiviert)

HINWEIS: da man auch nachträglich (also nicht unbedingt am gleichen Tag) die Daten eingeben kann,  
werden diese dann direkt ins Archive geschrieben und nicht mit __SetValue__. Dadurch müssen sie nicht immer den  
aktuellsten Stand abbilden.

#### Darstellungen

Die Dartsellungen werden den Variablen über direkte Assoziazion zugewiesen.  
Es werden keine Darstellungs-Templates angelegt.

### 6. Visualisierung

Man kann sowohl das gesamte Modul (HTML-SDK Support) als auch nur die Statusvariablen direkt in der Visualisierung verlinken.
Bitte dann aber den obigen HINWEIS beachten!!!

### 7. PHP-Befehlsreferenz

Ein direkter Aufruf von öffentlichen Funktionen ist nicht notwendig!

### 8. Versionshistorie

v2.0 20260730

* _NEU_: komplette Erweiterung durch neue Möglichkeiten in der TileVisu

v1.0.20220424

* _NEU_: Initialversion

## Entwickler

Seit nunmehr über 10 Jahren fasziniert mich das Thema Haussteuerung. In den letzten Jahren betätige ich mich auch intensiv in der IP-Symcon Community und steuere dort verschiedenste Skript und Module bei. Ihr findet mich dort unter dem Namen @pitti ;-)

[![GitHub](https://img.shields.io/badge/GitHub-@wilkware-181717.svg?style=for-the-badge&logo=github)](https://wilkware.github.io/)

## Spenden

Die Software ist für die nicht kommerzielle Nutzung kostenlos, über eine Spende bei Gefallen des Moduls würde ich mich freuen.

[![PayPal](https://img.shields.io/badge/PayPal-spenden-00457C.svg?style=for-the-badge&logo=paypal)](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=8816166)

## Lizenz

Namensnennung - Nicht-kommerziell - Weitergabe unter gleichen Bedingungen 4.0 International

[![Licence](https://img.shields.io/badge/License-CC_BY--NC--SA_4.0-EF9421.svg?style=for-the-badge&logo=creativecommons)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
