<?php

declare(strict_types=1);

/*
 * All-Inkl-Bundle: das Webhosting von All-Inkl über die KAS-API im
 * Contao-Backend verwalten — lauffähig unter Contao 4.13 und Contao 5.7.
 *
 * @license LGPL-3.0-or-later
 */

namespace Schachbulle\ContaoAllinklBundle\Kas;

/**
 * Baut SOAP-Anfragen an die KAS und liest ihre Antworten.
 *
 * Die KAS-API ist SOAP im Stil rpc/encoded, nimmt aber nur einen einzigen
 * Parameter „Params“ entgegen: einen JSON-Text mit Anmeldung, Funktion und
 * deren Parametern. Die Antwort ist SOAP-kodiert im Format von PHPs
 * SoapServer (Apache-„Map“ für assoziative Felder, SOAP-ENC:Array für
 * Listen).
 *
 * Bewusst ohne PHPs SOAP-Erweiterung: Sie ist bei vielen Hostern und auch
 * in der XAMPP-Vorgabe nicht aktiviert. Der Aufbau der Anfrage folgt dem
 * erprobten Weg des ACME-Clients lego (go-acme), die Antworten gleichen den
 * dort aufgezeichneten echten KAS-Antworten.
 */
class SoapKodierer
{
	/** Namensraum des SOAP-Umschlags */
	private const SOAP_NS = 'http://schemas.xmlsoap.org/soap/envelope/';

	/** Namensraum der Typangaben (xsi:type, xsi:nil) */
	private const XSI_NS = 'http://www.w3.org/2001/XMLSchema-instance';

	/**
	 * Baut den SOAP-Umschlag für einen Aufruf.
	 *
	 * Der JSON-Text wird für XML maskiert, damit „&“ oder „<“ in Kennwörtern
	 * und Texten das XML nicht zerbrechen (ein bekannter Fehler anderer
	 * KAS-Clients). Leere Parameterlisten gehen als JSON-Objekt „{}“ hinaus,
	 * nicht als leere Liste „[]“.
	 *
	 * @param string               $methode „KasApi“ oder „KasAuth“
	 * @param array<string, mixed> $params  Die Angaben für „Params“
	 *
	 * @return string Der vollständige SOAP-Umschlag
	 *
	 * @throws \JsonException Wenn sich die Parameter nicht als JSON darstellen
	 *                        lassen (etwa ungültiges UTF-8)
	 */
	public function anfrage(string $methode, array $params): string
	{
		if (isset($params['KasRequestParams']) && array() === $params['KasRequestParams']) {
			$params['KasRequestParams'] = new \stdClass();
		}

		$json = json_encode($params, JSON_THROW_ON_ERROR);

		return '<?xml version="1.0" encoding="UTF-8"?>'
			.'<Envelope xmlns="'.self::SOAP_NS.'"><Body>'
			.'<'.$methode.' xmlns="https://kasserver.com/">'
			.'<Params>'.htmlspecialchars($json, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Params>'
			.'</'.$methode.'>'
			.'</Body></Envelope>';
	}

	/**
	 * Liest eine SOAP-Antwort und liefert ihren Rückgabewert.
	 *
	 * @param string $xml    Der Antworttext der KAS, egal mit welchem
	 *                       HTTP-Status er kam (Faults kommen mit 500)
	 * @param string $aktion Die aufgerufene Funktion, nur für Fehlermeldungen
	 *
	 * @return mixed Bei KasAuth der Sitzungsschlüssel als Text, bei KasApi
	 *               ein Feld mit „Request“ und „Response“
	 *
	 * @throws KasFehler Mit dem Kürzel aus dem SOAP-Fault, oder
	 *                   „ungueltige_antwort“, wenn der Text kein SOAP ist
	 */
	public function antwort(string $xml, string $aktion = ''): mixed
	{
		$dokument = new \DOMDocument();
		$geladen = '' !== trim($xml) && @$dokument->loadXML($xml, LIBXML_NONET | LIBXML_NOCDATA);

		if (!$geladen) {
			throw new KasFehler('ungueltige_antwort', mb_substr(trim(strip_tags($xml)), 0, 200), $aktion);
		}

		$fault = $dokument->getElementsByTagNameNS(self::SOAP_NS, 'Fault')->item(0);

		if ($fault instanceof \DOMElement) {
			throw new KasFehler(
				trim($this->kindText($fault, 'faultstring')),
				trim($this->kindText($fault, 'detail')),
				$aktion,
			);
		}

		$body = $dokument->getElementsByTagNameNS(self::SOAP_NS, 'Body')->item(0);
		$antwort = $body instanceof \DOMElement ? $this->ersteKinder($body)[0] ?? null : null;
		$rueckgabe = $antwort instanceof \DOMElement ? $this->ersteKinder($antwort)[0] ?? null : null;

		if (!$rueckgabe instanceof \DOMElement) {
			throw new KasFehler('ungueltige_antwort', 'kein Rückgabewert', $aktion);
		}

		return $this->wert($rueckgabe);
	}

	/**
	 * Wandelt ein SOAP-kodiertes Element in einen PHP-Wert.
	 *
	 * Reihenfolge der Prüfungen: xsi:nil, dann Map (Kinder „item“ mit
	 * „key“/„value“), dann Liste (SOAP-ENC:Array oder lauter „item“-Kinder
	 * ohne „key“), zuletzt der skalare Typ aus xsi:type. Unbekannte Typen
	 * bleiben Text.
	 *
	 * @param \DOMElement $element Ein „return“-, „value“- oder „item“-Element
	 *
	 * @return mixed Text, Zahl, Wahrheitswert, null oder ein Feld
	 */
	private function wert(\DOMElement $element): mixed
	{
		if ('true' === $element->getAttributeNS(self::XSI_NS, 'nil')) {
			return null;
		}

		$typ = strtolower($element->getAttributeNS(self::XSI_NS, 'type'));
		$kurztyp = str_contains($typ, ':') ? substr($typ, strpos($typ, ':') + 1) : $typ;
		$kinder = $this->ersteKinder($element);

		if ('map' === $kurztyp || $this->istMap($kinder)) {
			$feld = array();

			foreach ($kinder as $eintrag) {
				$schluessel = null;
				$inhalt = null;

				foreach ($this->ersteKinder($eintrag) as $teil) {
					if ('key' === $teil->localName) {
						$schluessel = $teil->textContent;
					} elseif ('value' === $teil->localName) {
						$inhalt = $this->wert($teil);
					}
				}

				if (null !== $schluessel) {
					$feld[$schluessel] = $inhalt;
				}
			}

			return $feld;
		}

		if ('array' === $kurztyp || array() !== $kinder) {
			return array_map(fn (\DOMElement $kind): mixed => $this->wert($kind), $kinder);
		}

		$text = $element->textContent;

		return match ($kurztyp) {
			'int', 'integer', 'long', 'short' => (int) $text,
			'float', 'double', 'decimal' => (float) $text,
			'boolean' => 'true' === strtolower($text) || '1' === $text,
			default => $text,
		};
	}

	/**
	 * Sagt, ob die Kindelemente eine Map bilden.
	 *
	 * @param array<int, \DOMElement> $kinder Die Kindelemente
	 *
	 * @return bool true, wenn es Kinder gibt und jedes ein „item“ mit einem
	 *              „key“ darunter ist
	 */
	private function istMap(array $kinder): bool
	{
		if (array() === $kinder) {
			return false;
		}

		foreach ($kinder as $kind) {
			$hatSchluessel = false;

			foreach ($this->ersteKinder($kind) as $teil) {
				$hatSchluessel = $hatSchluessel || 'key' === $teil->localName;
			}

			if ('item' !== $kind->localName || !$hatSchluessel) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Liefert die direkten Kindelemente ohne Text- und Kommentarknoten.
	 *
	 * @param \DOMElement $element Das Elternelement
	 *
	 * @return array<int, \DOMElement> Die Elemente in Dokumentreihenfolge
	 */
	private function ersteKinder(\DOMElement $element): array
	{
		$kinder = array();

		foreach ($element->childNodes as $knoten) {
			if ($knoten instanceof \DOMElement) {
				$kinder[] = $knoten;
			}
		}

		return $kinder;
	}

	/**
	 * Liefert den Text eines benannten Kindelements.
	 *
	 * @param \DOMElement $element Das Elternelement
	 * @param string      $name    Der lokale Name des Kindes
	 *
	 * @return string Der Text, leer wenn es kein solches Kind gibt
	 */
	private function kindText(\DOMElement $element, string $name): string
	{
		foreach ($this->ersteKinder($element) as $kind) {
			if ($name === $kind->localName) {
				return $kind->textContent;
			}
		}

		return '';
	}
}
