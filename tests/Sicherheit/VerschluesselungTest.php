<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Tests\Sicherheit;

use PHPUnit\Framework\TestCase;
use Schachbulle\ContaoAllinklBundle\Kas\Fehlertext;
use Schachbulle\ContaoAllinklBundle\Kas\KasFehler;
use Schachbulle\ContaoAllinklBundle\Sicherheit\Verschluesselung;

/**
 * Prüft die Verschlüsselung der Zugangskennwörter und die Fehlertexte.
 */
final class VerschluesselungTest extends TestCase
{
	/**
	 * Hin und zurück ergibt den Klartext, zweimal verschlüsselt verschiedene Texte.
	 */
	public function testHinUndZurueck(): void
	{
		$verschluesselung = new Verschluesselung('app-secret');
		$a = $verschluesselung->verschluesseln('Kennwort mit Ümlaut & <Zeichen>');
		$b = $verschluesselung->verschluesseln('Kennwort mit Ümlaut & <Zeichen>');

		$this->assertNotSame($a, $b);
		$this->assertStringNotContainsString('Kennwort', $a);
		$this->assertSame('Kennwort mit Ümlaut & <Zeichen>', $verschluesselung->entschluesseln($a));
	}

	/**
	 * Mit anderem APP_SECRET lässt sich nichts entschlüsseln.
	 */
	public function testAnderesGeheimnis(): void
	{
		$text = (new Verschluesselung('eins'))->verschluesseln('x');

		$this->expectException(\RuntimeException::class);
		$this->expectExceptionMessage('APP_SECRET');

		(new Verschluesselung('zwei'))->entschluesseln($text);
	}

	/**
	 * Feste Texte und Muster der Fehlerkürzel.
	 */
	public function testFehlertexte(): void
	{
		$this->assertStringContainsString('schreibgeschützt', Fehlertext::zu(new KasFehler('schreibschutz', '', 'add_domain')));
		$this->assertStringContainsString('„domain path“', Fehlertext::zu(new KasFehler('domain_path_syntax_incorrect')));
		$this->assertStringContainsString('Kontingent', Fehlertext::zu(new KasFehler('max_mail_account_reached')));
		$this->assertStringContainsString('findet „subdomain“ nicht', Fehlertext::zu(new KasFehler('subdomain_doenst_exist')));
		$this->assertStringContainsString('(KAS: update_ssl – irgendwas_neues)', Fehlertext::zu(new KasFehler('irgendwas_neues', '', 'update_ssl')));
	}
}
