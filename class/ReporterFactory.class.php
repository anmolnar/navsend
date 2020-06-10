<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . "/../vendor/autoload.php";

class ReporterFactory {

    private static $apiUrl = "https://api-test.onlineszamla.nav.gov.hu/invoiceService/v2";

    private static $userData = array(
        "login" => "cov5jjp5s66tn5v",
        "password" => "Start123",
        "taxNumber" => "26717366",
        "signKey" => "a9-a8da-0a5b0826222b2YJVI80O7P4J",
        "exchangeKey" => "2efc2YJVI80OBAIZ",
    );

    private static $softwareData = array(
        "softwareId" => "DOLIBARR-NAVSEND-1",
        "softwareName" => "DolibarrNavsend",
        "softwareOperation" => "ONLINE_SERVICE",
        "softwareMainVersion" => "1.0",
        "softwareDevName" => "Molnar Andor",
        "softwareDevContact" => "andor@nu.hu",
        "softwareDevCountryCode" => "HU",
        "softwareDevTaxNumber" => "8413791138",
    );

    public static function getReporter() {
        $config = new NavOnlineInvoice\Config(ReporterFactory::$apiUrl, ReporterFactory::$userData, ReporterFactory::$softwareData);
        $config->setCurlTimeout(70); // 70 másodperces cURL timeout (NAV szerver hívásnál), opcionális
        return new NavOnlineInvoice\Reporter($config);
    }
}
