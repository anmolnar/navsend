<?php

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . '/navresult.class.php';

abstract class NavBase {
    private $apiUrl = "https://api-test.onlineszamla.nav.gov.hu/invoiceService/v2";

    private $userData = array(
        "login" => "cov5jjp5s66tn5v",
        "password" => "Start123",
        "taxNumber" => "26717366",
        "signKey" => "a9-a8da-0a5b0826222b2YJVI80O7P4J",
        "exchangeKey" => "2efc2YJVI80OBAIZ",
    );

    private $softwareData = array(
        "softwareId" => "DOLIBARR-NAVSEND-1",
        "softwareName" => "DolibarrNavsend",
        "softwareOperation" => "ONLINE_SERVICE",
        "softwareMainVersion" => "1.0",
        "softwareDevName" => "Molnar Andor",
        "softwareDevContact" => "andor@nu.hu",
        "softwareDevCountryCode" => "HU",
        "softwareDevTaxNumber" => "8413791138",
    );

    protected $db;
    protected $user;
    protected $ref;
    protected $reporter; /** @var NavReporter $reporter */

	const MODUSZ_CREATE = "CREATE";
	const MODUSZ_ANNULMENT = "ANNULMENT";
	const MODUSZ_MODIFY = "MODIFY";
	const MODUSZ_STORNO = "STORNO";

	public function __construct($db, $user, $ref) {
        $this->db = $db;
        $this->user = $user;
        $this->ref = $ref;
        $config = new NavOnlineInvoice\Config($this->apiUrl, $this->userData, $this->softwareData);
        $config->setCurlTimeout(70); // 70 másodperces cURL timeout (NAV szerver hívásnál), opcionális
        $this->reporter = new NavOnlineInvoice\Reporter($config);
    }

    public abstract function report(SimpleXMLElement $xml);

    public abstract function getModusz();

    public function getDb() {
    	return $this->db;
	}

	public function getUser() {
    	return $this->user;
	}

	public function getRef() {
    	return $this->ref;
	}
}
