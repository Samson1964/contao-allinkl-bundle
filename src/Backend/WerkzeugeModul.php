<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Backend;

use Contao\BackendModule;
use Contao\System;

/**
 * Das Backend-Modul „Werkzeuge“.
 *
 * Ein klassisches Modul über 'callback' in $GLOBALS['BE_MOD']; Contao legt es
 * mit „new“ an, in 4.13 wie in 5.7. Die Arbeit erledigt der Dienst
 * Werkzeuge, das Modul reicht nur dessen Daten an das Template weiter.
 */
class WerkzeugeModul extends BackendModule
{
	/** @var string Das Template */
	protected $strTemplate = 'be_allinkl_werkzeuge';

	/**
	 * Füllt das Template.
	 *
	 * Das Anfragetoken für die Formulare kommt aus Contaos Token-Verwaltung;
	 * die Konstante REQUEST_TOKEN gibt es in Contao 5 nicht mehr.
	 */
	protected function compile(): void
	{
		$container = System::getContainer();

		foreach ($container->get(Werkzeuge::class)->daten() as $name => $wert) {
			$this->Template->$name = $wert;
		}

		$this->Template->token = $container->get('contao.csrf.token_manager')->getDefaultTokenValue();
		$this->Template->formular = Werkzeuge::FORMULAR;
	}
}
