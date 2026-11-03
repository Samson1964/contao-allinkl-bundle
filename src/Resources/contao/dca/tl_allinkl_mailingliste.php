<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;
use Schachbulle\ContaoAllinklBundle\Bereich\DcaBauer;

/*
 * Spiegeltabelle des KAS-Bereichs „mailingliste“. Felder, Liste und Callbacks baut
 * DcaBauer aus der Definition in BereichRegister.
 */
$GLOBALS['TL_DCA']['tl_allinkl_mailingliste'] = DcaBauer::tabelle(BereichRegister::bereich('mailingliste'));
