<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\EventListener\DataContainer;

use Contao\CoreBundle\DataContainer\PaletteManipulator;
use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\DataContainer;
use Contao\Date;
use Contao\Input;
use Contao\StringUtil;
use Schachbulle\ContaoAllinklBundle\Backend\Meldungen;
use Schachbulle\ContaoAllinklBundle\Backend\Passwortablage;
use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;
use Schachbulle\ContaoAllinklBundle\Bereich\Eingabefehler;
use Schachbulle\ContaoAllinklBundle\Bereich\Schreiber;
use Schachbulle\ContaoAllinklBundle\Datenbank\KontoSpeicher;
use Schachbulle\ContaoAllinklBundle\Datenbank\Spiegel;
use Schachbulle\ContaoAllinklBundle\Kas\Fehlertext;
use Schachbulle\ContaoAllinklBundle\Kas\KasFehler;
use Schachbulle\ContaoAllinklBundle\Sicherheit\Verschluesselung;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Die Maske der KAS-Zugänge (tl_allinkl_konto).
 *
 * Das Kennwort steht verschlüsselt in der Datenbank und erscheint in der
 * Maske nur als Platzhalter; das echte Kennwort gelangt nie ins HTML. Die
 * Accounteinstellungen, die der Abgleich in die Zeile holt, gehen beim
 * Speichern über update_accountsettings an die KAS.
 */
class KontoListener
{
	/** Platzhalter für ein gespeichertes Kennwort */
	public const PLATZHALTER = '********';

	/**
	 * @param KontoSpeicher    $konten           Die Zugänge
	 * @param Spiegel          $spiegel          Die Spiegeltabellen
	 * @param Schreiber        $schreiber        Schickt Änderungen an die KAS
	 * @param Passwortablage   $ablage           Kennwörter der laufenden Anfrage
	 * @param Verschluesselung $verschluesselung Für das Zugangskennwort
	 * @param RequestStack     $anfragen         Für den Rücksprung in die Maske
	 * @param Meldungen $meldungen Sammelt die Meldungen für die Folgeseite
	 */
	public function __construct(
		private readonly KontoSpeicher $konten,
		private readonly Spiegel $spiegel,
		private readonly Schreiber $schreiber,
		private readonly Passwortablage $ablage,
		private readonly Verschluesselung $verschluesselung,
		private readonly RequestStack $anfragen,
		private readonly Meldungen $meldungen,
	) {
	}

	/**
	 * Blendet die Accounteinstellungen aus, solange es keinen Abgleich gab.
	 *
	 * Vor dem ersten Abgleich kennt das Bundle die Einstellungen nicht; eine
	 * Maske mit leeren Feldern würde beim Speichern Unsinn an die KAS
	 * schicken.
	 *
	 * @param DataContainer|null $dc Der Data Container
	 */
	#[AsCallback(table: 'tl_allinkl_konto', target: 'config.onload')]
	public function laden(?DataContainer $dc = null): void
	{
		if (null === $dc || 'edit' !== Input::get('act') || !$dc->id) {
			return;
		}

		$zeile = $this->konten->zeile((int) $dc->id);

		if (null !== $zeile && '' === (string) ($zeile['kas_daten'] ?? '')) {
			$manipulator = PaletteManipulator::create();

			foreach (array_keys(BereichRegister::bereich('einstellungen')->felder) as $spalte) {
				$manipulator->removeField($spalte, 'einstellungen_legend');
			}

			$manipulator->applyToPalette('default', 'tl_allinkl_konto');
		}
	}

	/**
	 * Zeigt statt des gespeicherten Kennworts einen Platzhalter.
	 *
	 * @param mixed $wert Der verschlüsselte Wert aus der Datenbank
	 *
	 * @return string Leer ohne Kennwort, sonst der Platzhalter
	 */
	#[AsCallback(table: 'tl_allinkl_konto', target: 'fields.kas_passwort.load')]
	public function passwortLaden(mixed $wert): string
	{
		return '' === (string) $wert ? '' : self::PLATZHALTER;
	}

	/**
	 * Verschlüsselt ein neu eingegebenes Kennwort.
	 *
	 * Bleibt der Platzhalter stehen oder das Feld leer, behält die Zeile das
	 * bisherige Kennwort.
	 *
	 * @param mixed         $wert Die Eingabe
	 * @param DataContainer $dc   Der Data Container
	 *
	 * @return string Der verschlüsselte Wert für die Datenbank
	 */
	#[AsCallback(table: 'tl_allinkl_konto', target: 'fields.kas_passwort.save')]
	public function passwortSpeichern(mixed $wert, DataContainer $dc): string
	{
		$wert = (string) $wert;

		if ('' === $wert || self::PLATZHALTER === $wert) {
			return (string) ($this->konten->zeile((int) $dc->id)['kas_passwort'] ?? '');
		}

		return $this->verschluesselung->verschluesseln($wert);
	}

	/**
	 * Schickt geänderte Accounteinstellungen an die KAS.
	 *
	 * Wurde ein neues KAS-Kennwort gesetzt und von der KAS angenommen, wird
	 * es zugleich als Zugangskennwort gespeichert – sonst schlüge jede
	 * weitere Anmeldung fehl.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @throws RedirectResponseException Zurück in die Maske bei einem Fehler
	 */
	#[AsCallback(table: 'tl_allinkl_konto', target: 'config.onsubmit')]
	public function speichern(DataContainer $dc): void
	{
		$zeile = $this->konten->zeile((int) $dc->id);
		$geheim = $this->ablage->abholen('tl_allinkl_konto');

		if (null === $zeile || '' === (string) ($zeile['kas_daten'] ?? '')) {
			return;
		}

		$bereich = BereichRegister::bereich('einstellungen');

		try {
			$ergebnis = $this->schreiber->aendern($bereich, $this->konten->zugang((int) $zeile['id']), $zeile + array('kas_schluessel' => 'einstellungen'), $geheim, null);
		} catch (Eingabefehler|KasFehler|\RuntimeException $fehler) {
			$this->meldungen->fehler('Die Accounteinstellungen wurden nicht geändert: '.Fehlertext::zu($fehler));
			$anfrage = $this->anfragen->getCurrentRequest();

			if (null !== $anfrage) {
				throw new RedirectResponseException($anfrage->getUri());
			}

			return;
		}

		if (!$ergebnis['geaendert']) {
			return;
		}

		if ('' !== ($geheim['account_password'] ?? '')) {
			$this->konten->passwortSetzen((int) $zeile['id'], $geheim['account_password']);
		}

		if (null !== $ergebnis['kas']) {
			$this->spiegel->einstellungenUebernehmen($bereich, (int) $zeile['id'], $ergebnis['kas']);
		}

		$this->meldungen->erfolg('Die Accounteinstellungen wurden bei All-Inkl geändert.');
	}

	/**
	 * Entfernt die gespiegelten Daten eines gelöschten Zugangs.
	 *
	 * Bei All-Inkl selbst bleibt alles bestehen.
	 *
	 * @param DataContainer $dc     Der Data Container
	 * @param int           $undoId Unbenutzt
	 */
	#[AsCallback(table: 'tl_allinkl_konto', target: 'config.ondelete')]
	public function loeschen(DataContainer $dc, int $undoId = 0): void
	{
		if ($dc->id) {
			$this->spiegel->kontoEntfernen((int) $dc->id);
		}
	}

	/**
	 * Beschriftet einen Zugang in der Liste.
	 *
	 * @param array<string, mixed>   $zeile Die Zeile
	 * @param string                 $label Die vorbereitete Beschriftung
	 * @param DataContainer|null     $dc    Der Data Container
	 * @param array<int, mixed>|null $args  Unbenutzt
	 *
	 * @return string Titel, Login, Schreibschutz und letzter Abgleich
	 */
	#[AsCallback(table: 'tl_allinkl_konto', target: 'list.label.label')]
	public function beschriftung(array $zeile, string $label, ?DataContainer $dc = null, ?array $args = null): string
	{
		$teile = array(
			'<strong>'.StringUtil::specialchars((string) $zeile['title']).'</strong>',
			'<span class="tl_gray">['.StringUtil::specialchars((string) $zeile['kas_login']).']</span>',
		);

		if ('' !== (string) $zeile['schreibschutz']) {
			$teile[] = '<span class="tl_blue">schreibgeschützt</span>';
		}

		if ('' === (string) $zeile['aktiv']) {
			$teile[] = '<span class="tl_gray">inaktiv</span>';
		}

		$teile[] = (int) $zeile['letzter_abgleich'] > 0
			? '<span class="tl_gray">abgeglichen '.Date::parse('d.m.Y H:i', (int) $zeile['letzter_abgleich']).'</span>'
			: '<span class="tl_red">noch nie abgeglichen</span>';

		return implode(' ', $teile);
	}
}
