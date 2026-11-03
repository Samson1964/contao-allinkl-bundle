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
 * Schickt einen SOAP-Umschlag ab und liefert den Antworttext.
 *
 * Als Schnittstelle getrennt, damit die Tests ohne Netz eine Attrappe
 * einsetzen können.
 */
interface Transport
{
	/**
	 * Schickt eine Anfrage per HTTP POST.
	 *
	 * @param string $adresse    Der SOAP-Endpunkt
	 * @param string $soapAction Der Wert für den Kopf „SOAPAction“
	 * @param string $xml        Der SOAP-Umschlag
	 *
	 * @return string Der Antworttext, auch bei HTTP-Fehlerstatus (die KAS
	 *                schickt Faults mit Status 500)
	 *
	 * @throws KasFehler Mit dem Kürzel „verbindung“, wenn keine Antwort kam
	 */
	public function senden(string $adresse, string $soapAction, string $xml): string;
}
