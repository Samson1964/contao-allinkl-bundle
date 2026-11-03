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
 * Der verwertbare Teil einer KAS-Antwort.
 */
final class KasAntwort
{
	/**
	 * @param mixed $info        Inhalt von „ReturnInfo“: bei get_* meist eine
	 *                           Liste von Feldern, bei add_* oft die neue
	 *                           Kennung (Login, ID), sonst „TRUE“ oder null
	 * @param float $floodDelay  Sekunden, die bis zum nächsten Aufruf vergehen
	 *                           müssen
	 */
	public function __construct(
		public readonly mixed $info,
		public readonly float $floodDelay = 0.0,
	) {
	}

	/**
	 * Liefert „ReturnInfo“ als Liste von Datensätzen.
	 *
	 * Die KAS schickt Listen als Feld von Feldern; fragt man gezielt nach
	 * einem Objekt, kommt mitunter ein einzelnes Feld. Beides wird hier zur
	 * Liste. Alles andere (Text, Zahl, leer) ergibt eine leere Liste.
	 *
	 * @return array<int, array<string, mixed>> Die Datensätze
	 */
	public function zeilen(): array
	{
		if (!\is_array($this->info) || array() === $this->info) {
			return array();
		}

		if (!array_is_list($this->info)) {
			return array($this->info);
		}

		return array_values(array_filter($this->info, 'is_array'));
	}

	/**
	 * Liefert „ReturnInfo“ als Text, etwa die Kennung eines neuen Objekts.
	 *
	 * @return string Der Text; leer, wenn ReturnInfo ein Feld oder leer ist
	 *                oder nur „TRUE“ enthält
	 */
	public function kennung(): string
	{
		if (!\is_scalar($this->info) || \is_bool($this->info)) {
			return '';
		}

		$text = trim((string) $this->info);

		return 'TRUE' === strtoupper($text) ? '' : $text;
	}
}
