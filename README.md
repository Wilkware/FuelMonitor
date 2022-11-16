# Lichtautomat (Light Automat)

[![Version](https://img.shields.io/badge/Symcon-PHP--Modul-red.svg)](https://www.symcon.de/service/dokumentation/entwicklerbereich/sdk-tools/sdk-php/)
[![Product](https://img.shields.io/badge/Symcon%20Version-6.0-blue.svg)](https://www.symcon.de/produkt/)
[![Version](https://img.shields.io/badge/Modul%20Version-6.0.20220401-orange.svg)](https://github.com/Wilkware/IPSymconFuelMonitor)
[![License](https://img.shields.io/badge/License-CC%20BY--NC--SA%204.0-green.svg)](https://creativecommons.org/licenses/by-nc-sa/4.0/)
[![Actions](https://github.com/Wilkware/IPSymconFuelMonitor/workflows/Check%20Style/badge.svg)](https://github.com/Wilkware/IPSymconFuelMonitor/actions)

Der Spritmonitor berechnet den Spritverbrauch Ihres Fahrzeugs und hilft bei der Verwaltung Ihrer Kosten.

## Inhaltverzeichnis

1. [Funktionsumfang](#user-content-1-funktionsumfang)
2. [Voraussetzungen](#user-content-2-voraussetzungen)
3. [Installation](#user-content-3-installation)
4. [Einrichten der Instanzen in IP-Symcon](#user-content-4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#user-content-5-statusvariablen-und-profile)
6. [WebFront](#user-content-6-webfront)
7. [PHP-Befehlsreferenz](#user-content-7-php-befehlsreferenz)
8. [Versionshistorie](#user-content-8-versionshistorie)

### 1. Funktionsumfang

Der Spritmonitor berechnet den Spritverbrauch Ihres Fahrzeugs und hilft bei der Verwaltung Ihrer Kosten.

### 2. Voraussetzungen

* IP-Symcon ab Version 6.0

### 3. Installation

* Über den Modul Store das Modul _Sprintmonitor_ installieren.
* Alternativ Über das Modul-Control folgende URL hinzufügen.  
`https://github.com/Wilkware/IPSymconFuelMonitor` oder `git://github.com/Wilkware/IPSymconFuelMonitor.git`

### 4. Einrichten der Instanzen in IP-Symcon

* Unter 'Instanz hinzufügen' ist das _Sprintmonitor_-Modul (Alias: _Benzinverbrauchsrechner_, _Benzinkostenrechner_) unter dem Hersteller '(Geräte)' aufgeführt.

__Konfigurationsseite__:

Einstellungsbereich:

> Geräte ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Variable                                         | Quellvariable, über welche ...

> Erweiterte Einstellungen ...

Name                                             | Beschreibung
------------------------------------------------ | ---------------------------------
Gleichzeitiges Ausführen eines Scriptes          | Auswahl eines Skriptes, welches zusätzlich ausgeführt werden soll (IPS_ExecScript).

### 5. Statusvariablen und Profile

Die Statusvariablen werden unter Berücksichtigung der erweiterten Einstellungen angelegt. Das Löschen einzelner kann zu Fehlfunktionen führen.

Name                 | Typ          | Beschreibung
-------------------- | ------------ | ----------------
Name                 | Boolean      | Text

Folgende Profile werden angelegt:

Name                 | Typ       | Beschreibung
-------------------- | --------- | ----------------
SVM.Profil           | Integer   | Text

### 6. WebFront

Alle Statusvariablen können im Webfront verlinkt werden.  

### 7. PHP-Befehlsreferenz

Ein direkter Aufruf von öffentlichen Funktionen ist nicht notwendig!

### 8. Versionshistorie

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
