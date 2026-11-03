<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Kas;

/**
 * Ein Fehler der KAS-API oder des Weges dorthin.
 *
 * Die KAS meldet Fehler als SOAP-Fault mit einem stabilen Kürzel im
 * faultstring („domain_syntax_incorrect“, „max_mail_account_reached“ …).
 * Dieses Kürzel steht in kasCode() und dient zur Übersetzung in eine
 * deutsche Meldung (siehe Fehlertext). Eigene Kürzel des Bundles:
 *
 * - „schreibschutz“: Der Zugang ist schreibgeschützt, der Aufruf wurde
 *   nicht abgeschickt.
 * - „verbindung“: Die Schnittstelle war nicht erreichbar.
 * - „ungueltige_antwort“: Die Antwort war kein lesbares SOAP.
 */
class KasFehler extends \RuntimeException
{
	/**
	 * @param string          $kasCode Das Fehlerkürzel der KAS oder des Bundles
	 * @param string          $detail  Zusatzangabe aus dem SOAP-Feld „detail“,
	 *                                 bei der Flood-Sperre die Wartezeit
	 * @param string          $aktion  Die aufgerufene KAS-Funktion, leer bei
	 *                                 der Anmeldung
	 * @param \Throwable|null $vorher  Der auslösende Fehler, etwa vom HTTP-Client
	 */
	public function __construct(
		private readonly string $kasCode,
		private readonly string $detail = '',
		private readonly string $aktion = '',
		?\Throwable $vorher = null,
	) {
		$text = '' === $aktion ? $kasCode : $aktion.': '.$kasCode;

		if ('' !== $detail) {
			$text .= ' ('.$detail.')';
		}

		parent::__construct($text, 0, $vorher);
	}

	/**
	 * Liefert das Fehlerkürzel.
	 *
	 * @return string Das Kürzel, unverändert so, wie die KAS es schickt –
	 *                Tippfehler der KAS („unkown_action“, „doenst_exist“)
	 *                eingeschlossen
	 */
	public function kasCode(): string
	{
		return $this->kasCode;
	}

	/**
	 * Liefert die Zusatzangabe des Faults.
	 *
	 * @return string Der Inhalt von „detail“, leer wenn keiner mitkam
	 */
	public function detail(): string
	{
		return $this->detail;
	}

	/**
	 * Liefert die KAS-Funktion, bei der der Fehler auftrat.
	 *
	 * @return string Etwa „add_domain“; leer bei der Anmeldung
	 */
	public function aktion(): string
	{
		return $this->aktion;
	}
}
