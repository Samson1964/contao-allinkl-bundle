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
 * Rechnet zwischen KAS-Datensätzen und Zeilen der Spiegeltabellen um.
 *
 * Reine Funktionen ohne Datenbank und Netz, damit die Umrechnung für sich
 * testbar ist. Grundsatz beim Ändern: Es geht nur hinaus, was sich gegenüber
 * dem letzten bekannten Stand der KAS (Spalte kas_daten) geändert hat. So
 * überschreibt eine Maske nie Werte, die jemand inzwischen im KAS selbst
 * geändert hat und die hier nur angezeigt werden.
 */
final class Abbild
{
	/** Muster für Felder der KAS-Antwort, die nie gespeichert werden */
	private const GEHEIM = '/pass(word|wort)|_key$|^ssl_certificate_sni_key$/i';

	/**
	 * Rechnet einen KAS-Datensatz in Spaltenwerte um.
	 *
	 * @param Bereich              $bereich Der Bereich
	 * @param array<string, mixed> $kas     Der Datensatz aus get_*
	 *
	 * @return array<string, string> Spalte => Wert, nur für Felder mit
	 *                               KAS-Namen; Geheimes bleibt draußen
	 */
	public static function spaltenAusKas(Bereich $bereich, array $kas): array
	{
		$werte = array();

		foreach ($bereich->felder as $feld) {
			if (array() === $feld->kasNamen || $feld->istGeheim()) {
				continue;
			}

			$wert = null;

			foreach ($feld->kasNamen as $name) {
				if (\array_key_exists($name, $kas) && null !== $kas[$name]) {
					$wert = $kas[$name];

					break;
				}
			}

			$werte[$feld->spalte] = $feld->vonKas($wert);
		}

		return $werte;
	}

	/**
	 * Entfernt Kennwörter und private Schlüssel aus einem KAS-Datensatz.
	 *
	 * Die KAS liefert bei Domains mit eigenem Zertifikat den privaten
	 * Schlüssel mit aus, bei manchen Bereichen auch Kennwörter. Beides hat in
	 * der Datenbank des Contao-Systems nichts verloren.
	 *
	 * @param array<string, mixed> $kas Der Datensatz
	 *
	 * @return array<string, mixed> Der Datensatz ohne geheime Felder
	 */
	public static function bereinigen(array $kas): array
	{
		// „show_password“ ist nur ein Schalter, kein Kennwort
		return array_filter($kas, static fn ($schluessel): bool => 'show_password' === $schluessel || 1 !== preg_match(self::GEHEIM, (string) $schluessel), ARRAY_FILTER_USE_KEY);
	}

	/**
	 * Wandelt einen bereinigten KAS-Datensatz in JSON für kas_daten.
	 *
	 * @param array<string, mixed> $kas Der Datensatz
	 *
	 * @return string Das JSON, Umlaute unmaskiert
	 */
	public static function alsJson(array $kas): string
	{
		return (string) json_encode(self::bereinigen($kas), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
	}

	/**
	 * Liest kas_daten.
	 *
	 * @param mixed $json Inhalt der Spalte, auch null
	 *
	 * @return array<string, mixed> Der Datensatz, leer bei fehlenden oder
	 *                              kaputten Daten
	 */
	public static function ausJson(mixed $json): array
	{
		if (!\is_string($json) || '' === $json) {
			return array();
		}

		$daten = json_decode($json, true);

		return \is_array($daten) ? $daten : array();
	}

	/**
	 * Sagt, ob die KAS das Objekt noch bearbeitet.
	 *
	 * @param array<string, mixed> $kas Der Datensatz
	 *
	 * @return bool true, wenn „in_progress“ gesetzt ist
	 */
	public static function inBearbeitung(array $kas): bool
	{
		$wert = $kas['in_progress'] ?? '';

		return true === $wert || \in_array(strtoupper(trim((string) $wert)), array('Y', 'TRUE', '1'), true);
	}

	/**
	 * Liefert die Pflichtfelder, die beim Anlegen noch leer sind.
	 *
	 * @param Bereich               $bereich Der Bereich
	 * @param array<string, mixed>  $zeile   Die Zeile der Maske
	 * @param array<string, string> $geheim  Kennwörter aus der Maske
	 *
	 * @return array<int, Feld> Die fehlenden Felder
	 */
	public static function fehlendBeimAnlegen(Bereich $bereich, array $zeile, array $geheim): array
	{
		$fehlend = array();

		foreach ($bereich->felder as $feld) {
			if (!$feld->pflicht || !$feld->beimAnlegen) {
				continue;
			}

			$wert = $feld->istGeheim() ? ($geheim[$feld->spalte] ?? '') : (string) ($zeile[$feld->spalte] ?? '');

			if ('' === trim($wert)) {
				$fehlend[] = $feld;
			}
		}

		return $fehlend;
	}

	/**
	 * Bildet die Parameter für add_*.
	 *
	 * Leere freiwillige Angaben gehen nicht hinaus, damit die Vorgaben der
	 * KAS greifen.
	 *
	 * @param Bereich               $bereich Der Bereich
	 * @param array<string, mixed>  $zeile   Die Zeile der Maske
	 * @param array<string, string> $geheim  Kennwörter aus der Maske
	 * @param array|null            $eltern  Die Elternzeile bei Kindbereichen
	 *
	 * @return array<string, mixed> Die Parameter
	 */
	public static function anlegenParams(Bereich $bereich, array $zeile, array $geheim, ?array $eltern): array
	{
		$params = array();

		foreach ($bereich->felder as $feld) {
			if (null === $feld->anlegenParam) {
				continue;
			}

			$wert = $feld->istGeheim() ? ($geheim[$feld->spalte] ?? '') : (string) ($zeile[$feld->spalte] ?? '');

			// Checkboxen haben immer einen Wert (Y oder N)
			if ('' === trim($wert) && 'janein' !== $feld->art) {
				continue;
			}

			foreach (array_merge(array($feld->anlegenParam), $feld->anlegenAuch) as $param) {
				self::setzen($params, $param, $feld, $wert);
			}
		}

		return $bereich->zusatzBeimAnlegen($zeile, $eltern) + $params;
	}

	/**
	 * Ermittelt, was sich gegenüber dem letzten KAS-Stand geändert hat.
	 *
	 * Kennwörter zählen als geändert, sobald eines eingegeben wurde. Felder
	 * mit immerSenden gehen mit, sobald sich in ihrer Gruppe überhaupt etwas
	 * ändert.
	 *
	 * @param Bereich               $bereich Der Bereich
	 * @param array<string, mixed>  $zeile   Die Zeile der Maske
	 * @param array<string, string> $geheim  Kennwörter aus der Maske
	 *
	 * @return array<string, array<string, mixed>> Gruppe => Parameter; die
	 *                                             Gruppe '' ist der Hauptaufruf
	 *                                             update_*. Leer, wenn sich
	 *                                             nichts geändert hat.
	 */
	public static function aenderungen(Bereich $bereich, array $zeile, array $geheim): array
	{
		$alt = self::spaltenAusKas($bereich, self::ausJson($zeile['kas_daten'] ?? null));
		$gruppen = array();
		$immer = array();

		foreach ($bereich->felder as $feld) {
			if (null === $feld->aendernParam) {
				continue;
			}

			$gruppe = $feld->gruppe ?? '';

			if ($feld->istGeheim()) {
				if ('' !== ($geheim[$feld->spalte] ?? '')) {
					self::setzen($gruppen[$gruppe], $feld->aendernParam, $feld, $geheim[$feld->spalte]);
				}

				continue;
			}

			$neu = (string) ($zeile[$feld->spalte] ?? '');

			if ($feld->immerSenden) {
				$immer[$gruppe][] = array($feld, $neu);

				continue;
			}

			// Felder ohne KAS-Namen (etwa „verschieben_nach“) zählen, sobald
			// sie einen Wert haben
			$vorher = array() === $feld->kasNamen ? '' : ($alt[$feld->spalte] ?? '');

			if (self::normal($neu) !== self::normal($vorher)) {
				self::setzen($gruppen[$gruppe], $feld->aendernParam, $feld, $neu);
			}
		}

		foreach ($immer as $gruppe => $felder) {
			if (!isset($gruppen[$gruppe])) {
				continue;
			}

			foreach ($felder as [$feld, $neu]) {
				self::setzen($gruppen[$gruppe], (string) $feld->aendernParam, $feld, $neu);
			}
		}

		return $gruppen;
	}

	/**
	 * Setzt einen Parameter, bei „_N“ als durchnummerierte Liste.
	 *
	 * @param array<string, mixed>|null $params Die Parameter, werden ergänzt
	 * @param string                    $param  Der Parametername
	 * @param Feld                      $feld   Das Feld
	 * @param string                    $wert   Der Spaltenwert
	 */
	private static function setzen(?array &$params, string $param, Feld $feld, string $wert): void
	{
		$params ??= array();

		if (str_ends_with($param, '_N')) {
			$basis = substr($param, 0, -1);

			foreach ($feld->teile($wert) as $nummer => $eintrag) {
				$params[$basis.$nummer] = $eintrag;
			}

			return;
		}

		$params[$param] = $feld->istGeheim() ? $wert : $feld->zuKas($wert);
	}

	/**
	 * Vereinheitlicht Zeilenenden und Ränder für den Vergleich.
	 *
	 * @param string $text Der Text
	 *
	 * @return string Der Text mit \n und ohne Leerraum an den Rändern
	 */
	private static function normal(string $text): string
	{
		return trim(str_replace(array("\r\n", "\r"), "\n", $text));
	}
}
