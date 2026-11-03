<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Sicherheit;

/**
 * Verschlüsselt die KAS-Kennwörter in tl_allinkl_konto.
 *
 * Contao\Encryption gibt es in Contao 5 nicht mehr, und „encrypt“ in eval
 * wertet dort kein Data Container mehr aus – das Kennwort stünde
 * stillschweigend im Klartext. Deshalb hier selbst mit libsodium
 * (XSalsa20-Poly1305); der Schlüssel wird aus dem APP_SECRET der
 * Installation abgeleitet. Wer APP_SECRET ändert, muss die Kennwörter neu
 * eintragen.
 */
class Verschluesselung
{
	/** Kennung vor dem verschlüsselten Text, erlaubt später ein neues Verfahren */
	private const PRAEFIX = 'sb1:';

	/** Der abgeleitete Schlüssel */
	private readonly string $schluessel;

	/**
	 * @param string $geheimnis Das APP_SECRET (%kernel.secret%)
	 */
	public function __construct(string $geheimnis)
	{
		$this->schluessel = sodium_crypto_generichash('contao-allinkl|'.$geheimnis, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
	}

	/**
	 * Verschlüsselt einen Text.
	 *
	 * @param string $klartext Der Text, etwa ein Kennwort
	 *
	 * @return string Präfix und Base64 aus Nonce und Geheimtext; jedes Mal
	 *                anders, auch bei gleichem Klartext
	 */
	public function verschluesseln(string $klartext): string
	{
		$nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

		return self::PRAEFIX.base64_encode($nonce.sodium_crypto_secretbox($klartext, $nonce, $this->schluessel));
	}

	/**
	 * Entschlüsselt einen mit verschluesseln() erzeugten Text.
	 *
	 * @param string $geheimtext Der gespeicherte Wert
	 *
	 * @return string Der Klartext
	 *
	 * @throws \RuntimeException Wenn der Wert fehlt, beschädigt ist oder mit
	 *                           einem anderen APP_SECRET verschlüsselt wurde
	 */
	public function entschluesseln(string $geheimtext): string
	{
		if (!str_starts_with($geheimtext, self::PRAEFIX)) {
			throw new \RuntimeException('Es ist kein verschlüsseltes Kennwort gespeichert.');
		}

		$roh = base64_decode(substr($geheimtext, \strlen(self::PRAEFIX)), true);

		if (false === $roh || \strlen($roh) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
			throw new \RuntimeException('Das gespeicherte Kennwort ist beschädigt.');
		}

		$klartext = sodium_crypto_secretbox_open(
			substr($roh, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
			substr($roh, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
			$this->schluessel,
		);

		if (false === $klartext) {
			throw new \RuntimeException('Das gespeicherte Kennwort lässt sich nicht entschlüsseln. Wurde APP_SECRET geändert? Dann das Kennwort neu eintragen.');
		}

		return $klartext;
	}
}
