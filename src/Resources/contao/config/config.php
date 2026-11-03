<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoAllinklBundle\Backend\Aktionen;
use Schachbulle\ContaoAllinklBundle\Backend\Module;
use Schachbulle\ContaoAllinklBundle\Backend\WerkzeugeModul;

/*
 * Menügruppe „All-Inkl“ im Backend, vor der Gruppe „System“.
 *
 * Jedes Tabellenmodul hat den key-Callback „abgleich“; die Zugänge zusätzlich
 * „pruefen“, die Domains „dnsreset“. Wer ein Modul sehen darf, entscheiden
 * Sicherheit\Zugang, EventListener\MenueListener und
 * EventListener\ZugriffListener: Der Hauptadmin sieht alles, andere
 * Administratoren nur ihre Freigaben, alle übrigen Benutzer arbeiten mit den
 * gewöhnlichen Modulrechten.
 */
$allinkl = array();

foreach (Module::ALLE as $modul => $tabellen) {
	$allinkl[$modul] = array() === $tabellen
		? array('callback' => WerkzeugeModul::class, 'stylesheet' => 'bundles/contaoallinkl/backend.css')
		: array('tables' => $tabellen, 'abgleich' => array(Aktionen::class, 'abgleich'), 'stylesheet' => 'bundles/contaoallinkl/backend.css');
}

$allinkl['allinkl_konten']['pruefen'] = array(Aktionen::class, 'pruefen');
$allinkl['allinkl_domains']['dnsreset'] = array(Aktionen::class, 'dnsZuruecksetzen');

$position = array_search('system', array_keys($GLOBALS['BE_MOD']), true);
$position = false === $position ? \count($GLOBALS['BE_MOD']) : $position;

$GLOBALS['BE_MOD'] = array_merge(
	array_slice($GLOBALS['BE_MOD'], 0, $position, true),
	array(Module::GRUPPE => $allinkl),
	array_slice($GLOBALS['BE_MOD'], $position, null, true),
);

unset($allinkl, $position);
