<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Bereich;

use Schachbulle\ContaoAllinklBundle\Kas\KasAntwort;

/**
 * Ein Bereich der KAS (Domains, Postfächer, Cronjobs …) mit seiner
 * Spiegeltabelle.
 *
 * Beschreibt, mit welchen Funktionen der Bereich gelesen, angelegt, geändert
 * und gelöscht wird und wie die Parameter dafür entstehen. Die Closures
 * erhalten immer die Zeile der Spiegeltabelle (Spaltenname => Wert), bei
 * Kindbereichen zusätzlich die Zeile des Elternobjekts.
 *
 * Ein Bereich mit „einzeln“ hat genau ein Objekt je Zugang (die
 * Accounteinstellungen); seine Spalten liegen in tl_allinkl_konto selbst.
 */
final class Bereich
{
	/** @var array<string, Feld> Die Felder, nach Spaltenname */
	public readonly array $felder;

	/**
	 * @param string                                   $name             Kurzname, etwa „domain“
	 * @param string                                   $tabelle          Die Spiegeltabelle
	 * @param string                                   $modul            Das Backend-Modul
	 * @param string                                   $titel            Bezeichnung in Meldungen
	 * @param array<int, Feld>                         $felder           Die Felder
	 * @param string                                   $lesenAktion      Die get_*-Funktion
	 * @param \Closure(?array): array|null             $lesenParams      Parameter für die ganze
	 *                                                                   Liste; erhält die
	 *                                                                   Elternzeile
	 * @param string|null                              $einzelParam      Parameter, mit dem
	 *                                                                   get_* ein einzelnes
	 *                                                                   Objekt liefert
	 * @param \Closure(array): string                  $schluessel       Bildet den Schlüssel
	 *                                                                   aus einem KAS-Datensatz
	 * @param string|null                              $eltern           Name des Elternbereichs
	 * @param \Closure(array, array): ?int|null        $zuordnen         Findet zu einem
	 *                                                                   KAS-Datensatz die
	 *                                                                   Elternzeile (ID) aus
	 *                                                                   der Liste aller
	 *                                                                   Elternzeilen; null:
	 *                                                                   Abgleich je Elternzeile
	 * @param string|null                              $anlegenAktion    Die add_*-Funktion
	 * @param \Closure(array, ?array): array|null      $anlegenZusatz    Berechnete Parameter
	 *                                                                   beim Anlegen
	 * @param \Closure(KasAntwort, array, ?array): string|null $neuerSchluessel Schlüssel nach dem
	 *                                                                   Anlegen; Vorgabe:
	 *                                                                   ReturnInfo
	 * @param string|null                              $aendernAktion    Die update_*-Funktion
	 * @param \Closure(array): array|null              $aendernSchluessel Schlüsselparameter beim
	 *                                                                   Ändern
	 * @param string|null                              $loeschenAktion   Die delete_*-Funktion
	 * @param \Closure(array): array|null              $loeschenSchluessel Schlüsselparameter beim
	 *                                                                   Löschen
	 * @param array<string, Aufrufgruppe>              $gruppen          Weitere Aufrufe beim
	 *                                                                   Ändern (SSL, Verschieben …)
	 * @param string|null                              $aendernBrauchtPasswort Spalte eines Kennworts,
	 *                                                                   ohne das die KAS kein
	 *                                                                   Ändern annimmt
	 * @param string                                   $sortierung       Spalte der Sortierung
	 * @param string                                   $loeschHinweis    Text der Sicherheitsabfrage
	 * @param bool                                     $einzeln          Ein Objekt je Zugang
	 */
	public function __construct(
		public readonly string $name,
		public readonly string $tabelle,
		public readonly string $modul,
		public readonly string $titel,
		array $felder,
		public readonly string $lesenAktion,
		public readonly \Closure $schluessel,
		public readonly ?\Closure $lesenParams = null,
		public readonly ?string $einzelParam = null,
		public readonly ?string $eltern = null,
		public readonly ?\Closure $zuordnen = null,
		public readonly ?string $anlegenAktion = null,
		public readonly ?\Closure $anlegenZusatz = null,
		public readonly ?\Closure $neuerSchluessel = null,
		public readonly ?string $aendernAktion = null,
		public readonly ?\Closure $aendernSchluessel = null,
		public readonly ?string $loeschenAktion = null,
		public readonly ?\Closure $loeschenSchluessel = null,
		public readonly array $gruppen = array(),
		public readonly ?string $aendernBrauchtPasswort = null,
		public readonly string $sortierung = '',
		public readonly string $loeschHinweis = '',
		public readonly bool $einzeln = false,
	) {
		$nachSpalte = array();

		foreach ($felder as $feld) {
			$nachSpalte[$feld->spalte] = $feld;
		}

		$this->felder = $nachSpalte;
	}

	/**
	 * Bildet einen Schlüssel aus dem ersten belegten von mehreren KAS-Feldern.
	 *
	 * Hilfsfunktion für die Definitionen, damit Tippfehler-Varianten der KAS
	 * („mail_forward_adress“ neben „mail_forward_address“) gleich behandelt
	 * werden.
	 *
	 * @param string ...$namen Die KAS-Feldnamen in der gewünschten Reihenfolge
	 *
	 * @return \Closure(array): string Liefert den Wert, leer wenn keiner belegt ist
	 */
	public static function schluesselAus(string ...$namen): \Closure
	{
		return static function (array $zeile) use ($namen): string {
			foreach ($namen as $name) {
				if (isset($zeile[$name]) && \is_scalar($zeile[$name]) && '' !== trim((string) $zeile[$name])) {
					return trim((string) $zeile[$name]);
				}
			}

			return '';
		};
	}

	/**
	 * Sagt, ob sich Objekte des Bereichs im Backend anlegen lassen.
	 *
	 * @return bool true, wenn es eine add_*-Funktion gibt
	 */
	public function kannAnlegen(): bool
	{
		return null !== $this->anlegenAktion;
	}

	/**
	 * Sagt, ob sich Objekte des Bereichs löschen lassen.
	 *
	 * @return bool true, wenn es eine delete_*-Funktion gibt
	 */
	public function kannLoeschen(): bool
	{
		return null !== $this->loeschenAktion;
	}

	/**
	 * Liefert die Parameter, mit denen get_* ein einzelnes Objekt liefert.
	 *
	 * @param string     $schluessel Der Schlüssel des Objekts
	 * @param array|null $eltern     Die Elternzeile bei Kindbereichen
	 *
	 * @return array<string, mixed> Die Parameter; enthält bei Kindbereichen
	 *                              auch die Listenparameter (etwa zone_host)
	 */
	public function einzelParams(string $schluessel, ?array $eltern): array
	{
		$params = null !== $this->lesenParams ? ($this->lesenParams)($eltern) : array();

		if (null !== $this->einzelParam) {
			$params[$this->einzelParam] = $schluessel;
		}

		return $params;
	}

	/**
	 * Liefert die Parameter für die ganze Liste.
	 *
	 * @param array|null $eltern Die Elternzeile bei Kindbereichen, die je
	 *                           Elternobjekt gelesen werden
	 *
	 * @return array<string, mixed> Die Parameter, oft leer
	 */
	public function listenParams(?array $eltern): array
	{
		return null !== $this->lesenParams ? ($this->lesenParams)($eltern) : array();
	}

	/**
	 * Liefert die Parameter, die beim Anlegen zusätzlich hinausgehen.
	 *
	 * @param array      $zeile  Die Zeile der Maske
	 * @param array|null $eltern Die Elternzeile bei Kindbereichen
	 *
	 * @return array<string, mixed> Die berechneten Parameter
	 */
	public function zusatzBeimAnlegen(array $zeile, ?array $eltern): array
	{
		return null !== $this->anlegenZusatz ? ($this->anlegenZusatz)($zeile, $eltern) : array();
	}

	/**
	 * Ermittelt den Schlüssel eines eben angelegten Objekts.
	 *
	 * @param KasAntwort $antwort Die Antwort von add_*
	 * @param array      $zeile   Die Zeile der Maske
	 * @param array|null $eltern  Die Elternzeile bei Kindbereichen
	 *
	 * @return string Der Schlüssel; leer, wenn er sich nicht bestimmen ließ
	 *                (dann füllt der nächste Abgleich ihn)
	 */
	public function schluesselNachAnlegen(KasAntwort $antwort, array $zeile, ?array $eltern): string
	{
		if (null !== $this->neuerSchluessel) {
			return ($this->neuerSchluessel)($antwort, $zeile, $eltern);
		}

		return $antwort->kennung();
	}

	/**
	 * Liefert die Schlüsselparameter für update_*.
	 *
	 * @param array $zeile Die Zeile mit gefülltem kas_schluessel
	 *
	 * @return array<string, mixed> Die Parameter
	 */
	public function schluesselZumAendern(array $zeile): array
	{
		return null !== $this->aendernSchluessel ? ($this->aendernSchluessel)($zeile) : array();
	}

	/**
	 * Liefert die Schlüsselparameter für delete_*.
	 *
	 * @param array $zeile Die Zeile mit gefülltem kas_schluessel
	 *
	 * @return array<string, mixed> Die Parameter
	 */
	public function schluesselZumLoeschen(array $zeile): array
	{
		return null !== $this->loeschenSchluessel ? ($this->loeschenSchluessel)($zeile) : $this->schluesselZumAendern($zeile);
	}

	/**
	 * Liefert die Felder, die in der Liste als Spalten erscheinen.
	 *
	 * @return array<int, string> Spaltennamen
	 */
	public function listenSpalten(): array
	{
		return array_keys(array_filter($this->felder, static fn (Feld $feld): bool => $feld->inListe));
	}
}
