<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Cron;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCronJob;
use Psr\Log\LoggerInterface;
use Schachbulle\ContaoAllinklBundle\Abgleich\Abgleicher;
use Schachbulle\ContaoAllinklBundle\Datenbank\KontoSpeicher;

/**
 * Gleicht einmal täglich alle Zugänge ab, bei denen das eingeschaltet ist.
 *
 * Läuft über Contaos Cron (Kommandozeile „contao:cron“ oder der
 * Frontend-Cron). Die Berichte stehen danach an den Zugängen; Fehler gehen
 * zusätzlich ins Systemprotokoll.
 */
#[AsCronJob('daily')]
class TaeglicherAbgleich
{
	/**
	 * @param Abgleicher      $abgleicher Liest die KAS ein
	 * @param KontoSpeicher   $konten     Die Zugänge
	 * @param LoggerInterface $protokoll  Contaos Systemprotokoll
	 */
	public function __construct(
		private readonly Abgleicher $abgleicher,
		private readonly KontoSpeicher $konten,
		private readonly LoggerInterface $protokoll,
	) {
	}

	/**
	 * Führt den Abgleich aus.
	 */
	public function __invoke(): void
	{
		foreach ($this->konten->aktive(true) as $konto) {
			$bericht = $this->abgleicher->abgleichen($konto);

			foreach ($bericht->fehlerListe() as $text) {
				$this->protokoll->error(sprintf('All-Inkl-Abgleich „%s“: %s', $bericht->zugang, $text));
			}
		}
	}
}
