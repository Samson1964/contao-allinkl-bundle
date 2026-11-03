<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\CoreBundle\DataContainer\PaletteManipulator;

/*
 * Modulfreigaben für Administratoren. Das Feld steht nur in der Palette
 * „admin“, denn nur für Administratoren gilt die Einschränkung; alle
 * übrigen Benutzer bekommen die Module über die Modulrechte ihrer Gruppen.
 * Sichtbar und änderbar ist es allein für den Hauptadmin
 * (EventListener\DataContainer\BenutzerListener).
 */
$GLOBALS['TL_DCA']['tl_user']['fields']['allinkl_module'] = array
(
	'exclude'   => true,
	'inputType' => 'checkbox',
	'eval'      => array('multiple' => true, 'tl_class' => 'clr'),
	'sql'       => 'blob NULL',
);

if (isset($GLOBALS['TL_DCA']['tl_user']['palettes']['admin'])) {
	PaletteManipulator::create()
		->addLegend('allinkl_legend', 'admin_legend', PaletteManipulator::POSITION_AFTER)
		->addField('allinkl_module', 'allinkl_legend', PaletteManipulator::POSITION_APPEND)
		->applyToPalette('admin', 'tl_user');
}
