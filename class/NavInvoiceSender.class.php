<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . "/../vendor/autoload.php";
require_once __DIR__ . "/../class/navresult.class.php";

class NavSendException extends Exception {}

class NavInvoiceSender {
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

    private $db;
    private $user;
    private $navResult;
    private $invoice;
    private $reporter;
    private $invoiceXml;
    private $errorMsg;    

    public function __construct($db, $user, $invoice) {
        $this->db = $db;
        $this->user = $user;
        $this->navResult = new NavResult($db);
        $this->invoice = $invoice;
        $config = new NavOnlineInvoice\Config($this->apiUrl, $this->userData, $this->softwareData);
        $config->setCurlTimeout(70); // 70 másodperces cURL timeout (NAV szerver hívásnál), opcionális
        $this->reporter = new NavOnlineInvoice\Reporter($config);
    }

    public function validate() {
        dol_syslog(__METHOD__." Validating invoice ref ".$this->invoice->getInvoice()->ref, LOG_INFO);
        $xml = $this->invoice->getXml();
        $errorMsg = NavOnlineInvoice\Reporter::getInvoiceValidationError($xml);
        if ($errorMsg) {
            $this->errorMsg = $errorMsg;
            return false;
        } else {
            $this->invoiceXml = $xml;
            return true;
        }
    }

    public function send() {
        $facture = $this->invoice->getInvoice();
        dol_syslog(__METHOD__." Sending invoice to NAV ref: ".$facture->ref, LOG_INFO);
        $this->navResult->ref = $facture->ref;
        $this->navResult->result = NavResult::RESULT_GENERATE;
        $this->navResultCreate();
        if (!$this->validate()) {
            $this->navResult->result = NavResult::RESULT_INVALID;
            $this->navResult->message = $this->errorMsg;
            $this->navResultUpdate();
        } else {
            $this->navResult->result = NavResult::RESULT_VALIDATED;
            $this->navResult->message = "";
            $this->navResultUpdate();
        }
        // Az $invoiceXml tartalmazza a számla (szakmai) SimpleXMLElement objektumot
        //$transactionId = $this->reporter->manageInvoice($this->invoice->getXml(), "CREATE");
        //print "Tranzakciós azonosító a státusz lekérdezéshez: " . $transactionId;

        return true;
    }

    private function navResultCreate() {
        $id = $this->navResult->create($this->user);
        if ($id < 0) {
            dol_print_error($this->db, $this->navResult->error);
            throw new NavSendException("Unable to write db");
        }
    }

    private function navResultUpdate() {
        $result = $this->navResult->update($this->user);
        if ($result < 0) {
            dol_print_error($this->db, $this->navResult->error);
            throw new NavSendException("Unable to update db");
        }
    }
}
