# Abdeckung der KAS-API

Alle 73 Funktionen laut [Dokumentation](https://kasapi.kasserver.com/dokumentation/phpdoc/) (Stand 2026-11-03).
Den Abgleich prüft der Test `tests/Bereich/BereichRegisterTest.php`.

| Modul | Lesen | Anlegen | Ändern | Löschen | Weitere |
| --- | --- | --- | --- | --- | --- |
| KAS-Zugänge (Accounteinstellungen) | get_accountsettings | – | update_accountsettings | – | Anmeldung über KasAuth (entspricht add_session; Sitzungen laufen nach 10 Minuten ab, delete_session ist nicht nötig) |
| Unteraccounts | get_accounts | add_account | update_account | delete_account | update_superusersettings (SSH) |
| Domains | get_domains | add_domain | update_domain | delete_domain | update_ssl, move_domain, reset_dns_settings |
| ↳ Subdomains | get_subdomains | add_subdomain | update_subdomain | delete_subdomain | update_ssl, move_subdomain |
| ↳ DNS-Einträge | get_dns_settings | add_dns_settings | update_dns_settings | delete_dns_settings | |
| Postfächer | get_mailaccounts | add_mailaccount | update_mailaccount | delete_mailaccount | add_mailstandardfilter, delete_mailstandardfilter |
| Weiterleitungen | get_mailforwards | add_mailforward | update_mailforward | delete_mailforward | |
| Mailinglisten | get_mailinglists | add_mailinglist | update_mailinglist | delete_mailinglist | |
| Datenbanken | get_databases | add_database | update_database | delete_database | |
| FTP-Zugänge | get_ftpusers | add_ftpusers | update_ftpuser | delete_ftpuser | |
| Cronjobs | get_cronjobs | add_cronjob | update_cronjob | delete_cronjob | |
| Verzeichnisschutz | get_directoryprotection | add_directoryprotection | update_directoryprotection | delete_directoryprotection | |
| DynDNS | get_ddnsusers | add_ddnsuser | update_ddnsuser | delete_ddnsuser | |
| Netzlaufwerke | get_sambausers | add_sambauser | update_sambauser | delete_sambauser | |
| Werkzeuge | get_accountresources, get_space, get_server_information, get_traffic, get_space_usage, get_topleveldomains, get_softwareinstall, get_mailstandardfilter | add_softwareinstall, add_symlink | update_chown | – | |

## Woher die Feldnamen der Antworten stammen

All-Inkl dokumentiert nur die Parameter, nicht die Felder der Antworten. Die Namen stammen aus den
Open-Source-Clients [chmmou/kasapi-cli](https://github.com/chmmou/kasapi-cli) und
[go-acme/lego](https://github.com/go-acme/lego), die sie an echten Antworten festgemacht haben. Die KAS schickt
manche Felder in zwei Schreibweisen (`mail_adresses` und `mail_addresses`); beide werden gelesen.

Weicht ein Feldname bei einem echten Account ab, bleibt die Spalte leer. Der vollständige Datensatz steht trotzdem
in der Ansicht **Anzeigen** (KAS-Rohdaten). So lässt sich die richtige Bezeichnung ablesen und in
`src/Bereich/BereichRegister.php` nachtragen.

Am 2026-11-03 an einem echten Account (11 Domains, 68 DNS-Einträge, 21 Postfächer …) lesend geprüft: Alle
abgebildeten Felder kommen in den Antworten vor; Domainnamen, Pfade und PHP-Fassungen stimmen mit der
KAS-Oberfläche überein.

## Besonderheiten der KAS

* `get_accountsettings` antwortet mit `{"settings": {…}}`; darin steht auch `account_password`. Das Bundle packt
  aus und entfernt Geheimes auf jeder Ebene.
* Bei Let’s-Encrypt-Zertifikaten liefert die API für „SSL erzwingen“ und HSTS `null`, obwohl die KAS-Oberfläche den
  Wert kennt. Die Maske weist darauf hin; ohne Häkchen wird dort nichts geändert.
* „SSL aktiv“ kommt als deutsches `j`, andere Schalter als `Y`/`N`.
* TXT-Einträge (DKIM) sind länger als 255 Zeichen.

* Fehler kommen als SOAP-Fault mit einem Kürzel (`domain_syntax_incorrect`, `max_mail_account_reached` …), teils mit
  Tippfehlern (`unkown_action`, `subdomain_doenst_exist`). Das Bundle übersetzt sie über Muster ins Deutsche und
  nennt das Kürzel in Klammern.
* `update_mailinglist` verlangt die vollständige Konfiguration, sie geht bei jeder Änderung mit.
* `update_directoryprotection` verlangt das Kennwort, auch wenn nur die Bezeichnung geändert wird.
* `update_ssl` erwartet laut Dokumentation Schlüssel und Zertifikat. Ob das Umschalten von „HTTPS erzwingen“ allein
  genügt, ist noch nicht geprüft (bisher nur lesend getestet).
* `add_sambauser`: Die Dokumentation nennt `samba_new_password`, ihr Beispiel `samba_password`. Beides geht hinaus.
* Die Abwesenheitsnotiz eines Postfachs ist `N`, `Y` oder `Beginn|Ende` als Unix-Zeitstempel und wird so
  eingegeben, wie die KAS sie erwartet.
