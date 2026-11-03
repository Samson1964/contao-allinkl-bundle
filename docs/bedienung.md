# Bedienung

## Listen

Jede Liste zeigt die Objekte aller Zugänge; der Filter **KAS-Zugang** schränkt auf einen ein. Oben steht
**Bei All-Inkl abgleichen**: Es liest den Bereich für alle aktiven Zugänge neu ein. In der Liste der Domains führen
die Symbole einer Zeile zu ihren **Subdomains** und **DNS-Einträgen**; im Aufklappmenü der Zeile steht
**DNS zurücksetzen** (setzt die Zone auf die Vorgabe von All-Inkl zurück, eigene Einträge gehen verloren).

Vermerke hinter einem Eintrag:

* *Entwurf – noch nicht bei All-Inkl*: Das Anlegen ist gescheitert oder wurde abgebrochen. Maske öffnen, Fehler
  beheben, erneut speichern, oder den Entwurf löschen (er existiert nur hier).
* *wird eingerichtet*: All-Inkl arbeitet noch daran; der nächste Abgleich holt die endgültigen Daten.
* *Fehler beim letzten Speichern*: Die KAS hat eine Änderung abgelehnt. Die Meldung steht im Tooltip. Die Zeile
  enthält die abgelehnte Eingabe, bis man sie korrigiert oder neu abgleicht.

## Anlegen

**Neu** öffnet eine Maske mit genau den Angaben, die die KAS zum Anlegen kennt; Pflichtfelder sind markiert. Beim
Speichern geht der Auftrag sofort an die KAS. Danach liest das Bundle das neue Objekt nach und zeigt die Angaben,
die All-Inkl vergeben hat (etwa das Login `m0123456` eines Postfachs).

* **Domains**: vollständig mit Endung eingeben (`verein.de`); das Bundle zerlegt den Namen für die KAS.
* **Subdomains**: nur das Label (`forum`); die Domain wird angehängt.
* **Weiterleitungen** und **Mailinglisten-Teilnehmer**: eine Adresse je Zeile.

## Ändern

Es geht nur hinaus, was sich gegenüber dem zuletzt gelesenen Stand geändert hat. Kennwortfelder leer lassen, um
das Kennwort zu behalten. Schreibgeschützte Felder lässt die KAS nach dem Anlegen nicht mehr ändern.

* **SSL** (Domains, Subdomains): eigene Zertifikate mit Schlüssel einspielen, HTTPS erzwingen, HSTS. Der private
  Schlüssel wird nie gespeichert.
* **In anderen Account verschieben**: Login des Zielaccounts eintragen. Nach dem Speichern verschwindet der Host
  hier und erscheint beim Abgleich des Zielaccounts.
* **Spamfilter** (Postfächer): Filterkette wie `greyl;rbl_cbl:mark`; die möglichen Filter zeigt **Werkzeuge →
  Spamfilter**. Leer entfernt alle Standardfilter.
* **SSH** (Unteraccounts): nur in Tarifen mit SSH.

## Löschen

Das Löschen fragt nach und geht sofort an die KAS. Lehnt sie ab, bleibt der Eintrag stehen und ihre Meldung
erscheint. Eine Domain nimmt ihre Subdomains und DNS-Einträge hier mit.

Einen **KAS-Zugang** zu löschen entfernt nur die gespiegelten Daten in Contao; bei All-Inkl bleibt alles bestehen.

## Werkzeuge

Oben den Zugang wählen, dann die Ansicht. Die Daten werden bei jedem Aufruf frisch gelesen. Die Formulare
**Software installieren**, **Besitzrechte setzen** und **Symlink anlegen** gehen sofort an die KAS (nicht bei
Schreibschutz).
