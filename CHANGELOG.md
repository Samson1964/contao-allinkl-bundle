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
