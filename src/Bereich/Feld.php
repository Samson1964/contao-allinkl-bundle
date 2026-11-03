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
 * Ein Feld einer Spiegeltabelle samt seiner Zuordnung zur KAS.
 *
 * Ein Feld weiß, unter welchen Namen die KAS seinen Wert liefert (die
 * Antworten enthalten teils Tippfehler-Varianten wie „mail_adresses“ neben
 * „mail_addresses“), unter welchem Parameter es beim Anlegen und Ändern
 * hinausgeht und wie es im Backend aussieht. Aus diesen Angaben bauen
 * DcaBauer die Maske und Abbild die Umrechnung.
 *
 * Arten:
 *
 * - text, zahl: einzeiliges Eingabefeld
 * - janein: Checkbox; die KAS spricht „Y“/„N“
 * - auswahl: Auswahlliste mit festen Werten
 * - textarea: mehrzeiliger Text, unverändert übertragen
 * - liste: mehrzeilig, eine Angabe je Zeile; zur KAS mit dem Trenner
 *   verbunden oder, bei einem Parameter auf „_N“, als param_0, param_1 …
 * - passwort: wird nie gespeichert und nie angezeigt, nur beim Speichern
 *   an die KAS weitergereicht
 * - geheim: wie passwort, aber mehrzeilig (privater SSL-Schlüssel)
 */
final class Feld
{
	/** @var array<int, string> Namen in der KAS-Antwort, der erste vorhandene gilt */
	public array $kasNamen = array();

	/** Parameter beim Anlegen, null: geht nicht direkt hinaus */
	public ?string $anlegenParam = null;

	/** Parameter beim Ändern, null: nach dem Anlegen nicht änderbar */
	public ?string $aendernParam = null;

	/** @var array<int, string> Weitere Parameter, unter denen der Wert beim Anlegen hinausgeht */
	public array $anlegenAuch = array();

	/** Feld erscheint beim Anlegen, auch ohne eigenen Parameter */
	public bool $beimAnlegen = false;

	/** Pflichtfeld beim Anlegen */
	public bool $pflicht = false;

	/** Wert geht bei jeder Änderung mit, nicht nur wenn er sich geändert hat */
	public bool $immerSenden = false;

	/** Schlüssel einer Aufrufgruppe des Bereichs (z. B. „ssl“), null: Hauptaufruf */
	public ?string $gruppe = null;

	/** @var array<string, string> Werte und Beschriftungen einer Auswahl */
	public array $optionen = array();

	/** Trenner einer Liste beim Senden an die KAS */
	public string $trenner = ',';

	/** Legende in der Maske */
	public string $legende = 'kas_legend';

	/** Vorgabewert neuer Datensätze */
	public string $vorgabe = '';

	/** Erscheint als Spalte in der Liste */
	public bool $inListe = false;

	/** @var array<string, mixed> Zusätzliche eval-Angaben für die Maske */
	public array $eval = array();

	/**
	 * @param string $spalte Spaltenname in der Spiegeltabelle
	 * @param string $art    Eine der oben beschriebenen Arten
	 * @param string $titel  Beschriftung in der Maske
	 * @param string $hilfe  Erklärung unter dem Feld
	 */
	private function __construct(
		public readonly string $spalte,
		public readonly string $art,
		public readonly string $titel,
		public readonly string $hilfe,
	) {
	}

	/**
	 * Legt ein einzeiliges Textfeld an.
	 *
	 * @param string $spalte Spaltenname
	 * @param string $titel  Beschriftung
	 * @param string $hilfe  Erklärung, darf leer sein
	 *
	 * @return self Das Feld zum Weiterbeschreiben
	 */
	public static function text(string $spalte, string $titel, string $hilfe = ''): self
	{
		return new self($spalte, 'text', $titel, $hilfe);
	}

	/**
	 * Legt ein Zahlenfeld an (ganze Zahlen, auch negativ).
	 *
	 * @param string $spalte Spaltenname
	 * @param string $titel  Beschriftung
	 * @param string $hilfe  Erklärung, darf leer sein
	 *
	 * @return self Das Feld
	 */
	public static function zahl(string $spalte, string $titel, string $hilfe = ''): self
	{
		return new self($spalte, 'zahl', $titel, $hilfe);
	}

	/**
	 * Legt eine Checkbox für einen Y/N-Wert der KAS an.
	 *
	 * @param string $spalte Spaltenname
	 * @param string $titel  Beschriftung
	 * @param string $hilfe  Erklärung, darf leer sein
	 *
	 * @return self Das Feld
	 */
	public static function janein(string $spalte, string $titel, string $hilfe = ''): self
	{
		return new self($spalte, 'janein', $titel, $hilfe);
	}

	/**
	 * Legt eine Auswahlliste an.
	 *
	 * @param string                $spalte   Spaltenname
	 * @param string                $titel    Beschriftung
	 * @param array<string, string> $optionen KAS-Wert => Beschriftung
	 * @param string                $hilfe    Erklärung, darf leer sein
	 *
	 * @return self Das Feld
	 */
	public static function auswahl(string $spalte, string $titel, array $optionen, string $hilfe = ''): self
	{
		$feld = new self($spalte, 'auswahl', $titel, $hilfe);
		$feld->optionen = $optionen;

		return $feld;
	}

	/**
	 * Legt ein mehrzeiliges Textfeld an.
	 *
	 * @param string $spalte Spaltenname
	 * @param string $titel  Beschriftung
	 * @param string $hilfe  Erklärung, darf leer sein
	 *
	 * @return self Das Feld
	 */
	public static function textarea(string $spalte, string $titel, string $hilfe = ''): self
	{
		return new self($spalte, 'textarea', $titel, $hilfe);
	}

	/**
	 * Legt eine Liste mit einer Angabe je Zeile an.
	 *
	 * @param string $spalte  Spaltenname
	 * @param string $titel   Beschriftung
	 * @param string $hilfe   Erklärung, darf leer sein
	 * @param string $trenner Verbindet die Zeilen beim Senden an die KAS
	 *
	 * @return self Das Feld
	 */
	public static function liste(string $spalte, string $titel, string $hilfe = '', string $trenner = ','): self
	{
		$feld = new self($spalte, 'liste', $titel, $hilfe);
		$feld->trenner = $trenner;

		return $feld;
	}

	/**
	 * Legt ein Kennwortfeld an, das nie gespeichert wird.
	 *
	 * @param string $spalte Spaltenname (die Spalte bleibt immer leer)
	 * @param string $titel  Beschriftung
	 * @param string $hilfe  Erklärung, darf leer sein
	 *
	 * @return self Das Feld
	 */
	public static function passwort(string $spalte, string $titel, string $hilfe = ''): self
	{
		return new self($spalte, 'passwort', $titel, $hilfe);
	}

	/**
	 * Legt ein mehrzeiliges Geheimfeld an, das nie gespeichert wird.
	 *
	 * @param string $spalte Spaltenname (die Spalte bleibt immer leer)
	 * @param string $titel  Beschriftung
	 * @param string $hilfe  Erklärung, darf leer sein
	 *
	 * @return self Das Feld
	 */
	public static function geheim(string $spalte, string $titel, string $hilfe = ''): self
	{
		return new self($spalte, 'geheim', $titel, $hilfe);
	}

	/**
	 * Nennt die Namen, unter denen die KAS den Wert liefert.
	 *
	 * @param string ...$namen Ein oder mehrere Namen; der erste in der Antwort
	 *                         vorhandene gilt
	 *
	 * @return self Das Feld
	 */
	public function kas(string ...$namen): self
	{
		$this->kasNamen = array_values($namen);

		return $this;
	}

	/**
	 * Nennt den Parameter beim Anlegen.
	 *
	 * @param string $param Name des Parameters der add_*-Funktion
	 *
	 * @return self Das Feld
	 */
	public function anlegen(string $param): self
	{
		$this->anlegenParam = $param;
		$this->beimAnlegen = true;

		return $this;
	}

	/**
	 * Nennt den Parameter beim Ändern.
	 *
	 * @param string $param Name des Parameters der update_*-Funktion
	 *
	 * @return self Das Feld
	 */
	public function aendern(string $param): self
	{
		$this->aendernParam = $param;

		return $this;
	}

	/**
	 * Schickt den Wert beim Anlegen zusätzlich unter weiteren Namen.
	 *
	 * Für Funktionen, bei denen Dokumentation und Beispiel der KAS
	 * verschiedene Namen nennen.
	 *
	 * @param string ...$params Die weiteren Parameternamen
	 *
	 * @return self Das Feld
	 */
	public function anlegenAuchAls(string ...$params): self
	{
		$this->anlegenAuch = array_values($params);

		return $this;
	}

	/**
	 * Nennt denselben Parameter für Anlegen und Ändern.
	 *
	 * @param string $param Name des Parameters
	 *
	 * @return self Das Feld
	 */
	public function beides(string $param): self
	{
		return $this->anlegen($param)->aendern($param);
	}

	/**
	 * Zeigt das Feld beim Anlegen, ohne dass es selbst hinausgeht.
	 *
	 * Für Angaben, aus denen der Bereich die Parameter erst berechnet, etwa
	 * den Domainnamen, der für add_domain in Name und TLD zerlegt wird.
	 *
	 * @return self Das Feld
	 */
	public function beimAnlegen(): self
	{
		$this->beimAnlegen = true;

		return $this;
	}

	/**
	 * Macht das Feld beim Anlegen zur Pflicht.
	 *
	 * @return self Das Feld
	 */
	public function pflicht(): self
	{
		$this->pflicht = true;

		return $this;
	}

	/**
	 * Schickt den Wert bei jeder Änderung mit.
	 *
	 * Für Parameter, die die KAS beim Ändern immer verlangt (etwa die
	 * Konfiguration einer Mailingliste).
	 *
	 * @return self Das Feld
	 */
	public function immerSenden(): self
	{
		$this->immerSenden = true;

		return $this;
	}

	/**
	 * Ordnet das Feld einer Aufrufgruppe des Bereichs zu.
	 *
	 * @param string $gruppe Schlüssel der Gruppe, etwa „ssl“
	 *
	 * @return self Das Feld
	 */
	public function gruppe(string $gruppe): self
	{
		$this->gruppe = $gruppe;

		return $this;
	}

	/**
	 * Setzt die Legende in der Maske.
	 *
	 * @param string $legende Schlüssel der Legende, etwa „ssl_legend“
	 *
	 * @return self Das Feld
	 */
	public function legende(string $legende): self
	{
		$this->legende = $legende;

		return $this;
	}

	/**
	 * Setzt den Vorgabewert neuer Datensätze.
	 *
	 * @param string $wert Wert in der Form der Spalte („1“ für Checkboxen)
	 *
	 * @return self Das Feld
	 */
	public function vorgabe(string $wert): self
	{
		$this->vorgabe = $wert;

		return $this;
	}

	/**
	 * Zeigt das Feld als Spalte in der Liste.
	 *
	 * @return self Das Feld
	 */
	public function inListe(): self
	{
		$this->inListe = true;

		return $this;
	}

	/**
	 * Ergänzt eval-Angaben der Maske.
	 *
	 * @param array<string, mixed> $eval Etwa array('rgxp' => 'email')
	 *
	 * @return self Das Feld
	 */
	public function eval(array $eval): self
	{
		$this->eval = array_merge($this->eval, $eval);

		return $this;
	}

	/**
	 * Sagt, ob der Wert nie gespeichert wird.
	 *
	 * @return bool true für Kennwörter und Geheimnisse
	 */
	public function istGeheim(): bool
	{
		return \in_array($this->art, array('passwort', 'geheim'), true);
	}

	/**
	 * Rechnet einen Wert der KAS in den Spaltenwert um.
	 *
	 * @param mixed $wert Wert aus der KAS-Antwort; null für „nicht geliefert“
	 *
	 * @return string Der Wert für die Spalte
	 */
	public function vonKas(mixed $wert): string
	{
		if (null === $wert || \is_array($wert)) {
			return '';
		}

		$text = \is_bool($wert) ? ($wert ? 'Y' : 'N') : trim((string) $wert);

		return match ($this->art) {
			'janein' => \in_array(strtoupper($text), array('Y', 'J', '1', 'TRUE'), true) ? '1' : '',
			'liste' => implode("\n", $this->teile($text)),
			default => $text,
		};
	}

	/**
	 * Rechnet einen Spaltenwert in den Wert für die KAS um.
	 *
	 * @param string $wert Der Spaltenwert
	 *
	 * @return string|int Der Wert für den KAS-Parameter
	 */
	public function zuKas(string $wert): string|int
	{
		return match ($this->art) {
			'janein' => '1' === $wert ? 'Y' : 'N',
			'zahl' => '' === trim($wert) ? '' : (int) $wert,
			'liste' => implode($this->trenner, $this->teile($wert)),
			default => $wert,
		};
	}

	/**
	 * Zerlegt eine Liste in ihre Einträge.
	 *
	 * Listen mit Zeilenumbruch als Trenner (Teilnehmer einer Mailingliste)
	 * werden nur an Zeilenumbrüchen zerlegt, weil ihre Einträge Leerzeichen
	 * enthalten dürfen („Max Muster <max@example.org>“). Alle übrigen trennt
	 * die KAS mal mit Komma, mal mit Leerzeichen; dort zählt jedes davon.
	 * Leere Einträge fallen weg.
	 *
	 * @param string $text Der Text
	 *
	 * @return array<int, string> Die Einträge in der Reihenfolge des Textes
	 */
	public function teile(string $text): array
	{
		$muster = "
" === $this->trenner ? '/\R/u' : '/[\s,;]+/u';
		$teile = preg_split($muster, $text) ?: array();

		return array_values(array_filter(array_map('trim', $teile), static fn (string $teil): bool => '' !== $teil));
	}
}
