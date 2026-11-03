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
use Schachbulle\ContaoAllinklBundle\Backend\Module;
use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;
use Schachbulle\ContaoAllinklBundle\Bereich\DcaBauer;
use Schachbulle\ContaoAllinklBundle\Bereich\Feld;
use Schachbulle\ContaoAllinklBundle\Bereich\Legenden;

/**
 * Prüft die Bereichsdefinitionen gegen die Dokumentation der KAS.
 */
final class BereichRegisterTest extends TestCase
{
	/**
	 * Alle 73 Funktionen der KAS-API laut kasapi.kasserver.com/dokumentation
	 * (Stand 2026-11-03).
	 */
	public const KAS_FUNKTIONEN = array(
		'add_account', 'add_cronjob', 'add_database', 'add_ddnsuser', 'add_directoryprotection', 'add_dns_settings',
		'add_domain', 'add_ftpusers', 'add_mailaccount', 'add_mailforward', 'add_mailinglist', 'add_mailstandardfilter',
		'add_sambauser', 'add_session', 'add_softwareinstall', 'add_subdomain', 'add_symlink', 'delete_account',
		'delete_cronjob', 'delete_database', 'delete_ddnsuser', 'delete_directoryprotection', 'delete_dns_settings',
		'delete_domain', 'delete_ftpuser', 'delete_mailaccount', 'delete_mailforward', 'delete_mailinglist',
		'delete_mailstandardfilter', 'delete_sambauser', 'delete_session', 'delete_subdomain', 'get_accountresources',
		'get_accounts', 'get_accountsettings', 'get_cronjobs', 'get_databases', 'get_ddnsusers',
		'get_directoryprotection', 'get_dns_settings', 'get_domains', 'get_ftpusers', 'get_mailaccounts',
		'get_mailforwards', 'get_mailinglists', 'get_mailstandardfilter', 'get_sambausers', 'get_server_information',
		'get_softwareinstall', 'get_space', 'get_space_usage', 'get_subdomains', 'get_topleveldomains', 'get_traffic',
		'move_domain', 'move_subdomain', 'reset_dns_settings', 'update_account', 'update_accountsettings',
		'update_chown', 'update_cronjob', 'update_database', 'update_ddnsuser', 'update_directoryprotection',
		'update_dns_settings', 'update_domain', 'update_ftpuser', 'update_mailaccount', 'update_mailforward',
		'update_mailinglist', 'update_sambauser', 'update_ssl', 'update_subdomain', 'update_superusersettings',
	);

	/**
	 * Funktionen, die nicht über einen Bereich laufen, und wo sie stehen.
	 */
	private const AUSSERHALB = array(
		'get_accountresources', 'get_server_information', 'get_softwareinstall', 'get_space', 'get_space_usage',
		'get_topleveldomains', 'get_traffic', 'get_mailstandardfilter', 'update_chown', 'add_symlink',
		'add_softwareinstall', 'reset_dns_settings',
		// KasClient meldet sich über KasAuth an, das dieselbe Sitzung erzeugt
		'add_session', 'delete_session',
	);

	/**
	 * Jede in den Bereichen genannte Funktion gibt es wirklich, und zusammen
	 * mit Werkzeugen, DNS-Rücksetzen und Anmeldung ist jede abgedeckt.
	 */
	public function testAlleFunktionenAbgedeckt(): void
	{
		$genutzt = array();

		foreach (BereichRegister::alle() as $bereich) {
			foreach (array($bereich->lesenAktion, $bereich->anlegenAktion, $bereich->aendernAktion, $bereich->loeschenAktion) as $aktion) {
				if (null !== $aktion) {
					$this->assertContains($aktion, self::KAS_FUNKTIONEN, $bereich->name);
					$genutzt[] = $aktion;
				}
			}
		}

		// Die Aufrufgruppen nennen ihre Funktionen in Closures; sie stehen hier
		$genutzt = array_merge($genutzt, array('update_ssl', 'update_superusersettings', 'move_domain', 'move_subdomain', 'add_mailstandardfilter', 'delete_mailstandardfilter'), self::AUSSERHALB);

		$this->assertSame(array(), array_values(array_diff(self::KAS_FUNKTIONEN, $genutzt)));
	}

	/**
	 * Jede Spiegeltabelle gehört zu einem Modul, und die DCA lässt sich bauen.
	 */
	public function testDcaFuerAlleBereiche(): void
	{
		foreach (BereichRegister::alle() as $bereich) {
			$this->assertSame($bereich->modul, Module::zurTabelle($bereich->tabelle), $bereich->name);

			if ($bereich->einzeln) {
				continue;
			}

			$dca = DcaBauer::tabelle($bereich);

			$this->assertArrayHasKey($bereich->sortierung, $dca['fields'], $bereich->name.': Sortierfeld fehlt');

			foreach (array('id', 'tstamp', 'konto', 'kas_schluessel', 'kas_daten') as $spalte) {
				$this->assertArrayHasKey($spalte, $dca['fields'], $bereich->name);
			}

			preg_match_all('/\{([a-z_]+?)(?::hide)?\}/', $dca['palettes']['default'], $treffer);

			foreach ($treffer[1] as $legende) {
				$this->assertArrayHasKey($legende, Legenden::TEXTE, $bereich->name.': Legende ohne Text');
			}

			foreach ($bereich->felder as $feld) {
				$this->assertFalse($dca['fields'][$feld->spalte]['exclude'], $bereich->name.'.'.$feld->spalte);
			}
		}
	}

	/**
	 * Neue Objekte zeigen nur Felder zum Anlegen, bestehende keine reinen
	 * Anlegefelder ohne KAS-Wert.
	 */
	public function testPalettenJeZustand(): void
	{
		$bereich = BereichRegister::bereich('postfach');

		$neu = DcaBauer::palette($bereich, 'neu');
		$this->assertStringContainsString('konto', $neu);
		$this->assertStringContainsString('local_part', $neu);
		$this->assertStringNotContainsString('mail_login', $neu);
		$this->assertStringNotContainsString('spamfilter', $neu);

		$bestehend = DcaBauer::palette($bereich, 'bestehend');
		$this->assertStringNotContainsString('konto', $bestehend);
		$this->assertStringNotContainsString('local_part', $bestehend);
		$this->assertStringContainsString('mail_login', $bestehend);
		$this->assertStringContainsString('spamfilter', $bestehend);
	}

	/**
	 * Kindbereiche haben ptable und pid, der Elternbereich ctable.
	 */
	public function testEltern(): void
	{
		$domain = DcaBauer::tabelle(BereichRegister::bereich('domain'));
		$dns = DcaBauer::tabelle(BereichRegister::bereich('dns'));

		$this->assertSame(array('tl_allinkl_subdomain', 'tl_allinkl_dns'), $domain['config']['ctable']);
		$this->assertSame('tl_allinkl_domain', $dns['config']['ptable']);
		$this->assertArrayHasKey('pid', $dns['fields']);
		$this->assertSame(4, $dns['list']['sorting']['mode']);
	}

	/**
	 * Subdomains finden ihre Domain über die längste passende Endung.
	 */
	public function testSubdomainZuordnung(): void
	{
		$zuordnen = BereichRegister::bereich('subdomain')->zuordnen;
		$domains = array(array('id' => 1, 'domain_name' => 'verein.de'), array('id' => 2, 'domain_name' => 'jugend.verein.de'));

		$this->assertSame(1, $zuordnen(array('subdomain_name' => 'forum.verein.de'), $domains));
		$this->assertSame(2, $zuordnen(array('subdomain_name' => 'www.jugend.verein.de'), $domains));
		$this->assertNull($zuordnen(array('subdomain_name' => 'x.anders.de'), $domains));
	}

	/**
	 * Listen mit Zeilenumbruch als Trenner behalten Leerzeichen im Eintrag.
	 */
	public function testListenTrenner(): void
	{
		$this->assertSame(array('Max Muster <a@b.de>', 'c@d.de'), Feld::liste('x', 'x', '', "\n")->teile("Max Muster <a@b.de>\r\nc@d.de\n"));
		$this->assertSame(array('a@b.de', 'c@d.de'), Feld::liste('x', 'x')->teile('a@b.de, c@d.de'));
	}
}
