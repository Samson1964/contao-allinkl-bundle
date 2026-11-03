<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Backend;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Input;
use Schachbulle\ContaoAllinklBundle\Abgleich\Abgleicher;
use Schachbulle\ContaoAllinklBundle\Abgleich\Bericht;
use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;
use Schachbulle\ContaoAllinklBundle\Datenbank\KontoSpeicher;
use Schachbulle\ContaoAllinklBundle\Datenbank\Spiegel;
use Schachbulle\ContaoAllinklBundle\Kas\Fehlertext;
use Schachbulle\ContaoAllinklBundle\Kas\KasClient;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Die Knöpfe der Listen: Abgleichen, Anmeldung prüfen, DNS zurücksetzen.
 *
 * Contao ruft sie als key-Callbacks aus $GLOBALS['BE_MOD'] auf
 * (&key=abgleich …) und holt den Dienst über System::importStatic() aus dem
 * Container; deshalb ist er öffentlich. Jede Aktion endet mit einer
 * Weiterleitung zurück in die Liste, die Ergebnisse stehen als Meldungen
 * darüber.
 */
class Aktionen
{
	/**
	 * @param Abgleicher    $abgleicher Liest die KAS ein
	 * @param KontoSpeicher $konten     Die Zugänge
	 * @param Spiegel       $spiegel    Die Spiegeltabellen
	 * @param KasClient     $client     Für einzelne Aufrufe
	 * @param RequestStack  $anfragen   Für die Rücksprungadresse
	 * @param Meldungen $meldungen Sammelt die Meldungen für die Folgeseite
	 */
	public function __construct(
		private readonly Abgleicher $abgleicher,
		private readonly KontoSpeicher $konten,
		private readonly Spiegel $spiegel,
		private readonly KasClient $client,
		private readonly RequestStack $anfragen,
		private readonly Meldungen $meldungen,
	) {
	}

	/**
	 * Gleicht ab: einen Zugang ganz, oder einen Bereich für alle Zugänge.
	 *
	 * In der Liste der Zugänge mit „id“ wird genau dieser Zugang mit allen
	 * Bereichen abgeglichen, ohne „id“ alle aktiven. In den übrigen Modulen
	 * werden deren Bereiche für alle aktiven Zugänge abgeglichen.
	 *
	 * @throws RedirectResponseException Immer, zurück in die Liste
	 */
	public function abgleich(): void
	{
		$modul = (string) Input::get('do');
		$id = (int) Input::get('id');
		$nurKonto = 'allinkl_konten' === $modul && $id > 0;
		$bereiche = 'allinkl_konten' === $modul ? null : array_keys(BereichRegister::zumModul($modul));
		$konten = $nurKonto ? array($id) : $this->konten->aktive();

		if (array() === $konten) {
			$this->meldungen->hinweis('Es gibt keinen aktiven KAS-Zugang. Zuerst im Modul „KAS-Zugänge“ einen anlegen.');
		}

		foreach ($konten as $konto) {
			$this->melden($this->abgleicher->abgleichen($konto, $bereiche));
		}

		throw new RedirectResponseException($this->zurueck($nurKonto));
	}

	/**
	 * Prüft die Anmeldedaten eines Zugangs.
	 *
	 * @throws RedirectResponseException Immer, zurück in die Liste
	 */
	public function pruefen(): void
	{
		$id = (int) Input::get('id');

		try {
			$zugang = $this->konten->zugang($id);
			$this->client->anmeldungPruefen($zugang);
			$this->meldungen->erfolg(sprintf('Die Anmeldung als %s bei der KAS war erfolgreich.', $zugang->login));
		} catch (\Throwable $fehler) {
			$this->meldungen->fehler('Die Anmeldung ist fehlgeschlagen: '.Fehlertext::zu($fehler));
		}

		throw new RedirectResponseException($this->zurueck(true));
	}

	/**
	 * Setzt die DNS-Einstellungen einer Domain auf die Vorgabe zurück.
	 *
	 * Danach werden die DNS-Einträge des Zugangs neu eingelesen.
	 *
	 * @throws RedirectResponseException Immer, zurück in die Liste
	 */
	public function dnsZuruecksetzen(): void
	{
		$domain = $this->spiegel->zeile('tl_allinkl_domain', (int) Input::get('id'));

		if (null !== $domain && '' !== (string) $domain['kas_schluessel']) {
			try {
				$zugang = $this->konten->zugang((int) $domain['konto']);
				$this->client->aufrufen($zugang, 'reset_dns_settings', array('zone_host' => rtrim((string) $domain['kas_schluessel'], '.').'.'));
				$this->meldungen->erfolg(sprintf('Die DNS-Einstellungen von %s wurden zurückgesetzt.', $domain['kas_schluessel']));
				$this->melden($this->abgleicher->abgleichen((int) $domain['konto'], array('dns')));
			} catch (\Throwable $fehler) {
				$this->meldungen->fehler(sprintf('Die DNS-Einstellungen von %s wurden nicht zurückgesetzt: %s', $domain['kas_schluessel'], Fehlertext::zu($fehler)));
			}
		}

		throw new RedirectResponseException($this->zurueck(true));
	}

	/**
	 * Überträgt einen Abgleichbericht in die Meldungen.
	 *
	 * @param Bericht $bericht Der Bericht
	 */
	private function melden(Bericht $bericht): void
	{
		if ('' !== $bericht->erfolge()) {
			$this->meldungen->erfolg(sprintf('%s abgeglichen – %s', $bericht->zugang, $bericht->erfolge()));
		}

		foreach ($bericht->fehlerListe() as $text) {
			$this->meldungen->fehler(sprintf('%s – %s', $bericht->zugang, $text));
		}

		foreach ($bericht->hinweise() as $text) {
			$this->meldungen->hinweis(sprintf('%s – %s', $bericht->zugang, $text));
		}
	}

	/**
	 * Liefert die Adresse der Liste ohne den Aktionsparameter.
	 *
	 * @param bool $ohneId true: auch „id“ entfernen (Aktion an einer Zeile
	 *                     einer flachen Liste); bei Kindlisten trägt „id“ die
	 *                     Elternzeile und bleibt
	 *
	 * @return string Die Adresse
	 */
	private function zurueck(bool $ohneId): string
	{
		$anfrage = $this->anfragen->getCurrentRequest();

		if (null === $anfrage) {
			return '/';
		}

		$parameter = $anfrage->query->all();
		unset($parameter['key']);

		if ($ohneId && !isset($parameter['table'])) {
			unset($parameter['id']);
		}

		return $anfrage->getSchemeAndHttpHost().$anfrage->getBaseUrl().$anfrage->getPathInfo().'?'.http_build_query($parameter);
	}
}
