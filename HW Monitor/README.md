# Libre Hardware Monitor Modul für IP-Symcon
Dieses Modul greift die JSON-Daten des **Libre Hardware Monitor** ab und stellt ausgewählte Sensorwerte als Variablen in **IP-Symcon** bereit.
Die Sensoren werden automatisch ausgelesen und können komfortabel über Checkboxen im Konfigurationsformular ausgewählt werden.

Jeder aktivierte Sensor erzeugt **vier Variablen** in IP-Symcon:
**Pfad**, **Minimum**, **Istwert**, **Maximum**.

---

### Inhaltsverzeichnis

1. [Funktionsumfang](#1-funktionsumfang)
2. [Voraussetzungen](#2-voraussetzungen)
3. [Software-Installation](#3-software-installation)
4. [Einrichten der Instanzen in IP-Symcon](#4-einrichten-der-instanzen-in-ip-symcon)
5. [Statusvariablen und Profile](#5-statusvariablen-und-profile)
6. [WebFront](#6-webfront)
7. [PHP-Befehlsreferenz](#7-php-befehlsreferenz)
8. [Versionen](#8-versionen)

---

### 1. Funktionsumfang

* Automatische Erkennung aller Sensoren aus dem Libre Hardware Monitor über die JSON-Schnittstelle (`http://<IP>:<Port>/data.json`).
* Auswahl der gewünschten Sensoren im Konfigurationsformular per Checkbox.
* Erstellung von vier Variablen pro Sensor:
  - **UID – Pfad** (String)
  - **UID – Min** (Float)
  - **UID – Value** (Float)
  - **UID – Max** (Float)
* Automatische Aktualisierung über ein einstellbares Intervall oder manuell über einen Button.
* Automatische Löschung nicht mehr aktivierter Variablen.

---

### 2. Voraussetzungen

- IP-Symcon ab Version 8.1
- Installierter [Libre Hardware Monitor](https://github.com/LibreHardwareMonitor/LibreHardwareMonitor)
- Netzwerkzugriff auf den HTTP-Port (Standard 8085)

---

### 3. Software-Installation

* Über den **Module Store** kann das *Hardware Monitor*-Modul direkt installiert werden.
* Alternativ über den **Module Control**:
  ```
  https://github.com/mb-stern/HW-Monitor
  ```

---

### 4. Einrichten der Instanzen in IP-Symcon

Unter *Instanz hinzufügen* kann das Modul **Hardware Monitor** gefunden werden.
Weitere Informationen: [IP-Symcon Dokumentation](https://www.symcon.de/service/dokumentation/konzepte/instanzen/#Instanz_hinzufügen)

**Konfigurationsseite:**

| Name | Beschreibung |
|------|---------------|
| **IP-Adresse** | Adresse des Rechners, auf dem der Libre Hardware Monitor läuft |
| **Port** | HTTP-Port (Standard 8085) |
| **Updateintervall (Sek.)** | Aktualisierungsintervall für die Daten |
| **Sensoren-Auswahl** | Liste aller Sensoren mit Checkboxen zur Auswahl |

Nach Klick auf **Übernehmen** werden die gewählten Sensoren gespeichert und ihre Variablen automatisch angelegt.
Ein Button *„Jetzt aktualisieren (ausgewählte Daten)“* kann jederzeit manuell ein Update auslösen.

---

### 5. Statusvariablen und Profile

#### Statusvariablen

| Name | Typ | Beschreibung |
|------|-----|--------------|
| UID – Pfad | String | Anzeigename oder Hierarchiepfad des Sensors |
| UID – Min | Float | Minimaler Messwert |
| UID – Value | Float | Aktueller Messwert |
| UID – Max | Float | Maximaler Messwert |

Nicht mehr aktivierte Sensoren werden automatisch gelöscht.

#### Profile

| Name | Typ |
|------|-----|
| HW.Fan | Float |
| HW.Clock | Float |
| HW.Temp | Float |
| HW.Data | Float |
| HW.Rate | Float |

---

### 6. WebFront

Die ausgewählten Sensorwerte können direkt im WebFront angezeigt oder für Diagramme genutzt werden.

---

### 7. PHP-Befehlsreferenz

```php
boolean HW_Update(integer $InstanzID);
```
Aktualisiert alle ausgewählten Sensorwerte.
Gibt `true` zurück bei erfolgreichem Abruf der JSON-Daten, andernfalls `false`.

**Beispiel:**
```php
HW_Update(12345);
```

---

### 8. Versionen

**Version 2.2 (31.01.2026)**
* Anpassungen zur Storekompatibilität.

**Version 2.1 (02.01.2026)**
* Umbau auf IPOSModuleStrict und hochsetzen der Kompatibilität auf 8.1.

**Version 2.0 (20.10.2025)**
* Komplette Überarbeitung mit Checkbox-basierter Auswahl. Achtung, beim Update werden bestehende Variablen gelöscht.
* Automatische Sensor-Erkennung aus JSON.
* Variablennamen basieren nun auf der Sensor-UID.
* Benutzer kann Variablennamen beibehalten.
* Automatische Bereinigung nicht aktivierter Sensoren.

**Version 1.9 (15.06.2025)**
* Variablen bleiben erhalten, wenn keine Daten vom Hardware Monitor verfügbar sind.
* Einige Codeoptimierungen

**Version 1.8 (22.12.2024)**
* Anpassung Modulname
* Anpassung Readme mit geänderter URL

**Version 1.7 (19.12.2024)**
* Der Name des Wertes wird wieder angezeigt.

**Version 1.6 (07.07.2024)**
* Spenden-Button hinzugefügt.
* Dokumentationslink hinzugefügt.

**Version 1.5 (05.05.2024)**
* Fehler behoben, dass alle Variablen gelöscht wurden, wenn die Verbindung unterbrochen war.

**Version 1.4 (18.03.2024)**
* Variablen mit Präfix (ID XX) übersichtlicher dargestellt.
* Nur noch vier Variablen pro ID (vorher sechs).

**Version 1.3 (17.02.2024)**
* Codeanpassung für Store-Kompatibilität.
* Debug und Fehlermeldungen verbessert.

**Version 1.2 (05.02.2024)**
* Debug hinzugefügt.
* Muster-IP-Adresse entfernt.
* Fenstergröße für ID-Liste im Formular angepasst.

**Version 1.1 (23.01.2024)**
* Variablenprofile erstellt und zugeordnet.

**Version 1.0 (21.01.2024)**
* Initiale Version.

---

### 9. Lizenz

Dieses Modul steht unter der **MIT-Lizenz**.  
© 2025 Stefan Künzli  
[https://opensource.org/licenses/MIT](https://opensource.org/licenses/MIT)