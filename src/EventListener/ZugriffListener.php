<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\EventListener;

use Contao\CoreBundle\Exception\AccessDeniedException;
use Schachbulle\ContaoAllinklBundle\Backend\Module;
use Schachbulle\ContaoAllinklBundle\Sicherheit\Zugang;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Sperrt die All-Inkl-Module für Administratoren ohne Freigabe.
 *
 * Contao lässt Administratoren in jedes Modul. Dieser Listener prüft jede
 * Backend-Anfrage mit „do“ auf ein Modul des Bundles – Listen, Masken,
 * key-Aktionen und das Werkzeug-Modul gleichermaßen – und weist ab, wem
 * Zugang::darfModul() den Zugang verweigert. Für Nicht-Administratoren ist
 * das dieselbe Entscheidung, die Contao selbst trifft.
 *
 * Läuft nach der Firewall (Priorität 8), damit der Benutzer feststeht.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: -20)]
class ZugriffListener
{
	/**
	 * @param Zugang $zugang Die Zugangsregel
	 */
	public function __construct(private readonly Zugang $zugang)
	{
	}

	/**
	 * Prüft die Anfrage.
	 *
	 * @param RequestEvent $event Das Ereignis
	 *
	 * @throws AccessDeniedException Wenn der Benutzer das Modul nicht nutzen darf
	 */
	public function __invoke(RequestEvent $event): void
	{
		$anfrage = $event->getRequest();

		if (!$event->isMainRequest() || 'contao_backend' !== $anfrage->attributes->get('_route')) {
			return;
		}

		$modul = (string) $anfrage->query->get('do', '');

		if (!Module::istEigenes($modul) || null === $this->zugang->benutzer()) {
			return;
		}

		if (!$this->zugang->darfModul($modul)) {
			throw new AccessDeniedException(sprintf('Kein Zugang zum Modul „%s“. Die Freigabe erteilt der Hauptadmin im Benutzerkonto.', $modul));
		}
	}
}
