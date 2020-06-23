<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/exception/NavSendException.class.php";

class ReporterFactory {

    private $apiUrl;

    private $userData;

    private $softwareData = array(
        "softwareId" => "DOLIBARR-NAVSEND-1",
        "softwareName" => "DolibarrNavsend",
        "softwareOperation" => "ONLINE_SERVICE",
        "softwareMainVersion" => "1.0",
        "softwareDevName" => "Molnar Andor",
        "softwareDevContact" => "andor@apache.org",
        "softwareDevCountryCode" => "HU",
        "softwareDevTaxNumber" => "8413791138",
    );

    public function __construct() {
        global $conf;
        $this->apiUrl = $conf->global->NAV_API_URL;
        $this->userData = array(
            "login" => $conf->global->NAV_LOGIN,
            "password" => $conf->global->NAV_PASSWORD,
            "taxNumber" => $conf->global->NAV_TAXNUMBER,
            "signKey" => $conf->global->NAV_SIGNKEY,
            "exchangeKey" => $conf->global->NAV_EXCHANGEKEY,
        );
    }

    public static function getReporter() {
        $rf = new ReporterFactory();
        if (empty($rf->apiUrl)) {
            throw new NavSendException("Reporter hasn't been configured yet. Please configure navsend module first.");
        }
        $config = new NavOnlineInvoice\Config($rf->apiUrl, $rf->userData, $rf->softwareData);
        $config->setCurlTimeout(70); // 70 másodperces cURL timeout (NAV szerver hívásnál), opcionális
        return new NavOnlineInvoice\Reporter($config);
    }
}
