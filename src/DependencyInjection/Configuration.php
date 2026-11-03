<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * Die Bundle-Parameter unter „contao_allinkl:“ in config/config.yaml.
 *
 * Sie stehen bewusst nicht in der Oberfläche: Wer den Hauptadmin oder die
 * Adressen der Schnittstelle ändern kann, kontrolliert den Zugang zum
 * gesamten Webhosting.
 */
class Configuration implements ConfigurationInterface
{
	/**
	 * Beschreibt die erlaubten Schlüssel und ihre Vorgaben.
	 *
	 * Die beiden Adressen lassen sich nur für den Prüfstand umbiegen (auf die
	 * KAS-Attrappe unter pruefstand/); im Betrieb bleiben die Vorgaben stehen.
	 *
	 * @return TreeBuilder Der Konfigurationsbaum
	 */
	public function getConfigTreeBuilder(): TreeBuilder
	{
		$baum = new TreeBuilder('contao_allinkl');

		$baum->getRootNode()
			->children()
				->integerNode('hauptadmin')
					->info('ID des Hauptadmins in tl_user; er sieht alle All-Inkl-Module und gibt sie anderen Administratoren frei.')
					->defaultValue(1)
					->min(1)
				->end()
				->scalarNode('api_adresse')
					->info('Endpunkt der KAS-API (SOAP).')
					->defaultValue('https://kasapi.kasserver.com/soap/KasApi.php')
					->cannotBeEmpty()
				->end()
				->scalarNode('auth_adresse')
					->info('Endpunkt der KAS-Anmeldung (SOAP).')
					->defaultValue('https://kasapi.kasserver.com/soap/KasAuth.php')
					->cannotBeEmpty()
				->end()
				->integerNode('zeitlimit')
					->info('Höchstdauer eines einzelnen KAS-Aufrufs in Sekunden.')
					->defaultValue(30)
					->min(5)
				->end()
			->end();

		return $baum;
	}
}
