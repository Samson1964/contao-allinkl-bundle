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
use Schachbulle\ContaoAllinklBundle\Kas\Zugangsdaten;

/**
 * Alle Bereiche der KAS mit ihren Feldern.
 *
 * Quelle der Funktions- und Parameternamen ist die offizielle Dokumentation
 * (kasapi.kasserver.com/dokumentation/phpdoc). Die Namen der Felder in den
 * Antworten dokumentiert All-Inkl nicht; sie stammen aus den Clients
 * chmmou/kasapi-cli und go-acme/lego, die sie an echten Antworten
 * festgemacht haben. Wo die KAS zwei Schreibweisen schickt, stehen beide
 * in kas().
 *
 * Statisch, weil die DCA-Dateien die Definitionen brauchen und dort kein
 * Dienstcontainer zur Hand ist. Die Definitionen sind reine Daten ohne
 * Abhängigkeiten.
 */
final class BereichRegister
{
	/** @var array<string, Bereich>|null Zwischenspeicher der Definitionen */
	private static ?array $bereiche = null;

	/** Optionen für Umleitungen von Domains und Subdomains */
	private const UMLEITUNG = array(
		'0'   => 'keine Umleitung',
		'301' => '301 – dauerhaft verschoben',
		'302' => '302 – vorübergehend verschoben',
		'307' => '307 – vorübergehend umgeleitet',
	);

	/** Optionen für die Webalizer-Statistik */
	private const STATISTIK = array('0' => 'keine', '4' => 'Version 4', '5' => 'Version 5', '7' => 'Version 7');

	/** Optionen für die Sprache der Statistik */
	private const SPRACHE = array('de' => 'Deutsch', 'en' => 'Englisch');

	/** Optionen für die Protokollierung */
	private const PROTOKOLL = array('keine' => 'keine', 'kurz' => 'kurz', 'ohneip' => 'ohne IP-Adresse', 'voll' => 'vollständig');

	/** Optionen für die Sprache der /usage-Statistik */
	private const STATISTIKSPRACHE = array('0' => 'keine Statistik', 'de' => 'Deutsch', 'en' => 'Englisch');

	/**
	 * Liefert einen Bereich.
	 *
	 * @param string $name Kurzname, etwa „domain“
	 *
	 * @return Bereich Die Definition
	 *
	 * @throws \InvalidArgumentException Bei einem unbekannten Namen
	 */
	public static function bereich(string $name): Bereich
	{
		$alle = self::alle();

		if (!isset($alle[$name])) {
			throw new \InvalidArgumentException(sprintf('Unbekannter KAS-Bereich „%s“.', $name));
		}

		return $alle[$name];
	}

	/**
	 * Liefert den Bereich einer Spiegeltabelle.
	 *
	 * @param string $tabelle Etwa „tl_allinkl_domain“
	 *
	 * @return Bereich|null Die Definition, null für fremde Tabellen
	 */
	public static function zurTabelle(string $tabelle): ?Bereich
	{
		foreach (self::alle() as $bereich) {
			if ($tabelle === $bereich->tabelle && !$bereich->einzeln) {
				return $bereich;
			}
		}

		return null;
	}

	/**
	 * Liefert die Bereiche eines Backend-Moduls.
	 *
	 * @param string $modul Etwa „allinkl_domains“
	 *
	 * @return array<string, Bereich> Die Bereiche in Abgleichreihenfolge
	 *                                (Eltern vor Kindern)
	 */
	public static function zumModul(string $modul): array
	{
		return array_filter(self::alle(), static fn (Bereich $bereich): bool => $modul === $bereich->modul);
	}

	/**
	 * Liefert die Kindbereiche eines Bereichs.
	 *
	 * @param string $name Kurzname des Elternbereichs
	 *
	 * @return array<string, Bereich> Die Kinder
	 */
	public static function kinder(string $name): array
	{
		return array_filter(self::alle(), static fn (Bereich $bereich): bool => $name === $bereich->eltern);
	}

	/**
	 * Liefert alle Bereiche in Abgleichreihenfolge.
	 *
	 * @return array<string, Bereich> Kurzname => Definition
	 */
	public static function alle(): array
	{
		return self::$bereiche ??= self::definieren();
	}

	/**
	 * Baut alle Definitionen.
	 *
	 * @return array<string, Bereich> Kurzname => Definition
	 */
	private static function definieren(): array
	{
		$liste = array(
			self::einstellungen(),
			self::account(),
			self::domain(),
			self::subdomain(),
			self::dns(),
			self::postfach(),
			self::weiterleitung(),
			self::mailingliste(),
			self::datenbank(),
			self::ftp(),
			self::cronjob(),
			self::verzeichnisschutz(),
			self::ddns(),
			self::netzlaufwerk(),
		);

		$bereiche = array();

		foreach ($liste as $bereich) {
			$bereiche[$bereich->name] = $bereich;
		}

		return $bereiche;
	}

	/**
	 * Schlüsselparameter aus kas_schluessel.
	 *
	 * @param string $param Name des Parameters
	 *
	 * @return \Closure(array): array Liefert array($param => Schlüssel)
	 */
	private static function nachSchluessel(string $param): \Closure
	{
		return static fn (array $zeile): array => array($param => (string) ($zeile['kas_schluessel'] ?? ''));
	}

	/**
	 * Die Accounteinstellungen des Zugangs selbst (get/update_accountsettings).
	 *
	 * Die Spalten liegen in tl_allinkl_konto. Ändert sich das KAS-Kennwort,
	 * speichert KontoListener es zugleich als neues Zugangskennwort.
	 *
	 * @return Bereich Die Definition
	 */
	private static function einstellungen(): Bereich
	{
		return new Bereich(
			name: 'einstellungen',
			tabelle: 'tl_allinkl_konto',
			modul: 'allinkl_konten',
			titel: 'Accounteinstellungen',
			felder: array(
				Feld::text('account_comment', 'Kommentar', 'Kommentar des Accounts bei All-Inkl.')->kas('account_comment')->aendern('account_comment')->legende('einstellungen_legend'),
				Feld::text('account_contact_mail', 'Kontaktadresse', 'An diese Adresse schickt All-Inkl Hinweise zum Account.')->kas('account_contact_mail')->aendern('account_contact_mail')->eval(array('rgxp' => 'email'))->legende('einstellungen_legend'),
				Feld::auswahl('logging', 'Zugriffsprotokoll', self::PROTOKOLL)->kas('logging')->aendern('logging')->legende('einstellungen_legend'),
				Feld::zahl('logage', 'Protokolle aufbewahren (Tage)', '1 bis 999 Tage.')->kas('logage')->aendern('logage')->legende('einstellungen_legend'),
				Feld::auswahl('statistic', 'Statistik unter /usage', self::STATISTIKSPRACHE)->kas('statistic')->aendern('statistic')->legende('einstellungen_legend'),
				Feld::janein('show_password', 'Kennwörter im KAS anzeigen', 'Hinweis zum Anzeigen der Kennwörter im KAS.')->kas('show_password')->aendern('show_password')->legende('einstellungen_legend'),
				Feld::passwort('account_password', 'Neues KAS-Kennwort', 'Ändert das Kennwort dieses Accounts bei All-Inkl. Das Bundle merkt sich das neue Kennwort selbst. Leer lassen, um es zu behalten.')->aendern('account_password')->legende('einstellungen_legend'),
			),
			lesenAktion: 'get_accountsettings',
			schluessel: static fn (array $zeile): string => 'einstellungen',
			aendernAktion: 'update_accountsettings',
			aendernSchluessel: static fn (array $zeile): array => array(),
			einzeln: true,
		);
	}

	/**
	 * Unteraccounts (get/add/update/delete_account, update_superusersettings).
	 *
	 * @return Bereich Die Definition
	 */
	private static function account(): Bereich
	{
		$kontingent = static fn (string $spalte, string $titel, string ...$kas): Feld => Feld::zahl($spalte, $titel)->kas($spalte, ...$kas)->beides($spalte)->legende('kontingent_legend');
		$recht = static fn (string $spalte, string $titel, string $hilfe = ''): Feld => Feld::janein($spalte, $titel, $hilfe)->kas($spalte)->beides($spalte)->legende('rechte_legend');

		return new Bereich(
			name: 'account',
			tabelle: 'tl_allinkl_account',
			modul: 'allinkl_accounts',
			titel: 'Unteraccount',
			felder: array(
				Feld::text('account_login', 'Login', 'Das KAS-Login des Unteraccounts; vergibt All-Inkl beim Anlegen.')->kas('account_login')->inListe(),
				Feld::text('account_comment', 'Kommentar')->kas('account_comment')->beides('account_comment')->inListe(),
				Feld::text('account_contact_mail', 'Kontaktadresse')->kas('account_contact_mail')->beides('account_contact_mail')->eval(array('rgxp' => 'email')),
				Feld::auswahl('hostname_art', 'Art des Hosts', array('' => 'ohne Host', 'domain' => 'mit eigener Domain', 'subdomain' => 'mit Subdomain'))->anlegen('hostname_art')->legende('host_legend'),
				Feld::text('hostname_part1', 'Domain- oder Subdomainlabel', 'Bei einer Domain etwa „meine-domain“, bei einer Subdomain etwa „forum“.')->anlegen('hostname_part1')->legende('host_legend'),
				Feld::text('hostname_part2', 'TLD oder übergeordnete Domain', 'Bei einer Domain die Endung („de“), bei einer Subdomain die Domain („meine-domain.de“).')->anlegen('hostname_part2')->legende('host_legend'),
				Feld::text('hostname_path', 'Pfad', 'Optional, Vorgabe ist /hostname/.')->anlegen('hostname_path')->legende('host_legend'),
				Feld::passwort('account_kas_password', 'KAS-Kennwort', 'Beim Anlegen Pflicht. Beim Bearbeiten leer lassen, um es zu behalten.')->beides('account_kas_password')->pflicht()->legende('zugang_legend'),
				Feld::passwort('account_ftp_password', 'FTP-Kennwort', 'Kennwort des Haupt-FTP-Zugangs.')->anlegen('account_ftp_password')->pflicht()->legende('zugang_legend'),
				$kontingent('max_account', 'Unteraccounts'),
				$kontingent('max_domain', 'Domains'),
				$kontingent('max_subdomain', 'Subdomains'),
				$kontingent('max_webspace', 'Speicherplatz (MB)'),
				$kontingent('max_mail_account', 'Postfächer'),
				$kontingent('max_mail_forward', 'Weiterleitungen'),
				$kontingent('max_mailinglist', 'Mailinglisten', 'max_mail_list'),
				$kontingent('max_database', 'Datenbanken', 'max_databases'),
				$kontingent('max_ftpuser', 'FTP-Zugänge'),
				$kontingent('max_sambauser', 'Netzlaufwerke'),
				$kontingent('max_cronjobs', 'Cronjobs'),
				$kontingent('max_wbk', 'Webbaukästen'),
				$recht('inst_htaccess', 'Verzeichnisschutz erlaubt')->vorgabe('1'),
				$recht('inst_fpse', 'Frontpage-Erweiterung erlaubt'),
				$recht('inst_software', 'Softwareinstallation erlaubt')->vorgabe('1'),
				$recht('dns_settings', 'DNS-Einstellungen erlaubt'),
				$recht('show_password', 'Kennwörter im KAS anzeigen'),
				Feld::auswahl('kas_access_forbidden', 'Zugang zum KAS', array('N' => 'erlaubt', 'Y' => 'gesperrt', 'forbidden' => 'vollständig gesperrt'))->kas('kas_access_forbidden')->beides('kas_access_forbidden')->vorgabe('N')->legende('rechte_legend'),
				Feld::auswahl('logging', 'Zugriffsprotokoll', self::PROTOKOLL)->kas('logging')->beides('logging')->vorgabe('keine')->legende('rechte_legend'),
				Feld::zahl('logage', 'Protokolle aufbewahren (Tage)')->kas('logage')->beides('logage')->legende('rechte_legend'),
				Feld::auswahl('statistic', 'Statistik unter /usage', self::STATISTIKSPRACHE)->kas('statistic')->beides('statistic')->legende('rechte_legend'),
				Feld::janein('ssh_access', 'SSH-Zugang', 'Nur in Tarifen mit SSH; geht über update_superusersettings.')->kas('ssh_access')->aendern('ssh_access')->gruppe('superuser')->legende('ssh_legend'),
				Feld::textarea('ssh_keys', 'SSH-Schlüssel', 'Öffentliche Schlüssel für die Anmeldung per Schlüssel, einer je Zeile.')->kas('ssh_keys')->aendern('ssh_keys')->gruppe('superuser')->legende('ssh_legend'),
			),
			lesenAktion: 'get_accounts',
			schluessel: Bereich::schluesselAus('account_login'),
			einzelParam: 'account_login',
			anlegenAktion: 'add_account',
			aendernAktion: 'update_account',
			aendernSchluessel: self::nachSchluessel('account_login'),
			loeschenAktion: 'delete_account',
			gruppen: array(
				'superuser' => Aufrufgruppe::einfach('update_superusersettings', self::nachSchluessel('account_login')),
			),
			sortierung: 'account_login',
			loeschHinweis: 'Der Unteraccount wird bei All-Inkl samt allen Daten, Domains und Postfächern unwiderruflich gelöscht.',
		);
	}

	/**
	 * Die gemeinsamen Felder von Domains und Subdomains.
	 *
	 * @param string $praefix „domain“ oder „subdomain“ (Präfix der KAS-Felder)
	 *
	 * @return array<int, Feld> Pfad, Umleitung, PHP, Statistik, aktiv, SSL,
	 *                          Verschieben
	 */
	private static function hostFelder(string $praefix): array
	{
		return array(
			Feld::text($praefix.'_path', 'Pfad oder Ziel', 'Verzeichnis im Account („/pfad/“), bei einer Umleitung die Zieladresse.')->kas($praefix.'_path')->beides($praefix.'_path'),
			Feld::auswahl('redirect_status', 'Umleitung', self::UMLEITUNG)->kas($praefix.'_redirect_status', 'redirect_status')->beides('redirect_status')->vorgabe('0'),
			Feld::text('php_version', 'PHP-Fassung', 'Etwa „8.3“. Die möglichen Fassungen nennt das Modul „Werkzeuge“.')->kas('php_version')->beides('php_version'),
			Feld::auswahl('statistic_version', 'Webalizer', self::STATISTIK)->kas('statistic_version')->anlegen('statistic_version')->vorgabe('5'),
			Feld::auswahl('statistic_language', 'Sprache der Statistik', self::SPRACHE)->kas('statistic_language')->anlegen('statistic_language')->vorgabe('de'),
			Feld::janein('is_active', 'Aktiv')->kas('is_active')->aendern('is_active'),
			Feld::janein('ssl_aktiv', 'SSL aktiv')->kas('ssl_certificate_sni_is_active', 'sni_is_active')->aendern('ssl_certificate_is_active')->gruppe('ssl')->legende('ssl_legend'),
			Feld::janein('ssl_https', 'HTTP auf HTTPS umleiten')->kas('ssl_certificate_sni_force_https')->aendern('ssl_certificate_force_https')->gruppe('ssl')->legende('ssl_legend'),
			Feld::zahl('ssl_hsts', 'HSTS max-age (Sekunden)', '-1 schaltet HSTS ab.')->kas('ssl_certificate_sni_hsts_max_age')->aendern('ssl_certificate_hsts_max_age')->gruppe('ssl')->legende('ssl_legend'),
			Feld::textarea('ssl_zertifikat', 'Zertifikat (CRT)', 'Nur für eigene Zertifikate; Let’s Encrypt verwaltet All-Inkl selbst.')->kas('ssl_certificate_sni_crt')->aendern('ssl_certificate_sni_crt')->gruppe('ssl')->legende('ssl_legend'),
			Feld::geheim('ssl_schluessel', 'Privater Schlüssel (KEY)', 'Wird nicht gespeichert und nicht angezeigt. Nur ausfüllen, um ein eigenes Zertifikat einzuspielen.')->aendern('ssl_certificate_sni_key')->gruppe('ssl')->legende('ssl_legend'),
			Feld::textarea('ssl_bundle', 'Zwischenzertifikate (Bundle)')->kas('ssl_certificate_sni_bundle')->aendern('ssl_certificate_sni_bundle')->gruppe('ssl')->legende('ssl_legend'),
			Feld::textarea('ssl_csr', 'Zertifikatsanfrage (CSR)')->kas('ssl_certificate_sni_csr')->aendern('ssl_certificate_sni_csr')->gruppe('ssl')->legende('ssl_legend'),
			Feld::text('verschieben_nach', 'In Account verschieben', 'Login des Zielaccounts, etwa „w0123456“. Nach dem Speichern gehört der Host zum Zielaccount und verschwindet hier.')->aendern('target_account')->gruppe('verschieben')->legende('verschieben_legend'),
		);
	}

	/**
	 * Domains (get/add/update/delete_domain, update_ssl, move_domain).
	 *
	 * @return Bereich Die Definition
	 */
	private static function domain(): Bereich
	{
		return new Bereich(
			name: 'domain',
			tabelle: 'tl_allinkl_domain',
			modul: 'allinkl_domains',
			titel: 'Domain',
			felder: array_merge(
				array(Feld::text('domain_name', 'Domain', 'Vollständig mit Endung, etwa „beispiel.de“.')->kas('domain_name')->beimAnlegen()->pflicht()->inListe()->eval(array('rgxp' => 'url'))),
				self::hostFelder('domain'),
			),
			lesenAktion: 'get_domains',
			schluessel: Bereich::schluesselAus('domain_name'),
			einzelParam: 'domain_name',
			anlegenAktion: 'add_domain',
			anlegenZusatz: static function (array $zeile): array {
				$name = strtolower(trim((string) $zeile['domain_name']));
				$punkt = strpos($name, '.');

				return false === $punkt
					? array('domain_name' => $name)
					: array('domain_name' => substr($name, 0, $punkt), 'domain_tld' => substr($name, $punkt + 1));
			},
			neuerSchluessel: static fn (KasAntwort $antwort, array $zeile): string => strtolower(trim((string) $zeile['domain_name'])),
			aendernAktion: 'update_domain',
			aendernSchluessel: self::nachSchluessel('domain_name'),
			loeschenAktion: 'delete_domain',
			gruppen: array(
				'ssl' => Aufrufgruppe::einfach('update_ssl', self::nachSchluessel('hostname')),
				'verschieben' => new Aufrufgruppe(
					static fn (array $geaendert, array $zeile, Zugangsdaten $zugang): array => '' === (string) ($geaendert['target_account'] ?? '') ? array() : array(
						array('move_domain', array('domain_name' => $zeile['kas_schluessel'], 'source_account' => $zugang->login) + $geaendert),
					),
					true,
				),
			),
			sortierung: 'domain_name',
			loeschHinweis: 'Die Domain wird bei All-Inkl samt Subdomains und DNS-Einträgen unwiderruflich gelöscht.',
		);
	}

	/**
	 * Subdomains als Kinder ihrer Domain.
	 *
	 * get_subdomains liefert alle Subdomains des Accounts; zugeordnet wird
	 * über die längste passende Domain-Endung.
	 *
	 * @return Bereich Die Definition
	 */
	private static function subdomain(): Bereich
	{
		$label = static function (array $zeile, ?array $eltern): string {
			$name = strtolower(trim((string) $zeile['subdomain_name']));
			$domain = strtolower((string) ($eltern['domain_name'] ?? ''));

			return '' !== $domain && str_ends_with($name, '.'.$domain) ? substr($name, 0, -\strlen($domain) - 1) : $name;
		};

		return new Bereich(
			name: 'subdomain',
			tabelle: 'tl_allinkl_subdomain',
			modul: 'allinkl_domains',
			titel: 'Subdomain',
			felder: array_merge(
				array(Feld::text('subdomain_name', 'Subdomain', 'Beim Anlegen nur das Label, etwa „forum“; die Domain wird angehängt.')->kas('subdomain_name')->beimAnlegen()->pflicht()->inListe()),
				self::hostFelder('subdomain'),
			),
			lesenAktion: 'get_subdomains',
			schluessel: Bereich::schluesselAus('subdomain_name'),
			einzelParam: 'subdomain_name',
			eltern: 'domain',
			zuordnen: static function (array $kas, array $domains): ?int {
				$name = strtolower((string) ($kas['subdomain_name'] ?? ''));
				$treffer = null;
				$laenge = 0;

				foreach ($domains as $domain) {
					$endung = '.'.strtolower((string) $domain['domain_name']);

					if (str_ends_with($name, $endung) && \strlen($endung) > $laenge) {
						$treffer = (int) $domain['id'];
						$laenge = \strlen($endung);
					}
				}

				return $treffer;
			},
			anlegenAktion: 'add_subdomain',
			anlegenZusatz: static fn (array $zeile, ?array $eltern): array => array(
				'subdomain_name' => $label($zeile, $eltern),
				'domain_name'    => (string) ($eltern['domain_name'] ?? ''),
			),
			neuerSchluessel: static fn (KasAntwort $antwort, array $zeile, ?array $eltern): string => $label($zeile, $eltern).'.'.strtolower((string) ($eltern['domain_name'] ?? '')),
			aendernAktion: 'update_subdomain',
			aendernSchluessel: self::nachSchluessel('subdomain_name'),
			loeschenAktion: 'delete_subdomain',
			gruppen: array(
				'ssl' => Aufrufgruppe::einfach('update_ssl', self::nachSchluessel('hostname')),
				'verschieben' => new Aufrufgruppe(
					static fn (array $geaendert, array $zeile, Zugangsdaten $zugang): array => '' === (string) ($geaendert['target_account'] ?? '') ? array() : array(
						array('move_subdomain', array('subdomain_name' => $zeile['kas_schluessel'], 'source_account' => $zugang->login) + $geaendert),
					),
					true,
				),
			),
			sortierung: 'subdomain_name',
			loeschHinweis: 'Die Subdomain wird bei All-Inkl unwiderruflich gelöscht.',
		);
	}

	/**
	 * DNS-Einträge als Kinder ihrer Domain (Zone).
	 *
	 * Die KAS erwartet die Zone mit abschließendem Punkt („beispiel.de.“).
	 *
	 * @return Bereich Die Definition
	 */
	private static function dns(): Bereich
	{
		$zone = static fn (?array $eltern): array => array('zone_host' => rtrim(strtolower((string) ($eltern['domain_name'] ?? '')), '.').'.');

		return new Bereich(
			name: 'dns',
			tabelle: 'tl_allinkl_dns',
			modul: 'allinkl_domains',
			titel: 'DNS-Eintrag',
			felder: array(
				Feld::text('record_name', 'Name', 'Ohne Domain, etwa „www“; leer für die Domain selbst.')->kas('record_name')->beides('record_name')->inListe(),
				Feld::auswahl('record_type', 'Typ', array('A' => 'A', 'AAAA' => 'AAAA', 'CNAME' => 'CNAME', 'MX' => 'MX', 'TXT' => 'TXT', 'SRV' => 'SRV', 'NS' => 'NS', 'CAA' => 'CAA', 'PTR' => 'PTR'))->kas('record_type')->anlegen('record_type')->pflicht()->inListe(),
				Feld::text('record_data', 'Daten', 'Ziel-IP, Hostname mit Punkt am Ende oder Text.')->kas('record_data')->beides('record_data')->pflicht()->inListe()->eval(array('maxlength' => 4096)),
				Feld::zahl('record_aux', 'Priorität (AUX)', 'Bei MX und SRV die Priorität, sonst 0.')->kas('record_aux')->beides('record_aux')->vorgabe('0'),
			),
			lesenAktion: 'get_dns_settings',
			schluessel: Bereich::schluesselAus('record_id'),
			lesenParams: $zone,
			einzelParam: 'record_id',
			eltern: 'domain',
			anlegenAktion: 'add_dns_settings',
			anlegenZusatz: static fn (array $zeile, ?array $eltern): array => $zone($eltern),
			aendernAktion: 'update_dns_settings',
			aendernSchluessel: self::nachSchluessel('record_id'),
			loeschenAktion: 'delete_dns_settings',
			sortierung: 'record_name',
			loeschHinweis: 'Der DNS-Eintrag wird bei All-Inkl sofort gelöscht.',
		);
	}

	/**
	 * Postfächer (get/add/update/delete_mailaccount, Spamfilter).
	 *
	 * Der Standardfilter geht über eigene Funktionen: Beim Ändern wird der
	 * alte Filter entfernt und der neue gesetzt; ein leerer Filter entfernt
	 * ihn nur.
	 *
	 * @return Bereich Die Definition
	 */
	private static function postfach(): Bereich
	{
		$xlist = static fn (string $spalte, string $titel): Feld => Feld::text($spalte, $titel)->kas($spalte)->beides($spalte)->legende('xlist_legend');

		return new Bereich(
			name: 'postfach',
			tabelle: 'tl_allinkl_mailkonto',
			modul: 'allinkl_postfaecher',
			titel: 'Postfach',
			felder: array(
				Feld::text('mail_login', 'Login', 'Vergibt All-Inkl beim Anlegen, etwa „m0123456“.')->kas('mail_login')->inListe(),
				Feld::text('mail_adressen', 'Adressen')->kas('mail_addresses', 'mail_adresses')->inListe(),
				Feld::text('local_part', 'Adresse vor dem @', 'Etwa „info“.')->anlegen('local_part')->pflicht(),
				Feld::text('domain_part', 'Domain der Adresse', 'Etwa „beispiel.de“; die Domain muss im Account liegen.')->anlegen('domain_part')->pflicht(),
				Feld::passwort('passwort', 'Kennwort', 'Beim Anlegen Pflicht. Beim Bearbeiten leer lassen, um es zu behalten.')->anlegen('mail_password')->aendern('mail_new_password')->pflicht(),
				Feld::text('mail_comment', 'Kommentar')->kas('mail_comment'),
				Feld::auswahl('is_active', 'Zustand', array('Y' => 'aktiv', 'N' => 'kein Empfang, Abruf möglich', 'forbidden' => 'gesperrt'))->kas('mail_is_active', 'is_active')->aendern('is_active')->vorgabe('Y'),
				Feld::janein('webmail_autologin', 'Webmail-Anmeldung aus dem KAS')->kas('webmail_autologin')->beides('webmail_autologin')->vorgabe('1'),
				Feld::text('copy_adress', 'Kopie an', 'Mehrere Adressen mit Komma trennen.')->kas('mail_copy_address', 'mail_copy_adress', 'copy_adress')->beides('copy_adress'),
				Feld::text('mail_sender_alias', 'Erlaubte Absenderadressen')->kas('mail_sender_alias')->beides('mail_sender_alias'),
				Feld::text('mail_allow_nets', 'Zugriff nur von', 'Etwa „1.2.3.4, 12.34.0.0/16, webmail“; leer für keine Beschränkung.')->kas('mail_allow_nets')->beides('mail_allow_nets'),
				Feld::text('responder', 'Abwesenheitsnotiz', '„N“ aus, „Y“ an, oder Beginn|Ende als Unix-Zeitstempel.')->kas('mail_responder', 'responder')->beides('responder')->vorgabe('N')->legende('responder_legend'),
				Feld::auswahl('mail_responder_content_type', 'Format der Notiz', array('text' => 'Text', 'html' => 'HTML'))->kas('mail_responder_content_type')->beides('mail_responder_content_type')->vorgabe('text')->legende('responder_legend'),
				Feld::text('mail_responder_displayname', 'Absendername der Notiz')->kas('mail_responder_displayname')->beides('mail_responder_displayname')->legende('responder_legend'),
				Feld::textarea('responder_text', 'Text der Notiz')->kas('mail_responder_text', 'responder_text')->beides('responder_text')->legende('responder_legend'),
				Feld::janein('mail_xlist_enabled', 'IMAP-Sonderordner (XLIST)')->kas('mail_xlist_enabled')->beides('mail_xlist_enabled')->legende('xlist_legend'),
				$xlist('mail_xlist_sent', 'Gesendet'),
				$xlist('mail_xlist_drafts', 'Entwürfe'),
				$xlist('mail_xlist_trash', 'Papierkorb'),
				$xlist('mail_xlist_spam', 'Spam'),
				$xlist('mail_xlist_archiv', 'Archiv'),
				Feld::text('spamfilter', 'Standardfilter', 'Etwa „greyl;rbl_cbl:mark“. Die verfügbaren Filter zeigt das Modul „Werkzeuge“. Leer entfernt alle Standardfilter.')->kas('mail_spamfilter')->aendern('filter')->gruppe('filter')->legende('filter_legend'),
			),
			lesenAktion: 'get_mailaccounts',
			schluessel: Bereich::schluesselAus('mail_login'),
			einzelParam: 'mail_login',
			anlegenAktion: 'add_mailaccount',
			aendernAktion: 'update_mailaccount',
			aendernSchluessel: self::nachSchluessel('mail_login'),
			loeschenAktion: 'delete_mailaccount',
			gruppen: array(
				'filter' => new Aufrufgruppe(static function (array $geaendert, array $zeile): array {
					$login = (string) $zeile['kas_schluessel'];
					$aufrufe = array(array('delete_mailstandardfilter', array('mail_login' => $login)));

					if ('' !== trim((string) ($geaendert['filter'] ?? ''))) {
						$aufrufe[] = array('add_mailstandardfilter', array('mail_login' => $login, 'filter' => $geaendert['filter']));
					}

					return $aufrufe;
				}),
			),
			sortierung: 'mail_adressen',
			loeschHinweis: 'Das Postfach wird bei All-Inkl samt allen E-Mails unwiderruflich gelöscht.',
		);
	}

	/**
	 * Weiterleitungen (get/add/update/delete_mailforward).
	 *
	 * Die Ziele gehen als target_0, target_1 … hinaus.
	 *
	 * @return Bereich Die Definition
	 */
	private static function weiterleitung(): Bereich
	{
		return new Bereich(
			name: 'weiterleitung',
			tabelle: 'tl_allinkl_weiterleitung',
			modul: 'allinkl_weiterleitungen',
			titel: 'Weiterleitung',
			felder: array(
				Feld::text('adresse', 'Adresse')->kas('mail_forward_address', 'mail_forward_adress', 'mail_forward')->inListe(),
				Feld::text('local_part', 'Adresse vor dem @', 'Etwa „info“.')->anlegen('local_part')->pflicht(),
				Feld::text('domain_part', 'Domain der Adresse', 'Etwa „beispiel.de“.')->anlegen('domain_part')->pflicht(),
				Feld::liste('ziele', 'Ziele', 'Eine Adresse je Zeile.')->kas('mail_forward_targets')->beides('target_N')->pflicht()->inListe(),
				Feld::text('kommentar', 'Kommentar')->kas('mail_forward_comment'),
			),
			lesenAktion: 'get_mailforwards',
			schluessel: Bereich::schluesselAus('mail_forward_address', 'mail_forward_adress', 'mail_forward'),
			einzelParam: 'mail_forward',
			anlegenAktion: 'add_mailforward',
			neuerSchluessel: static fn (KasAntwort $antwort, array $zeile): string => strtolower(trim((string) $zeile['local_part']).'@'.trim((string) $zeile['domain_part'])),
			aendernAktion: 'update_mailforward',
			aendernSchluessel: self::nachSchluessel('mail_forward'),
			loeschenAktion: 'delete_mailforward',
			sortierung: 'adresse',
			loeschHinweis: 'Die Weiterleitung wird bei All-Inkl gelöscht.',
		);
	}

	/**
	 * Mailinglisten (get/add/update/delete_mailinglist).
	 *
	 * update_mailinglist verlangt die vollständige Konfiguration, sie geht
	 * deshalb bei jeder Änderung mit.
	 *
	 * @return Bereich Die Definition
	 */
	private static function mailingliste(): Bereich
	{
		return new Bereich(
			name: 'mailingliste',
			tabelle: 'tl_allinkl_mailingliste',
			modul: 'allinkl_mailinglisten',
			titel: 'Mailingliste',
			felder: array(
				Feld::text('mailinglist_name', 'Name', 'Der Listenname, etwa „mitglieder“.')->kas('mailinglist_name')->anlegen('mailinglist_name')->pflicht()->inListe(),
				Feld::text('mailinglist_domain', 'Domain', 'Etwa „beispiel.de“.')->kas('mailinglist_domain')->anlegen('mailinglist_domain')->pflicht()->inListe(),
				Feld::passwort('passwort', 'Listenkennwort')->anlegen('mailinglist_password')->pflicht(),
				Feld::janein('is_active', 'Aktiv')->kas('mailinglist_is_active', 'is_active')->aendern('is_active'),
				Feld::liste('subscriber', 'Teilnehmer', 'Eine Adresse je Zeile.', "\n")->kas('mailinglist_subscriber', 'subscriber')->aendern('subscriber'),
				Feld::liste('restrict_post', 'Nur diese dürfen schreiben', 'Eine Adresse je Zeile; leer für alle Teilnehmer.', "\n")->kas('mailinglist_restrict_post', 'restrict_post')->aendern('restrict_post'),
				Feld::textarea('config', 'Konfiguration', 'Die vollständige Konfigurationsdatei der Liste; geht bei jeder Änderung mit.')->kas('mailinglist_config', 'config')->aendern('config')->immerSenden(),
			),
			lesenAktion: 'get_mailinglists',
			schluessel: Bereich::schluesselAus('mailinglist_name'),
			einzelParam: 'mailinglist_name',
			anlegenAktion: 'add_mailinglist',
			neuerSchluessel: static fn (KasAntwort $antwort, array $zeile): string => '' !== $antwort->kennung() ? $antwort->kennung() : trim((string) $zeile['mailinglist_name']),
			aendernAktion: 'update_mailinglist',
			aendernSchluessel: self::nachSchluessel('mailinglist_name'),
			loeschenAktion: 'delete_mailinglist',
			sortierung: 'mailinglist_name',
			loeschHinweis: 'Die Mailingliste wird bei All-Inkl samt Teilnehmern gelöscht.',
		);
	}

	/**
	 * Datenbanken (get/add/update/delete_database).
	 *
	 * @return Bereich Die Definition
	 */
	private static function datenbank(): Bereich
	{
		return new Bereich(
			name: 'datenbank',
			tabelle: 'tl_allinkl_datenbank',
			modul: 'allinkl_datenbanken',
			titel: 'Datenbank',
			felder: array(
				Feld::text('database_login', 'Login', 'Vergibt All-Inkl, etwa „d0123456“.')->kas('database_login')->inListe(),
				Feld::text('database_name', 'Datenbankname')->kas('database_name'),
				Feld::text('database_comment', 'Kommentar')->kas('database_comment')->beides('database_comment')->pflicht()->inListe(),
				Feld::passwort('passwort', 'Kennwort', 'Beim Anlegen Pflicht. Beim Bearbeiten leer lassen, um es zu behalten.')->anlegen('database_password')->aendern('database_new_password')->pflicht(),
				Feld::text('database_allowed_hosts', 'Erlaubte Rechner', 'IP-Adressen, die sich von außen verbinden dürfen; leer für nur lokal.')->kas('database_allowed_hosts')->beides('database_allowed_hosts'),
				Feld::zahl('belegung', 'Belegt (MB)')->kas('used_database_space'),
			),
			lesenAktion: 'get_databases',
			schluessel: Bereich::schluesselAus('database_login'),
			einzelParam: 'database_login',
			anlegenAktion: 'add_database',
			aendernAktion: 'update_database',
			aendernSchluessel: self::nachSchluessel('database_login'),
			loeschenAktion: 'delete_database',
			sortierung: 'database_login',
			loeschHinweis: 'Die Datenbank wird bei All-Inkl samt Inhalt unwiderruflich gelöscht.',
		);
	}

	/**
	 * FTP-Zugänge (get_ftpusers, add_ftpusers, update/delete_ftpuser).
	 *
	 * @return Bereich Die Definition
	 */
	private static function ftp(): Bereich
	{
		return new Bereich(
			name: 'ftp',
			tabelle: 'tl_allinkl_ftpnutzer',
			modul: 'allinkl_ftp',
			titel: 'FTP-Zugang',
			felder: array(
				Feld::text('ftp_login', 'Login', 'Vergibt All-Inkl, etwa „f0123456“.')->kas('ftp_login')->inListe(),
				Feld::text('ftp_comment', 'Kommentar')->kas('ftp_comment')->beides('ftp_comment')->pflicht()->inListe(),
				Feld::text('ftp_path', 'Verzeichnis', 'Vorgabe ist „/“.')->kas('ftp_path')->beides('ftp_path')->inListe(),
				Feld::passwort('passwort', 'Kennwort', 'Beim Anlegen Pflicht. Beim Bearbeiten leer lassen, um es zu behalten.')->anlegen('ftp_password')->aendern('ftp_new_password')->pflicht(),
				Feld::janein('ftp_permission_read', 'Lesen')->kas('ftp_permission_read')->beides('ftp_permission_read')->vorgabe('1')->legende('rechte_legend'),
				Feld::janein('ftp_permission_write', 'Schreiben')->kas('ftp_permission_write')->beides('ftp_permission_write')->vorgabe('1')->legende('rechte_legend'),
				Feld::janein('ftp_permission_list', 'Verzeichnisse auflisten')->kas('ftp_permission_list')->beides('ftp_permission_list')->vorgabe('1')->legende('rechte_legend'),
				Feld::janein('ftp_virus_clamav', 'Virenprüfung')->kas('ftp_virus_clamav')->beides('ftp_virus_clamav')->vorgabe('1')->legende('rechte_legend'),
			),
			lesenAktion: 'get_ftpusers',
			schluessel: Bereich::schluesselAus('ftp_login'),
			einzelParam: 'ftp_login',
			anlegenAktion: 'add_ftpusers',
			aendernAktion: 'update_ftpuser',
			aendernSchluessel: self::nachSchluessel('ftp_login'),
			loeschenAktion: 'delete_ftpuser',
			sortierung: 'ftp_login',
			loeschHinweis: 'Der FTP-Zugang wird bei All-Inkl gelöscht; die Dateien bleiben erhalten.',
		);
	}

	/**
	 * Cronjobs (get/add/update/delete_cronjob).
	 *
	 * @return Bereich Die Definition
	 */
	private static function cronjob(): Bereich
	{
		$zeit = static fn (string $spalte, string $titel, string $hilfe): Feld => Feld::text($spalte, $titel, $hilfe)->kas($spalte)->beides($spalte)->pflicht()->vorgabe('*')->legende('zeit_legend')->eval(array('maxlength' => 32));

		return new Bereich(
			name: 'cronjob',
			tabelle: 'tl_allinkl_cronjob',
			modul: 'allinkl_cronjobs',
			titel: 'Cronjob',
			felder: array(
				Feld::text('cronjob_comment', 'Kommentar')->kas('cronjob_comment')->beides('cronjob_comment')->pflicht()->inListe(),
				Feld::auswahl('protocol', 'Protokoll', array('https' => 'https', 'http' => 'http'))->kas('protocol')->beides('protocol')->pflicht()->vorgabe('https'),
				Feld::text('http_url', 'Adresse', 'Ohne Protokoll, etwa „beispiel.de/cron.php“.')->kas('http_url')->beides('http_url')->pflicht()->inListe(),
				$zeit('minute', 'Minute', '0–59, */n oder *'),
				$zeit('hour', 'Stunde', '0–23, */n oder *'),
				$zeit('day_of_month', 'Tag des Monats', '1–31 oder *'),
				$zeit('month', 'Monat', '* (die KAS kennt nur jeden Monat)'),
				$zeit('day_of_week', 'Wochentag', '0–7 (Sonntag = 0 oder 7) oder *'),
				Feld::janein('is_active', 'Aktiv')->kas('is_active')->beides('is_active')->vorgabe('1'),
				Feld::text('http_user', 'HTTP-Benutzer')->kas('http_user')->beides('http_user')->legende('auth_legend'),
				Feld::passwort('http_password', 'HTTP-Kennwort', 'Leer lassen, um es zu behalten.')->beides('http_password')->legende('auth_legend'),
				Feld::text('mail_address', 'Bestätigung an', 'Adresse für die Ausgabe des Laufs.')->kas('mail_adress', 'mail_address')->beides('mail_address')->eval(array('rgxp' => 'email'))->legende('mail_legend'),
				Feld::text('mail_condition', 'Keine Mail, wenn die Ausgabe enthält')->kas('mail_condition')->beides('mail_condition')->legende('mail_legend'),
				Feld::auswahl('mail_subject', 'Betreff', array('default' => 'Vorgabe der KAS', 'comment' => 'Kommentar des Cronjobs'))->kas('mail_subject')->beides('mail_subject')->vorgabe('default')->legende('mail_legend'),
			),
			lesenAktion: 'get_cronjobs',
			schluessel: Bereich::schluesselAus('cronjob_id'),
			einzelParam: 'cronjob_id',
			anlegenAktion: 'add_cronjob',
			aendernAktion: 'update_cronjob',
			aendernSchluessel: self::nachSchluessel('cronjob_id'),
			loeschenAktion: 'delete_cronjob',
			sortierung: 'cronjob_comment',
			loeschHinweis: 'Der Cronjob wird bei All-Inkl gelöscht.',
		);
	}

	/**
	 * Verzeichnisschutz (get/add/update/delete_directoryprotection).
	 *
	 * Ein Objekt ist ein Benutzer in einem Verzeichnis; der Schlüssel
	 * verbindet beides mit „|“. Zum Ändern verlangt die KAS das Kennwort.
	 *
	 * @return Bereich Die Definition
	 */
	private static function verzeichnisschutz(): Bereich
	{
		$teile = static function (array $zeile): array {
			[$pfad, $benutzer] = array_pad(explode('|', (string) ($zeile['kas_schluessel'] ?? ''), 2), 2, '');

			return array('directory_path' => $pfad, 'directory_user' => $benutzer);
		};

		return new Bereich(
			name: 'verzeichnisschutz',
			tabelle: 'tl_allinkl_verzeichnisschutz',
			modul: 'allinkl_verzeichnisschutz',
			titel: 'Verzeichnisschutz',
			felder: array(
				Feld::text('directory_path', 'Verzeichnis', 'Pfad im Account, etwa „/intern/“.')->kas('directory_path')->anlegen('directory_path')->pflicht()->inListe(),
				Feld::text('directory_user', 'Benutzer')->kas('directory_user')->anlegen('directory_user')->pflicht()->inListe(),
				Feld::passwort('passwort', 'Kennwort', 'Pflicht – die KAS verlangt es auch beim Ändern.')->beides('directory_password')->pflicht(),
				Feld::text('directory_authname', 'Bezeichnung', 'Erscheint im Anmeldefenster des Browsers.')->kas('directory_authname')->beides('directory_authname')->pflicht()->inListe(),
			),
			lesenAktion: 'get_directoryprotection',
			schluessel: static fn (array $kas): string => trim((string) ($kas['directory_path'] ?? '')).'|'.trim((string) ($kas['directory_user'] ?? '')),
			anlegenAktion: 'add_directoryprotection',
			neuerSchluessel: static fn (KasAntwort $antwort, array $zeile): string => trim((string) $zeile['directory_path']).'|'.trim((string) $zeile['directory_user']),
			aendernAktion: 'update_directoryprotection',
			aendernSchluessel: $teile,
			loeschenAktion: 'delete_directoryprotection',
			aendernBrauchtPasswort: 'passwort',
			sortierung: 'directory_path',
			loeschHinweis: 'Der Benutzer wird aus dem Verzeichnisschutz entfernt.',
		);
	}

	/**
	 * DynDNS-Benutzer (get_ddnsusers, add/update/delete_ddnsuser).
	 *
	 * @return Bereich Die Definition
	 */
	private static function ddns(): Bereich
	{
		return new Bereich(
			name: 'ddns',
			tabelle: 'tl_allinkl_ddns',
			modul: 'allinkl_ddns',
			titel: 'DynDNS-Benutzer',
			felder: array(
				Feld::text('dyndns_login', 'Login', 'Vergibt All-Inkl.')->kas('dyndns_login', 'ddns_login')->inListe(),
				Feld::text('dyndns_comment', 'Kommentar')->kas('dyndns_comment')->beides('dyndns_comment')->pflicht()->inListe(),
				Feld::passwort('passwort', 'Kennwort', 'Beim Anlegen Pflicht. Beim Bearbeiten leer lassen, um es zu behalten.')->beides('dyndns_password')->pflicht(),
				Feld::text('dyndns_zone', 'Zone', 'Die Domain, etwa „beispiel.de“.')->kas('dyndns_zone')->anlegen('dyndns_zone')->pflicht()->inListe(),
				Feld::text('dyndns_label', 'Label', 'Etwa „home“ für home.beispiel.de.')->kas('dyndns_label')->anlegen('dyndns_label')->pflicht()->inListe(),
				Feld::text('dyndns_target_ip', 'Ziel-IP (IPv4)', 'Anfangsziel; danach aktualisiert der Router.')->kas('dyndns_target_ip', 'dyndns_target_ipv4')->anlegen('dyndns_target_ip')->pflicht(),
				Feld::text('dyndns_target_ipv6', 'Ziel-IP (IPv6)')->kas('dyndns_target_ipv6'),
				Feld::janein('dyndns_dual_stack', 'Dual-Stack')->kas('dyndns_dual_stack')->beides('dyndns_dual_stack'),
			),
			lesenAktion: 'get_ddnsusers',
			schluessel: Bereich::schluesselAus('dyndns_login', 'ddns_login'),
			einzelParam: 'ddns_login',
			anlegenAktion: 'add_ddnsuser',
			aendernAktion: 'update_ddnsuser',
			aendernSchluessel: self::nachSchluessel('dyndns_login'),
			loeschenAktion: 'delete_ddnsuser',
			sortierung: 'dyndns_login',
			loeschHinweis: 'Der DynDNS-Benutzer wird bei All-Inkl gelöscht.',
		);
	}

	/**
	 * Netzlaufwerke (get_sambausers, add/update/delete_sambauser).
	 *
	 * Die Dokumentation nennt für add_sambauser „samba_new_password“, ihr
	 * eigenes Beispiel „samba_password“; beide gehen hinaus.
	 *
	 * @return Bereich Die Definition
	 */
	private static function netzlaufwerk(): Bereich
	{
		return new Bereich(
			name: 'netzlaufwerk',
			tabelle: 'tl_allinkl_netzlaufwerk',
			modul: 'allinkl_netzlaufwerke',
			titel: 'Netzlaufwerk',
			felder: array(
				Feld::text('samba_login', 'Login', 'Vergibt All-Inkl, etwa „s0123456“.')->kas('samba_login')->inListe(),
				Feld::text('samba_comment', 'Kommentar')->kas('samba_comment')->beides('samba_comment')->pflicht()->inListe(),
				Feld::text('samba_path', 'Verzeichnis')->kas('samba_path')->beides('samba_path')->pflicht()->inListe(),
				Feld::passwort('passwort', 'Kennwort', 'Beim Anlegen Pflicht. Beim Bearbeiten leer lassen, um es zu behalten.')->beides('samba_new_password')->anlegenAuchAls('samba_password')->pflicht(),
			),
			lesenAktion: 'get_sambausers',
			schluessel: Bereich::schluesselAus('samba_login'),
			einzelParam: 'samba_login',
			anlegenAktion: 'add_sambauser',
			aendernAktion: 'update_sambauser',
			aendernSchluessel: self::nachSchluessel('samba_login'),
			loeschenAktion: 'delete_sambauser',
			sortierung: 'samba_login',
			loeschHinweis: 'Das Netzlaufwerk wird bei All-Inkl gelöscht.',
		);
	}
}
