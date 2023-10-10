<?php

/**
 * Realpad Takeout client class
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023 SpojeNetIT s.r.o.
 */

declare(strict_types=1);

namespace SpojeNet\Realpad;

/**
 * Connect to TakeOut
 *
 * @author vitex
 */
class ApiClient extends \Ease\Molecule
{
    /**
     * RealPad URI
     * @var string
     */
    public $baseEndpoint = 'https://cms.realpad.eu/';

    private $debug;

    private $curl;

    private $timeout;

    /**
     * RealPad Data obtainer
     */
    public function __construct()
    {
        $this->apiUsername = \Ease\Shared::cfg('REALPAD_USERNAME');
        $this->apiPassword = \Ease\Shared::cfg('REALPAD_PASSWORD');
    }

    /**
     * Inicializace CURL
     *
     * @return boolean Online Status
     */
    public function curlInit()
    {
        if ($this->offline === false) {
            $this->curl = \curl_init(); // create curl resource
            \curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true); // return content as a string from curl_exec
            \curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true); // follow redirects
            \curl_setopt($this->curl, CURLOPT_HTTPAUTH, true);       // HTTP authentication
            \curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, true);
            \curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
            \curl_setopt($this->curl, CURLOPT_VERBOSE, ($this->debug === true)); // For debugging
            if (empty($this->authSessionId)) {
                \curl_setopt(
                    $this->curl,
                    CURLOPT_USERPWD,
                    $this->user . ':' . $this->password
                ); // set username and password
            }
            if (!is_null($this->timeout)) {
                \curl_setopt($this->curl, CURLOPT_HTTPHEADER, [
                    'Connection: Keep-Alive',
                    'Keep-Alive: ' . $this->timeout
                ]);
                \curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
            }

            \curl_setopt($this->curl, CURLOPT_USERAGENT, 'RealpadTakeout v' . \Ease\Shared::appVer() . ' https://github.com/Spoje-NET/Realpad-Takeout');
        }
        return !$this->offline;
    }

    /**
     * Vykonej HTTP požadavek
     *
     * @param string $url    URL požadavku
     * @param string $method HTTP Method GET|POST|PUT|OPTIONS|DELETE
     * @param string $format požadovaný formát komunikace
     *
     * @return int HTTP Response CODE
     */
    public function doCurlRequest($url, $method, $format = null)
    {
        if (is_null($format)) {
            $format = $this->format;
        }
        curl_setopt($this->curl, CURLOPT_URL, $url);
// Nastavení samotné operace
        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));
//Vždy nastavíme byť i prázná postdata jako ochranu před chybou 411
        curl_setopt($this->curl, CURLOPT_POSTFIELDS, $this->postFields);
        $httpHeaders = $this->defaultHttpHeaders;
        $formats = Formats::bySuffix();
        if (!isset($httpHeaders['Accept'])) {
            $httpHeaders['Accept'] = $formats[$format]['content-type'];
        }
        if (!isset($httpHeaders['Content-Type'])) {
            $httpHeaders['Content-Type'] = $formats[$format]['content-type'];
        }

        array_walk($httpHeaders, function (&$value, $header) {
            $value = $header . ': ' . $value;
        });
        curl_setopt($this->curl, CURLOPT_HTTPHEADER, $httpHeaders);
// Proveď samotnou operaci
        $this->lastCurlResponse = curl_exec($this->curl);
        $this->curlInfo = curl_getinfo($this->curl);
        $this->curlInfo['when'] = microtime();
        $this->responseFormat = $this->contentTypeToResponseFormat(strval($this->curlInfo['content_type']), $url);
        $this->lastResponseCode = $this->curlInfo['http_code'];
        $this->lastCurlError = curl_error($this->curl);
        if (strlen($this->lastCurlError)) {
            $msg = sprintf('Curl Error (HTTP %d): %s', $this->lastResponseCode, $this->lastCurlError);
            $this->addStatusMessage($msg, 'error');
            if ($this->throwException) {
                throw new Exception($msg, $this);
            }
        }

        if ($this->debug === true) {
            $this->saveDebugFiles();
        }
        return $this->lastResponseCode;
    }

    /**
     * Realpad server disconnect.
     */
    public function disconnect()
    {
        if (is_resource($this->curl)) {
            curl_close($this->curl);
        }
        $this->curl = null;
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->disconnect();
    }
}
