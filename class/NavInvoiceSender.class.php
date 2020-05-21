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
    private $builder; /** @var NavInvoiceXmlBuilder $builder */
    private $reporter;
    private $invoiceXml;
    private $errorMsg;

    public function __construct($db, $user, $builder) {
        $this->db = $db;
        $this->user = $user;
        $this->navResult = new NavResult($db);
        $this->builder = $builder;
        $config = new NavOnlineInvoice\Config($this->apiUrl, $this->userData, $this->softwareData);
        $config->setCurlTimeout(70); // 70 másodperces cURL timeout (NAV szerver hívásnál), opcionális
        $this->reporter = new NavOnlineInvoice\Reporter($config);
    }

    public function validate() {
        dol_syslog(__METHOD__." Validating invoice ref ".$this->builder->getInvoice()->ref, LOG_INFO);
        $this->errorMsg = NavOnlineInvoice\Reporter::getInvoiceValidationError($this->invoiceXml);
        if ($this->errorMsg) {
            throw new NavSendException("Validation error: ".$this->errorMsg);
        } else {
            return true;
        }
    }

    public function send() {
        $facture = $this->builder->getInvoice(); /** @var Facture $facture */
        dol_syslog(__METHOD__." Sending invoice to NAV ref: ".$facture->ref, LOG_INFO);

        try {
			// 1. GENERATE
			$id = $this->navResult->fetch(null, $facture->ref);
			print("Id = $id\n");
			if ($id < 0) {
				dol_print_error($this->db, $this->navResult->error);
				throw new Exception("Unable to query db");
			} else if ($id > 0) {
				throw new Exception("Invoice ref $facture->ref has already sent");
			}

			$this->navResult->ref = $facture->ref;
			$this->navResult->result = NavResult::RESULT_GENERATE;
			$this->navResult->errored = 0;
			$this->navResultCreate();

			$this->invoiceXml = $this->builder->build()->getXml();

			// 2. VALIDATE

			$this->navResult->result = NavResult::RESULT_VALIDATE;
			$this->navResult->message = "OK";
			$this->navResult->errored = 0;
			$this->navResult->xml = $this->invoiceXml->asXML();
			$this->navResultUpdate();

			$this->validate();

			// 3. SEND

			$this->navResult->result = NavResult::RESULT_INTRANSIT;
			$this->navResult->message = "OK";
			$this->navResult->errored = 0;
			$this->navResultUpdate();

			// Az $invoiceXml tartalmazza a számla (szakmai) SimpleXMLElement objektumot
			//$transactionId = $this->reporter->manageInvoice($this->invoice->getXml(), "CREATE");
			//print "Tranzakciós azonosító a státusz lekérdezéshez: " . $transactionId;

			$this->navResult->result = NavResult::RESULT_SENTOK;
			$this->navResult->message = "OK";
			$this->navResult->errored = 0;
			$this->navResultUpdate();

			return true;
		} catch (NavSendException $ex) {
			$this->navResult->errored = 1;
			$this->navResult->message = $ex->getMessage();
			$this->navResultUpdate();
			throw $ex;
		}
    }

    private function navResultCreate() {
        $id = $this->navResult->create($this->user);
        if ($id < 0) {
            dol_print_error($this->db, $this->navResult->error);
            throw new Exception("Unable to write db");
        }
        $this->navResult->fetch($id);
    }

    private function navResultUpdate() {
    	$this->navResult->tms = null;
        $result = $this->navResult->update($this->user);
        if ($result < 0) {
            dol_print_error($this->db, $this->navResult->error);
            throw new Exception("Unable to update db");
        }
    }
}
