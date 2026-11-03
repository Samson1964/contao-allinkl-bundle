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

/**
 * Prüft die Umrechnung zwischen KAS und Spiegeltabelle.
 */
final class AbbildTest extends TestCase
{
	/**
	 * Tippfehler-Varianten der KAS, Y/N und Listen werden umgerechnet.
	 */
	public function testSpaltenAusKas(): void
	{
		$spalten = Abbild::spaltenAusKas(BereichRegister::bereich('postfach'), array(
			'mail_login'        => 'm0123456',
			'mail_adresses'     => 'info@a.de',
			'webmail_autologin' => 'Y',
			'mail_xlist_enabled' => 'N',
			'mail_password'     => 'darf-nicht-rein',
		));

		$this->assertSame('m0123456', $spalten['mail_login']);
		$this->assertSame('info@a.de', $spalten['mail_adressen']);
		$this->assertSame('1', $spalten['webmail_autologin']);
		$this->assertSame('', $spalten['mail_xlist_enabled']);
		$this->assertArrayNotHasKey('passwort', $spalten);

		$ziele = Abbild::spaltenAusKas(BereichRegister::bereich('weiterleitung'), array('mail_forward_targets' => 'a@x.de, b@y.de'));
		$this->assertSame("a@x.de\nb@y.de", $ziele['ziele']);
	}

	/**
	 * Kennwörter und private Schlüssel landen nie in kas_daten.
	 */
	public function testBereinigen(): void
	{
		$bereinigt = Abbild::bereinigen(array(
			'domain_name'              => 'a.de',
			'ssl_certificate_sni_key'  => '-----BEGIN PRIVATE KEY-----',
			'mail_password'            => 'x',
			'ftp_passwort'             => 'x',
			'show_password'            => 'Y',
		));

		$this->assertSame(array('domain_name' => 'a.de', 'show_password' => 'Y'), $bereinigt);
	}

	/**
	 * add_domain bekommt Name und TLD getrennt, leere Angaben entfallen.
	 */
	public function testAnlegenParamsDomain(): void
	{
		$params = Abbild::anlegenParams(BereichRegister::bereich('domain'), array(
			'domain_name'        => 'Verein.co.uk',
			'domain_path'        => '',
			'redirect_status'    => '0',
			'php_version'        => '8.3',
			'statistic_version'  => '5',
			'statistic_language' => 'de',
		), array(), null);

		$this->assertSame('verein', $params['domain_name']);
		$this->assertSame('co.uk', $params['domain_tld']);
		$this->assertSame('8.3', $params['php_version']);
		$this->assertArrayNotHasKey('domain_path', $params);
	}

	/**
	 * Ziele einer Weiterleitung gehen als target_0, target_1 … hinaus.
	 */
	public function testAnlegenParamsDurchnummeriert(): void
	{
		$params = Abbild::anlegenParams(BereichRegister::bereich('weiterleitung'), array(
			'local_part'  => 'info',
			'domain_part' => 'a.de',
			'ziele'       => "x@b.de\r\ny@c.de\n",
		), array(), null);

		$this->assertSame(array('local_part' => 'info', 'domain_part' => 'a.de', 'target_0' => 'x@b.de', 'target_1' => 'y@c.de'), $params);
	}

	/**
	 * Nur Geänderte gehen hinaus, nach Gruppen getrennt; Kennwörter immer.
	 */
	public function testAenderungen(): void
	{
		$bereich = BereichRegister::bereich('domain');
		$kas = array('domain_name' => 'a.de', 'domain_path' => '/a/', 'domain_redirect_status' => '0', 'php_version' => '8.2', 'is_active' => 'Y', 'ssl_certificate_sni_force_https' => 'N');
		$zeile = Abbild::spaltenAusKas($bereich, $kas) + array('kas_daten' => Abbild::alsJson($kas), 'kas_schluessel' => 'a.de');

		$this->assertSame(array(), Abbild::aenderungen($bereich, $zeile, array()));

		$zeile['php_version'] = '8.3';
		$zeile['ssl_https'] = '1';
		$gruppen = Abbild::aenderungen($bereich, $zeile, array('ssl_schluessel' => 'KEY'));

		$this->assertSame(array('php_version' => '8.3'), $gruppen['']);
		$this->assertSame(array('ssl_certificate_force_https' => 'Y', 'ssl_certificate_sni_key' => 'KEY'), $gruppen['ssl']);
	}

	/**
	 * Die Konfiguration einer Mailingliste geht bei jeder Änderung mit.
	 */
	public function testImmerSenden(): void
	{
		$bereich = BereichRegister::bereich('mailingliste');
		$kas = array('mailinglist_name' => 'm', 'mailinglist_config' => 'a=1', 'mailinglist_is_active' => 'Y', 'mailinglist_subscriber' => "a@x.de");
		$zeile = Abbild::spaltenAusKas($bereich, $kas) + array('kas_daten' => Abbild::alsJson($kas));

		$this->assertSame(array(), Abbild::aenderungen($bereich, $zeile, array()));

		$zeile['subscriber'] = "a@x.de\nMax Muster <b@y.de>";
		$this->assertSame(array('subscriber' => "a@x.de\nMax Muster <b@y.de>", 'config' => 'a=1'), Abbild::aenderungen($bereich, $zeile, array())['']);
	}

	/**
	 * Pflichtfelder beim Anlegen, Kennwörter aus der Ablage.
	 */
	public function testFehlendBeimAnlegen(): void
	{
		$bereich = BereichRegister::bereich('datenbank');
		$fehlend = Abbild::fehlendBeimAnlegen($bereich, array('database_comment' => 'Test'), array());

		$this->assertSame(array('passwort'), array_map(static fn ($feld): string => $feld->spalte, $fehlend));
		$this->assertSame(array(), Abbild::fehlendBeimAnlegen($bereich, array('database_comment' => 'Test'), array('passwort' => 'x')));
	}
}
