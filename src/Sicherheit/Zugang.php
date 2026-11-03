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
use Contao\StringUtil;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Entscheidet, wer welches All-Inkl-Modul nutzen darf.
 *
 * Drei Gruppen:
 *
 * - Der Hauptadmin (Bundle-Parameter „hauptadmin“, Vorgabe ID 1) darf alles.
 * - Andere Administratoren dürfen nur die Module, die der Hauptadmin ihnen
 *   in ihrem Benutzerkonto freigegeben hat (tl_user.allinkl_module). Ohne
 *   Freigabe sehen sie die Gruppe gar nicht – das Admin-Kennzeichen von
 *   Contao allein genügt bewusst nicht, weil es um das ganze Webhosting geht.
 * - Alle übrigen Benutzer bekommen die Module über die gewöhnlichen
 *   Modulrechte ihrer Benutzergruppen.
 */
class Zugang
{
	/**
	 * @param TokenStorageInterface $tokens     Liefert den angemeldeten Benutzer
	 * @param int                   $hauptadmin ID des Hauptadmins in tl_user
	 */
	public function __construct(
		private readonly TokenStorageInterface $tokens,
		private readonly int $hauptadmin,
	) {
	}

	/**
	 * Liefert den angemeldeten Backend-Benutzer.
	 *
	 * @return BackendUser|null Der Benutzer, null ohne Backend-Anmeldung
	 */
	public function benutzer(): ?BackendUser
	{
		$benutzer = $this->tokens->getToken()?->getUser();

		return $benutzer instanceof BackendUser ? $benutzer : null;
	}

	/**
	 * Sagt, ob der angemeldete Benutzer der Hauptadmin ist.
	 *
	 * @return bool true nur für den konfigurierten Hauptadmin
	 */
	public function istHauptadmin(): bool
	{
		$benutzer = $this->benutzer();

		return null !== $benutzer && $this->hauptadmin === (int) $benutzer->id;
	}

	/**
	 * Sagt, ob der angemeldete Benutzer ein Modul nutzen darf.
	 *
	 * @param string $modul Etwa „allinkl_domains“
	 *
	 * @return bool true, wenn er darf; false ohne Anmeldung
	 */
	public function darfModul(string $modul): bool
	{
		$benutzer = $this->benutzer();

		return null !== $benutzer && $this->darf($benutzer, $modul);
	}

	/**
	 * Wendet die Regel auf einen bestimmten Benutzer an.
	 *
	 * Öffentlich für den Voter, der den Benutzer aus seinem eigenen Token
	 * bekommt, und für die Tests.
	 *
	 * @param BackendUser $benutzer Der Benutzer
	 * @param string      $modul    Das Modul
	 *
	 * @return bool true, wenn er das Modul nutzen darf
	 */
	public function darf(BackendUser $benutzer, string $modul): bool
	{
		if ($this->hauptadmin === (int) $benutzer->id) {
			return true;
		}

		if ($benutzer->isAdmin) {
			return \in_array($modul, $this->freigaben($benutzer), true);
		}

		return $benutzer->hasAccess($modul, 'modules');
	}

	/**
	 * Sagt, ob für den Benutzer die Einschränkung für Administratoren gilt.
	 *
	 * @param BackendUser $benutzer Der Benutzer
	 *
	 * @return bool true für Administratoren außer dem Hauptadmin
	 */
	public function istEingeschraenkterAdmin(BackendUser $benutzer): bool
	{
		return $benutzer->isAdmin && $this->hauptadmin !== (int) $benutzer->id;
	}

	/**
	 * Liefert die Modulfreigaben eines Administrators.
	 *
	 * @param BackendUser $benutzer Der Benutzer
	 *
	 * @return array<int, string> Die freigegebenen Module
	 */
	public function freigaben(BackendUser $benutzer): array
	{
		return array_values(array_filter(StringUtil::deserialize($benutzer->allinkl_module, true), 'is_string'));
	}
}
