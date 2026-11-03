<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Kas;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Ruft Funktionen der KAS-API auf.
 *
 * Kümmert sich um alles, was jeder Aufruf braucht:
 *
 * - Anmeldung: Je Zugang wird einmal über KasAuth ein Sitzungsschlüssel
 *   geholt (10 Minuten gültig, verlängert sich bei Benutzung) und für die
 *   Dauer der PHP-Anfrage im Speicher gehalten. Meldet die KAS die Sitzung
 *   als ungültig, wird einmal neu angemeldet.
 * - Flood-Sperre: Jede Antwort nennt in „KasFloodDelay“ die Sekunden bis zum
 *   nächsten erlaubten Aufruf. Der Client wartet sie ab. Kommt trotzdem der
 *   Fault „flood_protection“, wartet er nach und versucht es bis zu dreimal.
 * - Schreibschutz: Ist er am Zugang gesetzt, verlassen nur lesende Aufrufe
 *   (get_*) das Haus.
 *
 * Die Kennwörter landen nie im Protokoll; protokolliert werden nur Funktion,
 * Login und das Fehlerkürzel.
 */
class KasClient
{
	/** Gültigkeit eines Sitzungsschlüssels in Sekunden */
	private const SITZUNGSDAUER = 600;

	/** Wie oft nach „flood_protection“ erneut versucht wird */
	private const FLOOD_VERSUCHE = 3;

	/** Mindestwartezeit nach „flood_protection“ in Sekunden */
	private const FLOOD_MINDESTENS = 1.0;

	/** Fehlerkürzel, nach denen eine neue Anmeldung hilft */
	private const SITZUNG_UNGUELTIG = array('unknown_session', 'kas_session_invalid', 'session_expired');

	/** @var array<string, string> Sitzungsschlüssel je Login und Kennwort */
	private array $sitzungen = array();

	/** Frühester Zeitpunkt des nächsten Aufrufs (Unix-Zeit mit Bruchteilen) */
	private float $naechsterAufruf = 0.0;

	/** Wartet die übergebene Zahl Sekunden */
	private readonly \Closure $schlafen;

	/** Liefert die aktuelle Unix-Zeit mit Bruchteilen */
	private readonly \Closure $uhr;

	/** Das Protokoll */
	private readonly LoggerInterface $protokoll;

	/**
	 * @param Transport            $transport    Schickt die Umschläge ab
	 * @param SoapKodierer         $kodierer     Baut und liest die Umschläge
	 * @param string               $apiAdresse   Endpunkt der KAS-API
	 * @param string               $authAdresse  Endpunkt der Anmeldung
	 * @param LoggerInterface|null $protokoll    Contaos Systemprotokoll; null
	 *                                           in Tests
	 * @param \Closure|null        $schlafen     Ersatz für usleep() in Tests,
	 *                                           erhält Sekunden als float
	 * @param \Closure|null        $uhr          Ersatz für microtime(true) in
	 *                                           Tests
	 */
	public function __construct(
		private readonly Transport $transport,
		private readonly SoapKodierer $kodierer,
		private readonly string $apiAdresse,
		private readonly string $authAdresse,
		?LoggerInterface $protokoll = null,
		?\Closure $schlafen = null,
		?\Closure $uhr = null,
	) {
		$this->protokoll = $protokoll ?? new NullLogger();
		$this->schlafen = $schlafen ?? static function (float $sekunden): void {
			usleep((int) round($sekunden * 1000000));
		};
		$this->uhr = $uhr ?? static fn (): float => microtime(true);
	}

	/**
	 * Sagt, ob eine KAS-Funktion nur liest.
	 *
	 * @param string $aktion Name der Funktion, etwa „get_domains“
	 *
	 * @return bool true für get_*, die bei Schreibschutz erlaubt sind
	 */
	public static function istLesend(string $aktion): bool
	{
		return str_starts_with($aktion, 'get_');
	}

	/**
	 * Ruft eine Funktion der KAS-API auf.
	 *
	 * @param Zugangsdaten         $zugang Der Zugang
	 * @param string               $aktion Name der Funktion, etwa „add_domain“
	 * @param array<string, mixed> $params Die Parameter der Funktion ohne
	 *                                     Anmeldedaten; leere Texte werden
	 *                                     mitgeschickt, null-Werte nicht
	 *
	 * @return KasAntwort Der Inhalt von „ReturnInfo“ samt Flood-Sperre
	 *
	 * @throws KasFehler Bei Schreibschutz, Fehlern der KAS (Fault oder
	 *                   ReturnString ungleich „TRUE“) und Verbindungsfehlern
	 */
	public function aufrufen(Zugangsdaten $zugang, string $aktion, array $params = array()): KasAntwort
	{
		if ($zugang->schreibschutz && !self::istLesend($aktion)) {
			throw new KasFehler('schreibschutz', '', $aktion);
		}

		$params = array_filter($params, static fn ($wert): bool => null !== $wert);
		$neuAngemeldet = false;
		$versuch = 0;

		while (true) {
			++$versuch;
			$this->warten();

			try {
				$antwort = $this->einmalAufrufen($zugang, $aktion, $params);
			} catch (KasFehler $fehler) {
				if ('flood_protection' === $fehler->kasCode() && $versuch < self::FLOOD_VERSUCHE) {
					$this->sperreSetzen(max(self::FLOOD_MINDESTENS, (float) $fehler->detail()));

					continue;
				}

				if (!$neuAngemeldet && \in_array($fehler->kasCode(), self::SITZUNG_UNGUELTIG, true)) {
					unset($this->sitzungen[$this->sitzungsSchluessel($zugang)]);
					$neuAngemeldet = true;

					continue;
				}

				$this->protokoll->warning(sprintf('KAS-Aufruf %s für %s fehlgeschlagen: %s', $aktion, $zugang->login, $fehler->kasCode()));

				throw $fehler;
			}

			$this->sperreSetzen($antwort->floodDelay);

			return $antwort;
		}
	}

	/**
	 * Ruft eine lesende Funktion auf und liefert die Datensätze.
	 *
	 * Manche Listen meldet die KAS im leeren Zustand als Fault „empty_list“
	 * statt als leere Liste; das wird hier als leer gewertet.
	 *
	 * @param Zugangsdaten         $zugang Der Zugang
	 * @param string               $aktion Eine get_*-Funktion
	 * @param array<string, mixed> $params Ihre Parameter
	 *
	 * @return array<int, array<string, mixed>> Die Datensätze, leer wenn es
	 *                                          keine gibt
	 *
	 * @throws KasFehler Bei allen übrigen Fehlern
	 */
	public function lesen(Zugangsdaten $zugang, string $aktion, array $params = array()): array
	{
		try {
			return $this->aufrufen($zugang, $aktion, $params)->zeilen();
		} catch (KasFehler $fehler) {
			if ('empty_list' === $fehler->kasCode()) {
				return array();
			}

			throw $fehler;
		}
	}

	/**
	 * Prüft die Anmeldedaten, ohne etwas zu lesen.
	 *
	 * Holt einen frischen Sitzungsschlüssel. Dient dem Knopf „Zugang prüfen“.
	 *
	 * @param Zugangsdaten $zugang Der Zugang
	 *
	 * @throws KasFehler Wenn die KAS die Anmeldung ablehnt
	 */
	public function anmeldungPruefen(Zugangsdaten $zugang): void
	{
		unset($this->sitzungen[$this->sitzungsSchluessel($zugang)]);
		$this->warten();
		$this->sitzung($zugang);
	}

	/**
	 * Schickt genau einen Aufruf ab, ohne Wiederholung.
	 *
	 * @param Zugangsdaten         $zugang Der Zugang
	 * @param string               $aktion Name der Funktion
	 * @param array<string, mixed> $params Ihre Parameter
	 *
	 * @return KasAntwort Die ausgewertete Antwort
	 *
	 * @throws KasFehler Bei jedem Fehler
	 */
	private function einmalAufrufen(Zugangsdaten $zugang, string $aktion, array $params): KasAntwort
	{
		$xml = $this->kodierer->anfrage('KasApi', array(
			'kas_login'        => $zugang->login,
			'kas_auth_type'    => 'session',
			'kas_auth_data'    => $this->sitzung($zugang),
			'kas_action'       => $aktion,
			'KasRequestParams' => $params,
		));

		$daten = $this->kodierer->antwort(
			$this->transport->senden($this->apiAdresse, 'urn:xmethodsKasApi#KasApi', $xml),
			$aktion,
		);

		$antwort = \is_array($daten) && \is_array($daten['Response'] ?? null) ? $daten['Response'] : null;

		if (null === $antwort) {
			throw new KasFehler('ungueltige_antwort', 'Response fehlt', $aktion);
		}

		$ergebnis = $antwort['ReturnString'] ?? 'TRUE';

		if (\is_string($ergebnis) && 'TRUE' !== strtoupper($ergebnis)) {
			throw new KasFehler($ergebnis, '', $aktion);
		}

		return new KasAntwort($antwort['ReturnInfo'] ?? null, (float) ($antwort['KasFloodDelay'] ?? 0));
	}

	/**
	 * Liefert den Sitzungsschlüssel eines Zugangs, bei Bedarf neu angemeldet.
	 *
	 * @param Zugangsdaten $zugang Der Zugang
	 *
	 * @return string Der Sitzungsschlüssel
	 *
	 * @throws KasFehler Wenn die KAS die Anmeldung ablehnt
	 */
	private function sitzung(Zugangsdaten $zugang): string
	{
		$schluessel = $this->sitzungsSchluessel($zugang);

		if (isset($this->sitzungen[$schluessel])) {
			return $this->sitzungen[$schluessel];
		}

		$xml = $this->kodierer->anfrage('KasAuth', array(
			'kas_login'               => $zugang->login,
			'kas_auth_type'           => 'plain',
			'kas_auth_data'           => $zugang->passwort,
			'session_lifetime'        => self::SITZUNGSDAUER,
			'session_update_lifetime' => 'Y',
		));

		try {
			$token = $this->kodierer->antwort(
				$this->transport->senden($this->authAdresse, 'urn:xmethodsKasApiAuthentication#KasAuth', $xml),
			);
		} catch (KasFehler $fehler) {
			$this->protokoll->warning(sprintf('KAS-Anmeldung für %s fehlgeschlagen: %s', $zugang->login, $fehler->kasCode()));

			throw $fehler;
		}

		if (!\is_string($token) || '' === trim($token)) {
			throw new KasFehler('ungueltige_antwort', 'kein Sitzungsschlüssel');
		}

		// Die Anmeldung zählt für die Flood-Sperre mit; eine halbe Sekunde
		// Abstand hat sich bei anderen Clients bewährt.
		$this->sperreSetzen(0.5);

		return $this->sitzungen[$schluessel] = trim($token);
	}

	/**
	 * Bildet den Speicherschlüssel einer Sitzung.
	 *
	 * Das Kennwort geht als Prüfsumme ein, damit eine Kennwortänderung im
	 * selben Lauf eine neue Anmeldung auslöst.
	 *
	 * @param Zugangsdaten $zugang Der Zugang
	 *
	 * @return string Login und Prüfsumme
	 */
	private function sitzungsSchluessel(Zugangsdaten $zugang): string
	{
		return $zugang->login.'|'.hash('sha256', $zugang->passwort);
	}

	/**
	 * Wartet, bis die Flood-Sperre abgelaufen ist.
	 */
	private function warten(): void
	{
		$rest = $this->naechsterAufruf - ($this->uhr)();

		if ($rest > 0) {
			($this->schlafen)($rest);
		}
	}

	/**
	 * Merkt sich, wann der nächste Aufruf frühestens erfolgen darf.
	 *
	 * @param float $sekunden Abstand ab jetzt; Werte ≤ 0 ändern nichts
	 */
	private function sperreSetzen(float $sekunden): void
	{
		if ($sekunden > 0) {
			$this->naechsterAufruf = max($this->naechsterAufruf, ($this->uhr)() + $sekunden);
		}
	}
}
