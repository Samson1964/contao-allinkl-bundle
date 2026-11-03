<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Backend;

use Contao\Message;
use Contao\StringUtil;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Sammelt die Meldungen des Bundles und gibt sie am Ende der Anfrage an Contao.
 *
 * DC_Table ruft bei „Speichern und schließen“ Message::reset() auf, nachdem
 * der onsubmit_callback lief – eine dort gesetzte Bestätigung verschwände.
 * Deshalb werden die Meldungen hier vorgemerkt und erst bei kernel.response
 * übergeben, also nach jedem reset(). Gleiche Texte erscheinen nur einmal.
 *
 * Die Texte enthalten Angaben von der KAS und aus Masken (Domainnamen,
 * Fehlerkürzel); Contao gibt Meldungen unverändert als HTML aus, darum
 * werden sie hier maskiert.
 */
class Meldungen implements ResetInterface
{
	/** @var array<string, array<int, string>> Art => Texte */
	private array $offen = array();

	/**
	 * Merkt eine Fehlermeldung vor.
	 *
	 * @param string $text Der Text, unmaskiert
	 */
	public function fehler(string $text): void
	{
		$this->offen['error'][] = $text;
	}

	/**
	 * Merkt eine Erfolgsmeldung vor.
	 *
	 * @param string $text Der Text, unmaskiert
	 */
	public function erfolg(string $text): void
	{
		$this->offen['confirm'][] = $text;
	}

	/**
	 * Merkt einen Hinweis vor.
	 *
	 * @param string $text Der Text, unmaskiert
	 */
	public function hinweis(string $text): void
	{
		$this->offen['info'][] = $text;
	}

	/**
	 * Übergibt die vorgemerkten Meldungen an Contao.
	 *
	 * Nur für die Hauptanfrage. Der Bereich „BE“ wird ausdrücklich genannt,
	 * weil Message ihn in 4.13 aus TL_MODE und in 5.7 aus dem Scope-Matcher
	 * ableitet. Läuft mit der Vorgabepriorität 0 und damit vor Symfonys
	 * SessionListener (-1000), der die Sitzung speichert.
	 *
	 * Seiteneffekte: schreibt in den Flash-Speicher der Sitzung.
	 *
	 * @param ResponseEvent $event Das Ereignis kernel.response
	 */
	#[AsEventListener(event: KernelEvents::RESPONSE)]
	public function beiAntwort(ResponseEvent $event): void
	{
		if (!$event->isMainRequest() || array() === $this->offen) {
			return;
		}

		foreach ($this->offen as $art => $texte) {
			foreach (array_unique($texte) as $text) {
				$text = StringUtil::specialchars($text);

				match ($art) {
					'error' => Message::addError($text, 'BE'),
					'confirm' => Message::addConfirmation($text, 'BE'),
					default => Message::addInfo($text, 'BE'),
				};
			}
		}

		$this->offen = array();
	}

	/**
	 * Verwirft alle Vormerkungen zwischen zwei Anfragen desselben Prozesses.
	 */
	public function reset(): void
	{
		$this->offen = array();
	}
}
