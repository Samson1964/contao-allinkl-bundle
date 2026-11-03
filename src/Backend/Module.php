<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Backend;

/**
 * Die Backend-Module der Menügruppe „All-Inkl“ und ihre Tabellen.
 *
 * Zentrale Liste für die Menüdefinition (config.php), die Modulfreigaben
 * der Administratoren (tl_user.allinkl_module) und die Zugriffsprüfungen.
 */
final class Module
{
	/** Schlüssel der Menügruppe in $GLOBALS['BE_MOD'] */
	public const GRUPPE = 'allinkl';

	/** Modul => Tabellen, in Menüreihenfolge */
	public const ALLE = array(
		'allinkl_konten'            => array('tl_allinkl_konto'),
		'allinkl_accounts'          => array('tl_allinkl_account'),
		'allinkl_domains'           => array('tl_allinkl_domain', 'tl_allinkl_subdomain', 'tl_allinkl_dns'),
		'allinkl_postfaecher'       => array('tl_allinkl_mailkonto'),
		'allinkl_weiterleitungen'   => array('tl_allinkl_weiterleitung'),
		'allinkl_mailinglisten'     => array('tl_allinkl_mailingliste'),
		'allinkl_datenbanken'       => array('tl_allinkl_datenbank'),
		'allinkl_ftp'               => array('tl_allinkl_ftpnutzer'),
		'allinkl_cronjobs'          => array('tl_allinkl_cronjob'),
		'allinkl_verzeichnisschutz' => array('tl_allinkl_verzeichnisschutz'),
		'allinkl_ddns'              => array('tl_allinkl_ddns'),
		'allinkl_netzlaufwerke'     => array('tl_allinkl_netzlaufwerk'),
		'allinkl_werkzeuge'         => array(),
	);

	/**
	 * Liefert das Modul, zu dem eine Tabelle gehört.
	 *
	 * @param string $tabelle Etwa „tl_allinkl_dns“
	 *
	 * @return string|null Etwa „allinkl_domains“, null für fremde Tabellen
	 */
	public static function zurTabelle(string $tabelle): ?string
	{
		foreach (self::ALLE as $modul => $tabellen) {
			if (\in_array($tabelle, $tabellen, true)) {
				return $modul;
			}
		}

		return null;
	}

	/**
	 * Sagt, ob ein Modul zur Gruppe gehört.
	 *
	 * @param string $modul Wert von „do“
	 *
	 * @return bool true für die Module dieses Bundles
	 */
	public static function istEigenes(string $modul): bool
	{
		return isset(self::ALLE[$modul]);
	}
}
