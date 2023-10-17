# Realpad Takeout API Client for PHP

*for real estate developer IT / reporting departments, integrators, and other 3rd parties*

This document describes how to use the Realpad Takeout API to back up the data stored in the
system. Both structured data (such as lists of customers, deals, etc) and files uploaded to the
CRM (unit plans, contract scans, ...) can be automatically retrieved this way. You are
encouraged to implement an automatic backup system that will download the data from our
server at any frequency you prefer, and use the data as a source for a reporting solution, or any
other purpose.

## Request

First of all please contact <support@realpadsoftware.com> to obtain the credentials to use the
Takeout API endpoints. You will perform POST requests over HTTPS and then store the
resulting data. Most of the Takeout endpoints have just 2 parameters, both required: `login`` and
`password`.

To configure API client please define this Environment variables first:

```env
REALPAD_USERNAME=realpad
REALPAD_PASSWORD=realpad
```

(Configuration mechanism can also use The PHP constants)

Example call in cURL:

```php
<?php
\Ease\Shared::init(['REALPAD_USERNAME','REALPAD_PASSWORD'], '../.env');

$client = new \SpojeNet\Realpad\ApiClient();
$responseCode = $client->doCurlRequest($client->baseEndpoint . 'ws/v10/list-resources', 'POST');
$dataObtained = $client->lastCurlResponse;
```

### Response

#### Endpoints with XML payload

**list-resources**

```php
<?php
$client = new \SpojeNet\Realpad\ApiClient();
$resources = $client->listResources();
```

Example of response:

<pre>
Array
(
    [b19ddfdd-70f1-44cc-8457-190977152325] => Array
        (
            [uid] => b19ddfdd-70f1-44cc-8457-190121223233
            [content-type] => application/pdf
            [file-name] => SomeFile.pdf
            [size] => 1679680
            [checksum] => a78bbad8279871223313232b40d1d32e92060be06ea16bf4eeece8c503b6369b
            [position] => 0
        )

    [5e09db8c-b97e-45a3-8872-abe623232d56] => Array
        (
            [uid] => 5e09db8c-b97e-45a3-8872-abe60a232123
            [content-type] => application/xml
            [file-name] => Bankovni_doklady.xml
            [size] => 566580
            [checksum] => 333553a21c200020a20eb3213232132132132114903eb9aa8e4a1ca7030373e8
            [position] => 1
        )
...
</pre>


● **uid** is the unique identifier of this resource, by which it can be retrieved using
get-projects.

● **content-type** is the MIME type of the file, resolved when uploaded to the system (it’s
the best guess).

● **file-name** is the original file name when it was uploaded to the system.

● **size** is the file size in bytes.

● **crc** is the CRC32 checksum of the file.

This endpoint will always return all the resources. It’s up to your system to determine which
ones you haven’t downloaded yet. You may rely on UID as the unique identifier to distinguish
between the files. You can fetch resources using HTTP GET by retrieving a URL in the following
form: `<https://cms.realpad.eu/resource/><UID>`
Example call in cURL:

```shell
curl \
--output cached_resource \
https://cms.realpad.eu/resource/bd5563ae-abc...
```

## Endpoints

All of these endpoints return a single Excel file with a .xls extension, containing all the relevant
data stored in our system. These endpoints behave just like get-resource, in that the HTTP
headers contain a reasonable file name (e.g. when running from a web browser).
If an extra parameter xlsx is sent with a non-empty value, Takeout API will instead provide the
data in the Excel newer .xlsx format.

**list-excel-customers**
The last column contains the unique customer ID from the Realpad database.

<pre>
Array
(
    [2] => Array
        (
            [Projekt] => Nove Město 3 - C
            [Datum přidání] => 6/20/2017
            [Stav] => Postaveno
            [E-mail] => zakaznik@server.eu
            [Jméno] => Ukazkovy Zakaznik
            [Tagy] => 
            [Zákazník ID] => 4268453
            [Prodejce ID] => 914756
            [Stav ID] => 1
            [Zdroj ID] => 12
        )
...
</pre>

**list-excel-products**
The last columns contain the unique unit ID, numeric ID of the unit type, numeric ID of the unit
availability, unique project ID and deal ID from the Realpad database. See the appendix for the
unit type and availability enums.

**list-excel-business-cases**
The last column contains the unique Deal ID from the Realpad database.

**list-excel-projects**
The last column contains the unique project ID from the Realpad database.

**list-excel-deal-documents**
The last three columns contain the unique document ID, customer ID, and sales agent ID from
the Realpad database. The first column is the relevant deal ID.

**list-excel-payments-prescribed**
The last column contains the unique payment ID from the Realpad database. The second
column is the relevant deal ID.

**list-excel-payments-incoming**
The first column contains the unique incoming payment ID from the Realpad database. The
second column is the relevant Deal ID.

**list-excel-additional-products**
The last columns contain the additional product ID, its type ID, and the ID of the associated
prescribed payment from the Realpad database. The first column is the relevant deal ID.

**list-excel-inspections**
Among the columns, there are those representing the deal ID and inspection ID from the
Realpad database.

**list-excel-defects**
Accepts an additional optional parameter mode. By default all the Deal Warranty Claim Defects
are returned. Certain developers will also see the Communal Areas Defects here by default. If
mode is specified, other Defects can be returned. Available modes are: DEAL_DEFECTS,
DEAL_DEFECTS_COMMUNAL_AREA, DEAL_DEFECTS_COMBINED, INSPECTION_DEFECTS,
INSPECTION_DEFECTS_COMMUNAL_AREA, INSPECTION_DEFECTS_COMBINED.
The last column contains the unique defect ID from the Realpad database. The second column
is the relevant deal ID.

```php
$client = new \SpojeNet\Realpad\ApiClient();
$defects = $client->listDefects('DEAL_DEFECTS');
print_r($defects);
```

<pre>
Array
(
    [2] => Array
        (
            [Projekt] => Nove Sidliste
            [Obchodní případ] => 12323234
            [Typ kontroly] => Technická přejímka
            [Typ položky technické přejímky] => Podlahy
            [Jednotka] => TEST TECHNICKÁ PŘEJÍMKA
            [Zákazník] => REALPAD TEST
            [Telefon] => 
            [E-mail] => realpad@test.eu
            [Číslo vady] => 25456542
            [Problémová vada] => Ne
            [Číslo vady dle zákazníka] => 
            [Popis] => prasklá dlažba
            [Lokace (např. místnost)] => 
            [Poslední vyjádření developera] => 
            [Odesláno zákazníkovi] => 
            [Poslední vyjádření dodavatele] => 
            [Přijato dne] => 7/7/2023
            [Termín pro odstranění vady] => 8/6/2023
            [Plánovaný termín opravy] => 
            [Odstraněna dne] => 
            [Poznámka] => 
            [Odpovědná osoba] => 
            [Speciální záruční lhůta] => Ne
            [Stav] => Přijato do evidence
            [Část bytu které sa vada týká] => 
            [Běžný problém] => 
            [Místnost, které se vada týká] => Koupelna/WC
            [Dodavatel] => 
            [Generální dodavatel] => 
            [Reklamace ID] => 25654654
        )

</pre>

**list-excel-tasks**
The last columns contain the task ID, customer ID, and sales agent ID from the Realpad
database.

**list-excel-events**
The last columns contain the event ID, customer ID, unit, and project ID from the Realpad
database.

**list-excel-sales-status**
The last column contains the unit ID from the Realpad database.

**list-excel-unit-history**
Accepts an additional required parameter unitid, which has to be a valid unit Realpad database
ID obtained from some other endpoint.
The first column contains the timestamp of when the given unit started containing the data on
the given row. The second column contains the name of the user who caused that data to be
recorded.

**list-excel-invoices**
Accepts several additional optional parameters:
● `filter_status` - if left empty, invoices in all statuses are sent. 1 - new invoices. 2 -
invoices in Review #1. 3 - invoices in Review #2. 4 - invoices in approval. 5 - fully
approved invoices. 6 - fully rejected invoices.

● `filter_groupcompany` - if left empty, invoices from all the group companies are sent. If
Realpad database IDs of group companies are provided (as a comma-separated list),
then only invoices from these companies are sent.

● `filter_issued_from`` - specify a date in the 2019-12-31 format to only send invoices
issues after that date.

● `filter_issued_to` - specify a date in the 2019-12-31 format to only send invoices issues
before that date.
The initial set of columns describes the Invoice itself, and the last set of columns contains the
data of its Lines.

## Appendix

Unit status enumeration

● 0 - free.

● 1 - pre-reserved.

● 2 - reserved.

● 3 - sold.

● 4 - not for sale.

● 5 - delayed.

Unit type enumeration

● 1 - flat.

● 2 - parking.

● 3 - cellar.

● 4 - outdoor parking.

● 5 - garage.

● 6 - commercial space.

● 7 - family house.

● 8 - land.

● 9 - atelier.

● 10 - office.

● 11 - art workshop.

● 12 - non-residential unit.

● 13 - motorbike parking.

● 14 - creative workshop.

● 15 - townhouse.

● 16 - utility room.

● 17 - condominium.

● 18 - storage.

● 19 - apartment.

● 20 - accommodation unit.

● 21 - bike stand.

● 22 - communal area.
