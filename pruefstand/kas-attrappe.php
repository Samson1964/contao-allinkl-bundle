<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

/*
 * KAS-Attrappe für den Prüfstand.
 *
 * Ein nachgebauter KAS-Server für den eingebauten PHP-Webserver, damit sich
 * Abgleich, Anlegen, Ändern und Löschen im Backend durchspielen lassen, ohne
 * ein echtes All-Inkl-Konto anzufassen. Er benutzt PHPs SoapServer mit den
 * echten WSDL-Dateien der KAS; die Antworten haben damit genau das Format der
 * KAS (Apache-Map, SOAP-ENC:Array), Fehler kommen als SOAP-Fault.
 *
 * Start (die SOAP-Erweiterung ist in XAMPP nur auskommentiert):
 *
 *   C:\xampp\php\php.exe -d extension=soap -S 127.0.0.1:8174 pruefstand\kas-attrappe.php
 *
 * Das Bundle zeigt per config/config.yaml darauf:
 *
 *   contao_allinkl:
 *       api_adresse: 'http://127.0.0.1:8174/KasApi.php'
 *       auth_adresse: 'http://127.0.0.1:8174/KasAuth.php'
 *
 * Anmeldung: jedes Login, Kennwort „attrappe“ (oder das per
 * update_accountsettings gesetzte). Prüfhilfen:
 *
 * - GET /zustand zeigt den Zustand als JSON, GET /zuruecksetzen stellt die
 *   Startdaten wieder her.
 * - Jeder Parameter mit dem Wert „FEHLER“ führt zum Fault
 *   „<parameter>_syntax_incorrect“ – so lässt sich der Fehlerweg prüfen.
 * - Unbekannte Parameternamen werden mit „unknown_parameter“ abgewiesen
 *   (strenger als die echte KAS), damit Abweichungen von der Dokumentation
 *   auffallen. Die erlaubten Namen stehen in kas-parameter.php.
 */

if ('cli-server' !== PHP_SAPI) {
	exit('Nur für den eingebauten PHP-Webserver.');
}

if (!class_exists(SoapServer::class)) {
	http_response_code(500);
	exit('Die SOAP-Erweiterung fehlt: php -d extension=soap -S …');
}

const ZUSTAND = __DIR__.'/attrappe-zustand.json';

$pfad = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

if ('/zuruecksetzen' === $pfad) {
	file_put_contents(ZUSTAND, json_encode(Attrappe::startdaten(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	header('Content-Type: text/plain; charset=utf-8');
	exit('zurückgesetzt');
}

if ('/zustand' === $pfad) {
	header('Content-Type: application/json; charset=utf-8');
	exit(json_encode(Attrappe::laden(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$wsdl = match ($pfad) {
	'/KasAuth.php' => __DIR__.'/KasAuth.wsdl',
	'/KasApi.php' => __DIR__.'/KasApi.wsdl',
	default => null,
};

if (null === $wsdl || 'POST' !== $_SERVER['REQUEST_METHOD']) {
	http_response_code(404);
	exit('KAS-Attrappe: POST /KasAuth.php, POST /KasApi.php, GET /zustand, GET /zuruecksetzen');
}

$server = new SoapServer($wsdl, array('cache_wsdl' => WSDL_CACHE_NONE));
$server->setObject(new Attrappe());
$server->handle();

/**
 * Die nachgebaute KAS.
 */
final class Attrappe
{
	/** Wartezeit, die jede Antwort als KasFloodDelay nennt */
	private const FLOOD = 0.2;

	/** Die Bereiche: Sammlung => Schlüsselfeld */
	private const SCHLUESSEL = array(
		'accounts'            => 'account_login',
		'domains'             => 'domain_name',
		'subdomains'          => 'subdomain_name',
		'dns'                 => 'record_id',
		'mailaccounts'        => 'mail_login',
		'mailforwards'        => 'mail_forward_address',
		'mailinglists'        => 'mailinglist_name',
		'databases'           => 'database_login',
		'ftpusers'            => 'ftp_login',
		'cronjobs'            => 'cronjob_id',
		'directoryprotection' => 'schluessel',
		'ddnsusers'           => 'dyndns_login',
		'sambausers'          => 'samba_login',
	);

	/** @var array<string, mixed> Der Zustand */
	private array $zustand;

	/**
	 * Lädt den Zustand oder legt die Startdaten an.
	 *
	 * @return array<string, mixed> Der Zustand
	 */
	public static function laden(): array
	{
		if (!is_file(ZUSTAND)) {
			file_put_contents(ZUSTAND, json_encode(self::startdaten(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
		}

		return json_decode((string) file_get_contents(ZUSTAND), true);
	}

	/**
	 * Liefert die Startdaten: ein Account mit je ein bis zwei Objekten.
	 *
	 * @return array<string, mixed> Der Zustand
	 */
	public static function startdaten(): array
	{
		return array(
			'passwort' => 'attrappe',
			'naechste_id' => 1000,
			'accountsettings' => array('account_login' => 'w0000001', 'account_comment' => 'Prüfstand', 'account_contact_mail' => 'pruefung@example.org', 'logging' => 'kurz', 'logage' => 30, 'statistic' => 'de', 'show_password' => 'N'),
			'accounts' => array(
				array('account_login' => 'w0000002', 'account_comment' => 'Jugendseite', 'account_contact_mail' => '', 'max_account' => 0, 'max_domain' => 1, 'max_subdomain' => 5, 'max_webspace' => 2048, 'max_mail_account' => 10, 'max_mail_forward' => 10, 'max_mail_list' => 1, 'max_databases' => 2, 'max_ftpuser' => 2, 'max_sambauser' => 0, 'max_cronjobs' => 2, 'max_wbk' => 0, 'inst_htaccess' => 'Y', 'inst_fpse' => 'N', 'inst_software' => 'Y', 'dns_settings' => 'N', 'show_password' => 'N', 'kas_access_forbidden' => 'N', 'logging' => 'keine', 'logage' => 190, 'statistic' => '0', 'ssh_access' => 'N', 'ssh_keys' => ''),
			),
			'domains' => array(
				array('domain_name' => 'verein.test', 'domain_path' => '/verein.test/', 'domain_redirect_status' => '0', 'php_version' => '8.3', 'statistic_version' => '5', 'statistic_language' => 'de', 'is_active' => 'Y', 'in_progress' => 'FALSE', 'ssl_certificate_sni_is_active' => 'Y', 'ssl_certificate_sni_force_https' => 'Y', 'ssl_certificate_sni_hsts_max_age' => '-1', 'ssl_certificate_sni_crt' => '', 'ssl_certificate_sni_key' => '-----BEGIN PRIVATE KEY-----geheim', 'ssl_certificate_sni_bundle' => '', 'ssl_certificate_sni_csr' => ''),
				array('domain_name' => 'schach.test', 'domain_path' => 'https://verein.test/', 'domain_redirect_status' => '301', 'php_version' => '8.2', 'statistic_version' => '0', 'statistic_language' => 'de', 'is_active' => 'Y', 'in_progress' => 'FALSE', 'ssl_certificate_sni_is_active' => 'N', 'ssl_certificate_sni_force_https' => 'N', 'ssl_certificate_sni_hsts_max_age' => '-1'),
			),
			'subdomains' => array(
				array('subdomain_name' => 'forum.verein.test', 'subdomain_path' => '/forum/', 'subdomain_redirect_status' => '0', 'php_version' => '8.3', 'statistic_version' => '5', 'statistic_language' => 'de', 'is_active' => 'Y', 'in_progress' => 'FALSE', 'ssl_certificate_sni_is_active' => 'Y', 'ssl_certificate_sni_force_https' => 'N'),
				array('subdomain_name' => 'fremd.anders.test', 'subdomain_path' => '/fremd/', 'subdomain_redirect_status' => '0', 'php_version' => '8.3', 'is_active' => 'Y', 'in_progress' => 'FALSE'),
			),
			'dns' => array(
				array('record_zone' => 'verein.test', 'record_name' => '', 'record_type' => 'A', 'record_data' => '192.0.2.10', 'record_aux' => 0, 'record_id' => '501', 'record_changeable' => 'Y', 'record_deleteable' => 'Y'),
				array('record_zone' => 'verein.test', 'record_name' => 'www', 'record_type' => 'CNAME', 'record_data' => 'verein.test.', 'record_aux' => 0, 'record_id' => '502', 'record_changeable' => 'Y', 'record_deleteable' => 'Y'),
				array('record_zone' => 'verein.test', 'record_name' => '', 'record_type' => 'MX', 'record_data' => 'mail.verein.test.', 'record_aux' => 10, 'record_id' => '503', 'record_changeable' => 'Y', 'record_deleteable' => 'Y'),
				array('record_zone' => 'schach.test', 'record_name' => '', 'record_type' => 'A', 'record_data' => '192.0.2.11', 'record_aux' => 0, 'record_id' => '601', 'record_changeable' => 'Y', 'record_deleteable' => 'Y'),
			),
			'mailaccounts' => array(
				array('mail_login' => 'm0000101', 'mail_adresses' => 'info@verein.test', 'mail_addresses' => 'info@verein.test', 'mail_comment' => '', 'mail_is_active' => 'Y', 'webmail_autologin' => 'Y', 'mail_responder' => 'N', 'mail_responder_content_type' => 'text', 'mail_responder_displayname' => '', 'mail_responder_text' => '', 'mail_copy_adress' => '', 'mail_copy_address' => '', 'mail_sender_alias' => '', 'mail_allow_nets' => '', 'mail_xlist_enabled' => 'Y', 'mail_xlist_sent' => 'Sent', 'mail_xlist_drafts' => 'Drafts', 'mail_xlist_trash' => 'Trash', 'mail_xlist_spam' => 'Spam', 'mail_xlist_archiv' => 'Archiv', 'mail_spamfilter' => 'greyl', 'mail_password' => 'darf-nie-gespeichert-werden', 'in_progress' => 'FALSE'),
			),
			'mailforwards' => array(
				array('mail_forward_address' => 'vorstand@verein.test', 'mail_forward_adress' => 'vorstand@verein.test', 'mail_forward_comment' => '', 'mail_forward_targets' => 'a@example.org, b@example.org', 'mail_forward_spamfilter' => '', 'in_progress' => 'FALSE'),
			),
			'mailinglists' => array(
				array('mailinglist_name' => 'mitglieder', 'mailinglist_domain' => 'verein.test', 'mailinglist_is_active' => 'Y', 'mailinglist_subscriber' => "a@example.org\nMax Muster <max@example.org>", 'mailinglist_restrict_post' => '', 'mailinglist_config' => "subject_prefix = [Mitglieder]\n", 'in_progress' => 'FALSE'),
			),
			'databases' => array(
				array('database_login' => 'd0000201', 'database_name' => 'd0000201', 'database_comment' => 'Contao', 'database_allowed_hosts' => '', 'used_database_space' => 42, 'in_progress' => 'FALSE'),
			),
			'ftpusers' => array(
				array('ftp_login' => 'f0000301', 'ftp_comment' => 'Webdesigner', 'ftp_path' => '/verein.test/', 'ftp_permission_read' => 'Y', 'ftp_permission_write' => 'Y', 'ftp_permission_list' => 'Y', 'ftp_virus_clamav' => 'Y', 'ftp_is_main_user' => 'N', 'in_progress' => 'FALSE'),
			),
			'cronjobs' => array(
				array('cronjob_id' => '401', 'cronjob_comment' => 'Contao-Cron', 'protocol' => 'https', 'http_url' => 'verein.test/_contao/cron', 'minute' => '*/5', 'hour' => '*', 'day_of_month' => '*', 'month' => '*', 'day_of_week' => '*', 'http_user' => '', 'mail_adress' => '', 'mail_condition' => '', 'mail_subject' => 'default', 'is_active' => 'Y'),
			),
			'directoryprotection' => array(
				array('schluessel' => '/verein.test/intern/|vorstand', 'directory_path' => '/verein.test/intern/', 'directory_user' => 'vorstand', 'directory_authname' => 'Interner Bereich'),
			),
			'ddnsusers' => array(),
			'sambausers' => array(),
		);
	}

	/**
	 * Lädt den Zustand.
	 */
	public function __construct()
	{
		$this->zustand = self::laden();
	}

	/**
	 * Die Anmeldung (KasAuth).
	 *
	 * @param string $params JSON mit kas_login, kas_auth_type, kas_auth_data
	 *
	 * @return string Der Sitzungsschlüssel
	 *
	 * @throws SoapFault Bei falschem Kennwort
	 */
	public function KasAuth(string $params): string
	{
		$p = json_decode($params, true) ?: array();

		if (($p['kas_auth_data'] ?? '') !== $this->zustand['passwort']) {
			throw new SoapFault('Client', 'kas_password_incorrect', 'KasAuth');
		}

		return 'attrappe-'.($p['kas_login'] ?? '');
	}

	/**
	 * Ein Aufruf der KAS-API.
	 *
	 * @param string $params JSON mit Anmeldung, kas_action und KasRequestParams
	 *
	 * @return array<string, mixed> Request und Response wie bei der KAS
	 *
	 * @throws SoapFault Bei jedem Fehler
	 */
	public function KasApi(string $params): array
	{
		$p = json_decode($params, true) ?: array();

		if ('session' !== ($p['kas_auth_type'] ?? '') || !str_starts_with((string) ($p['kas_auth_data'] ?? ''), 'attrappe-')) {
			throw new SoapFault('Client', 'kas_session_invalid', 'KasApi');
		}

		$aktion = (string) ($p['kas_action'] ?? '');
		$r = \is_array($p['KasRequestParams'] ?? null) ? $p['KasRequestParams'] : array();
		$this->pruefen($aktion, $r);

		$info = $this->ausfuehren($aktion, $r);
		file_put_contents(ZUSTAND, json_encode($this->zustand, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

		return array(
			'Request'  => array('KasRequestTime' => time(), 'KasRequestType' => null, 'KasRequestParams' => $r),
			'Response' => array('KasFloodDelay' => self::FLOOD, 'ReturnString' => 'TRUE', 'ReturnInfo' => $info),
		);
	}

	/**
	 * Prüft Funktion und Parameternamen gegen die Dokumentation.
	 *
	 * @param string               $aktion Die Funktion
	 * @param array<string, mixed> $r      Die Parameter
	 *
	 * @throws SoapFault Bei unbekannter Funktion, unbekanntem Parameter oder
	 *                   dem Prüfwert „FEHLER“
	 */
	private function pruefen(string $aktion, array $r): void
	{
		static $erlaubt = null;
		$erlaubt ??= require __DIR__.'/kas-parameter.php';

		if (!isset($erlaubt[$aktion])) {
			throw new SoapFault('Server', 'unkown_action', 'KasApi', $aktion);
		}

		foreach ($r as $name => $wert) {
			$muster = preg_replace('/_\d+$/', '_N', (string) $name);

			if (!\in_array($name, $erlaubt[$aktion], true) && !\in_array($muster, $erlaubt[$aktion], true)) {
				throw new SoapFault('Server', 'unknown_parameter', 'KasApi', $aktion.': '.$name);
			}

			if ('FEHLER' === $wert) {
				throw new SoapFault('Server', $name.'_syntax_incorrect', 'KasApi');
			}
		}
	}

	/**
	 * Führt eine Funktion aus.
	 *
	 * @param string               $aktion Die Funktion
	 * @param array<string, mixed> $r      Die Parameter
	 *
	 * @return mixed Inhalt von ReturnInfo
	 *
	 * @throws SoapFault Wenn ein Objekt fehlt
	 */
	private function ausfuehren(string $aktion, array $r): mixed
	{
		$jn = static fn ($wert, string $vorgabe = 'Y'): string => \in_array($wert, array('Y', 'N'), true) ? $wert : $vorgabe;

		switch ($aktion) {
			// Lesen
			case 'get_accounts': return $this->liste('accounts', $r['account_login'] ?? null);
			case 'get_domains': return $this->liste('domains', $r['domain_name'] ?? null);
			case 'get_subdomains': return $this->liste('subdomains', $r['subdomain_name'] ?? null);
			case 'get_mailaccounts': return $this->liste('mailaccounts', $r['mail_login'] ?? null);
			case 'get_mailforwards': return $this->liste('mailforwards', $r['mail_forward'] ?? null);
			case 'get_mailinglists': return $this->liste('mailinglists', $r['mailinglist_name'] ?? null);
			case 'get_databases': return $this->liste('databases', $r['database_login'] ?? null);
			case 'get_ftpusers': return $this->liste('ftpusers', $r['ftp_login'] ?? null);
			case 'get_cronjobs': return $this->liste('cronjobs', $r['cronjob_id'] ?? null);
			case 'get_ddnsusers': return $this->liste('ddnsusers', $r['ddns_login'] ?? null);
			case 'get_sambausers': return $this->liste('sambausers', $r['samba_login'] ?? null);
			case 'get_accountsettings': return $this->zustand['accountsettings'];

			case 'get_directoryprotection':
				return array_values(array_map(static function (array $zeile): array {
					unset($zeile['schluessel']);

					return $zeile;
				}, array_filter($this->zustand['directoryprotection'], static fn (array $zeile): bool => !isset($r['directory_path']) || $zeile['directory_path'] === $r['directory_path'])));

			case 'get_dns_settings':
				$zone = rtrim((string) ($r['zone_host'] ?? ''), '.');

				if (!$this->finde('domains', $zone)) {
					throw new SoapFault('Server', 'zone_not_found', 'KasApi');
				}

				return array_values(array_filter($this->zustand['dns'], static fn (array $zeile): bool => $zeile['record_zone'] === $zone && (!isset($r['record_id']) || (string) $zeile['record_id'] === (string) $r['record_id'])));

			// Werkzeuge
			case 'get_accountresources': return array('max_domain' => array('max' => 10, 'used' => \count($this->zustand['domains'])), 'max_mail_account' => array('max' => 100, 'used' => \count($this->zustand['mailaccounts'])));
			case 'get_space': return array(array('account_login' => 'w0000001', 'max_webspace' => 51200, 'used_htdocs_space' => 1234, 'used_database_space' => 42, 'used_mailaccount_space' => 88, 'last_calculation' => time()));
			case 'get_space_usage': return array(array('directory' => (string) ($r['directory'] ?? '/'), 'used_webspace' => 1234, 'has_sub_dirs' => 'Y'));
			case 'get_server_information': return array(array('service' => 'php', 'version' => '8.4', 'interface' => 'fpm'), array('service' => 'php', 'version' => '8.3', 'interface' => 'fpm'));
			case 'get_traffic': return array(array('year' => (int) ($r['year'] ?? date('Y')), 'month' => (int) ($r['month'] ?? date('n')), 'http_traffic' => 1048576, 'ftp_traffic' => 2048));
			case 'get_topleveldomains': return array(array('tld_name' => 'de', 'tld_minlen' => 1, 'tld_maxlen' => 63), array('tld_name' => 'test', 'tld_minlen' => 1, 'tld_maxlen' => 63));
			case 'get_softwareinstall': return array(array('software_id' => 'contao_5.7', 'software_name' => 'Contao', 'software_version' => '5.7', 'software_category' => 'CMS'));
			case 'get_mailstandardfilter': return array(array('filter' => 'greyl', 'title' => 'Greylisting', 'type' => 'standard'), array('filter' => 'rbl_cbl', 'title' => 'RBL CBL', 'type' => 'content'));
			case 'update_chown':
			case 'add_symlink':
			case 'add_softwareinstall':
				return 'TRUE';

			// Accounteinstellungen
			case 'update_accountsettings':
				if (isset($r['account_password'])) {
					$this->zustand['passwort'] = (string) $r['account_password'];
					unset($r['account_password']);
				}

				$this->zustand['accountsettings'] = array_merge($this->zustand['accountsettings'], $r);

				return 'TRUE';

			// Unteraccounts
			case 'add_account':
				$login = sprintf('w%07d', $this->id());
				$this->zustand['accounts'][] = array_merge(array('account_login' => $login, 'ssh_access' => 'N', 'ssh_keys' => ''), $this->ohne($r, array('account_kas_password', 'account_ftp_password', 'hostname_art', 'hostname_part1', 'hostname_part2', 'hostname_path')));

				return $login;
			case 'update_account': return $this->aendern('accounts', (string) $r['account_login'], $this->ohne($r, array('account_login', 'account_kas_password')));
			case 'update_superusersettings': return $this->aendern('accounts', (string) $r['account_login'], $this->ohne($r, array('account_login')));
			case 'delete_account': return $this->entfernen('accounts', (string) $r['account_login']);

			// Domains und Subdomains
			case 'add_domain':
				$name = strtolower($r['domain_name'].'.'.($r['domain_tld'] ?? ''));
				$this->zustand['domains'][] = array('domain_name' => $name, 'domain_path' => $r['domain_path'] ?? '/'.$name.'/', 'domain_redirect_status' => (string) ($r['redirect_status'] ?? '0'), 'php_version' => $r['php_version'] ?? '8.3', 'statistic_version' => $r['statistic_version'] ?? '5', 'statistic_language' => $r['statistic_language'] ?? 'de', 'is_active' => 'Y', 'in_progress' => 'FALSE');
				$this->zustand['dns'][] = array('record_zone' => $name, 'record_name' => '', 'record_type' => 'A', 'record_data' => '192.0.2.99', 'record_aux' => 0, 'record_id' => (string) $this->id(), 'record_changeable' => 'Y', 'record_deleteable' => 'Y');

				return 'TRUE';
			case 'update_domain': return $this->hostAendern('domains', 'domain', $r, 'domain_name');
			case 'delete_domain':
				$this->zustand['dns'] = array_values(array_filter($this->zustand['dns'], static fn (array $zeile): bool => $zeile['record_zone'] !== $r['domain_name']));

				return $this->entfernen('domains', (string) $r['domain_name']);
			case 'move_domain': return $this->entfernen('domains', (string) $r['domain_name']);

			case 'add_subdomain':
				$name = strtolower($r['subdomain_name'].'.'.$r['domain_name']);
				$this->zustand['subdomains'][] = array('subdomain_name' => $name, 'subdomain_path' => $r['subdomain_path'] ?? '/'.$name.'/', 'subdomain_redirect_status' => (string) ($r['redirect_status'] ?? '0'), 'php_version' => $r['php_version'] ?? '8.3', 'is_active' => 'Y', 'in_progress' => 'FALSE');

				return 'TRUE';
			case 'update_subdomain': return $this->hostAendern('subdomains', 'subdomain', $r, 'subdomain_name');
			case 'delete_subdomain':
			case 'move_subdomain':
				return $this->entfernen('subdomains', (string) $r['subdomain_name']);

			case 'update_ssl':
				$sammlung = $this->finde('domains', (string) $r['hostname']) ? 'domains' : 'subdomains';
				$ssl = array();

				foreach (array('ssl_certificate_is_active' => 'ssl_certificate_sni_is_active', 'ssl_certificate_force_https' => 'ssl_certificate_sni_force_https', 'ssl_certificate_hsts_max_age' => 'ssl_certificate_sni_hsts_max_age') as $param => $feld) {
					if (isset($r[$param])) {
						$ssl[$feld] = (string) $r[$param];
					}
				}

				foreach (array('ssl_certificate_sni_crt', 'ssl_certificate_sni_key', 'ssl_certificate_sni_bundle', 'ssl_certificate_sni_csr') as $feld) {
					if (isset($r[$feld])) {
						$ssl[$feld] = (string) $r[$feld];
					}
				}

				return $this->aendern($sammlung, (string) $r['hostname'], $ssl);

			// DNS
			case 'add_dns_settings':
				$id = (string) $this->id();
				$this->zustand['dns'][] = array('record_zone' => rtrim((string) $r['zone_host'], '.'), 'record_name' => (string) $r['record_name'], 'record_type' => (string) $r['record_type'], 'record_data' => (string) $r['record_data'], 'record_aux' => (int) ($r['record_aux'] ?? 0), 'record_id' => $id, 'record_changeable' => 'Y', 'record_deleteable' => 'Y');

				return $id;
			case 'update_dns_settings': return $this->aendern('dns', (string) $r['record_id'], $this->ohne($r, array('record_id')));
			case 'delete_dns_settings': return $this->entfernen('dns', (string) $r['record_id']);
			case 'reset_dns_settings':
				$zone = rtrim((string) $r['zone_host'], '.');
				$this->zustand['dns'] = array_values(array_filter($this->zustand['dns'], static fn (array $zeile): bool => $zeile['record_zone'] !== $zone));
				$this->zustand['dns'][] = array('record_zone' => $zone, 'record_name' => '', 'record_type' => 'A', 'record_data' => '192.0.2.1', 'record_aux' => 0, 'record_id' => (string) $this->id(), 'record_changeable' => 'Y', 'record_deleteable' => 'Y');

				return 'TRUE';

			// Postfächer und Spamfilter
			case 'add_mailaccount':
				$login = sprintf('m%07d', $this->id());
				$adresse = $r['local_part'].'@'.$r['domain_part'];
				$this->zustand['mailaccounts'][] = array('mail_login' => $login, 'mail_adresses' => $adresse, 'mail_addresses' => $adresse, 'mail_is_active' => 'Y', 'webmail_autologin' => $jn($r['webmail_autologin'] ?? 'Y'), 'mail_responder' => $r['responder'] ?? 'N', 'mail_responder_text' => $r['responder_text'] ?? '', 'mail_copy_adress' => $r['copy_adress'] ?? '', 'mail_spamfilter' => '', 'in_progress' => 'TRUE');

				return $login;
			case 'update_mailaccount':
				$aenderung = $this->ohne($r, array('mail_login', 'mail_new_password'));

				foreach (array('is_active' => 'mail_is_active', 'responder' => 'mail_responder', 'responder_text' => 'mail_responder_text', 'copy_adress' => 'mail_copy_adress') as $param => $feld) {
					if (isset($aenderung[$param])) {
						$aenderung[$feld] = $aenderung[$param];
						unset($aenderung[$param]);
					}
				}

				return $this->aendern('mailaccounts', (string) $r['mail_login'], $aenderung + array('in_progress' => 'FALSE'));
			case 'delete_mailaccount': return $this->entfernen('mailaccounts', (string) $r['mail_login']);
			case 'add_mailstandardfilter': return $this->aendern('mailaccounts', (string) $r['mail_login'], array('mail_spamfilter' => (string) $r['filter']));
			case 'delete_mailstandardfilter': return $this->aendern('mailaccounts', (string) $r['mail_login'], array('mail_spamfilter' => ''));

			// Weiterleitungen
			case 'add_mailforward':
			case 'update_mailforward':
				$ziele = array();

				foreach ($r as $name => $wert) {
					if (1 === preg_match('/^target_\d+$/', (string) $name)) {
						$ziele[] = $wert;
					}
				}

				if ('update_mailforward' === $aktion) {
					return $this->aendern('mailforwards', (string) $r['mail_forward'], array('mail_forward_targets' => implode(', ', $ziele)));
				}

				$adresse = $r['local_part'].'@'.$r['domain_part'];
				$this->zustand['mailforwards'][] = array('mail_forward_address' => $adresse, 'mail_forward_adress' => $adresse, 'mail_forward_targets' => implode(', ', $ziele), 'in_progress' => 'FALSE');

				return 'TRUE';
			case 'delete_mailforward': return $this->entfernen('mailforwards', (string) $r['mail_forward']);

			// Mailinglisten
			case 'add_mailinglist':
				$this->zustand['mailinglists'][] = array('mailinglist_name' => (string) $r['mailinglist_name'], 'mailinglist_domain' => (string) $r['mailinglist_domain'], 'mailinglist_is_active' => 'Y', 'mailinglist_subscriber' => '', 'mailinglist_restrict_post' => '', 'mailinglist_config' => '');

				return 'TRUE';
			case 'update_mailinglist':
				if (!isset($r['config'])) {
					throw new SoapFault('Server', 'missing_parameter', 'KasApi', 'config');
				}

				return $this->aendern('mailinglists', (string) $r['mailinglist_name'], array('mailinglist_config' => $r['config']) + (isset($r['subscriber']) ? array('mailinglist_subscriber' => $r['subscriber']) : array()) + (isset($r['restrict_post']) ? array('mailinglist_restrict_post' => $r['restrict_post']) : array()) + (isset($r['is_active']) ? array('mailinglist_is_active' => $r['is_active']) : array()));
			case 'delete_mailinglist': return $this->entfernen('mailinglists', (string) $r['mailinglist_name']);

			// Datenbanken, FTP, Cronjobs, Verzeichnisschutz, DynDNS, Netzlaufwerke
			case 'add_database':
				$login = sprintf('d%07d', $this->id());
				$this->zustand['databases'][] = array('database_login' => $login, 'database_name' => $login, 'database_comment' => (string) $r['database_comment'], 'database_allowed_hosts' => $r['database_allowed_hosts'] ?? '', 'used_database_space' => 0);

				return $login;
			case 'update_database': return $this->aendern('databases', (string) $r['database_login'], $this->ohne($r, array('database_login', 'database_new_password')));
			case 'delete_database': return $this->entfernen('databases', (string) $r['database_login']);

			case 'add_ftpusers':
				$login = sprintf('f%07d', $this->id());
				$this->zustand['ftpusers'][] = array_merge(array('ftp_login' => $login, 'ftp_path' => '/', 'ftp_is_main_user' => 'N'), $this->ohne($r, array('ftp_password')));

				return $login;
			case 'update_ftpuser': return $this->aendern('ftpusers', (string) $r['ftp_login'], $this->ohne($r, array('ftp_login', 'ftp_new_password')));
			case 'delete_ftpuser': return $this->entfernen('ftpusers', (string) $r['ftp_login']);

			case 'add_cronjob':
				$id = (string) $this->id();
				$this->zustand['cronjobs'][] = array_merge(array('cronjob_id' => $id), $this->umbenennen($this->ohne($r, array('http_password')), array('mail_address' => 'mail_adress')));

				return $id;
			case 'update_cronjob': return $this->aendern('cronjobs', (string) $r['cronjob_id'], $this->umbenennen($this->ohne($r, array('cronjob_id', 'http_password')), array('mail_address' => 'mail_adress')));
			case 'delete_cronjob': return $this->entfernen('cronjobs', (string) $r['cronjob_id']);

			case 'add_directoryprotection':
				$this->zustand['directoryprotection'][] = array('schluessel' => $r['directory_path'].'|'.$r['directory_user'], 'directory_path' => (string) $r['directory_path'], 'directory_user' => (string) $r['directory_user'], 'directory_authname' => (string) ($r['directory_authname'] ?? ''));

				return 'TRUE';
			case 'update_directoryprotection':
				if (!isset($r['directory_password'])) {
					throw new SoapFault('Server', 'missing_parameter', 'KasApi', 'directory_password');
				}

				return $this->aendern('directoryprotection', $r['directory_path'].'|'.$r['directory_user'], array('directory_authname' => (string) ($r['directory_authname'] ?? '')));
			case 'delete_directoryprotection': return $this->entfernen('directoryprotection', $r['directory_path'].'|'.$r['directory_user']);

			case 'add_ddnsuser':
				$login = sprintf('dyn%05d', $this->id());
				$this->zustand['ddnsusers'][] = array('dyndns_login' => $login, 'dyndns_comment' => (string) $r['dyndns_comment'], 'dyndns_zone' => (string) $r['dyndns_zone'], 'dyndns_label' => (string) $r['dyndns_label'], 'dyndns_target_ip' => (string) $r['dyndns_target_ip'], 'dyndns_dual_stack' => $jn($r['dyndns_dual_stack'] ?? 'N', 'N'));

				return $login;
			case 'update_ddnsuser': return $this->aendern('ddnsusers', (string) $r['dyndns_login'], $this->ohne($r, array('dyndns_login', 'dyndns_password')));
			case 'delete_ddnsuser': return $this->entfernen('ddnsusers', (string) $r['dyndns_login']);

			case 'add_sambauser':
				$login = sprintf('s%07d', $this->id());
				$this->zustand['sambausers'][] = array('samba_login' => $login, 'samba_comment' => (string) $r['samba_comment'], 'samba_path' => (string) $r['samba_path']);

				return $login;
			case 'update_sambauser': return $this->aendern('sambausers', (string) $r['samba_login'], $this->ohne($r, array('samba_login', 'samba_new_password')));
			case 'delete_sambauser': return $this->entfernen('sambausers', (string) $r['samba_login']);
		}

		throw new SoapFault('Server', 'unkown_action', 'KasApi', $aktion);
	}

	/**
	 * Liefert eine Sammlung, wahlweise nur ein Objekt.
	 *
	 * @param string      $sammlung   Die Sammlung
	 * @param string|null $schluessel Der Schlüssel oder null für alle
	 *
	 * @return array<int, array<string, mixed>> Die Objekte
	 */
	private function liste(string $sammlung, ?string $schluessel): array
	{
		$feld = self::SCHLUESSEL[$sammlung];

		return array_values(array_filter($this->zustand[$sammlung], static fn (array $zeile): bool => null === $schluessel || (string) $zeile[$feld] === $schluessel));
	}

	/**
	 * Sagt, ob es ein Objekt gibt.
	 *
	 * @param string $sammlung   Die Sammlung
	 * @param string $schluessel Der Schlüssel
	 *
	 * @return bool true, wenn es existiert
	 */
	private function finde(string $sammlung, string $schluessel): bool
	{
		return array() !== $this->liste($sammlung, $schluessel);
	}

	/**
	 * Ändert ein Objekt.
	 *
	 * @param string               $sammlung   Die Sammlung
	 * @param string               $schluessel Der Schlüssel
	 * @param array<string, mixed> $werte      Die neuen Werte
	 *
	 * @return string „TRUE“
	 *
	 * @throws SoapFault Wenn es das Objekt nicht gibt
	 */
	private function aendern(string $sammlung, string $schluessel, array $werte): string
	{
		$feld = self::SCHLUESSEL[$sammlung];

		foreach ($this->zustand[$sammlung] as $nummer => $zeile) {
			if ((string) $zeile[$feld] === $schluessel) {
				$this->zustand[$sammlung][$nummer] = array_merge($zeile, $werte);

				return 'TRUE';
			}
		}

		throw new SoapFault('Server', rtrim($sammlung, 's').'_not_found', 'KasApi');
	}

	/**
	 * Ändert eine Domain oder Subdomain; die Parameter heißen anders als die Felder.
	 *
	 * @param string               $sammlung „domains“ oder „subdomains“
	 * @param string               $praefix  „domain“ oder „subdomain“
	 * @param array<string, mixed> $r        Die Parameter
	 * @param string               $schluesselParam Der Schlüsselparameter
	 *
	 * @return string „TRUE“
	 */
	private function hostAendern(string $sammlung, string $praefix, array $r, string $schluesselParam): string
	{
		return $this->aendern($sammlung, (string) $r[$schluesselParam], $this->umbenennen($this->ohne($r, array($schluesselParam)), array('redirect_status' => $praefix.'_redirect_status')));
	}

	/**
	 * Entfernt ein Objekt.
	 *
	 * @param string $sammlung   Die Sammlung
	 * @param string $schluessel Der Schlüssel
	 *
	 * @return string „TRUE“
	 *
	 * @throws SoapFault Wenn es das Objekt nicht gibt (Kürzel wie bei der KAS,
	 *                   samt Tippfehler „doenst“ bei Subdomains)
	 */
	private function entfernen(string $sammlung, string $schluessel): string
	{
		$feld = self::SCHLUESSEL[$sammlung];
		$vorher = \count($this->zustand[$sammlung]);
		$this->zustand[$sammlung] = array_values(array_filter($this->zustand[$sammlung], static fn (array $zeile): bool => (string) $zeile[$feld] !== $schluessel));

		if ($vorher === \count($this->zustand[$sammlung])) {
			throw new SoapFault('Server', 'subdomains' === $sammlung ? 'subdomain_doenst_exist' : rtrim($sammlung, 's').'_not_found', 'KasApi');
		}

		return 'TRUE';
	}

	/**
	 * Entfernt Schlüssel aus einem Feld.
	 *
	 * @param array<string, mixed> $feld   Das Feld
	 * @param array<int, string>   $namen  Die zu entfernenden Schlüssel
	 *
	 * @return array<string, mixed> Das Feld ohne sie
	 */
	private function ohne(array $feld, array $namen): array
	{
		return array_diff_key($feld, array_flip($namen));
	}

	/**
	 * Benennt Schlüssel um.
	 *
	 * @param array<string, mixed>  $feld  Das Feld
	 * @param array<string, string> $namen Alt => neu
	 *
	 * @return array<string, mixed> Das umbenannte Feld
	 */
	private function umbenennen(array $feld, array $namen): array
	{
		foreach ($namen as $alt => $neu) {
			if (\array_key_exists($alt, $feld)) {
				$feld[$neu] = $feld[$alt];
				unset($feld[$alt]);
			}
		}

		return $feld;
	}

	/**
	 * Vergibt eine neue Nummer.
	 *
	 * @return int Die Nummer
	 */
	private function id(): int
	{
		return ++$this->zustand['naechste_id'];
	}
}
