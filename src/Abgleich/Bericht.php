<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Abgleich;

/**
 * Das Ergebnis eines Abgleichs für einen Zugang.
 */
final class Bericht
{
	/** @var array<int, string> Erfolgsmeldungen je Bereich */
	private array $erfolge = array();

	/** @var array<int, string> Fehlermeldungen */
	private array $fehler = array();

	/** @var array<int, string> Hinweise */
	private array $hinweise = array();

	/**
	 * @param string $zugang Bezeichnung des Zugangs für die Meldungen
	 */
	public function __construct(public readonly string $zugang)
	{
	}

	/**
	 * Hält einen erfolgreich abgeglichenen Bereich fest.
	 *
	 * @param string $titel  Bezeichnung des Bereichs
	 * @param int    $anzahl Anzahl der Objekte
	 */
	public function erfolg(string $titel, int $anzahl): void
	{
		$this->erfolge[] = sprintf('%s: %d', $titel, $anzahl);
	}

	/**
	 * Hält einen Fehler fest.
	 *
	 * @param string $titel Bezeichnung des Bereichs
	 * @param string $text  Die Meldung
	 */
	public function fehler(string $titel, string $text): void
	{
		$this->fehler[] = sprintf('%s: %s', $titel, $text);
	}

	/**
	 * Hält einen Hinweis fest, der kein Fehler ist.
	 *
	 * @param string $text Der Hinweis
	 */
	public function hinweis(string $text): void
	{
		$this->hinweise[] = $text;
	}

	/**
	 * Sagt, ob Fehler auftraten.
	 *
	 * @return bool true bei mindestens einem Fehler
	 */
	public function hatFehler(): bool
	{
		return array() !== $this->fehler;
	}

	/**
	 * Liefert die Erfolgsmeldung in einer Zeile.
	 *
	 * @return string Etwa „Domains: 3 · Subdomains: 7“, leer ohne Erfolge
	 */
	public function erfolge(): string
	{
		return implode(' · ', $this->erfolge);
	}

	/**
	 * Liefert die Fehler.
	 *
	 * @return array<int, string> Die Meldungen
	 */
	public function fehlerListe(): array
	{
		return $this->fehler;
	}

	/**
	 * Liefert die Hinweise.
	 *
	 * @return array<int, string> Die Hinweise
	 */
	public function hinweise(): array
	{
		return $this->hinweise;
	}

	/**
	 * Liefert den Bericht als Text für tl_allinkl_konto.abgleich_bericht.
	 *
	 * @return string Mehrzeiliger Text
	 */
	public function alsText(): string
	{
		$zeilen = array(date('d.m.Y H:i').' – '.('' === $this->erfolge() ? 'nichts abgeglichen' : $this->erfolge()));

		foreach ($this->fehler as $text) {
			$zeilen[] = 'Fehler: '.$text;
		}

		foreach ($this->hinweise as $text) {
			$zeilen[] = 'Hinweis: '.$text;
		}

		return implode("\n", $zeilen);
	}
}
