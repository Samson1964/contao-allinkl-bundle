<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\DataContainer;
use Schachbulle\ContaoAllinklBundle\Backend\Module;
use Schachbulle\ContaoAllinklBundle\Sicherheit\Zugang;

/**
 * Schützt die Modulfreigaben der Administratoren am Backend-Benutzer.
 *
 * Nur der Hauptadmin sieht und ändert das Feld allinkl_module; für alle
 * anderen – auch Administratoren – wird es beim Laden aus der Palette, aus
 * dem Filter und aus der Ansicht „Anzeigen“ genommen, und ein save_callback
 * weist Änderungen zusätzlich ab.
 */
class BenutzerListener
{
	/**
	 * @param Zugang $zugang Erkennt den Hauptadmin
	 */
	public function __construct(private readonly Zugang $zugang)
	{
	}

	/**
	 * Macht das Feld für alle außer dem Hauptadmin unsichtbar.
	 *
	 * Ändert nur $GLOBALS['TL_DCA'] der laufenden Anfrage.
	 */
	#[AsCallback(table: 'tl_user', target: 'config.onload')]
	public function feldEinschraenken(): void
	{
		if ($this->zugang->istHauptadmin()) {
			return;
		}

		if (isset($GLOBALS['TL_DCA']['tl_user']['palettes']['admin'])) {
			PaletteManipulator::create()
				->removeField('allinkl_module', 'allinkl_legend')
				->applyToPalette('admin', 'tl_user');
		}

		$GLOBALS['TL_DCA']['tl_user']['fields']['allinkl_module']['filter'] = false;
		$GLOBALS['TL_DCA']['tl_user']['fields']['allinkl_module']['eval']['doNotShow'] = true;
	}

	/**
	 * Weist Änderungen durch andere als den Hauptadmin ab.
	 *
	 * @param mixed         $wert Der neue Wert
	 * @param DataContainer $dc   Der Data Container
	 *
	 * @return mixed Der unveränderte Wert
	 *
	 * @throws \RuntimeException Für alle außer dem Hauptadmin
	 */
	#[AsCallback(table: 'tl_user', target: 'fields.allinkl_module.save')]
	public function freigabeSchuetzen(mixed $wert, DataContainer $dc): mixed
	{
		if (!$this->zugang->istHauptadmin()) {
			throw new \RuntimeException('Nur der Hauptadmin darf die All-Inkl-Module freigeben.');
		}

		return $wert;
	}

	/**
	 * Liefert die Module zur Auswahl.
	 *
	 * @return array<string, string> Modul => Beschriftung aus TL_LANG['MOD']
	 */
	#[AsCallback(table: 'tl_user', target: 'fields.allinkl_module.options')]
	public function module(): array
	{
		$optionen = array();

		foreach (array_keys(Module::ALLE) as $modul) {
			$text = $GLOBALS['TL_LANG']['MOD'][$modul] ?? $modul;
			$optionen[$modul] = \is_array($text) ? (string) ($text[0] ?? $modul) : (string) $text;
		}

		return $optionen;
	}
}
