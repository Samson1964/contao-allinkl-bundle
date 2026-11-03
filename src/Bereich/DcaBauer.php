<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Bereich;

use Contao\DC_Table;
use Schachbulle\ContaoAllinklBundle\EventListener\DataContainer\ObjektListener;

/**
 * Baut die DCA-Definitionen der Spiegeltabellen aus den Bereichen.
 *
 * Alle Spiegeltabellen gleichen sich bis auf ihre Felder: Spalten für
 * Zugang und KAS-Stand, Liste mit Abgleich-Knopf, Callbacks für das
 * Durchreichen an die KAS. Statt dreizehn fast gleicher DCA-Dateien gibt
 * es deshalb diese eine Bauanleitung; die DCA-Dateien rufen nur noch
 * tabelle() auf. Andere Bundles können das Ergebnis wie gewohnt über
 * $GLOBALS['TL_DCA'] erweitern.
 *
 * Die Callbacks verweisen auf den öffentlichen Dienst ObjektListener;
 * System::importStatic() holt ihn in 4.13 wie in 5.7 aus dem Container,
 * weil die Dienst-ID ein Klassenname mit Backslash ist.
 */
final class DcaBauer
{
	/**
	 * Baut die vollständige DCA einer Spiegeltabelle.
	 *
	 * @param Bereich $bereich Der Bereich
	 *
	 * @return array<string, mixed> Inhalt für $GLOBALS['TL_DCA'][tabelle]
	 */
	public static function tabelle(Bereich $bereich): array
	{
		$kinder = BereichRegister::kinder($bereich->name);
		$eltern = null !== $bereich->eltern ? BereichRegister::bereich($bereich->eltern) : null;
		$listener = ObjektListener::class;

		$konfiguration = array(
			'dataContainer'    => DC_Table::class,
			'enableVersioning' => false,
			'notCopyable'      => true,
			'closed'           => !$bereich->kannAnlegen(),
			'notDeletable'     => !$bereich->kannLoeschen(),
			'onload_callback'  => array(array($listener, 'laden')),
			'onsubmit_callback' => array(array($listener, 'speichern')),
			'ondelete_callback' => array(array($listener, 'loeschen')),
			'sql'              => array(
				'keys' => array(
					'id'             => 'primary',
					'konto'          => 'index',
					'kas_schluessel' => 'index',
				),
			),
		);

		if (null !== $eltern) {
			$konfiguration['ptable'] = $eltern->tabelle;
			$konfiguration['sql']['keys']['pid'] = 'index';
		}

		if (array() !== $kinder) {
			$konfiguration['ctable'] = array_values(array_map(static fn (Bereich $kind): string => $kind->tabelle, $kinder));
		}

		return array(
			'config'   => $konfiguration,
			'list'     => self::liste($bereich, $eltern, $kinder),
			'palettes' => array('default' => self::palette($bereich, 'alle')),
			'fields'   => self::grundfelder($bereich, $eltern) + self::felder($bereich),
		);
	}

	/**
	 * Baut die Paletten-Zeichenkette.
	 *
	 * @param Bereich $bereich Der Bereich
	 * @param string  $zustand „neu“ (nur Felder zum Anlegen), „bestehend“
	 *                         (änderbare und angezeigte Felder) oder „alle“
	 *
	 * @return string Die Palette, etwa „{konto_legend},konto;{kas_legend},…“
	 */
	public static function palette(Bereich $bereich, string $zustand): string
	{
		$legenden = array();

		// Der Zugang wird nur bei neuen Objekten flacher Bereiche gewählt;
		// Kindobjekte erben ihn von ihrer Domain
		if (null === $bereich->eltern && !$bereich->einzeln && 'bestehend' !== $zustand) {
			$legenden['konto_legend'][] = 'konto';
		}

		foreach ($bereich->felder as $feld) {
			if (self::sichtbar($feld, $zustand)) {
				$legenden[$feld->legende][] = $feld->spalte;
			}
		}

		$teile = array();
		$erste = true;

		foreach ($legenden as $legende => $spalten) {
			// Nur die erste Legende ist offen, Zusatzangaben sind eingeklappt
			$teile[] = '{'.$legende.($erste ? '' : ':hide').'},'.implode(',', $spalten);
			$erste = $erste && 'konto_legend' === $legende;
		}

		return implode(';', $teile);
	}

	/**
	 * Sagt, ob ein Feld in einem Zustand der Maske erscheint.
	 *
	 * @param Feld   $feld    Das Feld
	 * @param string $zustand „neu“, „bestehend“ oder „alle“
	 *
	 * @return bool true, wenn es in die Palette gehört
	 */
	public static function sichtbar(Feld $feld, string $zustand): bool
	{
		return match ($zustand) {
			'neu' => $feld->beimAnlegen,
			'bestehend' => null !== $feld->aendernParam || (array() !== $feld->kasNamen && 'janein' !== $feld->art),
			default => true,
		};
	}

	/**
	 * Baut die Felddefinitionen eines Bereichs.
	 *
	 * @param Bereich $bereich Der Bereich
	 *
	 * @return array<string, array<string, mixed>> Spalte => Definition
	 */
	public static function felder(Bereich $bereich): array
	{
		$felder = array();

		foreach ($bereich->felder as $feld) {
			$felder[$feld->spalte] = self::feld($feld);
		}

		return $felder;
	}

	/**
	 * Baut die Definition eines einzelnen Felds.
	 *
	 * Alle Felder tragen ausdrücklich „exclude“ => false: Contao 5 behandelt
	 * Felder ohne Angabe für Nicht-Administratoren als gesperrt
	 * (TableAccessVoter), 4.13 als frei. So gilt in beiden: Wer das Modul
	 * nutzen darf, darf alle seine Felder bearbeiten.
	 *
	 * @param Feld $feld Das Feld
	 *
	 * @return array<string, mixed> Die Definition
	 */
	public static function feld(Feld $feld): array
	{
		$eval = array('tl_class' => 'w50');
		$definition = array(
			'label'   => array($feld->titel, $feld->hilfe),
			'exclude' => false,
		);

		switch ($feld->art) {
			case 'janein':
				$definition['inputType'] = 'checkbox';
				$eval['tl_class'] = 'w50 m12';
				$definition['sql'] = "char(1) NOT NULL default ''";
				break;

			case 'auswahl':
				$definition['inputType'] = 'select';
				$definition['options'] = array_keys($feld->optionen);
				$definition['reference'] = $feld->optionen;
				$eval['includeBlankOption'] = isset($feld->optionen['']) ? false : true;
				$definition['sql'] = "varchar(64) NOT NULL default ''";
				break;

			case 'zahl':
				$definition['inputType'] = 'text';
				$eval['maxlength'] = 32;
				$eval['rgxp'] = 'digit';
				$definition['sql'] = "varchar(32) NOT NULL default ''";
				break;

			case 'textarea':
			case 'liste':
				$definition['inputType'] = 'textarea';
				$eval['tl_class'] = 'clr';
				$eval['style'] = 'height:6em';
				$eval['preserveTags'] = true;
				$eval['decodeEntities'] = true;
				$definition['sql'] = 'mediumtext NULL';
				break;

			case 'geheim':
				$definition['inputType'] = 'textarea';
				$eval['tl_class'] = 'clr';
				$eval['style'] = 'height:6em';
				$eval['preserveTags'] = true;
				$eval['decodeEntities'] = true;
				$eval['doNotShow'] = true;
				$eval['doNotCopy'] = true;
				$definition['save_callback'] = array(array(ObjektListener::class, 'kennwortMerken'));
				$definition['sql'] = 'text NULL';
				break;

			case 'passwort':
				$definition['inputType'] = 'text';
				$eval['maxlength'] = 255;
				$eval['hideInput'] = true;
				$eval['preserveTags'] = true;
				$eval['decodeEntities'] = true;
				$eval['doNotShow'] = true;
				$eval['doNotCopy'] = true;
				$eval['autocomplete'] = 'new-password';
				$definition['save_callback'] = array(array(ObjektListener::class, 'kennwortMerken'));
				$definition['sql'] = "varchar(255) NOT NULL default ''";
				break;

			default:
				$definition['inputType'] = 'text';
				$eval['maxlength'] = 255;
				$eval['decodeEntities'] = true;
				$definition['sql'] = "varchar(255) NOT NULL default ''";
		}

		if ('' !== $feld->vorgabe) {
			$definition['default'] = $feld->vorgabe;
		}

		if ($feld->inListe) {
			$definition['search'] = true;
			$definition['sorting'] = true;
		}

		if ('auswahl' === $feld->art || 'janein' === $feld->art) {
			$definition['filter'] = true;
		}

		$definition['eval'] = array_merge($eval, $feld->eval);

		return $definition;
	}

	/**
	 * Baut die Listenansicht.
	 *
	 * Flache Bereiche zeigen alle Objekte aller Zugänge mit einem Filter nach
	 * Zugang; Kindbereiche erscheinen unter ihrer Domain (Eltern-Modus).
	 *
	 * @param Bereich                $bereich Der Bereich
	 * @param Bereich|null           $eltern  Der Elternbereich
	 * @param array<string, Bereich> $kinder  Die Kindbereiche
	 *
	 * @return array<string, mixed> Die Angaben unter „list“
	 */
	private static function liste(Bereich $bereich, ?Bereich $eltern, array $kinder): array
	{
		$listener = ObjektListener::class;
		$spalten = $bereich->listenSpalten();

		if (null === $eltern) {
			$sortierung = array(
				'mode'        => 1,
				'fields'      => array($bereich->sortierung),
				'flag'        => 11,
				'panelLayout' => 'filter;sort,search,limit',
			);
			$beschriftung = array(
				'fields'         => array_merge(array('konto'), $spalten),
				'showColumns'    => true,
				'label_callback' => array($listener, 'beschriftung'),
			);
		} else {
			$sortierung = array(
				'mode'         => 4,
				'fields'       => array($bereich->sortierung),
				'headerFields' => array($eltern->sortierung, 'konto', 'kas_abgleich'),
				'panelLayout'  => 'search,limit',
			);
			$beschriftung = array(
				'fields'         => $spalten,
				'format'         => implode(' · ', array_fill(0, \count($spalten), '%s')),
				'label_callback' => array($listener, 'beschriftung'),
			);
		}

		$operationen = array(
			'edit' => array(
				'label'   => array('Bearbeiten', 'Objekt %s bearbeiten'),
				'href'    => 'act=edit',
				'icon'    => 'edit.svg',
				'primary' => true,
			),
		);

		foreach ($kinder as $kind) {
			$operationen[$kind->name] = array(
				'label'   => array($kind->titel.'-Liste', $kind->titel.' der Domain %s'),
				'href'    => 'table='.$kind->tabelle,
				'icon'    => 'dns' === $kind->name ? 'settings.svg' : 'regular.svg',
				'primary' => true,
			);
		}

		if ('domain' === $bereich->name) {
			$operationen['dnsreset'] = array(
				'label'      => array('DNS zurücksetzen', 'DNS-Einstellungen der Domain %s auf die Vorgabe von All-Inkl zurücksetzen'),
				'href'       => 'key=dnsreset',
				'icon'       => 'undo.svg',
				'attributes' => 'onclick="if(!confirm(\'Alle DNS-Einträge dieser Domain werden auf die Vorgabe von All-Inkl zurückgesetzt. Eigene Einträge gehen verloren. Fortfahren?\'))return false;Backend.getScrollOffset()"',
			);
		}

		$operationen['show'] = array(
			'label'   => array('Anzeigen', 'Objekt %s mit allen KAS-Daten anzeigen'),
			'href'    => 'act=show',
			'icon'    => 'show.svg',
			'primary' => true,
		);

		if ($bereich->kannLoeschen()) {
			$operationen['delete'] = array(
				'label'      => array('Löschen', 'Objekt %s bei All-Inkl löschen'),
				'href'       => 'act=delete',
				'icon'       => 'delete.svg',
				'primary'    => true,
				'attributes' => 'onclick="if(!confirm(\''.addslashes($bereich->loeschHinweis.' Wirklich löschen?').'\'))return false;Backend.getScrollOffset()"',
			);
		}

		return array(
			'sorting'           => $sortierung,
			'label'             => $beschriftung,
			'global_operations' => array(
				'abgleich' => array(
					'label'      => array('Bei All-Inkl abgleichen', 'Alle aktiven KAS-Zugänge für diesen Bereich neu einlesen'),
					'href'       => 'key=abgleich',
					'icon'       => 'sync.svg',
					'class'      => 'header_sync',
					'primary'    => true,
					'attributes' => 'onclick="Backend.getScrollOffset()"',
				),
				'all' => array(
					'href'       => 'act=select',
					'class'      => 'header_edit_all',
					'attributes' => 'onclick="Backend.getScrollOffset()" accesskey="e"',
				),
			),
			'operations' => $operationen,
		);
	}

	/**
	 * Baut die Felder, die jede Spiegeltabelle hat.
	 *
	 * @param Bereich      $bereich Der Bereich
	 * @param Bereich|null $eltern  Der Elternbereich
	 *
	 * @return array<string, array<string, mixed>> Spalte => Definition
	 */
	private static function grundfelder(Bereich $bereich, ?Bereich $eltern): array
	{
		$felder = array(
			'id'     => array('sql' => 'int(10) unsigned NOT NULL auto_increment'),
			'tstamp' => array('sql' => 'int(10) unsigned NOT NULL default 0'),
			'konto'  => array(
				'label'      => array('KAS-Zugang', 'Der All-Inkl-Account, zu dem das Objekt gehört.'),
				'exclude'    => false,
				'filter'     => true,
				'inputType'  => 'select',
				'foreignKey' => 'tl_allinkl_konto.title',
				'eval'       => array('mandatory' => null === $eltern, 'includeBlankOption' => true, 'chosen' => true, 'tl_class' => 'w50'),
				'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
				'sql'        => 'int(10) unsigned NOT NULL default 0',
			),
			'kas_schluessel' => array(
				'label' => array('Kennung bei der KAS', ''),
				'sql'   => "varchar(255) NOT NULL default ''",
			),
			'kas_abgleich' => array(
				'label' => array('Zuletzt von der KAS gelesen', ''),
				'eval'  => array('rgxp' => 'datim'),
				'sql'   => 'int(10) unsigned NOT NULL default 0',
			),
			'kas_in_bearbeitung' => array(
				'label' => array('Bei All-Inkl in Bearbeitung', ''),
				'sql'   => "char(1) NOT NULL default ''",
			),
			'kas_fehler' => array(
				'label' => array('Letzter Fehler', ''),
				'sql'   => 'text NULL',
			),
			'kas_daten' => array(
				'label' => array('KAS-Rohdaten', 'Der zuletzt gelesene Datensatz, ohne Kennwörter und Schlüssel.'),
				'sql'   => 'mediumtext NULL',
			),
		);

		if (null !== $eltern) {
			$felder = array('pid' => array(
				'foreignKey' => $eltern->tabelle.'.'.$eltern->sortierung,
				'relation'   => array('type' => 'belongsTo', 'load' => 'lazy'),
				'sql'        => 'int(10) unsigned NOT NULL default 0',
			)) + $felder;
		}

		return $felder;
	}
}
