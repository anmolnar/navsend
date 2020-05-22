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
    private $builder; /** @var NavInvoiceXmlBuilder $builder */
    private $reporter;
    private $invoiceXml;

    public function __construct($db, $user, $builder) {
        $this->db = $db;
        $this->user = $user;
        $this->builder = $builder;
        $config = new NavOnlineInvoice\Config($this->apiUrl, $this->userData, $this->softwareData);
        $config->setCurlTimeout(70); // 70 másodperces cURL timeout (NAV szerver hívásnál), opcionális
        $this->reporter = new NavOnlineInvoice\Reporter($config);
    }

    public function send() {
		$ref = $this->builder->getRef();
        dol_syslog(__METHOD__." Sending invoice ref: ".$ref, LOG_INFO);

        try {
			// 1. BUILD

			$this->invoiceXml = $this->builder->build()->getXml();

			// 2. SEND

			// Az $invoiceXml tartalmazza a számla (szakmai) SimpleXMLElement objektumot
			$transactionId = $this->reporter->manageInvoice($this->invoiceXml, "CREATE");

			// 3. PERSIST

			$this->resultCreateOrUpdate($ref, NavResult::RESULT_SENTOK, "OK", "", $transactionId);

			dol_syslog("Invoice ref $ref has been successfully sent. Transaction ID = $transactionId", LOG_INFO);
			return true;
		} catch (NavOnlineInvoice\XsdValidationError $ex) {
        	dol_syslog(__METHOD__." ".$ex->getMessage(), LOG_ERR);
			$this->resultCreateOrUpdate($ref, NavResult::RESULT_XSDERROR, $ex->getMessage(), "", "");
		} catch (NavOnlineInvoice\CurlError | NavOnlineInvoice\HttpResponseError $ex) {
			dol_syslog(__METHOD__ . " " . $ex->getMessage(), LOG_ERR);
			$this->resultCreateOrUpdate($ref, NavResult::RESULT_NETERROR, $ex->getMessage(), "", "");
		} catch (NavOnlineInvoice\GeneralErrorResponse | NavOnlineInvoice\GeneralExceptionResponse $ex) {
			dol_syslog(__METHOD__ . " " . $ex->getMessage(), LOG_ERR);
			$this->resultCreateOrUpdate($ref, NavResult::RESULT_NAVERROR, $ex->getMessage(), $ex->getErrorCode(), "");
		} catch (Exception $ex) {
			dol_syslog(__METHOD__." ".$ex->getMessage(), LOG_ERR);
        	$this->resultCreateOrUpdate($ref, NavResult::RESULT_ERROR, $ex->getMessage(), "", "");
		} finally {
        	$this->db->commit();
		}
    }

    private function resultCreateOrUpdate($ref, $result, $msg, $errored, $tid) {
		$nav = new NavResult($this->db);
		$needCreate = false;
		$id = $nav->fetch(null, $ref);
		if ($id < 0) {
			dol_print_error($this->db, $nav->error);
			throw new Exception("Unable to query db");
		} else if ($id == 0) {
			$needCreate = true;
		}

		$nav->tms = dol_now();
		$nav->ref = $ref;
    	$nav->result = $result;
    	$nav->message = $msg;
    	$nav->error_code = $errored;
    	$nav->xml = $this->invoiceXml->asXML();
    	$nav->transaction_id = $tid;

    	if ($needCreate) {
    		$result = $nav->create($this->user);
		} else {
    		$result = $nav->update($this->user);
		}
		if ($result < 0) {
			dol_print_error($this->db, $nav->error);
			throw new Exception("Unable to write db");
		}
	}
}
