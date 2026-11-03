<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Tests\Kas;

use Schachbulle\ContaoAllinklBundle\Kas\Transport;

/**
 * Ein Transport ohne Netz: liefert vorbereitete Antworten und merkt sich
 * jede Anfrage.
 */
final class TransportAttrappe implements Transport
{
	/** @var array<int, array{adresse: string, xml: string, params: array<string, mixed>}> Die Anfragen */
	public array $anfragen = array();

	/** @var array<int, string|\Closure> Die Antworten in Reihenfolge */
	private array $antworten;

	/**
	 * @param string|\Closure ...$antworten SOAP-Texte oder Closures, die aus
	 *                                      den Params eine Antwort bilden
	 */
	public function __construct(string|\Closure ...$antworten)
	{
		$this->antworten = $antworten;
	}

	/**
	 * Liefert die nächste vorbereitete Antwort.
	 *
	 * @param string $adresse    Der Endpunkt
	 * @param string $soapAction Unbenutzt
	 * @param string $xml        Der Umschlag
	 *
	 * @return string Die Antwort
	 */
	public function senden(string $adresse, string $soapAction, string $xml): string
	{
		preg_match('#<Params>(.*)</Params>#s', $xml, $treffer);
		$params = json_decode(htmlspecialchars_decode($treffer[1] ?? '', ENT_XML1 | ENT_QUOTES), true) ?: array();
		$this->anfragen[] = array('adresse' => $adresse, 'xml' => $xml, 'params' => $params);

		if (array() === $this->antworten) {
			throw new \LogicException('Keine Antwort mehr vorbereitet für '.($params['kas_action'] ?? 'KasAuth'));
		}

		$antwort = array_shift($this->antworten);

		return $antwort instanceof \Closure ? $antwort($params) : $antwort;
	}

	/**
	 * Liefert die Namen der aufgerufenen KAS-Funktionen.
	 *
	 * @return array<int, string> „KasAuth“ für Anmeldungen, sonst kas_action
	 */
	public function aktionen(): array
	{
		return array_map(static fn (array $anfrage): string => (string) ($anfrage['params']['kas_action'] ?? 'KasAuth'), $this->anfragen);
	}

	/**
	 * Baut eine Anmeldeantwort.
	 *
	 * @param string $token Der Sitzungsschlüssel
	 *
	 * @return string Der SOAP-Text
	 */
	public static function anmeldung(string $token = 'sitzung123'): string
	{
		return '<?xml version="1.0"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"><SOAP-ENV:Body><ns1:KasAuthResponse xmlns:ns1="x"><return xsi:type="xsd:string">'.$token.'</return></ns1:KasAuthResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>';
	}

	/**
	 * Baut eine KAS-Antwort im Format von PHPs SoapServer.
	 *
	 * @param mixed $info       Inhalt von ReturnInfo
	 * @param float $floodDelay Wert von KasFloodDelay
	 *
	 * @return string Der SOAP-Text
	 */
	public static function antwort(mixed $info, float $floodDelay = 0.0): string
	{
		$wert = static function (mixed $daten) use (&$wert): string {
			if (null === $daten) {
				return ' xsi:nil="true">';
			}

			if (\is_array($daten)) {
				if (array_is_list($daten)) {
					return ' xsi:type="SOAP-ENC:Array">'.implode('', array_map(static fn ($teil): string => '<item'.$wert($teil).'</item>', $daten));
				}

				$innen = '';

				foreach ($daten as $schluessel => $teil) {
					$innen .= '<item><key xsi:type="xsd:string">'.htmlspecialchars((string) $schluessel).'</key><value'.$wert($teil).'</value></item>';
				}

				return ' xsi:type="ns2:Map">'.$innen;
			}

			$typ = \is_int($daten) ? 'int' : (\is_float($daten) ? 'float' : 'string');

			return ' xsi:type="xsd:'.$typ.'">'.htmlspecialchars((string) $daten);
		};

		$rueckgabe = array('Response' => array('KasFloodDelay' => $floodDelay, 'ReturnString' => 'TRUE', 'ReturnInfo' => $info));

		return '<?xml version="1.0"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:ns2="http://xml.apache.org/xml-soap" xmlns:SOAP-ENC="http://schemas.xmlsoap.org/soap/encoding/"><SOAP-ENV:Body><ns1:KasApiResponse xmlns:ns1="x"><return'.$wert($rueckgabe).'</return></ns1:KasApiResponse></SOAP-ENV:Body></SOAP-ENV:Envelope>';
	}

	/**
	 * Baut einen SOAP-Fault.
	 *
	 * @param string $code   Das Fehlerkürzel
	 * @param string $detail Die Zusatzangabe
	 *
	 * @return string Der SOAP-Text
	 */
	public static function fehler(string $code, string $detail = ''): string
	{
		return '<?xml version="1.0"?><SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/"><SOAP-ENV:Body><SOAP-ENV:Fault><faultcode>SOAP-ENV:Server</faultcode><faultstring>'.$code.'</faultstring><faultactor>KasApi</faultactor>'.('' === $detail ? '' : '<detail>'.$detail.'</detail>').'</SOAP-ENV:Fault></SOAP-ENV:Body></SOAP-ENV:Envelope>';
	}
}
