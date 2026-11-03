<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Backend;

/**
 * Hält die Kennwörter einer Maske für die Dauer einer Anfrage fest.
 *
 * Kennwortfelder der Spiegeltabellen werden nie gespeichert: Ihr
 * save_callback legt den eingegebenen Wert hier ab und gibt einen leeren
 * Text an Contao zurück. Der onsubmit_callback holt ihn danach für den
 * KAS-Aufruf ab. In 4.13 wie in 5.7 laufen alle save_callbacks vor dem
 * onsubmit_callback derselben Anfrage.
 */
class Passwortablage
{
	/** @var array<string, array<string, string>> Tabelle => Spalte => Kennwort */
	private array $werte = array();

	/**
	 * Legt ein Kennwort ab.
	 *
	 * @param string $tabelle Die Tabelle der Maske
	 * @param string $spalte  Das Feld
	 * @param string $wert    Das eingegebene Kennwort; leer wird ignoriert
	 */
	public function ablegen(string $tabelle, string $spalte, string $wert): void
	{
		if ('' !== $wert) {
			$this->werte[$tabelle][$spalte] = $wert;
		}
	}

	/**
	 * Gibt alle Kennwörter einer Tabelle heraus und vergisst sie.
	 *
	 * @param string $tabelle Die Tabelle der Maske
	 *
	 * @return array<string, string> Spalte => Kennwort
	 */
	public function abholen(string $tabelle): array
	{
		$werte = $this->werte[$tabelle] ?? array();
		unset($this->werte[$tabelle]);

		return $werte;
	}
}
