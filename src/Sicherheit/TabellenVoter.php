<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Sicherheit;

use Contao\BackendUser;
use Contao\CoreBundle\Security\DataContainer\AbstractAction;
use Schachbulle\ContaoAllinklBundle\Backend\Module;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Sperrt die Spiegeltabellen in Contaos Datensatzrechten für
 * Administratoren ohne Freigabe (nur Contao 5).
 *
 * Contao 5 fragt Datensatzzugriffe auch außerhalb der Module ab, etwa in
 * der Backend-Suche, und gibt Administratoren dort immer recht. Dieser
 * Voter läuft vor Contaos TableAccessVoter und verweigert eingeschränkten
 * Administratoren die Tabellen der Module, die ihnen nicht freigegeben
 * sind. In allen anderen Fällen enthält er sich.
 *
 * Unter Contao 4.13 gibt es weder die Action-Klassen noch „contao_dc.“-Rechte;
 * der instanceof-Vergleich mit einer unbekannten Klasse ist in PHP schlicht
 * false, der Voter enthält sich also immer.
 */
class TabellenVoter implements VoterInterface
{
	/**
	 * @param Zugang $zugang Die Zugangsregel
	 */
	public function __construct(private readonly Zugang $zugang)
	{
	}

	/**
	 * Entscheidet über Zugriffe auf die Spiegeltabellen.
	 *
	 * Die Signatur ist mit Symfony 5.4 (drei Parameter) und 7.4 (optionaler
	 * vierter Parameter $vote) verträglich.
	 *
	 * @param TokenInterface $token      Die Anmeldung
	 * @param mixed          $subject    In Contao 5 eine Create-, Read-, Update-
	 *                                   oder DeleteAction
	 * @param array<mixed>   $attributes Die abgefragten Rechte
	 * @param mixed          $vote       Symfony 7.3+: Begründungsobjekt, unbenutzt
	 *
	 * @return int ACCESS_DENIED für eingeschränkte Administratoren ohne
	 *             Freigabe, sonst ACCESS_ABSTAIN
	 */
	public function vote(TokenInterface $token, mixed $subject, array $attributes, mixed $vote = null): int
	{
		if (!$subject instanceof AbstractAction) {
			return self::ACCESS_ABSTAIN;
		}

		$modul = Module::zurTabelle((string) $subject->getDataSource());
		$benutzer = $token->getUser();

		if (null === $modul || !$benutzer instanceof BackendUser || !$this->zugang->istEingeschraenkterAdmin($benutzer)) {
			return self::ACCESS_ABSTAIN;
		}

		$fragtDatencontainer = array_filter($attributes, static fn ($attribut): bool => \is_string($attribut) && str_starts_with($attribut, 'contao_dc.'));

		if (array() === $fragtDatencontainer || $this->zugang->darf($benutzer, $modul)) {
			return self::ACCESS_ABSTAIN;
		}

		return self::ACCESS_DENIED;
	}
}
