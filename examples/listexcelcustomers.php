<?php

require_once __DIR__ . '/../vendor/autoload.php';


\Ease\Shared::init(['REALPAD_USERNAME','REALPAD_PASSWORD'], '../.env' );

$client = new \SpojeNet\Realpad\ApiClient();

$resources = $client->getResources();

print_r($resources);

