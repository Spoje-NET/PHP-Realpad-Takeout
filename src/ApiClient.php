<?php

/**
 * Realpad Takeout client class
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023 SpojeNetIT s.r.o.
 */

declare(strict_types=1);

namespace SpojeNet\Realpad;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Connect to TakeOut
 *
 * @author vitex
 */
class ApiClient extends \Ease\Sand
{
    /**
     * RealPad URI
     * @var string
     */
    public $baseEndpoint = 'https://cms.realpad.eu/';

    /**
     * CURL resource handle
     * @var resource|\CurlHandle|null
     */
    private $curl;

    /**
     * CURL response timeout
     * @var int
     */
    private $timeout = 0;

    /**
     * Last CURL response info
     * @var array
     */
    private $curlInfo = [];

    /**
     * Last CURL response error
     * @var string
     */
    private $lastCurlError;

    /**
     * Throw Exception on error ?
     * @var boolean
     */
    public $throwException = true;

    /**
     * Realpad Username
     * @var string
     */
    private $apiUsername;

    /**
     * Realpad User password
     * @var string
     */
    private $apiPassword;

    /**
     * May be huge response
     * @var string
     */
    private $lastCurlResponse;

    /**
     * HTTP Response code of latst request
     * @var int
     */
    private $lastResponseCode;

    /**
     * RealPad Data obtainer
     */
    public function __construct()
    {
        $this->apiUsername = \Ease\Shared::cfg('REALPAD_USERNAME');
        $this->apiPassword = \Ease\Shared::cfg('REALPAD_PASSWORD');
        $this->curlInit();
    }

    /**
     * Initialize CURL
     *
     * @return mixed|boolean Online Status
     */
    public function curlInit()
    {
        $this->curl = \curl_init(); // create curl resource
        \curl_setopt($this->curl, CURLOPT_RETURNTRANSFER, true); // return content as a string from curl_exec
        \curl_setopt($this->curl, CURLOPT_FOLLOWLOCATION, true); // follow redirects
        \curl_setopt($this->curl, CURLOPT_HTTPAUTH, true); // HTTP authentication
        \curl_setopt($this->curl, CURLOPT_SSL_VERIFYPEER, true);
        \curl_setopt($this->curl, CURLOPT_SSL_VERIFYHOST, false);
        \curl_setopt($this->curl, CURLOPT_VERBOSE, ($this->debug === true)); // For debugging
        if ($this->timeout) {
            \curl_setopt($this->curl, CURLOPT_HTTPHEADER, [
                'Connection: Keep-Alive',
                'Keep-Alive: ' . $this->timeout
            ]);
            \curl_setopt($this->curl, CURLOPT_TIMEOUT, $this->timeout);
        }
        \curl_setopt(
            $this->curl,
            CURLOPT_USERAGENT,
            'RealpadTakeout v' . \Ease\Shared::appVersion() . ' https://github.com/Spoje-NET/Realpad-Takeout'
        );
        \curl_setopt(
            $this->curl,
            CURLOPT_POSTFIELDS,
            'login=' . $this->apiUsername . '&password=' . $this->apiPassword
        );
        return $this->curl;
    }

    /**
     * Execute HTTP request
     *
     * @param string $url    URL of request
     * @param string $method HTTP Method GET|POST|PUT|OPTIONS|DELETE
     *
     * @return int HTTP Response CODE
     */
    public function doCurlRequest($url, $method = 'GET')
    {
        curl_setopt($this->curl, CURLOPT_URL, $url);

        curl_setopt($this->curl, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        $this->lastCurlResponse = curl_exec($this->curl);
        $this->curlInfo = curl_getinfo($this->curl);
        $this->curlInfo['when'] = microtime();
        $this->lastResponseCode = $this->curlInfo['http_code'];
        $this->lastCurlError = curl_error($this->curl);
        if (strlen($this->lastCurlError)) {
            $msg = sprintf('Curl Error (HTTP %d): %s', $this->lastResponseCode, $this->lastCurlError);
            $this->addStatusMessage($msg, 'error');
            if ($this->throwException) {
                throw new Exception($msg, $this);
            }
        }
        return $this->lastResponseCode;
    }

    /**
     * Curl Error getter
     *
     * @return string
     */
    public function getErrors()
    {
        return $this->lastCurlError;
    }

    /**
     *
     * @return
     */
    public function getLastResponseCode()
    {
        return $this->lastResponseCode;
    }

    /**
     * Convert XML to array
     */
    public static function xml2array($xmlObject, $out = [])
    {
        foreach ((array) $xmlObject as $index => $node) {
            $out[$index] = (is_object($node) || is_array($node)) ? self::xml2array($node) : $node;
        }
        return $out;
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
     * @return array
     */
    public function listResources()
    {
        $responseData = [];
        $responseCode = $this->doCurlRequest($this->baseEndpoint . 'ws/v10/list-resources', 'POST');
        if ($responseCode == 200) {
            $responseRaw = self::xml2array(new \SimpleXMLElement($this->lastCurlResponse));
            foreach ($responseRaw['resource'] as $position => $attributes) {
                $responseData[$attributes['@attributes']['uid']] = array_values($attributes)[0];
                $responseData[$attributes['@attributes']['uid']]['position'] = $position;
            }
        }
        return $responseData;
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     *
     * @return array
     */
    public function listCustomers()
    {
        $responseCode = $this->doCurlRequest($this->baseEndpoint . 'ws/v10/list-excel-customers', 'POST');
        $customersData = [];
        if ($responseCode == 200) {
            $xls = sys_get_temp_dir() . '/' . \Ease\Functions::randomString() . '.xls';
            file_put_contents($xls, $this->lastCurlResponse);
            $spreadsheet = IOFactory::load($xls);
            unlink($xls);
            $customersDataRaw = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            unset($customersDataRaw[1]);
            foreach ($customersDataRaw as $recordId => $recordData) {
                $customersData[$recordId] = array_combine($customersDataRaw[1], $recordData);
            }
        }
        return $customersData;
    }
}
