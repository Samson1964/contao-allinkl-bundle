<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\EventListener;

use Contao\CoreBundle\Event\MenuEvent;
use Schachbulle\ContaoAllinklBundle\Backend\Module;
use Schachbulle\ContaoAllinklBundle\Sicherheit\Zugang;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Nimmt Administratoren ohne Freigabe die Module aus dem Menü.
 *
 * Contao baut die Menügruppe für Administratoren immer vollständig. Der
 * Listener läuft nach Contaos eigenem (Priorität 10) und entfernt jedes
 * Modul, das Zugang::darf() verweigert; bleibt keines übrig, verschwindet
 * die ganze Gruppe. Nicht-Administratoren bekommen ohnehin nur ihre
 * freigegebenen Module.
 */
#[AsEventListener(priority: -10)]
class MenueListener
{
	/**
	 * @param Zugang $zugang Die Zugangsregel
	 */
	public function __construct(private readonly Zugang $zugang)
	{
	}

	/**
	 * Bereinigt das Hauptmenü.
	 *
	 * @param MenuEvent $event Das Menü-Ereignis; auch für das Kopfzeilenmenü,
	 *                         das hier nicht interessiert
	 */
	public function __invoke(MenuEvent $event): void
	{
		$baum = $event->getTree();
		$benutzer = $this->zugang->benutzer();

		if ('mainMenu' !== $baum->getName() || null === $benutzer || !$this->zugang->istEingeschraenkterAdmin($benutzer)) {
			return;
		}

		$gruppe = $baum->getChild(Module::GRUPPE);

		if (null === $gruppe) {
			return;
		}

		foreach (array_keys($gruppe->getChildren()) as $modul) {
			if (!$this->zugang->darf($benutzer, (string) $modul)) {
				$gruppe->removeChild($modul);
			}
		}

		if (0 === \count($gruppe->getChildren())) {
			$baum->removeChild(Module::GRUPPE);
		}
	}
}
