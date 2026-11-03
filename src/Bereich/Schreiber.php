<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Bereich;

use Schachbulle\ContaoAllinklBundle\Kas\KasClient;
use Schachbulle\ContaoAllinklBundle\Kas\KasFehler;
use Schachbulle\ContaoAllinklBundle\Kas\Zugangsdaten;

/**
 * Schickt Anlegen, Ändern und Löschen eines Objekts an die KAS.
 *
 * Kennt keine Datenbank: Er erhält die Zeile der Maske und liefert zurück,
 * was danach in die Spiegeltabelle gehört. So bleibt die Logik ohne Contao
 * testbar; die Maske (ObjektListener) übernimmt das Speichern.
 */
class Schreiber
{
	/**
	 * @param KasClient $client Der KAS-Client
	 */
	public function __construct(private readonly KasClient $client)
	{
	}

	/**
	 * Legt ein Objekt bei der KAS an.
	 *
	 * @param Bereich               $bereich Der Bereich
	 * @param Zugangsdaten          $zugang  Der Zugang
	 * @param array<string, mixed>  $zeile   Die Zeile der Maske
	 * @param array<string, string> $geheim  Kennwörter aus der Maske
	 * @param array|null            $eltern  Die Elternzeile bei Kindbereichen
	 *
	 * @return array{schluessel: string, kas: array<string, mixed>|null} Der
	 *         Schlüssel des neuen Objekts und, wenn das Nachladen klappte,
	 *         sein Datensatz
	 *
	 * @throws Eingabefehler Wenn Pflichtangaben fehlen
	 * @throws KasFehler     Wenn die KAS das Anlegen ablehnt
	 */
	public function anlegen(Bereich $bereich, Zugangsdaten $zugang, array $zeile, array $geheim, ?array $eltern): array
	{
		if (!$bereich->kannAnlegen()) {
			throw new Eingabefehler(sprintf('%s lässt sich über die KAS-API nicht anlegen.', $bereich->titel));
		}

		$fehlend = Abbild::fehlendBeimAnlegen($bereich, $zeile, $geheim);

		if (array() !== $fehlend) {
			throw new Eingabefehler('Zum Anlegen fehlt noch: '.implode(', ', array_map(static fn (Feld $feld): string => '„'.$feld->titel.'“', $fehlend)).'.');
		}

		$antwort = $this->client->aufrufen($zugang, (string) $bereich->anlegenAktion, Abbild::anlegenParams($bereich, $zeile, $geheim, $eltern));
		$schluessel = $bereich->schluesselNachAnlegen($antwort, $zeile, $eltern);

		return array(
			'schluessel' => $schluessel,
			'kas'        => '' === $schluessel ? null : $this->nachladen($bereich, $zugang, $schluessel, $eltern),
		);
	}

	/**
	 * Schickt die Änderungen eines Objekts an die KAS.
	 *
	 * Zuerst geht der Hauptaufruf update_* hinaus, danach die Gruppen (SSL,
	 * SSH, Spamfilter, Verschieben) in der Reihenfolge ihrer Definition.
	 * Schlägt ein Aufruf fehl, unterbleiben die folgenden; was bis dahin
	 * hinausging, ist bei der KAS geändert. „nothing_to_do“ gilt als Erfolg.
	 *
	 * @param Bereich               $bereich Der Bereich
	 * @param Zugangsdaten          $zugang  Der Zugang
	 * @param array<string, mixed>  $zeile   Die Zeile der Maske samt kas_schluessel
	 *                                       und kas_daten
	 * @param array<string, string> $geheim  Kennwörter aus der Maske
	 * @param array|null            $eltern  Die Elternzeile bei Kindbereichen
	 *
	 * @return array{geaendert: bool, entfernt: bool, kas: array<string, mixed>|null}
	 *         Ob etwas hinausging, ob das Objekt den Zugang verlassen hat
	 *         (Verschieben) und der frisch gelesene Datensatz
	 *
	 * @throws Eingabefehler Wenn ein verlangtes Kennwort fehlt
	 * @throws KasFehler     Wenn die KAS eine Änderung ablehnt
	 */
	public function aendern(Bereich $bereich, Zugangsdaten $zugang, array $zeile, array $geheim, ?array $eltern): array
	{
		$gruppen = Abbild::aenderungen($bereich, $zeile, $geheim);
		$ergebnis = array('geaendert' => false, 'entfernt' => false, 'kas' => null);

		if (array() === $gruppen) {
			return $ergebnis;
		}

		if (null !== $bereich->aendernBrauchtPasswort && '' === ($geheim[$bereich->aendernBrauchtPasswort] ?? '')) {
			$feld = $bereich->felder[$bereich->aendernBrauchtPasswort] ?? null;

			throw new Eingabefehler(sprintf('Zum Ändern verlangt die KAS das Feld „%s“.', $feld?->titel ?? $bereich->aendernBrauchtPasswort));
		}

		if (isset($gruppen['']) && null !== $bereich->aendernAktion) {
			$this->stillAufrufen($zugang, $bereich->aendernAktion, $bereich->schluesselZumAendern($zeile) + $gruppen['']);
			$ergebnis['geaendert'] = true;
		}

		foreach ($bereich->gruppen as $name => $gruppe) {
			if (!isset($gruppen[$name])) {
				continue;
			}

			$aufrufe = ($gruppe->aufrufe)($gruppen[$name], $zeile, $zugang);

			foreach ($aufrufe as [$aktion, $params]) {
				$this->stillAufrufen($zugang, $aktion, $params);
				$ergebnis['geaendert'] = true;
			}

			if ($gruppe->entferntObjekt && array() !== $aufrufe) {
				$ergebnis['entfernt'] = true;
			}
		}

		if ($ergebnis['geaendert'] && !$ergebnis['entfernt']) {
			$ergebnis['kas'] = $this->nachladen($bereich, $zugang, (string) ($zeile['kas_schluessel'] ?? ''), $eltern);
		}

		return $ergebnis;
	}

	/**
	 * Löscht ein Objekt bei der KAS.
	 *
	 * @param Bereich              $bereich Der Bereich
	 * @param Zugangsdaten         $zugang  Der Zugang
	 * @param array<string, mixed> $zeile   Die Zeile samt kas_schluessel
	 *
	 * @throws KasFehler Wenn die KAS das Löschen ablehnt; ein Objekt, das es
	 *                   dort schon nicht mehr gibt, gilt als gelöscht
	 */
	public function loeschen(Bereich $bereich, Zugangsdaten $zugang, array $zeile): void
	{
		if (!$bereich->kannLoeschen()) {
			throw new Eingabefehler(sprintf('%s lässt sich über die KAS-API nicht löschen.', $bereich->titel));
		}

		try {
			$this->client->aufrufen($zugang, (string) $bereich->loeschenAktion, $bereich->schluesselZumLoeschen($zeile));
		} catch (KasFehler $fehler) {
			if (!self::istNichtGefunden($fehler->kasCode())) {
				throw $fehler;
			}
		}
	}

	/**
	 * Liest ein einzelnes Objekt frisch von der KAS.
	 *
	 * Die Antwort wird nach dem Schlüssel gefiltert, weil manche get_*
	 * trotz Einzelparameter mehrere Objekte liefern (Verzeichnisschutz: alle
	 * Benutzer eines Verzeichnisses).
	 *
	 * @param Bereich      $bereich    Der Bereich
	 * @param Zugangsdaten $zugang     Der Zugang
	 * @param string       $schluessel Der Schlüssel des Objekts
	 * @param array|null   $eltern     Die Elternzeile bei Kindbereichen
	 *
	 * @return array<string, mixed>|null Der Datensatz, null wenn die KAS ihn
	 *                                   (noch) nicht liefert – etwa weil sie
	 *                                   ein neues Objekt noch einrichtet
	 */
	public function nachladen(Bereich $bereich, Zugangsdaten $zugang, string $schluessel, ?array $eltern): ?array
	{
		try {
			$zeilen = $this->client->lesen($zugang, $bereich->lesenAktion, $bereich->einzeln ? array() : $bereich->einzelParams($schluessel, $eltern));
		} catch (KasFehler) {
			return null;
		}

		if ($bereich->einzeln) {
			return isset($zeilen[0]) ? Abbild::auspacken($zeilen[0]) : null;
		}

		foreach ($zeilen as $kas) {
			if (0 === strcasecmp(($bereich->schluessel)($kas), $schluessel)) {
				return $kas;
			}
		}

		return null;
	}

	/**
	 * Sagt, ob ein Fehlerkürzel ein fehlendes Objekt meldet.
	 *
	 * Die KAS kennt dafür mehrere Endungen, darunter den Tippfehler
	 * „doenst_exist“.
	 *
	 * @param string $code Das Fehlerkürzel
	 *
	 * @return bool true für *_not_found, *_doesnt_exist und *_doenst_exist
	 */
	public static function istNichtGefunden(string $code): bool
	{
		return 1 === preg_match('/(_not_found|_not_found_in_kas|_doesnt_exist|_doenst_exist|_does_not_exist)$/', $code);
	}

	/**
	 * Ruft eine schreibende Funktion auf und wertet „nothing_to_do“ als Erfolg.
	 *
	 * @param Zugangsdaten         $zugang Der Zugang
	 * @param string               $aktion Die Funktion
	 * @param array<string, mixed> $params Ihre Parameter
	 *
	 * @throws KasFehler Bei allen anderen Fehlern
	 */
	private function stillAufrufen(Zugangsdaten $zugang, string $aktion, array $params): void
	{
		try {
			$this->client->aufrufen($zugang, $aktion, $params);
		} catch (KasFehler $fehler) {
			if ('nothing_to_do' !== $fehler->kasCode()) {
				throw $fehler;
			}
		}
	}
}
