<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Kas;

/**
 * Übersetzt Fehlerkürzel der KAS in deutsche Meldungen fürs Backend.
 *
 * Die KAS kennt einige hundert Kürzel; sie folgen aber wenigen Mustern
 * (*_syntax_incorrect, max_*_reached, *_not_found …). Feste Texte gibt es
 * für die häufigen Einzelfälle, alles Übrige wird über die Muster erklärt.
 * Das Originalkürzel steht immer in Klammern dahinter, damit man beim
 * Support von All-Inkl danach fragen kann.
 */
final class Fehlertext
{
	/** Feste Texte für einzelne Kürzel */
	private const TEXTE = array(
		'schreibschutz'           => 'Der KAS-Zugang ist schreibgeschützt, deshalb ging nichts an All-Inkl. Den Schreibschutz schaltet man im Modul „KAS-Zugänge“ ab.',
		'verbindung'              => 'Die KAS-Schnittstelle war nicht erreichbar.',
		'ungueltige_antwort'      => 'Die KAS hat unverständlich geantwortet.',
		'flood_protection'        => 'Die KAS hat zu viele Anfragen kurz hintereinander abgewiesen. Bitte in einigen Sekunden erneut versuchen.',
		'in_progress'             => 'All-Inkl bearbeitet dieses Objekt gerade noch. Bitte in einigen Minuten erneut versuchen.',
		'missing_parameter'       => 'Der KAS fehlt eine Pflichtangabe.',
		'nothing_to_do'           => 'Es gab nichts zu ändern.',
		'kas_password_incorrect'  => 'Die KAS hat das Kennwort abgelehnt.',
		'kas_login_incorrect'     => 'Die KAS kennt dieses Login nicht.',
		'kas_login_syntax_incorrect' => 'Das KAS-Login hat nicht die erwartete Form (etwa „w0123456“).',
		'kas_auth_failed'         => 'Die Anmeldung bei der KAS ist fehlgeschlagen.',
		'no_auth'                 => 'Die Anmeldung bei der KAS ist fehlgeschlagen.',
		'got_no_login_data'       => 'Die KAS hat keine Anmeldedaten erhalten.',
		'kas_access_forbidden'    => 'Für diesen Account ist der Zugang zur KAS gesperrt.',
		'ip_blocked'              => 'Die KAS sperrt diese IP-Adresse vorübergehend (zu viele Fehlversuche).',
		'session_2fa_required'    => 'Für diesen Account ist die Zwei-Faktor-Anmeldung aktiv; die KAS-API lässt sich damit nicht automatisch nutzen.',
		'unkown_action'           => 'Die KAS kennt diese Funktion nicht.',
		'unknown_session'         => 'Die Sitzung bei der KAS ist abgelaufen.',
		'kas_session_invalid'     => 'Die Sitzung bei der KAS ist abgelaufen.',
		'account_is_dummyaccount' => 'In diesem Account ist das nicht möglich.',
		'host_is_dummyhost'       => 'Dieser Host lässt sich nicht bearbeiten.',
		'dns_settings_not_allowed' => 'Dieser Account darf keine DNS-Einstellungen ändern.',
		'settings_not_in_contract' => 'Das ist im Tarif nicht enthalten.',
		'record_already_exists'   => 'Diesen DNS-Eintrag gibt es schon.',
		'hostname_forbidden'      => 'Dieser Hostname ist nicht erlaubt.',
		'hostname_exists_as_domain' => 'Diesen Hostnamen gibt es schon als Domain.',
		'hostname_exist_as_domain' => 'Diesen Hostnamen gibt es schon als Domain.',
		'hostname_exist_as_subdomain' => 'Diesen Hostnamen gibt es schon als Subdomain.',
		'hostname_has_active_mail_addresses' => 'Unter diesem Hostnamen gibt es noch aktive E-Mail-Adressen.',
		'empty_list'              => 'Die Liste ist leer.',
	);

	/**
	 * Liefert die Meldung zu einem Fehler.
	 *
	 * @param \Throwable $fehler Ein KasFehler oder ein anderer Fehler
	 *
	 * @return string Die deutsche Meldung samt Kürzel
	 */
	public static function zu(\Throwable $fehler): string
	{
		if (!$fehler instanceof KasFehler) {
			return $fehler->getMessage();
		}

		$code = $fehler->kasCode();
		$text = self::TEXTE[$code] ?? self::nachMuster($code);

		if ('verbindung' === $code && '' !== $fehler->detail()) {
			$text .= ' '.$fehler->detail();
		}

		return sprintf('%s (KAS: %s%s)', $text, '' === $fehler->aktion() ? '' : $fehler->aktion().' – ', $code);
	}

	/**
	 * Erklärt ein Kürzel über seine Form.
	 *
	 * @param string $code Das Kürzel
	 *
	 * @return string Die Meldung
	 */
	private static function nachMuster(string $code): string
	{
		$name = static fn (string $kern): string => str_replace('_', ' ', $kern);

		if (1 === preg_match('/^(.+)_syntax_incorrect$/', $code, $treffer)) {
			return sprintf('Die Angabe „%s“ hat nicht die Form, die die KAS erwartet.', $name($treffer[1]));
		}

		if (1 === preg_match('/^max_(.+)_reached$/', $code, $treffer) || 1 === preg_match('/^(.+)_limit_reached$/', $code, $treffer)) {
			return sprintf('Das Kontingent ist ausgeschöpft (%s).', $name($treffer[1]));
		}

		if (1 === preg_match('/^(.+?)_(not_found|not_found_in_kas|doesnt_exist|doenst_exist|does_not_exist)$/', $code, $treffer)) {
			return sprintf('Die KAS findet „%s“ nicht.', $name($treffer[1]));
		}

		if (1 === preg_match('/^(.+)_not_allowed$/', $code, $treffer)) {
			return sprintf('Das ist nicht erlaubt (%s).', $name($treffer[1]));
		}

		if (1 === preg_match('/^(.+)_(already_exists|exists|exist)$/', $code, $treffer)) {
			return sprintf('Das gibt es schon (%s).', $name($treffer[1]));
		}

		return 'Die KAS hat den Auftrag abgelehnt.';
	}
}
