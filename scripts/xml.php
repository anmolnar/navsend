<?php

require_once __DIR__ . "/../vendor/autoload.php";

$userData = array(
    "login" => "cov5jjp5s66tn5v",
    "password" => "Start123",
    "taxNumber" => "26717366",
    "signKey" => "a9-a8da-0a5b0826222b2YJVI80O7P4J",
    "exchangeKey" => "2efc2YJVI80OBAIZ",
);

$softwareData = array(
    "softwareId" => "DOLIBARR-NAVSEND-1",
    "softwareName" => "DolibarrNavsend",
    "softwareOperation" => "ONLINE_SERVICE",
    "softwareMainVersion" => "1.0",
    "softwareDevName" => "Molnar Andor",
    "softwareDevContact" => "andor@nu.hu",
    "softwareDevCountryCode" => "HU",
    "softwareDevTaxNumber" => "8413791138",
);

$apiUrl = "https://api-test.onlineszamla.nav.gov.hu/invoiceService/v2";

$config = new NavOnlineInvoice\Config($apiUrl, $userData, $softwareData);
$config->setCurlTimeout(70); // 70 másodperces cURL timeout (NAV szerver hívásnál), opcionális

$reporter = new NavOnlineInvoice\Reporter($config);

try {
    $result = $reporter->queryTaxpayer("26717366");

    if ($result) {
        print "Az adószám valid.\n";
        print "Az adószámhoz tartozó név: $result->taxpayerName\n";

        print "További lehetséges információk az adózóról:\n";
        print_r($result->taxpayerShortName);
        print_r($result->taxNumberDetail);
        print_r($result->vatGroupMembership);
        print_r($result->taxpayerAddressList);
    } else {
        print "Az adószám nem valid.";
    }

} catch(Exception $ex) {
    print get_class($ex) . ": " . $ex->getMessage();
}
