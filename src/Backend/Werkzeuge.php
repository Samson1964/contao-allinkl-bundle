<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Backend;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Schachbulle\ContaoAllinklBundle\Datenbank\KontoSpeicher;
use Schachbulle\ContaoAllinklBundle\Kas\Fehlertext;
use Schachbulle\ContaoAllinklBundle\Kas\KasClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Die KAS-Funktionen ohne eigene Tabelle.
 *
 * Lesend: get_accountresources, get_space, get_server_information,
 * get_traffic, get_space_usage, get_topleveldomains, get_softwareinstall und
 * get_mailstandardfilter. Schreibend: update_chown, add_symlink und
 * add_softwareinstall. Die Daten werden bei jedem Aufruf frisch gelesen und
 * nicht gespeichert.
 *
 * Öffentlich, weil das klassische Backend-Modul WerkzeugeModul ihn über
 * System::getContainer() holt.
 */
class Werkzeuge
{
	/** Die Ansichten mit ihren Beschriftungen */
	public const ANSICHTEN = array(
		'uebersicht' => 'Übersicht',
		'verkehr'    => 'Datenverkehr',
		'speicher'   => 'Speicher je Verzeichnis',
		'tlds'       => 'Domainendungen',
		'software'   => 'Software',
		'filter'     => 'Spamfilter',
		'dateien'    => 'Besitzrechte und Symlinks',
	);

	/** Kennung der Formulare dieses Moduls */
	public const FORMULAR = 'allinkl_werkzeuge';

	/**
	 * @param KontoSpeicher $konten   Die Zugänge
	 * @param KasClient     $client   Der KAS-Client
	 * @param RequestStack  $anfragen Die laufende Anfrage
	 * @param Meldungen $meldungen Sammelt die Meldungen für die Folgeseite
	 */
	public function __construct(
		private readonly KontoSpeicher $konten,
		private readonly KasClient $client,
		private readonly RequestStack $anfragen,
		private readonly Meldungen $meldungen,
	) {
	}

	/**
	 * Stellt alles zusammen, was das Template braucht.
	 *
	 * Ein abgeschicktes Formular wird zuerst ausgeführt; danach leitet die
	 * Methode auf dieselbe Ansicht weiter (Post/Redirect/Get), damit ein
	 * Neuladen den Auftrag nicht wiederholt.
	 *
	 * @return array<string, mixed> Zugänge, gewählter Zugang, Ansichten,
	 *                              gewählte Ansicht, Datenblöcke als HTML und
	 *                              Vorgaben für die Formulare
	 *
	 * @throws RedirectResponseException Nach einem abgeschickten Formular
	 */
	public function daten(): array
	{
		$anfrage = $this->anfragen->getCurrentRequest() ?? new Request();
		$konten = $this->konten->auswahl();
		$konto = (int) $anfrage->query->get('konto', (string) (array_key_first($konten) ?? 0));
		$ansicht = (string) $anfrage->query->get('ansicht', 'uebersicht');

		if (!isset(self::ANSICHTEN[$ansicht])) {
			$ansicht = 'uebersicht';
		}

		if (!isset($konten[$konto])) {
			$konto = (int) (array_key_first($konten) ?? 0);
		}

		if ($anfrage->isMethod('POST') && self::FORMULAR === $anfrage->request->get('FORM_SUBMIT') && $konto > 0) {
			$this->absenden($konto, $anfrage);

			throw new RedirectResponseException($anfrage->getUri());
		}

		return array(
			'konten'   => $konten,
			'konto'    => $konto,
			'ansichten' => self::ANSICHTEN,
			'ansicht'  => $ansicht,
			'bloecke'  => $konto > 0 ? $this->bloecke($konto, $ansicht, $anfrage) : array(),
			'jahr'     => (int) $anfrage->query->get('jahr', date('Y')),
			'monat'    => (int) $anfrage->query->get('monat', date('n')),
			'verzeichnis' => (string) $anfrage->query->get('verzeichnis', '/'),
		);
	}

	/**
	 * Wandelt KAS-Daten in eine HTML-Tabelle.
	 *
	 * Eine Liste von Feldern wird zu einer Tabelle mit allen vorkommenden
	 * Schlüsseln als Spalten, ein einzelnes Feld zu einer zweispaltigen
	 * Tabelle. Verschachtelte Werte werden genauso dargestellt. Alles wird
	 * maskiert.
	 *
	 * @param mixed $daten Die Daten aus ReturnInfo
	 *
	 * @return string Das HTML
	 */
	public static function tabelle(mixed $daten): string
	{
		if (!\is_array($daten)) {
			return null === $daten || '' === $daten ? '<p class="tl_gray">–</p>' : '<p>'.self::text($daten).'</p>';
		}

		if (array() === $daten) {
			return '<p class="tl_gray">keine Einträge</p>';
		}

		if (array_is_list($daten) && array() === array_filter($daten, static fn ($zeile): bool => !\is_array($zeile) || array_is_list($zeile))) {
			$spalten = array();

			foreach ($daten as $zeile) {
				foreach (array_keys($zeile) as $schluessel) {
					$spalten[$schluessel] = true;
				}
			}

			$html = '<table class="tl_listing allinkl_tabelle"><thead><tr>';

			foreach (array_keys($spalten) as $spalte) {
				$html .= '<th class="tl_folder_tlist">'.self::text($spalte).'</th>';
			}

			$html .= '</tr></thead><tbody>';

			foreach ($daten as $zeile) {
				$html .= '<tr>';

				foreach (array_keys($spalten) as $spalte) {
					$html .= '<td class="tl_file_list">'.self::zelle($zeile[$spalte] ?? null).'</td>';
				}

				$html .= '</tr>';
			}

			return $html.'</tbody></table>';
		}

		$html = '<table class="tl_listing allinkl_tabelle"><tbody>';

		foreach ($daten as $schluessel => $wert) {
			$html .= '<tr><td class="tl_file_list"><strong>'.self::text($schluessel).'</strong></td><td class="tl_file_list">'.self::zelle($wert).'</td></tr>';
		}

		return $html.'</tbody></table>';
	}

	/**
	 * Führt ein abgeschicktes Formular aus.
	 *
	 * @param int     $konto   Der Zugang
	 * @param Request $anfrage Die Anfrage mit den Formularwerten
	 */
	private function absenden(int $konto, Request $anfrage): void
	{
		$wert = static fn (string $name): string => trim((string) $anfrage->request->get($name, ''));
		$janein = static fn (string $name): string => '' === $wert($name) ? 'N' : 'Y';

		[$aktion, $params, $erfolg] = match ($wert('aktion')) {
			'chown' => array('update_chown', array(
				'chown_path' => $wert('chown_path'),
				'chown_user' => $wert('chown_user'),
				'recursive'  => $janein('recursive'),
			), 'Die Besitzrechte wurden gesetzt.'),
			'symlink' => array('add_symlink', array(
				'symlink_target' => $wert('symlink_target'),
				'symlink_name'   => $wert('symlink_name'),
			), 'Der Symlink wurde angelegt.'),
			'software' => array('add_softwareinstall', array(
				'software_id'                   => $wert('software_id'),
				'software_database'             => $wert('software_database'),
				'software_database_password'    => (string) $anfrage->request->get('software_database_password', ''),
				'software_hostname'             => $wert('software_hostname'),
				'software_path'                 => $wert('software_path'),
				'software_admin_mail'           => $wert('software_admin_mail'),
				'software_admin_user'           => $wert('software_admin_user'),
				'software_admin_pass'           => (string) $anfrage->request->get('software_admin_pass', ''),
				'software_install_example_data' => $janein('software_install_example_data'),
				'language'                      => 'en' === $wert('language') ? 'en' : 'de',
			), 'Die Installation wurde beauftragt; All-Inkl schickt eine Bestätigung an die angegebene Adresse.'),
			default => array('', array(), ''),
		};

		if ('' === $aktion) {
			return;
		}

		try {
			$this->client->aufrufen($this->konten->zugang($konto), $aktion, array_filter($params, static fn ($wert): bool => '' !== $wert));
			$this->meldungen->erfolg($erfolg);
		} catch (\Throwable $fehler) {
			$this->meldungen->fehler(Fehlertext::zu($fehler));
		}
	}

	/**
	 * Liest die Daten einer Ansicht.
	 *
	 * Schlägt ein Aufruf fehl, erscheint statt der Tabelle die Meldung; die
	 * übrigen Blöcke der Ansicht werden trotzdem gelesen.
	 *
	 * @param int     $konto   Der Zugang
	 * @param string  $ansicht Die Ansicht
	 * @param Request $anfrage Für Jahr, Monat und Verzeichnis
	 *
	 * @return array<int, array{titel: string, html: string}> Die Blöcke
	 */
	private function bloecke(int $konto, string $ansicht, Request $anfrage): array
	{
		$aufrufe = match ($ansicht) {
			'uebersicht' => array(
				'Ressourcen des Accounts' => array('get_accountresources', array()),
				'Speicherbelegung' => array('get_space', array('show_subaccounts' => 'N', 'show_details' => 'Y')),
				'Server' => array('get_server_information', array()),
			),
			'verkehr' => array(
				'Datenverkehr' => array('get_traffic', array(
					'year'  => (int) $anfrage->query->get('jahr', date('Y')),
					'month' => (int) $anfrage->query->get('monat', date('n')),
				)),
			),
			'speicher' => array(
				'Belegung' => array('get_space_usage', array('directory' => (string) $anfrage->query->get('verzeichnis', '/'))),
			),
			'tlds' => array('Mögliche Domainendungen' => array('get_topleveldomains', array())),
			'software' => array('Verfügbare Software' => array('get_softwareinstall', array())),
			'filter' => array('Verfügbare Standardfilter für Postfächer' => array('get_mailstandardfilter', array())),
			default => array(),
		};

		if (array() === $aufrufe) {
			return array();
		}

		try {
			$zugang = $this->konten->zugang($konto);
		} catch (\Throwable $fehler) {
			return array(array('titel' => 'Zugang', 'html' => '<p class="tl_error">'.self::text(Fehlertext::zu($fehler)).'</p>'));
		}

		$bloecke = array();

		foreach ($aufrufe as $titel => [$aktion, $params]) {
			try {
				$html = self::tabelle($this->client->aufrufen($zugang, $aktion, $params)->info);
			} catch (\Throwable $fehler) {
				$html = '<p class="tl_error">'.self::text(Fehlertext::zu($fehler)).'</p>';
			}

			$bloecke[] = array('titel' => $titel, 'html' => $html);
		}

		return $bloecke;
	}

	/**
	 * Stellt einen Zellenwert dar.
	 *
	 * @param mixed $wert Der Wert
	 *
	 * @return string Das HTML, verschachtelte Felder als eigene Tabelle
	 */
	private static function zelle(mixed $wert): string
	{
		return \is_array($wert) ? self::tabelle($wert) : self::text($wert);
	}

	/**
	 * Maskiert einen Wert für HTML.
	 *
	 * @param mixed $wert Der Wert
	 *
	 * @return string Der maskierte Text; Wahrheitswerte als „ja“/„nein“
	 */
	private static function text(mixed $wert): string
	{
		if (\is_bool($wert)) {
			return $wert ? 'ja' : 'nein';
		}

		return htmlspecialchars((string) $wert, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
	}
}
