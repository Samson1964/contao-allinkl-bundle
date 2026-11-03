<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoAllinklBundle\Bereich\Legenden;

// Die Beschriftungen der Felder stehen in der DCA, hier nur die Legenden
$GLOBALS['TL_LANG']['tl_allinkl_konto'] = array_merge($GLOBALS['TL_LANG']['tl_allinkl_konto'] ?? array(), Legenden::TEXTE);
