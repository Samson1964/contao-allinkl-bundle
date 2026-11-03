# Changelog

## Version 0.1.0 (2026-11-03)

* Add: Menügruppe „All-Inkl“ mit KAS-Zugängen, Unteraccounts, Domains (mit Subdomains und DNS), Postfächern,
  Weiterleitungen, Mailinglisten, Datenbanken, FTP-Zugängen, Cronjobs, Verzeichnisschutz, DynDNS, Netzlaufwerken
  und Werkzeugen; alle 73 Funktionen der KAS-API
* Add: Spiegeltabellen mit Abgleich per Knopf und täglich über Contaos Cron
* Add: Anlegen, Ändern und Löschen gehen sofort an die KAS; es geht nur hinaus, was sich gegenüber dem letzten
  KAS-Stand geändert hat
* Add: Schreibschutz je Zugang (Vorgabe: an)
* Add: Kennwörter der Zugänge mit libsodium verschlüsselt; Kennwortfelder der Objekte werden nie gespeichert
* Add: Hauptadmin sieht alles, andere Administratoren nur freigegebene Module, übrige Benutzer über die
  Modulrechte
* Add: eigener SOAP-Aufruf ohne ext-soap, mit Flood-Sperre und Wiederholung
* Add: KAS-Attrappe als Prüfstand
* Add: Symbol der Menügruppe, folgt in Contao 5 dem hellen und dunklen Farbschema
* Change: Knöpfe merken die Scrollposition in Contao 5 über den Stimulus-Controller statt über das abgekündigte
  Backend.getScrollOffset()
* Fix: Befunde aus dem lesenden Test an einem echten Account (Accounteinstellungen unter „settings“, lange
  TXT-Einträge, Geheimes in verschachtelten Feldern, von der KAS verschwiegene SSL-Werte)
