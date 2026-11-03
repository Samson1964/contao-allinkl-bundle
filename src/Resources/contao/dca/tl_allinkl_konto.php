<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

use Contao\DC_Table;
use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;
use Schachbulle\ContaoAllinklBundle\Bereich\DcaBauer;
use Schachbulle\ContaoAllinklBundle\EventListener\DataContainer\ObjektListener;

/*
 * Die KAS-Zugänge: je All-Inkl-Account (Haupt- oder Unteraccount) ein
 * Datensatz mit Login und verschlüsseltem Kennwort. Dazu die
 * Accounteinstellungen, die der Abgleich einliest (Bereich „einstellungen“).
 *
 * Callbacks stehen in EventListener\DataContainer\KontoListener.
 */
$einstellungen = BereichRegister::bereich('einstellungen');

$GLOBALS['TL_DCA']['tl_allinkl_konto'] = array
(
	'config' => array
	(
		'dataContainer'    => DC_Table::class,
		'enableVersioning' => true,
		'notCopyable'      => true,
		'sql'              => array
		(
			'keys' => array
			(
				'id' => 'primary',
			),
		),
	),

	'list' => array
	(
		'sorting' => array
		(
			'mode'        => 1,
			'fields'      => array('title'),
			'flag'        => 1,
			'panelLayout' => 'filter;search,limit',
		),
		'label' => array
		(
			'fields' => array('title', 'kas_login'),
			'format' => '%s [%s]',
		),
		'global_operations' => array
		(
			'abgleich' => array
			(
				'label'      => array('Alle abgleichen', 'Alle aktiven KAS-Zugänge vollständig neu einlesen'),
				'href'       => 'key=abgleich',
				'icon'       => 'sync.svg',
				'class'      => 'header_sync',
				'primary'    => true,
				'attributes' => 'onclick="Backend.getScrollOffset()"',
			),
			'all' => array
			(
				'href'       => 'act=select',
				'class'      => 'header_edit_all',
				'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
			),
		),
		'operations' => array
		(
			'edit' => array
			(
				'label'   => array('Bearbeiten', 'Zugang %s bearbeiten'),
				'href'    => 'act=edit',
				'icon'    => 'edit.svg',
				'primary' => true,
			),
			'abgleich' => array
			(
				'label'      => array('Abgleichen', 'Alle Bereiche dieses Zugangs bei All-Inkl neu einlesen'),
				'href'       => 'key=abgleich',
				'icon'       => 'sync.svg',
				'primary'    => true,
				'attributes' => 'onclick="Backend.getScrollOffset()"',
			),
			'pruefen' => array
			(
				'label'   => array('Anmeldung prüfen', 'Prüfen, ob die KAS Login und Kennwort annimmt'),
				'href'    => 'key=pruefen',
				'icon'    => 'ok.svg',
				'primary' => true,
			),
			'show' => array
			(
				'label'   => array('Anzeigen', 'Zugang %s anzeigen'),
				'href'    => 'act=show',
				'icon'    => 'show.svg',
				'primary' => true,
			),
			'delete' => array
			(
				'label'      => array('Löschen', 'Zugang %s löschen'),
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'primary'    => true,
				'attributes' => 'onclick="if(!confirm(\'Der Zugang und alle hier gespiegelten Daten werden gelöscht. Bei All-Inkl bleibt alles bestehen. Fortfahren?\'))return false;Backend.getScrollOffset()"',
			),
		),
	),

	'palettes' => array
	(
		'default' => '{titel_legend},title,aktiv;{anmeldung_legend},kas_login,kas_passwort,schreibschutz;{abgleich_legend},abgleich_taeglich,letzter_abgleich,abgleich_bericht;{notiz_legend:hide},notiz;'.str_replace('{einstellungen_legend}', '{einstellungen_legend:hide}', DcaBauer::palette($einstellungen, 'bestehend')),
	),

	'fields' => array_merge(array
	(
		'id' => array
		(
			'sql' => 'int(10) unsigned NOT NULL auto_increment',
		),
		'tstamp' => array
		(
			'sql' => 'int(10) unsigned NOT NULL default 0',
		),
		'title' => array
		(
			'label'     => array('Bezeichnung', 'Ein Name für den Zugang, etwa „Vereinsseite“.'),
			'exclude'   => false,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 255, 'tl_class' => 'w50'),
			'sql'       => "varchar(255) NOT NULL default ''",
		),
		'aktiv' => array
		(
			'label'     => array('Aktiv', 'Nur aktive Zugänge werden abgeglichen und stehen beim Anlegen zur Wahl.'),
			'exclude'   => false,
			'filter'    => true,
			'default'   => '1',
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'w50 m12'),
			'sql'       => "char(1) NOT NULL default '1'",
		),
		'kas_login' => array
		(
			'label'     => array('KAS-Login', 'Das Login des Accounts, etwa „w0123456“.'),
			'exclude'   => false,
			'search'    => true,
			'inputType' => 'text',
			'eval'      => array('mandatory' => true, 'maxlength' => 32, 'tl_class' => 'w50'),
			'sql'       => "varchar(32) NOT NULL default ''",
		),
		'kas_passwort' => array
		(
			'label'     => array('KAS-Kennwort', 'Wird verschlüsselt gespeichert und nie angezeigt. Leer lassen oder den Platzhalter stehen lassen, um es zu behalten. Die Zwei-Faktor-Anmeldung muss für diesen Account aus sein.'),
			'exclude'   => false,
			'inputType' => 'text',
			'eval'      => array('maxlength' => 255, 'hideInput' => true, 'preserveTags' => true, 'decodeEntities' => true, 'doNotShow' => true, 'doNotCopy' => true, 'autocomplete' => 'new-password', 'tl_class' => 'w50'),
			'sql'       => 'text NULL',
		),
		'schreibschutz' => array
		(
			'label'     => array('Schreibschutz', 'Solange gesetzt, schickt das Bundle nur lesende Aufrufe an die KAS. Anlegen, Ändern und Löschen werden abgewiesen. Zum Ausprobieren eingeschaltet lassen.'),
			'exclude'   => false,
			'filter'    => true,
			'default'   => '1',
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'w50 m12'),
			'sql'       => "char(1) NOT NULL default '1'",
		),
		'abgleich_taeglich' => array
		(
			'label'     => array('Täglich abgleichen', 'Liest den Zugang einmal am Tag über Contaos Cron neu ein.'),
			'exclude'   => false,
			'inputType' => 'checkbox',
			'eval'      => array('tl_class' => 'w50 m12'),
			'sql'       => "char(1) NOT NULL default ''",
		),
		'letzter_abgleich' => array
		(
			'label'     => array('Letzter Abgleich', ''),
			'inputType' => 'text',
			'load_callback' => array(static fn ($wert) => (int) $wert > 0 ? $wert : ''),
			'eval'      => array('rgxp' => 'datim', 'readonly' => true, 'doNotCopy' => true, 'tl_class' => 'w50'),
			'sql'       => 'int(10) unsigned NOT NULL default 0',
		),
		'abgleich_bericht' => array
		(
			'label'     => array('Bericht des letzten Abgleichs', ''),
			'inputType' => 'textarea',
			'eval'      => array('readonly' => true, 'doNotCopy' => true, 'style' => 'height:8em', 'tl_class' => 'clr'),
			'sql'       => 'text NULL',
		),
		'notiz' => array
		(
			'label'     => array('Notiz', 'Nur für dieses Backend, geht nicht an All-Inkl.'),
			'exclude'   => false,
			'inputType' => 'textarea',
			'eval'      => array('style' => 'height:6em', 'tl_class' => 'clr'),
			'sql'       => 'text NULL',
		),
		'kas_daten' => array
		(
			'label' => array('KAS-Rohdaten der Accounteinstellungen', ''),
			'sql'   => 'mediumtext NULL',
		),
	), DcaBauer::felder($einstellungen)),
);

// Das neue KAS-Kennwort der Accounteinstellungen geht wie alle Kennwörter
// der Spiegeltabellen über die Passwortablage
$GLOBALS['TL_DCA']['tl_allinkl_konto']['fields']['account_password']['save_callback'] = array(array(ObjektListener::class, 'kennwortMerken'));

// Nach Protokoll oder Statistik filtert in der Liste der Zugänge niemand
foreach (array_keys($einstellungen->felder) as $spalte) {
	unset($GLOBALS['TL_DCA']['tl_allinkl_konto']['fields'][$spalte]['filter']);
}

unset($einstellungen);
