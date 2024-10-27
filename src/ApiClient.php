<?php

/**
 * Realpad Takeout client class.
 *
 * @author     Vítězslav Dvořák <info@vitexsoftware.cz>
 * @copyright  2023 SpojeNetIT s.r.o.
 */

declare(strict_types=1);

/**
 * This file is part of the RealpadTakeout package
 *
 * https://github.com/Spoje-NET/PHP-Realpad-Takeout
 *
 * (c) Spoje.Net IT s.r.o. <http://spojenenet.cz/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace SpojeNet\Realpad;

use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Connect to TakeOut.
 *
 * @author vitex
 */
class ApiClient extends \Ease\Sand
{
    /**
     * RealPad URI.
     */
    public string $baseEndpoint = 'https://cms.realpad.eu/';

    /**
     * Throw Exception on error ?
     */
    public bool $throwException = true;

    /**
     * @var array Unit status enumeration
     */
    public array $unitStatus = [
        0 => 'free',
        1 => 'pre-reserved',
        2 => 'reserved',
        3 => 'sold',
        4 => 'not for sale',
        5 => 'delayed',
    ];

    /**
     * @var array Unit type enumeration
     */
    public array $unitType = [
        1 => 'flat',
        2 => 'parking',
        3 => 'cellar',
        4 => 'outdoor parking',
        5 => 'garage',
        6 => 'commercial space',
        7 => 'family house',
        8 => 'land',
        9 => 'atelier',
        10 => 'office',
        11 => 'art workshop',
        12 => 'non-residential unit',
        13 => 'motorbike parking',
        14 => 'creative workshop',
        15 => 'townhouse',
        16 => 'utility room',
        17 => 'condominium',
        18 => 'storage',
        19 => 'apartment',
        20 => 'accommodation unit',
        21 => 'bike stand',
        22 => 'communal area',
    ];

    /**
     * CURL resource handle.
     *
     * @var null|\CurlHandle|resource
     */
    private $curl;

    /**
     * CURL response timeout.
     */
    private int $timeout = 0;

    /**
     * Last CURL response info.
     */
    private array $curlInfo = [];

    /**
     * Last CURL response error.
     */
    private string $lastCurlError;

    /**
     * Realpad Username.
     */
    private string $apiUsername;

    /**
     * Realpad User password.
     */
    private string $apiPassword;

    /**
     * May be huge response.
     */
    private string $lastCurlResponse;

    /**
     * HTTP Response code of latst request.
     */
    private int $lastResponseCode;

    /**
     * RealPad Data obtainer.
     *
     * @var string - leave empty to use Environment or constant REALPAD_USERNAME
     * @var string - leave empty to use Environment or constant REALPAD_PASSWORD
     *
     * @param mixed $username
     * @param mixed $password
     */
    public function __construct($username = '', $password = '')
    {
        $this->apiUsername = \strlen($username) ? $username : \Ease\Shared::cfg('REALPAD_USERNAME');
        $this->apiPassword = \strlen($password) ? $password : \Ease\Shared::cfg('REALPAD_PASSWORD');
        $this->curlInit();
        $this->setObjectName();
    }

    /**
     * Close Curl Handle before serizaliation.
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Initialize CURL.
     *
     * @return bool|mixed Online Status
     */
    public function curlInit()
    {
        $this->curl = \curl_init(); // create curl resource
        \curl_setopt($this->curl, \CURLOPT_RETURNTRANSFER, true); // return content as a string from curl_exec
        \curl_setopt($this->curl, \CURLOPT_FOLLOWLOCATION, true); // follow redirects
        \curl_setopt($this->curl, \CURLOPT_HTTPAUTH, true); // HTTP authentication
        \curl_setopt($this->curl, \CURLOPT_SSL_VERIFYPEER, true);
        \curl_setopt($this->curl, \CURLOPT_SSL_VERIFYHOST, false);
        \curl_setopt($this->curl, \CURLOPT_VERBOSE, $this->debug === true); // For debugging

        if ($this->timeout) {
            \curl_setopt($this->curl, \CURLOPT_HTTPHEADER, [
                'Connection: Keep-Alive',
                'Keep-Alive: '.$this->timeout,
            ]);
            \curl_setopt($this->curl, \CURLOPT_TIMEOUT, $this->timeout);
        }

        \curl_setopt(
            $this->curl,
            \CURLOPT_USERAGENT,
            'RealpadTakeout v'.\Ease\Shared::appVersion().' https://github.com/Spoje-NET/Realpad-Takeout',
        );

        return $this->curl;
    }

    /**
     * Execute HTTP request.
     *
     * @param string $url        URL of request
     * @param string $method     HTTP Method GET|POST|PUT|OPTIONS|DELETE
     * @param mixed  $postParams
     *
     * @return int HTTP Response CODE
     */
    public function doCurlRequest($url, $method = 'GET', $postParams = [])
    {
        \curl_setopt(
            $this->curl,
            \CURLOPT_POSTFIELDS,
            array_merge(
                ['login' => $this->apiUsername, 'password' => $this->apiPassword],
                $postParams,
            ),
        );
        curl_setopt($this->curl, \CURLOPT_URL, $url);

        curl_setopt($this->curl, \CURLOPT_CUSTOMREQUEST, strtoupper($method));

        $this->lastCurlResponse = curl_exec($this->curl);
        $this->curlInfo = curl_getinfo($this->curl);
        $this->curlInfo['when'] = microtime();
        $this->lastResponseCode = $this->curlInfo['http_code'];
        $this->lastCurlError = curl_error($this->curl);

        if (\strlen($this->lastCurlError)) {
            $msg = sprintf('Curl Error (HTTP %d): %s', $this->lastResponseCode, $this->lastCurlError);
            $this->addStatusMessage($msg, 'error');

            if ($this->throwException) {
                throw new Exception($msg, $this);
            }
        }

        return $this->lastResponseCode;
    }

    /**
     * Curl Error getter.
     *
     * @return string
     */
    public function getErrors()
    {
        return $this->lastCurlError;
    }

    public function getLastResponseCode()
    {
        return $this->lastResponseCode;
    }

    /**
     * Convert XML to array.
     *
     * @param mixed $xmlObject
     * @param mixed $out
     */
    public static function xml2array($xmlObject, $out = [])
    {
        foreach ((array) $xmlObject as $index => $node) {
            $out[$index] = (\is_object($node) || \is_array($node)) ? self::xml2array($node) : $node;
        }

        return $out;
    }

    /**
     * Realpad server disconnect.
     */
    public function disconnect(): void
    {
        if (\is_resource($this->curl)) {
            curl_close($this->curl);
        }

        $this->curl = null;
    }

    /**
     * Obtain All resources listing.
     *
     * @return array
     */
    public function listResources()
    {
        $responseData = [];
        $responseCode = $this->doCurlRequest($this->baseEndpoint.'ws/v10/list-resources', 'POST');

        if ($responseCode === 200) {
            $responseRaw = self::xml2array(new \SimpleXMLElement($this->lastCurlResponse));

            foreach ($responseRaw['resource'] as $position => $attributes) {
                $responseData[$attributes['@attributes']['uid']] = array_values($attributes)[0];
                $responseData[$attributes['@attributes']['uid']]['position'] = $position;
            }
        }

        return $responseData;
    }

    /**
     * Obtain Resource by UID.
     *
     * @param string $uid
     *
     * @return null|string
     */
    public function getResource($uid)
    {
        $responseCode = $this->doCurlRequest($this->baseEndpoint.'resource/'.$uid, 'POST');

        return $responseCode === 200 ? $this->lastCurlResponse : null;
    }

    /**
     * Saver Response.
     *
     * @param string $uid      Resource UUID
     * @param string $filename Save
     *
     * @return int size of saved file in bites
     */
    public function saveResource($uid, $filename)
    {
        $resource = $this->getResource($uid);

        return $this->lastResponseCode === 200 ? file_put_contents($filename, $resource) : 0;
    }

    /**
     * Gives you endpoint's excel data as PHP array.
     *
     * @param string $endpoint suffix
     * @param mixed  $params
     *
     * @return array
     */
    public function getExcelData($endpoint, $params = [])
    {
        $responseCode = $this->doCurlRequest($this->baseEndpoint.'ws/v10/'.$endpoint, 'POST', $params);
        $excelData = [];

        if ($responseCode === 200) {
            $xls = sys_get_temp_dir().'/'.$endpoint.'_'.\Ease\Functions::randomString().'.xls';
            file_put_contents($xls, $this->lastCurlResponse);
            $spreadsheet = IOFactory::load($xls);
            unlink($xls);
            $customersDataRaw = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
            $columns = $customersDataRaw[1];
            unset($customersDataRaw[1]);

            foreach ($customersDataRaw as $recordId => $recordData) {
                $excelData[$recordId] = array_combine($columns, $recordData);
            }
        }

        return $excelData;
    }

    /**
     * Obtain listing of all Customers.
     *
     * @return array
     */
    public function listCustomers()
    {
        return $this->getExcelData('list-excel-customers');
    }

    /**
     * The last columns contain the unique unit ID, numeric ID of the unit type,
     * numeric ID of the unit availability, unique project ID and deal ID from
     * the Realpad database. See the appendix for the unit type and availability
     * enums.
     *
     * @return array
     */
    public function listProducts()
    {
        return $this->getExcelData('list-excel-products');
    }

    /**
     * The last column contains the unique Deal ID from the Realpad database.
     *
     * @return array
     */
    public function listBusinessCases()
    {
        return $this->getExcelData('list-excel-business-cases');
    }

    /**
     * Obtain listing of all Projects.
     *
     * @return array
     */
    public function listProjects()
    {
        return $this->getExcelData('list-excel-projects');
    }

    /**
     * The last three columns contain the unique document ID, customer ID,
     * and sales agent ID from the Realpad database. The first column is the
     * relevant deal ID.
     *
     * @return array
     */
    public function listDealDocuments()
    {
        return $this->getExcelData('list-excel-deal-documents');
    }

    /**
     * The last column contains the unique payment ID from the Realpad database.
     * The second column is the relevant deal ID.
     *
     * @return array
     */
    public function listPaymentsPrescribed()
    {
        return $this->getExcelData('list-excel-payments-prescribed');
    }

    /**
     * The first column contains the unique incoming payment ID from the Realpad
     * database. The second column is the relevant Deal ID.
     *
     * @return array
     */
    public function listPaymentsIncoming()
    {
        return $this->getExcelData('list-excel-payments-incoming');
    }

    /**
     * The last columns contain the additional product ID, its type ID, and the
     * ID of the associated prescribed payment from the Realpad database.
     * The first column is the relevant deal ID.
     *
     * @return array
     */
    public function listAdditionalProducts()
    {
        return $this->getExcelData('list-excel-additional-products');
    }

    /**
     * Among the columns, there are those representing the deal ID and
     * inspection ID from the Realpad database.
     *
     * @return array
     */
    public function listInspections()
    {
        return $this->getExcelData('list-excel-inspections');
    }

    /**
     * Accepts an additional optional parameter mode. By default all the Deal
     * Warranty Claim Defects are returned. Certain developers will also see the
     * Communal Areas Defects here by default. If mode is specified, other
     * Defects can be returned.
     *
     * The last column contains the unique defect ID from the Realpad database.
     * The second column is the relevant deal ID.
     *
     * @todo Implement Modes
     *
     * @var string mode none or one from: DEAL_DEFECTS,
     *             DEAL_DEFECTS_COMMUNAL_AREA,
     *             DEAL_DEFECTS_COMBINED,
     *             INSPECTION_DEFECTS,
     *             INSPECTION_DEFECTS_COMMUNAL_AREA,
     *             INSPECTION_DEFECTS_COMBINED
     *
     * @param mixed $mode
     *
     * @return array
     */
    public function listDefects($mode = '')
    {
        $modesAvailble = [
            'DEAL_DEFECTS',
            'DEAL_DEFECTS_COMMUNAL_AREA',
            'DEAL_DEFECTS_COMBINED',
            'INSPECTION_DEFECTS',
            'INSPECTION_DEFECTS_COMMUNAL_AREA',
            'INSPECTION_DEFECTS_COMBINED',
        ];

        if (\strlen($mode) && (array_search($mode, $modesAvailble, true) === false)) {
            throw new \SpojeNet\Realpad\Exception('Iillegal inspection Mode '.$mode);
        }

        return $this->getExcelData('list-excel-defects', ['mode' => $mode]);
    }

    /**
     * The last columns contain the task ID, customer ID, and sales agent ID
     * from the Realpad database.
     *
     * @return array
     */
    public function listTasks()
    {
        return $this->getExcelData('list-excel-tasks');
    }

    /**
     * The last columns contain the event ID, customer ID, unit, and project ID
     * from the Realpad database.
     *
     * @return array
     */
    public function listEvents()
    {
        return $this->getExcelData('list-excel-events');
    }

    /**
     * The last column contains the unit ID from the Realpad database.
     *
     * @return array
     */
    public function listSalesStatus()
    {
        return $this->getExcelData('list-excel-sales-status');
    }

    /**
     * The first column contains the timestamp of when the given unit started
     * containing the data on the given row. The second column contains the name
     * of the user who caused that data to be recorded.
     *
     * @var int required parameter unitid, which has to be a valid unit
     *          Realpad database ID obtained from some other endpoint
     *
     * @return array
     */
    public function listUnitHistory(int $unitID)
    {
        return $this->getExcelData('list-excel-unit-history', ['unitid' => $unitID]);
    }

    /**
     * Listing of Invoices. The initial set of columns describes the Invoice
     * itself, and the last set of columns contains the data of its Lines.
     *
     * @var array ● `filter_status` - if left empty, invoices in all statuses are sent. 1 - new invoices. 2 -
     *            invoices in Review #1. 3 - invoices in Review #2. 4 - invoices in approval. 5 - fully
     *            approved invoices. 6 - fully rejected invoices.
     *
     *                      ● `filter_groupcompany` - if left empty, invoices from all the group companies are sent. If
     *                                          Realpad database IDs of group companies are provided (as a comma-separated list),
     *                                          then only invoices from these companies are sent.
     *
     *                      ● `filter_issued_from` - specify a date in the 2019-12-31 format to only send invoices
     *                                          issues after that date.
     *
     *                      ● `filter_issued_to` - specify a date in the 2019-12-31 format to only send invoices issues before that date.
     *
     * @param mixed $options
     *
     * @return array
     */
    public function listInvoices($options = [])
    {
        $colsAvailble = [
            'filter_status',
            'filter_groupcompany',
            'filter_issued_from',
            'filter_issued_to',
        ];

        foreach ($options as $key => $value) {
            if (array_search($key, $colsAvailble, true) === false) {
                throw new \SpojeNet\Realpad\Exception('Iillegal Invoice option '.$key);
            }
        }

        return $this->getExcelData('list-excel-invoices', $options);
    }
}
