<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Bereich;

/**
 * Eine Eingabe in der Maske reicht für den KAS-Aufruf nicht aus.
 *
 * Die Meldung ist auf Deutsch und für die Anzeige im Backend gedacht. Der
 * Aufruf an die KAS ist in diesem Fall nicht erfolgt.
 */
class Eingabefehler extends \RuntimeException
{
}
