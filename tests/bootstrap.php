<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * Bootstrap für die Unit-Tests.
 *
 * Die Tests brauchen nur die Klassen des Bundles und einige Contao- und
 * Symfony-Klassen aus dem eigenen vendor/; Netz und Datenbank werden durch
 * Attrappen ersetzt.
 */

$autoload = __DIR__.'/../vendor/autoload.php';

if (!is_file($autoload)) {
	fwrite(STDERR, "vendor/autoload.php fehlt. Bitte zuerst im Bundle-Verzeichnis ausführen:\n/c/xampp/php/php.exe C:/Users/samso/bin/composer.phar install --no-security-blocking\n");

	exit(1);
}

require $autoload;
