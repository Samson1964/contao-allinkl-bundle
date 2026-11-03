<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Kas;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Schickt SOAP-Umschläge über Symfonys HTTP-Client.
 *
 * Den Dienst „http_client“ registriert nicht jede Contao-Installation (in
 * den Testinstallationen 4.13 und 5.7 fehlt er), das Paket
 * symfony/http-client ist aber immer da. Fehlt der Dienst, baut die Klasse
 * sich deshalb selbst einen Client.
 */
class HttpTransport implements Transport
{
	/** Der HTTP-Client */
	private readonly HttpClientInterface $client;

	/**
	 * @param int                      $zeitlimit Höchstdauer eines Aufrufs in
	 *                                            Sekunden
	 * @param HttpClientInterface|null $client    Contaos Client, wenn vorhanden
	 */
	public function __construct(
		private readonly int $zeitlimit = 30,
		?HttpClientInterface $client = null,
	) {
		$this->client = $client ?? HttpClient::create();
	}

	/**
	 * Schickt die Anfrage ab.
	 *
	 * getContent(false) wirft bei HTTP-Status 500 nicht, denn genau damit
	 * kommen die SOAP-Faults der KAS. Nur Netzfehler (DNS, Zeitüberschreitung,
	 * TLS) werden zu einem KasFehler „verbindung“.
	 *
	 * @param string $adresse    Der SOAP-Endpunkt
	 * @param string $soapAction Der Wert für den Kopf „SOAPAction“
	 * @param string $xml        Der SOAP-Umschlag
	 *
	 * @return string Der Antworttext
	 *
	 * @throws KasFehler Wenn keine Antwort kam
	 */
	public function senden(string $adresse, string $soapAction, string $xml): string
	{
		try {
			$antwort = $this->client->request('POST', $adresse, array(
				'headers' => array(
					'Content-Type' => 'text/xml; charset=utf-8',
					'SOAPAction'   => '"'.$soapAction.'"',
				),
				'body'    => $xml,
				'timeout' => $this->zeitlimit,
			));

			return $antwort->getContent(false);
		} catch (ExceptionInterface $fehler) {
			throw new KasFehler('verbindung', $fehler->getMessage(), '', $fehler);
		}
	}
}
