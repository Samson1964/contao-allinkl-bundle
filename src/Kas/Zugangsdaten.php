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
 * Die Anmeldedaten eines KAS-Zugangs, entschlüsselt und nur im Speicher.
 */
final class Zugangsdaten
{
	/**
	 * @param string $login         Das KAS-Login, etwa „w0123456“
	 * @param string $passwort      Das KAS-Kennwort im Klartext
	 * @param bool   $schreibschutz true: nur lesende Aufrufe (get_*) sind
	 *                              erlaubt, alles andere wird nicht abgeschickt
	 * @param int    $kontoId       ID in tl_allinkl_konto, 0 außerhalb der
	 *                              Datenbank (Tests)
	 */
	public function __construct(
		public readonly string $login,
		public readonly string $passwort,
		public readonly bool $schreibschutz = true,
		public readonly int $kontoId = 0,
	) {
	}
}
