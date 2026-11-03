<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Tests\Kas;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoAllinklBundle\Kas\KasFehler;
use Schachbulle\ContaoAllinklBundle\Kas\SoapKodierer;

/**
 * Prüft Aufbau und Auswertung der SOAP-Umschläge.
 *
 * Die Datei Fixtures/get_domains.xml hat PHPs SoapServer mit der echten
 * KasApi.wsdl erzeugt – im selben Format antwortet die KAS (Apache-Map,
 * SOAP-ENC:Array, xsi:nil).
 */
final class SoapKodiererTest extends TestCase
{
	/**
	 * Sonderzeichen im Kennwort dürfen das XML nicht zerbrechen.
	 */
	public function testAnfrageMaskiertSonderzeichen(): void
	{
		$xml = (new SoapKodierer())->anfrage('KasApi', array('kas_auth_data' => 'a&b<c>"d\'', 'KasRequestParams' => array('x' => 1)));

		$dokument = new \DOMDocument();
		$this->assertTrue($dokument->loadXML($xml));

		$params = json_decode($dokument->getElementsByTagName('Params')->item(0)->textContent, true);
		$this->assertSame('a&b<c>"d\'', $params['kas_auth_data']);
	}

	/**
	 * Leere Parameter gehen als JSON-Objekt hinaus, nicht als Liste.
	 */
	public function testLeereParameterAlsObjekt(): void
	{
		$xml = (new SoapKodierer())->anfrage('KasApi', array('kas_action' => 'get_domains', 'KasRequestParams' => array()));

		$this->assertStringContainsString('&quot;KasRequestParams&quot;:{}', $xml);
	}

	/**
	 * Map, Liste von Maps, nil, int, float und leere Liste werden richtig gelesen.
	 */
	public function testAntwortDesSoapServers(): void
	{
		$daten = (new SoapKodierer())->antwort((string) file_get_contents(__DIR__.'/../Fixtures/get_domains.xml'));

		$this->assertSame(1625014992, $daten['Request']['KasRequestTime']);
		$this->assertNull($daten['Request']['KasRequestType']);
		$this->assertSame(0.5, $daten['Response']['KasFloodDelay']);
		$this->assertSame('TRUE', $daten['Response']['ReturnString']);
		$this->assertCount(2, $daten['Response']['ReturnInfo']);
		$this->assertSame('beispiel.de', $daten['Response']['ReturnInfo'][0]['domain_name']);
		$this->assertSame('', $daten['Response']['ReturnInfo'][0]['leer']);
		$this->assertSame(array('a', 'b'), $daten['Response']['ReturnInfo'][0]['liste']);
		$this->assertSame(array(), $daten['Response']['ReturnInfo'][1]['liste']);
	}

	/**
	 * Ein Fault wird zum KasFehler mit Kürzel und Zusatzangabe.
	 */
	public function testFaultWirdZuKasFehler(): void
	{
		try {
			(new SoapKodierer())->antwort((string) file_get_contents(__DIR__.'/../Fixtures/fault_flood.xml'), 'get_domains');
			$this->fail('Kein Fehler geworfen');
		} catch (KasFehler $fehler) {
			$this->assertSame('flood_protection', $fehler->kasCode());
			$this->assertSame('0.0688529014587', $fehler->detail());
			$this->assertSame('get_domains', $fehler->aktion());
		}
	}

	/**
	 * Eine HTML-Fehlerseite statt SOAP ergibt „ungueltige_antwort“.
	 */
	public function testKeinSoap(): void
	{
		$this->expectException(KasFehler::class);
		$this->expectExceptionMessage('ungueltige_antwort');

		(new SoapKodierer())->antwort('<html><body>502 Bad Gateway</body>');
	}
}
