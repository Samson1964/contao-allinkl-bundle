<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\EventListener\DataContainer;

use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\DataContainer;
use Contao\Input;
use Contao\Message;
use Contao\StringUtil;
use Schachbulle\ContaoAllinklBundle\Backend\Meldungen;
use Schachbulle\ContaoAllinklBundle\Backend\Module;
use Schachbulle\ContaoAllinklBundle\Backend\Passwortablage;
use Schachbulle\ContaoAllinklBundle\Bereich\Abbild;
use Schachbulle\ContaoAllinklBundle\Bereich\Bereich;
use Schachbulle\ContaoAllinklBundle\Bereich\BereichRegister;
use Schachbulle\ContaoAllinklBundle\Bereich\DcaBauer;
use Schachbulle\ContaoAllinklBundle\Bereich\Eingabefehler;
use Schachbulle\ContaoAllinklBundle\Bereich\Schreiber;
use Schachbulle\ContaoAllinklBundle\Datenbank\KontoSpeicher;
use Schachbulle\ContaoAllinklBundle\Datenbank\Spiegel;
use Schachbulle\ContaoAllinklBundle\Kas\Fehlertext;
use Schachbulle\ContaoAllinklBundle\Kas\KasFehler;
use Schachbulle\ContaoAllinklBundle\Kas\Zugangsdaten;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Verbindet die Masken der Spiegeltabellen mit der KAS.
 *
 * Contao speichert eine Maske zuerst in der eigenen Datenbank; danach
 * schickt speichern() die Änderung an die KAS. Lehnt die KAS ab, bleibt die
 * Eingabe in der Zeile stehen, die Meldung erscheint und die Maske öffnet
 * sich erneut – so kann man korrigieren, statt alles neu einzugeben. Der
 * nächste Abgleich stellt den Stand der KAS wieder her, falls man aufgibt.
 *
 * Ein Löschen geht erst an die KAS; nur wenn sie zustimmt, löscht Contao
 * die Zeile.
 *
 * Die Callbacks trägt DcaBauer als Paar aus Dienst-ID und Methode ein,
 * deshalb ist der Dienst öffentlich.
 */
class ObjektListener
{
	/**
	 * @param Spiegel               $spiegel  Die Spiegeltabellen
	 * @param KontoSpeicher         $konten   Die Zugänge
	 * @param Schreiber             $schreiber Schickt die Aufträge an die KAS
	 * @param Passwortablage        $ablage   Kennwörter der laufenden Anfrage
	 * @param RequestStack          $anfragen Für die Rücksprungadressen
	 * @param UrlGeneratorInterface $router   Für die Listenadresse
	 * @param Meldungen $meldungen Sammelt die Meldungen für die Folgeseite
	 */
	public function __construct(
		private readonly Spiegel $spiegel,
		private readonly KontoSpeicher $konten,
		private readonly Schreiber $schreiber,
		private readonly Passwortablage $ablage,
		private readonly RequestStack $anfragen,
		private readonly UrlGeneratorInterface $router,
		private readonly Meldungen $meldungen,
	) {
	}

	/**
	 * Passt die Maske an das geöffnete Objekt an.
	 *
	 * Ein neues Objekt (noch ohne kas_schluessel) zeigt nur die Felder, die
	 * add_* entgegennimmt, und markiert die Pflichtfelder. Ein bestehendes
	 * zeigt die änderbaren Felder; was die KAS nur liefert, aber nicht ändern
	 * lässt, erscheint schreibgeschützt. Die Änderungen gelten nur für diese
	 * Anfrage ($GLOBALS['TL_DCA']).
	 *
	 * @param DataContainer|null $dc Der Data Container; in Contao 5 auch bei
	 *                               Listenansichten gesetzt
	 */
	public function laden(?DataContainer $dc = null): void
	{
		$bereich = null === $dc ? null : BereichRegister::zurTabelle((string) $dc->table);

		if (null === $bereich || 'edit' !== Input::get('act') || !$dc->id) {
			return;
		}

		$zeile = $this->spiegel->zeile($bereich->tabelle, (int) $dc->id);

		if (null === $zeile) {
			return;
		}

		$neu = '' === (string) $zeile['kas_schluessel'];
		$dca = &$GLOBALS['TL_DCA'][$bereich->tabelle];
		$dca['palettes']['default'] = DcaBauer::palette($bereich, $neu ? 'neu' : 'bestehend');
		$unbekannt = $neu ? array() : Abbild::unbekannteSpalten($bereich, Abbild::ausJson($zeile['kas_daten'] ?? null));

		foreach ($bereich->felder as $feld) {
			if (!isset($dca['fields'][$feld->spalte])) {
				continue;
			}

			$definition = &$dca['fields'][$feld->spalte];

			if ($neu) {
				if ($feld->pflicht && $feld->beimAnlegen) {
					$definition['eval']['mandatory'] = true;
				}
			} elseif (null === $feld->aendernParam) {
				// Auswahllisten lassen sich in HTML nicht schreibschützen;
				// sie erscheinen als Textfeld mit dem Rohwert der KAS
				$definition['inputType'] = 'textarea' === ($definition['inputType'] ?? '') ? 'textarea' : 'text';
				$definition['eval']['readonly'] = true;
				$definition['eval']['mandatory'] = false;
				unset($definition['options'], $definition['reference'], $definition['eval']['rgxp']);
			}

			// Die KAS verschweigt den aktuellen Wert; was die Maske zeigt, ist
			// dann nicht der Stand bei All-Inkl
			if (null !== $feld->aendernParam && \in_array($feld->spalte, $unbekannt, true)) {
				$definition['label'][1] = trim(($definition['label'][1] ?? '').' Die KAS meldet den aktuellen Wert nicht – die Anzeige ist nicht der Stand bei All-Inkl. '.('janein' === $feld->art ? 'Ohne Häkchen bleibt die Einstellung unverändert, mit Häkchen wird sie eingeschaltet.' : 'Leer lassen ändert nichts.'));
			}

			unset($definition);
		}

		if (!$neu && '' !== (string) $zeile['kas_in_bearbeitung']) {
			Message::addInfo('All-Inkl richtet dieses Objekt noch ein. Der nächste Abgleich holt die endgültigen Daten.');
		}
	}

	/**
	 * Schickt das gespeicherte Objekt an die KAS.
	 *
	 * @param DataContainer $dc Der Data Container
	 *
	 * @throws RedirectResponseException Zurück in die Maske bei einem Fehler,
	 *                                   in die Liste, wenn das Objekt den
	 *                                   Zugang verlassen hat
	 */
	public function speichern(DataContainer $dc): void
	{
		$bereich = BereichRegister::zurTabelle((string) $dc->table);
		$zeile = null === $bereich ? null : $this->spiegel->zeile($bereich->tabelle, (int) $dc->id);

		if (null === $bereich || null === $zeile) {
			return;
		}

		$geheim = $this->ablage->abholen($bereich->tabelle);
		$eltern = $this->eltern($bereich, $zeile);

		try {
			$zugang = $this->konten->zugang((int) $zeile['konto']);

			if ('' === (string) $zeile['kas_schluessel']) {
				$this->neuAnlegen($bereich, $zeile, $geheim, $eltern, $zugang);

				return;
			}

			$ergebnis = $this->schreiber->aendern($bereich, $zugang, $zeile, $geheim, $eltern);
		} catch (Eingabefehler|KasFehler|\RuntimeException $fehler) {
			$text = Fehlertext::zu($fehler);
			$this->spiegel->fehlerVermerken($bereich, (int) $zeile['id'], $text);
			$this->meldungen->fehler($text);

			throw new RedirectResponseException($this->zurueckInDieMaske());
		}

		if ($ergebnis['entfernt']) {
			$this->spiegel->loeschen($bereich, array((int) $zeile['id']));
			$this->meldungen->erfolg(sprintf('%s „%s“ wurde verschoben und erscheint beim Abgleich des Zielaccounts.', $bereich->titel, $zeile['kas_schluessel']));

			throw new RedirectResponseException($this->liste($bereich, $zeile));
		}

		if (null !== $ergebnis['kas']) {
			$this->spiegel->uebernehmen($bereich, (int) $zeile['id'], $ergebnis['kas']);
		} else {
			$this->spiegel->fehlerVermerken($bereich, (int) $zeile['id'], '');
		}

		if ($ergebnis['geaendert']) {
			$this->meldungen->erfolg(sprintf('%s „%s“ wurde bei All-Inkl geändert.', $bereich->titel, $zeile['kas_schluessel']));
		}
	}

	/**
	 * Löscht das Objekt bei der KAS, bevor Contao die Zeile löscht.
	 *
	 * Entwürfe, die nie bei der KAS ankamen, löscht Contao ohne Rückfrage
	 * dort. Lehnt die KAS ab, wird das Löschen abgebrochen.
	 *
	 * @param DataContainer $dc     Der Data Container
	 * @param int           $undoId ID des Wiederherstellungsschritts, unbenutzt
	 *
	 * @throws RedirectResponseException Zurück in die Liste, wenn die KAS das
	 *                                   Löschen ablehnt
	 */
	public function loeschen(DataContainer $dc, int $undoId = 0): void
	{
		$bereich = BereichRegister::zurTabelle((string) $dc->table);
		$zeile = null === $bereich ? null : $this->spiegel->zeile($bereich->tabelle, (int) $dc->id);

		if (null === $bereich || null === $zeile || '' === (string) $zeile['kas_schluessel']) {
			return;
		}

		try {
			$this->schreiber->loeschen($bereich, $this->konten->zugang((int) $zeile['konto']), $zeile);
		} catch (Eingabefehler|KasFehler|\RuntimeException $fehler) {
			$this->meldungen->fehler(sprintf('%s „%s“ wurde nicht gelöscht: %s', $bereich->titel, $zeile['kas_schluessel'], Fehlertext::zu($fehler)));

			throw new RedirectResponseException($this->liste($bereich, $zeile));
		}

		$this->meldungen->erfolg(sprintf('%s „%s“ wurde bei All-Inkl gelöscht.', $bereich->titel, $zeile['kas_schluessel']));
	}

	/**
	 * Ergänzt die Listenzeile um den Stand bei der KAS.
	 *
	 * Mit „showColumns“ (flache Bereiche) erwartet Contao die Spalten als
	 * Feld zurück, im Eltern-Modus einen Text. Die Signatur deckt beide
	 * Fassungen ab: 4.13 ruft im Eltern-Modus mit drei Argumenten auf.
	 *
	 * @param array<string, mixed>   $zeile Die Zeile
	 * @param string                 $label Die vorbereitete Beschriftung
	 * @param DataContainer|null     $dc    Der Data Container
	 * @param array<int, mixed>|null $args  Die Spaltenwerte bei showColumns
	 *
	 * @return array<int, mixed>|string Die Spalten oder die Beschriftung
	 */
	public function beschriftung(array $zeile, string $label, ?DataContainer $dc = null, ?array $args = null): array|string
	{
		$vermerk = $this->vermerk($zeile);
		$tabelle = (string) ($dc?->table ?? '');

		// 5.7 übergibt $args auch im Eltern-Modus; maßgeblich ist showColumns
		$spalten = \is_array($args) && true === ($GLOBALS['TL_DCA'][$tabelle]['list']['label']['showColumns'] ?? false);

		// Der Eintrag für die Domain selbst hat keinen Namen
		if ('tl_allinkl_dns' === $tabelle && '' === (string) ($zeile['record_name'] ?? '')) {
			$label = '@'.$label;
		}

		if ($spalten) {
			$letzte = array_key_last($args);

			if (null !== $letzte && '' !== $vermerk) {
				$args[$letzte] = $args[$letzte].' '.$vermerk;
			}

			return $args;
		}

		return $label.('' === $vermerk ? '' : ' '.$vermerk);
	}

	/**
	 * Hält ein eingegebenes Kennwort fest, statt es zu speichern.
	 *
	 * @param mixed         $wert Die Eingabe
	 * @param DataContainer $dc   Der Data Container
	 *
	 * @return string Immer leer – die Spalte bleibt leer
	 */
	public function kennwortMerken(mixed $wert, DataContainer $dc): string
	{
		$this->ablage->ablegen((string) $dc->table, (string) $dc->field, (string) $wert);

		return '';
	}

	/**
	 * Legt ein neues Objekt bei der KAS an und übernimmt das Ergebnis.
	 *
	 * Lässt sich der Schlüssel des neuen Objekts nicht bestimmen, wird die
	 * Zeile gelöscht, damit der nächste Abgleich keine Doppelung erzeugt.
	 *
	 * @param Bereich                                         $bereich Der Bereich
	 * @param array<string, mixed>                            $zeile   Die Zeile
	 * @param array<string, string>                           $geheim  Die Kennwörter
	 * @param array<string, mixed>|null                       $eltern  Die Elternzeile
	 * @param Zugangsdaten                                    $zugang  Der Zugang
	 *
	 * @throws Eingabefehler|KasFehler Werden von speichern() behandelt
	 * @throws RedirectResponseException In die Liste, wenn die Zeile entfiel
	 */
	private function neuAnlegen(Bereich $bereich, array $zeile, array $geheim, ?array $eltern, Zugangsdaten $zugang): void
	{
		$ergebnis = $this->schreiber->anlegen($bereich, $zugang, $zeile, $geheim, $eltern);
		$id = (int) $zeile['id'];

		if (null !== $ergebnis['kas']) {
			$this->spiegel->uebernehmen($bereich, $id, $ergebnis['kas'], $ergebnis['schluessel']);
		} elseif ('' !== $ergebnis['schluessel']) {
			$this->spiegel->schluesselSetzen($bereich, $id, $ergebnis['schluessel']);
		} else {
			$this->spiegel->loeschen($bereich, array($id));
			$this->meldungen->erfolg(sprintf('%s wurde bei All-Inkl angelegt. Ein Abgleich holt es in die Liste.', $bereich->titel));

			throw new RedirectResponseException($this->liste($bereich, $zeile));
		}

		$this->meldungen->erfolg(sprintf('%s „%s“ wurde bei All-Inkl angelegt.', $bereich->titel, $ergebnis['schluessel']));
	}

	/**
	 * Liefert die Elternzeile und gleicht den Zugang des Kindes an.
	 *
	 * Kindobjekte (Subdomains, DNS) wählen keinen Zugang; sie gehören zum
	 * Zugang ihrer Domain. Die Spalte konto wird hier nachgezogen.
	 *
	 * @param Bereich              $bereich Der Bereich
	 * @param array<string, mixed> $zeile   Die Zeile, wird angepasst
	 *
	 * @return array<string, mixed>|null Die Elternzeile, null bei flachen
	 *                                   Bereichen
	 */
	private function eltern(Bereich $bereich, array &$zeile): ?array
	{
		if (null === $bereich->eltern) {
			return null;
		}

		$eltern = $this->spiegel->zeile(BereichRegister::bereich($bereich->eltern)->tabelle, (int) $zeile['pid']);

		if (null !== $eltern && (int) $eltern['konto'] !== (int) $zeile['konto']) {
			$zeile['konto'] = (int) $eltern['konto'];
			$this->spiegel->kontoSetzen($bereich, (int) $zeile['id'], (int) $eltern['konto']);
		}

		return $eltern;
	}

	/**
	 * Bildet den Zustandsvermerk einer Listenzeile.
	 *
	 * @param array<string, mixed> $zeile Die Zeile
	 *
	 * @return string HTML; leer, wenn alles in Ordnung ist
	 */
	private function vermerk(array $zeile): string
	{
		$teile = array();

		if ('' === (string) ($zeile['kas_schluessel'] ?? '')) {
			$teile[] = '<span class="tl_red">(Entwurf – noch nicht bei All-Inkl)</span>';
		}

		if ('' !== (string) ($zeile['kas_in_bearbeitung'] ?? '')) {
			$teile[] = '<span class="tl_gray">(wird eingerichtet)</span>';
		}

		if ('' !== trim((string) ($zeile['kas_fehler'] ?? ''))) {
			$teile[] = '<span class="tl_red" title="'.StringUtil::specialchars((string) $zeile['kas_fehler']).'">(Fehler beim letzten Speichern)</span>';
		}

		return implode(' ', $teile);
	}

	/**
	 * Liefert die Adresse der laufenden Anfrage für den Rücksprung in die Maske.
	 *
	 * @return string Die Adresse samt Abfrageparametern
	 */
	private function zurueckInDieMaske(): string
	{
		$anfrage = $this->anfragen->getCurrentRequest();

		return null === $anfrage ? $this->router->generate('contao_backend') : $anfrage->getUri();
	}

	/**
	 * Liefert die Adresse der Liste, in der die Zeile steht.
	 *
	 * Bei Kindbereichen ist das die Liste unter der Domain (id = pid). Die
	 * Referer-ID „ref“ hängt nur Contao 4.13 an; ein null-Wert fällt weg.
	 *
	 * @param Bereich              $bereich Der Bereich
	 * @param array<string, mixed> $zeile   Die Zeile
	 *
	 * @return string Die Adresse
	 */
	private function liste(Bereich $bereich, array $zeile): string
	{
		$anfrage = $this->anfragen->getCurrentRequest();

		return $this->router->generate('contao_backend', array(
			'do'    => Module::zurTabelle($bereich->tabelle),
			'table' => $bereich->tabelle,
			'id'    => null === $bereich->eltern ? null : (int) $zeile['pid'],
			'ref'   => $anfrage?->attributes->get('_contao_referer_id'),
		));
	}
}
