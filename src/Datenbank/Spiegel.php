<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Datenbank;

use Doctrine\DBAL\Connection;
use Schachbulle\ContaoAllinklBundle\Bereich\Abbild;
use Schachbulle\ContaoAllinklBundle\Bereich\Bereich;
use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;

/**
 * Schreibt KAS-Datensätze in die Spiegeltabellen.
 *
 * Jede Spiegeltabelle hat neben ihren Feldern die Spalten konto (ID des
 * Zugangs), kas_schluessel (Kennung bei der KAS), kas_daten (letzter
 * gelesener Datensatz als JSON, ohne Geheimnisse), kas_abgleich (Zeitpunkt),
 * kas_in_bearbeitung und kas_fehler. Zeilen ohne kas_schluessel sind
 * Entwürfe, die noch nicht bei der KAS angekommen sind; der Abgleich lässt
 * sie stehen.
 */
class Spiegel
{
	/**
	 * @param Connection $db Die Datenbank
	 */
	public function __construct(private readonly Connection $db)
	{
	}

	/**
	 * Gleicht die Zeilen eines Bereichs mit einer Liste von der KAS ab.
	 *
	 * Vorhandene Zeilen werden aktualisiert, neue angelegt und Zeilen, deren
	 * Objekt die KAS nicht mehr liefert, samt Kindzeilen gelöscht.
	 *
	 * @param Bereich                          $bereich Der Bereich
	 * @param int                              $kontoId Der Zugang
	 * @param int|null                         $pid     Die Elternzeile bei
	 *                                                  Kindbereichen
	 * @param array<int, array<string, mixed>> $liste   Die Datensätze von get_*
	 *
	 * @return int Anzahl der Objekte nach dem Abgleich
	 */
	public function spiegeln(Bereich $bereich, int $kontoId, ?int $pid, array $liste): int
	{
		$bedingung = 'konto = ? AND kas_schluessel <> \'\''.(null === $pid ? '' : ' AND pid = ?');
		$werte = null === $pid ? array($kontoId) : array($kontoId, $pid);
		$vorhanden = array();

		foreach ($this->db->fetchAllAssociative('SELECT id, kas_schluessel FROM '.$bereich->tabelle.' WHERE '.$bedingung, $werte) as $zeile) {
			$vorhanden[strtolower((string) $zeile['kas_schluessel'])] = (int) $zeile['id'];
		}

		$gesehen = array();

		foreach ($liste as $kas) {
			$schluessel = ($bereich->schluessel)($kas);

			if ('' === $schluessel || isset($gesehen[strtolower($schluessel)])) {
				continue;
			}

			$gesehen[strtolower($schluessel)] = true;
			$id = $vorhanden[strtolower($schluessel)] ?? null;

			if (null !== $id) {
				$this->uebernehmen($bereich, $id, $kas, $schluessel);

				continue;
			}

			$neu = array('tstamp' => time(), 'konto' => $kontoId, 'kas_schluessel' => $schluessel);

			if (null !== $pid) {
				$neu['pid'] = $pid;
			}

			$this->db->insert($bereich->tabelle, $neu + $this->spalten($bereich, $kas));
		}

		$weg = array_values(array_diff_key($vorhanden, $gesehen));

		if (array() !== $weg) {
			$this->loeschen($bereich, $weg);
		}

		return \count($gesehen);
	}

	/**
	 * Überträgt einen frisch gelesenen Datensatz in eine Zeile.
	 *
	 * @param Bereich              $bereich    Der Bereich
	 * @param int                  $id         ID der Zeile
	 * @param array<string, mixed> $kas        Der Datensatz
	 * @param string|null          $schluessel Neuer kas_schluessel; null lässt
	 *                                         ihn stehen
	 */
	public function uebernehmen(Bereich $bereich, int $id, array $kas, ?string $schluessel = null): void
	{
		$werte = $this->spalten($bereich, $kas);

		if (null !== $schluessel) {
			$werte['kas_schluessel'] = $schluessel;
		}

		$this->db->update($bereich->tabelle, $werte, array('id' => $id));
	}

	/**
	 * Vermerkt einen Fehler an einer Zeile.
	 *
	 * @param Bereich $bereich Der Bereich
	 * @param int     $id      ID der Zeile
	 * @param string  $text    Die Meldung; leer löscht den Vermerk
	 */
	public function fehlerVermerken(Bereich $bereich, int $id, string $text): void
	{
		$this->db->update($bereich->tabelle, array('kas_fehler' => $text), array('id' => $id));
	}

	/**
	 * Setzt den Schlüssel einer Zeile, deren Objekt die KAS noch einrichtet.
	 *
	 * @param Bereich $bereich    Der Bereich
	 * @param int     $id         ID der Zeile
	 * @param string  $schluessel Der Schlüssel
	 */
	public function schluesselSetzen(Bereich $bereich, int $id, string $schluessel): void
	{
		$this->db->update($bereich->tabelle, array('kas_schluessel' => $schluessel, 'kas_in_bearbeitung' => '1', 'kas_fehler' => ''), array('id' => $id));
	}

	/**
	 * Ordnet eine Zeile einem anderen Zugang zu.
	 *
	 * Für Kindobjekte, die den Zugang ihrer Domain erben.
	 *
	 * @param Bereich $bereich Der Bereich
	 * @param int     $id      ID der Zeile
	 * @param int     $kontoId Der Zugang
	 */
	public function kontoSetzen(Bereich $bereich, int $id, int $kontoId): void
	{
		$this->db->update($bereich->tabelle, array('konto' => $kontoId), array('id' => $id));
	}

	/**
	 * Entfernt alle Zeilen eines Zugangs aus allen Spiegeltabellen.
	 *
	 * Läuft, wenn ein Zugang gelöscht wird. Bei All-Inkl bleibt alles
	 * erhalten.
	 *
	 * @param int $kontoId Der Zugang
	 */
	public function kontoEntfernen(int $kontoId): void
	{
		foreach (BereichRegister::alle() as $bereich) {
			if (!$bereich->einzeln) {
				$this->db->delete($bereich->tabelle, array('konto' => $kontoId));
			}
		}
	}

	/**
	 * Löscht Zeilen samt den Zeilen ihrer Kindbereiche.
	 *
	 * @param Bereich         $bereich Der Bereich
	 * @param array<int, int> $ids     Die IDs
	 */
	public function loeschen(Bereich $bereich, array $ids): void
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));

		if (array() === $ids) {
			return;
		}

		foreach (BereichRegister::kinder($bereich->name) as $kind) {
			$kindIds = $this->db->fetchFirstColumn('SELECT id FROM '.$kind->tabelle.' WHERE pid IN ('.implode(',', $ids).')');
			$this->loeschen($kind, array_map('intval', $kindIds));
		}

		$this->db->executeStatement('DELETE FROM '.$bereich->tabelle.' WHERE id IN ('.implode(',', $ids).')');
	}

	/**
	 * Überträgt die Accounteinstellungen in die Zeile des Zugangs.
	 *
	 * @param Bereich              $bereich Der Bereich „einstellungen“
	 * @param int                  $kontoId Der Zugang
	 * @param array<string, mixed> $kas     Der Datensatz von get_accountsettings
	 */
	public function einstellungenUebernehmen(Bereich $bereich, int $kontoId, array $kas): void
	{
		$werte = Abbild::spaltenAusKas($bereich, $kas);
		$werte['kas_daten'] = Abbild::alsJson($kas);

		$this->db->update($bereich->tabelle, $werte, array('id' => $kontoId));
	}

	/**
	 * Liefert die Zeilen eines Elternbereichs für einen Zugang.
	 *
	 * @param Bereich $bereich Der Elternbereich
	 * @param int     $kontoId Der Zugang
	 *
	 * @return array<int, array<string, mixed>> Die Zeilen, die schon bei der
	 *                                          KAS angekommen sind
	 */
	public function zeilen(Bereich $bereich, int $kontoId): array
	{
		return $this->db->fetchAllAssociative('SELECT * FROM '.$bereich->tabelle." WHERE konto = ? AND kas_schluessel <> '' ORDER BY id", array($kontoId));
	}

	/**
	 * Liefert eine Zeile.
	 *
	 * @param string $tabelle Die Tabelle
	 * @param int    $id      Die ID
	 *
	 * @return array<string, mixed>|null Die Zeile, null wenn es sie nicht gibt
	 */
	public function zeile(string $tabelle, int $id): ?array
	{
		$zeile = $this->db->fetchAssociative('SELECT * FROM '.$tabelle.' WHERE id = ?', array($id));

		return false === $zeile ? null : $zeile;
	}

	/**
	 * Bildet die Spaltenwerte aus einem KAS-Datensatz.
	 *
	 * @param Bereich              $bereich Der Bereich
	 * @param array<string, mixed> $kas     Der Datensatz
	 *
	 * @return array<string, string|int> Spalte => Wert, samt den kas_*-Spalten
	 */
	private function spalten(Bereich $bereich, array $kas): array
	{
		return Abbild::spaltenAusKas($bereich, $kas) + array(
			'kas_daten'          => Abbild::alsJson($kas),
			'kas_abgleich'       => time(),
			'kas_in_bearbeitung' => Abbild::inBearbeitung($kas) ? '1' : '',
			'kas_fehler'         => '',
		);
	}
}
