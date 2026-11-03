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
use Schachbulle\ContaoAllinklBundle\Kas\KasClient;
use Schachbulle\ContaoAllinklBundle\Kas\KasFehler;
use Schachbulle\ContaoAllinklBundle\Kas\SoapKodierer;
use Schachbulle\ContaoAllinklBundle\Kas\Zugangsdaten;

/**
 * Prüft Anmeldung, Flood-Sperre, Wiederholung und Schreibschutz.
 *
 * Uhr und Warten sind ersetzt: Die Uhr rückt nur vor, wenn der Client
 * wartet. So lässt sich genau ablesen, wie lange er gewartet hätte.
 */
final class KasClientTest extends TestCase
{
	/** Simulierte Uhrzeit in Sekunden */
	private float $jetzt = 1000.0;

	/** @var array<int, float> Die Wartezeiten des Clients */
	private array $gewartet = array();

	/**
	 * Erst Anmeldung, dann der Aufruf mit Sitzungsschlüssel.
	 */
	public function testMeldetSichAnUndRuftAuf(): void
	{
		$transport = new TransportAttrappe(TransportAttrappe::anmeldung('tok'), TransportAttrappe::antwort(array(array('domain_name' => 'a.de'))));
		$zeilen = $this->client($transport)->lesen($this->zugang(), 'get_domains');

		$this->assertSame(array(array('domain_name' => 'a.de')), $zeilen);
		$this->assertSame(array('KasAuth', 'get_domains'), $transport->aktionen());
		$this->assertSame('geheim', $transport->anfragen[0]['params']['kas_auth_data']);
		$this->assertSame('plain', $transport->anfragen[0]['params']['kas_auth_type']);
		$this->assertSame('session', $transport->anfragen[1]['params']['kas_auth_type']);
		$this->assertSame('tok', $transport->anfragen[1]['params']['kas_auth_data']);
		$this->assertStringContainsString('KasAuth.php', $transport->anfragen[0]['adresse']);
		$this->assertStringContainsString('KasApi.php', $transport->anfragen[1]['adresse']);
	}

	/**
	 * Der zweite Aufruf wartet die KasFloodDelay des ersten ab und braucht
	 * keine neue Anmeldung.
	 */
	public function testWartetFloodDelayAb(): void
	{
		$transport = new TransportAttrappe(
			TransportAttrappe::anmeldung(),
			TransportAttrappe::antwort(array(), 2.0),
			TransportAttrappe::antwort(array()),
		);
		$client = $this->client($transport);

		$client->lesen($this->zugang(), 'get_domains');
		$this->gewartet = array();
		$client->lesen($this->zugang(), 'get_subdomains');

		$this->assertSame(array('KasAuth', 'get_domains', 'get_subdomains'), $transport->aktionen());
		$this->assertCount(1, $this->gewartet);
		$this->assertEqualsWithDelta(2.0, $this->gewartet[0], 0.001);
	}

	/**
	 * Nach „flood_protection“ wartet der Client und versucht es erneut.
	 */
	public function testWiederholtNachFloodProtection(): void
	{
		$transport = new TransportAttrappe(
			TransportAttrappe::anmeldung(),
			TransportAttrappe::fehler('flood_protection', '0.07'),
			TransportAttrappe::antwort(array(array('x' => '1'))),
		);

		$zeilen = $this->client($transport)->lesen($this->zugang(), 'get_domains');

		$this->assertCount(1, $zeilen);
		$this->assertSame(array('KasAuth', 'get_domains', 'get_domains'), $transport->aktionen());
		$this->assertGreaterThanOrEqual(1.0, max($this->gewartet));
	}

	/**
	 * Eine abgelaufene Sitzung führt zu genau einer neuen Anmeldung.
	 */
	public function testMeldetSichBeiAbgelaufenerSitzungNeuAn(): void
	{
		$transport = new TransportAttrappe(
			TransportAttrappe::anmeldung('alt'),
			TransportAttrappe::fehler('kas_session_invalid'),
			TransportAttrappe::anmeldung('neu'),
			TransportAttrappe::antwort('TRUE'),
		);

		$this->client($transport)->aufrufen($this->zugang(false), 'update_domain', array('domain_name' => 'a.de'));

		$this->assertSame(array('KasAuth', 'update_domain', 'KasAuth', 'update_domain'), $transport->aktionen());
		$this->assertSame('neu', $transport->anfragen[3]['params']['kas_auth_data']);
	}

	/**
	 * Bei Schreibschutz verlässt kein schreibender Aufruf das Haus.
	 */
	public function testSchreibschutzBlockiertOhneAnfrage(): void
	{
		$transport = new TransportAttrappe();

		try {
			$this->client($transport)->aufrufen($this->zugang(true), 'delete_domain', array('domain_name' => 'a.de'));
			$this->fail('Kein Fehler geworfen');
		} catch (KasFehler $fehler) {
			$this->assertSame('schreibschutz', $fehler->kasCode());
		}

		$this->assertSame(array(), $transport->anfragen);
	}

	/**
	 * „empty_list“ gilt beim Lesen als leere Liste, andere Fehler nicht.
	 */
	public function testEmptyListIstLeer(): void
	{
		$transport = new TransportAttrappe(TransportAttrappe::anmeldung(), TransportAttrappe::fehler('empty_list'));

		$this->assertSame(array(), $this->client($transport)->lesen($this->zugang(), 'get_cronjobs'));
	}

	/**
	 * Null-Werte gehen nicht hinaus, leere Texte schon.
	 */
	public function testNullWerteEntfallen(): void
	{
		$transport = new TransportAttrappe(TransportAttrappe::anmeldung(), TransportAttrappe::antwort('TRUE'));

		$this->client($transport)->aufrufen($this->zugang(false), 'update_cronjob', array('cronjob_id' => '5', 'http_user' => '', 'mail_condition' => null));

		$this->assertSame(array('cronjob_id' => '5', 'http_user' => ''), $transport->anfragen[1]['params']['KasRequestParams']);
	}

	/**
	 * Ein ReturnString ungleich „TRUE“ ist ein Fehler.
	 */
	public function testReturnStringUngleichTrue(): void
	{
		$antwort = str_replace('>TRUE<', '>max_domain_reached<', TransportAttrappe::antwort(null));
		$transport = new TransportAttrappe(TransportAttrappe::anmeldung(), $antwort);

		$this->expectException(KasFehler::class);
		$this->expectExceptionMessage('max_domain_reached');

		$this->client($transport)->aufrufen($this->zugang(false), 'add_domain');
	}

	/**
	 * Baut den Client mit ersetzter Uhr.
	 *
	 * @param TransportAttrappe $transport Der Transport
	 *
	 * @return KasClient Der Client
	 */
	private function client(TransportAttrappe $transport): KasClient
	{
		return new KasClient(
			$transport,
			new SoapKodierer(),
			'https://kas.test/KasApi.php',
			'https://kas.test/KasAuth.php',
			null,
			function (float $sekunden): void {
				$this->gewartet[] = $sekunden;
				$this->jetzt += $sekunden;
			},
			fn (): float => $this->jetzt,
		);
	}

	/**
	 * Baut Zugangsdaten.
	 *
	 * @param bool $schreibschutz Schreibschutz an oder aus
	 *
	 * @return Zugangsdaten Die Daten
	 */
	private function zugang(bool $schreibschutz = true): Zugangsdaten
	{
		return new Zugangsdaten('w0123456', 'geheim', $schreibschutz, 1);
	}
}
