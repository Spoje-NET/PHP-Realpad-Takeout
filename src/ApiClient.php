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
        $this->setObjectName();
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
     * Obtain All resources listing
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
     * Obtain Resource by UID
     *
     * @param string $uid
     *
     * @return string|null
     */
    public function getResource($uid)
    {
        $responseCode = $this->doCurlRequest($this->baseEndpoint . 'resource/' . $uid, 'POST');
        return $responseCode == 200 ? $this->lastCurlResponse : null;
    }

    /**
     * Saver Response
     *
     * @param string $uid      Resource UUID
     * @param string $filename Save
     *
     * @return int size of saved file in bites
     */
    public function saveResource($uid, $filename)
    {
        $resource = $this->getResource($uid);
        return $this->lastResponseCode == 200 ? file_put_contents($filename, $resource) : 0;
    }

    /**
     * Close Curl Handle before serizaliation
     */
    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * Gives you endpoint's excel data as PHP array
     *
     * @param string $endpoint suffix
     *
     * @return array
     */
    public function getExcelData($endpoint)
    {
        $responseCode = $this->doCurlRequest($this->baseEndpoint . 'ws/v10/' . $endpoint, 'POST');
        $excelData = [];
        if ($responseCode == 200) {
            $xls = sys_get_temp_dir() . '/' . $endpoint . '_' . \Ease\Functions::randomString() . '.xls';
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
     * Obtain listing of all Customers
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
     * Obtain listing of all Projects
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
     * Defects can be returned. Available modes are:
     * DEAL_DEFECTS, DEAL_DEFECTS_COMMUNAL_AREA, DEAL_DEFECTS_COMBINED,
     * INSPECTION_DEFECTS, INSPECTION_DEFECTS_COMMUNAL_AREA,
     * INSPECTION_DEFECTS_COMBINED.
     *
     * The last column contains the unique defect ID from the Realpad database.
     * The second column is the relevant deal ID.
     *
     * @todo Implement Modes
     *
     * @return array
     */
    public function listDefects()
    {
        return $this->getExcelData('list-excel-defects');
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
     * Accepts an additional required parameter unitid, which has to be a valid
     * unit Realpad database ID obtained from some other endpoint.
     * The first column contains the timestamp of when the given unit started
     * containing the data on the given row. The second column contains the name
     * of the user who caused that data to be recorded.
     *
     * @return array
     */
    public function listUnitHistory()
    {
        return $this->getExcelData('list-excel-unit-history');
    }

    /**
     * Accepts several additional optional parameters:
     * ● `filter_status` - if left empty, invoices in all statuses are sent. 1 - new invoices. 2 -
     * invoices in Review #1. 3 - invoices in Review #2. 4 - invoices in approval. 5 - fully
     * approved invoices. 6 - fully rejected invoices.
     *
     * ● `filter_groupcompany` - if left empty, invoices from all the group companies are sent. If
     * Realpad database IDs of group companies are provided (as a comma-separated list),
     * then only invoices from these companies are sent.
     *
     * ● `filter_issued_from`` - specify a date in the 2019-12-31 format to only send invoices
     * issues after that date.
     *
     * ● `filter_issued_to` - specify a date in the 2019-12-31 format to only send invoices issues
     * before that date.
     * The initial set of columns describes the Invoice itself, and the last set of columns contains the
     * data of its Lines.
     *
     * @todo Implement Filters
     *
     * @return array
     */
    public function listInvoices()
    {
        return $this->getExcelData('list-excel-invoices');
    }
}
