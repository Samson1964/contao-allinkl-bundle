<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\EventListener\DataContainer;

use Contao\CoreBundle\DependencyInjection\Attribute\AsCallback;
use Contao\CoreBundle\Exception\RedirectResponseException;
use Contao\Input;
use Contao\Message;
use Doctrine\DBAL\Connection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Verhindert das Wiederherstellen gelöschter KAS-Objekte.
 *
 * Ein gelöschtes Objekt ist bei All-Inkl wirklich weg; „Wiederherstellen“
 * würde nur die Zeile der Spiegeltabelle zurückholen, ohne dass es das
 * Objekt bei der KAS wieder gäbe. Der nächste Abgleich würde sie ohnehin
 * entfernen. Statt dieser Täuschung gibt es eine Meldung.
 */
class UndoListener
{
	/**
	 * @param Connection            $db     Die Datenbank
	 * @param UrlGeneratorInterface $router Für die Rücksprungadresse
	 */
	public function __construct(
		private readonly Connection $db,
		private readonly UrlGeneratorInterface $router,
	) {
	}

	/**
	 * Weist das Wiederherstellen von Zeilen der Spiegeltabellen ab.
	 *
	 * Zugänge (tl_allinkl_konto) dürfen wiederhergestellt werden; ihre
	 * gespiegelten Daten holt ein Abgleich zurück.
	 *
	 * @throws RedirectResponseException Zurück zur Liste „Wiederherstellen“
	 */
	#[AsCallback(table: 'tl_undo', target: 'config.onload')]
	public function __invoke(): void
	{
		$id = Input::get('id');

		if ('undo' !== Input::get('act') || !\is_string($id) || 1 !== preg_match('/^\d+$/D', $id)) {
			return;
		}

		$tabelle = (string) $this->db->fetchOne('SELECT fromTable FROM tl_undo WHERE id = ?', array((int) $id));

		if (!str_starts_with($tabelle, 'tl_allinkl_') || 'tl_allinkl_konto' === $tabelle) {
			return;
		}

		Message::addError('Gelöschte KAS-Objekte lassen sich nicht wiederherstellen – bei All-Inkl sind sie bereits entfernt. Bitte neu anlegen.');

		throw new RedirectResponseException($this->router->generate('contao_backend', array('do' => 'undo')));
	}
}
