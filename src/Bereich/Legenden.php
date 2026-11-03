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
 * Die Beschriftungen der Legenden aller Masken.
 *
 * Contao liest Legenden nur aus $GLOBALS['TL_LANG'][tabelle][legende]; die
 * Sprachdateien der Tabellen übernehmen deshalb alle aus dieser einen
 * Liste.
 */
final class Legenden
{
	/** Legende => Beschriftung */
	public const TEXTE = array(
		'konto_legend'         => 'KAS-Zugang',
		'kas_legend'           => 'Angaben',
		'host_legend'          => 'Host des Unteraccounts',
		'zugang_legend'        => 'Kennwörter',
		'kontingent_legend'    => 'Kontingente',
		'rechte_legend'        => 'Rechte',
		'ssh_legend'           => 'SSH',
		'ssl_legend'           => 'SSL',
		'verschieben_legend'   => 'In anderen Account verschieben',
		'responder_legend'     => 'Abwesenheitsnotiz',
		'xlist_legend'         => 'IMAP-Sonderordner',
		'filter_legend'        => 'Spamfilter',
		'zeit_legend'          => 'Zeitplan',
		'auth_legend'          => 'HTTP-Anmeldung',
		'mail_legend'          => 'Bestätigungsmail',
		'titel_legend'         => 'Zugang',
		'anmeldung_legend'     => 'KAS-Anmeldung',
		'abgleich_legend'      => 'Abgleich',
		'notiz_legend'         => 'Notiz',
		'einstellungen_legend' => 'Accounteinstellungen bei All-Inkl',
	);
}
