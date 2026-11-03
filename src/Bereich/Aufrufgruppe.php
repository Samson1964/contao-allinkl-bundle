<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Bereich;

use Schachbulle\ContaoAllinklBundle\Kas\Zugangsdaten;

/**
 * Felder eines Bereichs, die über eine eigene KAS-Funktion geändert werden.
 *
 * Beispiele: Die SSL-Angaben einer Domain gehen über update_ssl, die
 * SSH-Freigabe eines Unteraccounts über update_superusersettings, das
 * Verschieben einer Domain über move_domain. Sie stehen in derselben Maske
 * wie die übrigen Felder; beim Speichern wird die Gruppe nur aufgerufen,
 * wenn sich eines ihrer Felder geändert hat.
 */
final class Aufrufgruppe
{
	/**
	 * @param \Closure(array<string, mixed>, array, Zugangsdaten): array<int, array{0: string, 1: array<string, mixed>}> $aufrufe
	 *        Bildet aus den geänderten Parametern der Gruppe, der Zeile und
	 *        dem Zugang die Liste der Aufrufe als Paare aus Funktion und
	 *        Parametern
	 * @param bool $entferntObjekt true: Nach Erfolg gehört das Objekt nicht
	 *        mehr zu diesem Zugang (Verschieben), die Zeile wird gelöscht
	 */
	public function __construct(
		public readonly \Closure $aufrufe,
		public readonly bool $entferntObjekt = false,
	) {
	}

	/**
	 * Legt eine Gruppe mit genau einem Aufruf an.
	 *
	 * @param string                       $aktion     Die KAS-Funktion
	 * @param \Closure(array): array       $schluessel Bildet aus der Zeile die
	 *                                                 Schlüsselparameter
	 *
	 * @return self Die Gruppe
	 */
	public static function einfach(string $aktion, \Closure $schluessel): self
	{
		return new self(static fn (array $geaendert, array $zeile): array => array(
			array($aktion, $schluessel($zeile) + $geaendert),
		));
	}
}
