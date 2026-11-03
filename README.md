# All-Inkl-Bundle für Contao

Verwaltet ein Webhosting bei [All-Inkl](https://all-inkl.com/) direkt im Contao-Backend: Domains, Subdomains,
DNS, SSL, Postfächer, Weiterleitungen, Mailinglisten, Datenbanken, FTP-Zugänge, Cronjobs, Verzeichnisschutz,
DynDNS, Netzlaufwerke, Unteraccounts und Accounteinstellungen. Grundlage ist die
[KAS-API](https://kasapi.kasserver.com/dokumentation/) von All-Inkl.

Läuft unter **Contao 4.13 und Contao 5.7** mit PHP 8.1 bis 8.4.

## Funktionen

* Eigene Menügruppe **All-Inkl** mit 13 Modulen. Subdomains und DNS-Einträge liegen als Kindtabellen unter ihrer
  Domain.
* **Mehrere KAS-Zugänge**: Haupt- und Unteraccounts nebeneinander, jedes Objekt gehört zu genau einem Zugang.
* **Abgleich** per Knopf, für einen Zugang ganz oder für einen Bereich über alle Zugänge, wahlweise täglich über
  Contaos Cron.
* **Anlegen, Ändern, Löschen** gehen sofort an die KAS. Lehnt die KAS ab, erscheint ihre Meldung auf Deutsch, und
  die Maske bleibt mit der Eingabe offen.
* **Schreibschutz je Zugang**, für neue Zugänge eingeschaltet. Solange er gesetzt ist, verlässt nur Lesendes
  (`get_*`) das Haus.
* **Werkzeuge**: Ressourcen, Speicherbelegung, Serverinfos (PHP-Fassungen), Datenverkehr, Speicher je Verzeichnis,
  mögliche Domainendungen, verfügbare Software samt Installation, verfügbare Spamfilter, Besitzrechte setzen,
  Symlinks anlegen.

Alle 73 Funktionen der KAS-API sind abgedeckt; die Zuordnung steht in [docs/funktionen.md](docs/funktionen.md).

## Installation

```bash
composer require schachbulle/contao-allinkl-bundle
```

Danach die Datenbank aktualisieren (Contao Manager oder `vendor/bin/contao-console contao:migrate`).

Voraussetzungen: die PHP-Erweiterungen `dom`, `json` und `sodium`. Die SOAP-Erweiterung wird **nicht** gebraucht.
Das Bundle spricht SOAP selbst, weil `ext-soap` bei vielen Hostern fehlt.

## Einrichtung

1. **KAS-Zugänge → Neu**: Bezeichnung, KAS-Login (etwa `w0123456`) und KAS-Kennwort eintragen. Das Kennwort wird
   mit dem `APP_SECRET` der Installation verschlüsselt gespeichert und nie wieder angezeigt.
2. **Anmeldung prüfen** (Häkchen in der Zeile) testet Login und Kennwort.
3. **Abgleichen** (Pfeile in der Zeile) liest alle Bereiche ein.
4. Erst wenn alles stimmt: in der Maske des Zugangs den **Schreibschutz** abschalten.

Hinweise:

* Die **Zwei-Faktor-Anmeldung** muss für den Account aus sein; die KAS-API verlangt sonst bei jeder Sitzung einen
  Einmalcode.
* Wer `APP_SECRET` ändert, muss die Kennwörter der Zugänge neu eintragen.
* Ändert man in den **Accounteinstellungen** eines Zugangs das KAS-Kennwort, übernimmt das Bundle das neue Kennwort
  selbst.

## Rechte

| Wer | Was er sieht |
| --- | --- |
| Hauptadmin (Vorgabe: Benutzer mit der ID 1) | alle Module |
| andere Administratoren | nur die Module, die der Hauptadmin in ihrem Benutzerkonto unter **All-Inkl-Module** freigibt; ohne Freigabe nichts |
| übrige Benutzer | wie gewohnt über die Modulrechte ihrer Benutzergruppe |

Unter **Contao 5** brauchen Nicht-Administratoren zusätzlich die **Tabellenrechte** (Anlegen, Bearbeiten, Löschen)
für die Tabellen `tl_allinkl_*` der freigegebenen Module.

Den Hauptadmin legt die `config/config.yaml` fest:

```yaml
contao_allinkl:
    hauptadmin: 1
```

## Arbeitsweise

* Jeder Bereich hat eine **Spiegeltabelle**. Der Abgleich füllt sie; Objekte, die die KAS nicht mehr liefert,
  verschwinden. Der zuletzt gelesene KAS-Datensatz steht in jeder Zeile (Ansicht **Anzeigen**), ohne Kennwörter und
  private Schlüssel.
* Beim Speichern geht **nur hinaus, was sich gegenüber dem letzten KAS-Stand geändert hat**. Was jemand
  inzwischen im KAS selbst geändert hat, wird nicht überschrieben.
* **Kennwortfelder** (Postfach, FTP, Datenbank …) werden nie gespeichert. Leer lassen heißt: Kennwort behalten.
* Felder, die die KAS nach dem Anlegen nicht mehr ändern lässt, erscheinen schreibgeschützt.
* Gelöschte KAS-Objekte lassen sich über **Wiederherstellen** nicht zurückholen, denn bei All-Inkl sind sie weg.
* Die KAS bremst schnelle Aufrufe (Flood-Sperre); ein vollständiger Abgleich dauert deshalb einige Sekunden, bei
  vielen Domains entsprechend länger.

Die Bedienung der einzelnen Masken beschreibt [docs/bedienung.md](docs/bedienung.md).

## Prüfstand

Unter `pruefstand/` liegt eine **KAS-Attrappe**: ein nachgebauter KAS-Server auf Basis von PHPs `SoapServer` mit den
echten WSDL-Dateien. Sie prüft die Parameternamen streng gegen die Dokumentation. Start und Einbindung stehen im
Kopf von `pruefstand/kas-attrappe.php`.

```bash
vendor/bin/phpunit
```

## Lizenz

LGPL-3.0-or-later
