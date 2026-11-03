<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Tests\Bereich;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoAllinklBundle\Bereich\Abbild;
use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;
use Schachbulle\ContaoAllinklBundle\Bereich\Eingabefehler;
use Schachbulle\ContaoAllinklBundle\Bereich\Schreiber;
use Schachbulle\ContaoAllinklBundle\Kas\KasClient;
use Schachbulle\ContaoAllinklBundle\Kas\SoapKodierer;
use Schachbulle\ContaoAllinklBundle\Kas\Zugangsdaten;
use Schachbulle\ContaoAllinklBundle\Tests\Kas\TransportAttrappe;

/**
 * Prüft, welche Aufrufe beim Anlegen, Ändern und Löschen hinausgehen.
 */
final class SchreiberTest extends TestCase
{
	/**
	 * Anlegen nutzt ReturnInfo als Schlüssel und lädt das Objekt nach.
	 */
	public function testAnlegenMitNachladen(): void
	{
		$transport = new TransportAttrappe(
			TransportAttrappe::anmeldung(),
			TransportAttrappe::antwort('m0123456'),
			TransportAttrappe::antwort(array(array('mail_login' => 'm0123456', 'mail_addresses' => 'info@a.de'))),
		);

		$ergebnis = $this->schreiber($transport)->anlegen(BereichRegister::bereich('postfach'), $this->zugang(), array(
			'local_part' => 'info', 'domain_part' => 'a.de', 'is_active' => 'Y', 'webmail_autologin' => '1',
		), array('passwort' => 'Geheim1!'), null);

		$this->assertSame('m0123456', $ergebnis['schluessel']);
		$this->assertSame('info@a.de', $ergebnis['kas']['mail_addresses']);
		$this->assertSame(array('KasAuth', 'add_mailaccount', 'get_mailaccounts'), $transport->aktionen());

		$params = $transport->anfragen[1]['params']['KasRequestParams'];
		$this->assertSame('Geheim1!', $params['mail_password']);
		$this->assertSame('info', $params['local_part']);
		$this->assertSame(array('mail_login' => 'm0123456'), $transport->anfragen[2]['params']['KasRequestParams']);
	}

	/**
	 * Fehlende Pflichtangaben verhindern den Aufruf.
	 */
	public function testAnlegenOhnePflichtangaben(): void
	{
		$transport = new TransportAttrappe();

		$this->expectException(Eingabefehler::class);
		$this->expectExceptionMessage('Kennwort');

		$this->schreiber($transport)->anlegen(BereichRegister::bereich('postfach'), $this->zugang(), array('local_part' => 'info', 'domain_part' => 'a.de'), array(), null);
	}

	/**
	 * Hauptaufruf und SSL-Gruppe gehen getrennt hinaus, danach wird nachgeladen.
	 */
	public function testAendernMitGruppe(): void
	{
		$bereich = BereichRegister::bereich('domain');
		$kas = array('domain_name' => 'a.de', 'php_version' => '8.2', 'ssl_certificate_sni_force_https' => 'N');
		$zeile = Abbild::spaltenAusKas($bereich, $kas) + array('kas_daten' => Abbild::alsJson($kas), 'kas_schluessel' => 'a.de');
		$zeile['php_version'] = '8.3';
		$zeile['ssl_https'] = '1';

		$transport = new TransportAttrappe(
			TransportAttrappe::anmeldung(),
			TransportAttrappe::antwort('TRUE'),
			TransportAttrappe::antwort('TRUE'),
			TransportAttrappe::antwort(array(array('domain_name' => 'a.de', 'php_version' => '8.3'))),
		);

		$ergebnis = $this->schreiber($transport)->aendern($bereich, $this->zugang(), $zeile, array(), null);

		$this->assertTrue($ergebnis['geaendert']);
		$this->assertFalse($ergebnis['entfernt']);
		$this->assertSame(array('KasAuth', 'update_domain', 'update_ssl', 'get_domains'), $transport->aktionen());
		$this->assertSame(array('domain_name' => 'a.de', 'php_version' => '8.3'), $transport->anfragen[1]['params']['KasRequestParams']);
		$this->assertSame(array('hostname' => 'a.de', 'ssl_certificate_force_https' => 'Y'), $transport->anfragen[2]['params']['KasRequestParams']);
	}

	/**
	 * Verschieben ruft move_domain mit Quell- und Zielaccount und meldet „entfernt“.
	 */
	public function testVerschieben(): void
	{
		$bereich = BereichRegister::bereich('domain');
		$kas = array('domain_name' => 'a.de');
		$zeile = Abbild::spaltenAusKas($bereich, $kas) + array('kas_daten' => Abbild::alsJson($kas), 'kas_schluessel' => 'a.de', 'verschieben_nach' => 'w0999999');

		$transport = new TransportAttrappe(TransportAttrappe::anmeldung(), TransportAttrappe::antwort('TRUE'));
		$ergebnis = $this->schreiber($transport)->aendern($bereich, $this->zugang(), $zeile, array(), null);

		$this->assertTrue($ergebnis['entfernt']);
		$this->assertSame(array('domain_name' => 'a.de', 'source_account' => 'w0123456', 'target_account' => 'w0999999'), $transport->anfragen[1]['params']['KasRequestParams']);
	}

	/**
	 * Der Verzeichnisschutz verlangt beim Ändern das Kennwort.
	 */
	public function testVerzeichnisschutzBrauchtKennwort(): void
	{
		$bereich = BereichRegister::bereich('verzeichnisschutz');
		$kas = array('directory_path' => '/intern/', 'directory_user' => 'max', 'directory_authname' => 'Intern');
		$zeile = Abbild::spaltenAusKas($bereich, $kas) + array('kas_daten' => Abbild::alsJson($kas), 'kas_schluessel' => '/intern/|max');
		$zeile['directory_authname'] = 'Nur Vorstand';

		$this->expectException(Eingabefehler::class);

		$this->schreiber(new TransportAttrappe())->aendern($bereich, $this->zugang(), $zeile, array(), null);
	}

	/**
	 * Ein Objekt, das es bei der KAS nicht mehr gibt, gilt als gelöscht.
	 */
	public function testLoeschenSchonWeg(): void
	{
		$transport = new TransportAttrappe(TransportAttrappe::anmeldung(), TransportAttrappe::fehler('subdomain_doenst_exist'));

		$this->schreiber($transport)->loeschen(BereichRegister::bereich('subdomain'), $this->zugang(), array('kas_schluessel' => 'x.a.de'));

		$this->assertSame(array('subdomain_name' => 'x.a.de'), $transport->anfragen[1]['params']['KasRequestParams']);
	}

	/**
	 * Baut den Schreiber ohne Wartezeiten.
	 *
	 * @param TransportAttrappe $transport Der Transport
	 *
	 * @return Schreiber Der Schreiber
	 */
	private function schreiber(TransportAttrappe $transport): Schreiber
	{
		return new Schreiber(new KasClient($transport, new SoapKodierer(), 'https://kas.test/KasApi.php', 'https://kas.test/KasAuth.php', null, static function (): void {
		}));
	}

	/**
	 * Baut einen Zugang ohne Schreibschutz.
	 *
	 * @return Zugangsdaten Die Daten
	 */
	private function zugang(): Zugangsdaten
	{
		return new Zugangsdaten('w0123456', 'geheim', false, 1);
	}
}
