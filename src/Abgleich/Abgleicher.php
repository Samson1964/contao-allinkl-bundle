<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Abgleich;

use Schachbulle\ContaoAllinklBundle\Bereich\Abbild;
use Schachbulle\ContaoAllinklBundle\Bereich\Bereich;
use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;
use Schachbulle\ContaoAllinklBundle\Datenbank\KontoSpeicher;
use Schachbulle\ContaoAllinklBundle\Datenbank\Spiegel;
use Schachbulle\ContaoAllinklBundle\Kas\Fehlertext;
use Schachbulle\ContaoAllinklBundle\Kas\KasClient;
use Schachbulle\ContaoAllinklBundle\Kas\KasFehler;
use Schachbulle\ContaoAllinklBundle\Kas\Zugangsdaten;

/**
 * Liest die Bereiche eines Zugangs bei der KAS und füllt die
 * Spiegeltabellen.
 *
 * Jeder Bereich wird für sich abgeglichen: Lehnt die KAS einen ab (etwa
 * „dns_settings_not_allowed“ in kleinen Tarifen), bleiben seine Zeilen
 * unverändert stehen und die übrigen Bereiche laufen weiter. Scheitert
 * schon die Anmeldung, bricht der Abgleich des Zugangs ab.
 *
 * Ein Abgleich aller Bereiche braucht wegen der Flood-Sperre der KAS je
 * Aufruf etwa eine halbe Sekunde, bei DNS zusätzlich einen Aufruf je
 * Domain. Deshalb wird die Laufzeitgrenze von PHP aufgehoben, soweit der
 * Server das erlaubt.
 */
class Abgleicher
{
	/**
	 * @param KasClient     $client  Der KAS-Client
	 * @param KontoSpeicher $konten  Die Zugänge
	 * @param Spiegel       $spiegel Die Spiegeltabellen
	 */
	public function __construct(
		private readonly KasClient $client,
		private readonly KontoSpeicher $konten,
		private readonly Spiegel $spiegel,
	) {
	}

	/**
	 * Gleicht einen Zugang ab.
	 *
	 * @param int                     $kontoId  ID in tl_allinkl_konto
	 * @param array<int, string>|null $bereiche Kurznamen der Bereiche; null
	 *                                          für alle. Kindbereiche werden
	 *                                          bei ihren Eltern mitgenommen.
	 *
	 * @return Bericht Was abgeglichen wurde und was nicht; auch am Zugang
	 *                 gespeichert
	 */
	public function abgleichen(int $kontoId, ?array $bereiche = null): Bericht
	{
		@set_time_limit(0);

		$zeile = $this->konten->zeile($kontoId);
		$bericht = new Bericht((string) ($zeile['title'] ?? $kontoId));

		try {
			$zugang = $this->konten->zugang($kontoId);
			$this->client->anmeldungPruefen($zugang);
		} catch (\Throwable $fehler) {
			$bericht->fehler('Anmeldung', Fehlertext::zu($fehler));
			$this->konten->berichtSpeichern($kontoId, $bericht->alsText());

			return $bericht;
		}

		foreach (BereichRegister::alle() as $bereich) {
			$gewuenscht = null === $bereiche
				|| \in_array($bereich->name, $bereiche, true)
				|| (null !== $bereich->eltern && \in_array($bereich->eltern, $bereiche, true));

			if (!$gewuenscht) {
				continue;
			}

			try {
				$anzahl = match (true) {
					$bereich->einzeln => $this->einzelnAbgleichen($bereich, $zugang),
					null !== $bereich->eltern => $this->kinderAbgleichen($bereich, $zugang, $bericht),
					default => $this->spiegel->spiegeln($bereich, $kontoId, null, $this->client->lesen($zugang, $bereich->lesenAktion, $bereich->listenParams(null))),
				};

				$bericht->erfolg($bereich->titel, $anzahl);
			} catch (\Throwable $fehler) {
				// Auch Datenbankfehler (etwa ein unerwartet langer KAS-Wert)
				// betreffen nur diesen Bereich; die übrigen laufen weiter
				$bericht->fehler($bereich->titel, Fehlertext::zu($fehler));
			}
		}

		$this->konten->berichtSpeichern($kontoId, $bericht->alsText());

		return $bericht;
	}

	/**
	 * Gleicht einen Bereich mit genau einem Objekt je Zugang ab.
	 *
	 * @param Bereich      $bereich Der Bereich (Accounteinstellungen)
	 * @param Zugangsdaten $zugang  Der Zugang
	 *
	 * @return int 1, wenn die KAS Daten lieferte, sonst 0
	 *
	 * @throws KasFehler Wenn die KAS das Lesen ablehnt
	 */
	private function einzelnAbgleichen(Bereich $bereich, Zugangsdaten $zugang): int
	{
		$zeilen = $this->client->lesen($zugang, $bereich->lesenAktion);

		if (!isset($zeilen[0])) {
			return 0;
		}

		$this->spiegel->einstellungenUebernehmen($bereich, $zugang->kontoId, Abbild::auspacken($zeilen[0]));

		return 1;
	}

	/**
	 * Gleicht einen Kindbereich ab.
	 *
	 * Zwei Wege: Liefert die KAS alle Objekte auf einmal (Subdomains), werden
	 * sie über „zuordnen“ ihren Elternzeilen zugeteilt; Objekte ohne
	 * passende Elternzeile landen als Hinweis im Bericht. Sonst wird je
	 * Elternzeile gelesen (DNS je Zone); scheitert eine Zone, bleiben ihre
	 * Zeilen stehen.
	 *
	 * @param Bereich      $bereich Der Kindbereich
	 * @param Zugangsdaten $zugang  Der Zugang
	 * @param Bericht      $bericht Nimmt Hinweise und Fehler einzelner Zonen auf
	 *
	 * @return int Anzahl der Objekte über alle Elternzeilen
	 *
	 * @throws KasFehler Wenn die KAS die Gesamtliste ablehnt
	 */
	private function kinderAbgleichen(Bereich $bereich, Zugangsdaten $zugang, Bericht $bericht): int
	{
		$eltern = $this->spiegel->zeilen(BereichRegister::bereich((string) $bereich->eltern), $zugang->kontoId);
		$summe = 0;

		if (null !== $bereich->zuordnen) {
			$gruppen = array();
			$ohne = array();

			foreach ($this->client->lesen($zugang, $bereich->lesenAktion, $bereich->listenParams(null)) as $kas) {
				$pid = ($bereich->zuordnen)($kas, $eltern);

				if (null === $pid) {
					$ohne[] = ($bereich->schluessel)($kas);
				} else {
					$gruppen[$pid][] = $kas;
				}
			}

			foreach ($eltern as $elternZeile) {
				$summe += $this->spiegel->spiegeln($bereich, $zugang->kontoId, (int) $elternZeile['id'], $gruppen[(int) $elternZeile['id']] ?? array());
			}

			if (array() !== $ohne) {
				$bericht->hinweis(sprintf('%s ohne passende Domain in diesem Zugang: %s', $bereich->titel, implode(', ', $ohne)));
			}

			return $summe;
		}

		foreach ($eltern as $elternZeile) {
			try {
				$liste = $this->client->lesen($zugang, $bereich->lesenAktion, $bereich->listenParams($elternZeile));
			} catch (KasFehler $fehler) {
				$bericht->fehler(sprintf('%s %s', $bereich->titel, $elternZeile['kas_schluessel']), Fehlertext::zu($fehler));

				continue;
			}

			$summe += $this->spiegel->spiegeln($bereich, $zugang->kontoId, (int) $elternZeile['id'], $liste);
		}

		return $summe;
	}
}
