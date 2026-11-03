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
use Schachbulle\ContaoAllinklBundle\Kas\Zugangsdaten;
use Schachbulle\ContaoAllinklBundle\Sicherheit\Verschluesselung;

/**
 * Liest und schreibt die KAS-Zugänge in tl_allinkl_konto.
 */
class KontoSpeicher
{
	/** Die Tabelle der Zugänge */
	public const TABELLE = 'tl_allinkl_konto';

	/**
	 * @param Connection       $db               Die Datenbank
	 * @param Verschluesselung $verschluesselung Für die Kennwörter
	 */
	public function __construct(
		private readonly Connection $db,
		private readonly Verschluesselung $verschluesselung,
	) {
	}

	/**
	 * Liefert die Zeile eines Zugangs.
	 *
	 * @param int $id ID in tl_allinkl_konto
	 *
	 * @return array<string, mixed>|null Die Zeile, null wenn es sie nicht gibt
	 */
	public function zeile(int $id): ?array
	{
		$zeile = $this->db->fetchAssociative('SELECT * FROM '.self::TABELLE.' WHERE id = ?', array($id));

		return false === $zeile ? null : $zeile;
	}

	/**
	 * Liefert die entschlüsselten Anmeldedaten eines Zugangs.
	 *
	 * @param int $id ID in tl_allinkl_konto
	 *
	 * @return Zugangsdaten Die Anmeldedaten
	 *
	 * @throws \RuntimeException Wenn es den Zugang nicht gibt, er kein Login
	 *                           hat oder das Kennwort sich nicht
	 *                           entschlüsseln lässt
	 */
	public function zugang(int $id): Zugangsdaten
	{
		$zeile = $this->zeile($id);

		if (null === $zeile) {
			throw new \RuntimeException(sprintf('Den KAS-Zugang mit der ID %d gibt es nicht.', $id));
		}

		if ('' === trim((string) $zeile['kas_login'])) {
			throw new \RuntimeException(sprintf('Beim KAS-Zugang „%s“ fehlt das Login.', $zeile['title']));
		}

		return new Zugangsdaten(
			trim((string) $zeile['kas_login']),
			$this->verschluesselung->entschluesseln((string) $zeile['kas_passwort']),
			'' !== (string) $zeile['schreibschutz'],
			(int) $zeile['id'],
		);
	}

	/**
	 * Liefert die IDs aller aktiven Zugänge.
	 *
	 * @param bool $nurAutomatisch true: nur Zugänge mit täglichem Abgleich
	 *
	 * @return array<int, int> Die IDs, nach Titel sortiert
	 */
	public function aktive(bool $nurAutomatisch = false): array
	{
		$sql = 'SELECT id FROM '.self::TABELLE." WHERE aktiv = '1'".($nurAutomatisch ? " AND abgleich_taeglich = '1'" : '').' ORDER BY title';

		return array_map('intval', $this->db->fetchFirstColumn($sql));
	}

	/**
	 * Liefert die aktiven Zugänge für eine Auswahlliste.
	 *
	 * @return array<int, string> ID => Titel mit Login, nach Titel sortiert
	 */
	public function auswahl(): array
	{
		$auswahl = array();

		foreach ($this->db->fetchAllAssociative('SELECT id, title, kas_login FROM '.self::TABELLE." WHERE aktiv = '1' ORDER BY title") as $zeile) {
			$auswahl[(int) $zeile['id']] = sprintf('%s (%s)', $zeile['title'], $zeile['kas_login']);
		}

		return $auswahl;
	}

	/**
	 * Speichert ein neues Kennwort verschlüsselt.
	 *
	 * @param int    $id       ID in tl_allinkl_konto
	 * @param string $klartext Das Kennwort
	 */
	public function passwortSetzen(int $id, string $klartext): void
	{
		$this->db->update(self::TABELLE, array('kas_passwort' => $this->verschluesselung->verschluesseln($klartext), 'tstamp' => time()), array('id' => $id));
	}

	/**
	 * Hält das Ergebnis eines Abgleichs am Zugang fest.
	 *
	 * @param int    $id      ID in tl_allinkl_konto
	 * @param string $bericht Der Bericht in Textform
	 */
	public function berichtSpeichern(int $id, string $bericht): void
	{
		$this->db->update(self::TABELLE, array('letzter_abgleich' => time(), 'abgleich_bericht' => $bericht), array('id' => $id));
	}
}
