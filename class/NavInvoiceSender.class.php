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
    private $reporter;
    private $invoiceXml;

    public function __construct($db, $user) {
        $this->db = $db;
        $this->user = $user;
        $config = new NavOnlineInvoice\Config($this->apiUrl, $this->userData, $this->softwareData);
        $config->setCurlTimeout(70); // 70 másodperces cURL timeout (NAV szerver hívásnál), opcionális
        $this->reporter = new NavOnlineInvoice\Reporter($config);
    }

    public function send($builder) {
        $ref = $builder->getRef();
        $modusz = $builder->createOrModify();
        dol_syslog(__METHOD__." Sending invoice ref $ref modusz $modusz", LOG_INFO);

        try {
			// 1. BUILD

			$this->invoiceXml = $builder->build()->getXml();

			// 2. SEND

			// Az $invoiceXml tartalmazza a számla (szakmai) SimpleXMLElement objektumot
			$transactionId = $this->reporter->manageInvoice($this->invoiceXml, $modusz);

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

    public function queryNavStatus() {
		$nav = new NavResult($this->db);
		$result = $nav->fetchAll('','', 100,0, array("result" => 4));

		if (!is_array($result)) {
			dol_print_error($this->db, $nav->error);
			throw new NavSendException("Unable to query db");
        }

        if (count($result) <= 0) {
            return;
        }

        dol_syslog(__METHOD__." Updating ".count($result)." row(s) in NAV result table", LOG_INFO);

        foreach ($result as $n) { /** @var NavResult $n */
            try {
                dol_syslog(__METHOD__." Query NAV for invoice ref $n->ref with transaction id $n->transaction_id", LOG_INFO);
                $transactionId = $n->transaction_id;
                $statusXml = $this->reporter->queryTransactionStatus($transactionId); /** @var SimpleXMLElement $statusXml */

                /* Invoice status */
				$result = $statusXml->processingResults->processingResult[0];
                dol_syslog(__METHOD__." Invoice ref $n->ref NAV invoice status: ".$statusXml->asXML(), LOG_INFO);
                $n->error_code = $result->invoiceStatus;

                /* Validation messages */
				$validationMessages = $result->businessValidationMessages;
                if (!empty($validationMessages)) {
                    $n->error_code = $validationMessages->validationErrorCode;
                    $n->message = $validationMessages->message;
                }

                /* Annulment data */
				$annulmentVerificationStatus = "";
				$annulmentData = $statusXml->processingResults->annulmentData;
				if (!empty($annulmentData)) {
					$annulmentVerificationStatus = $annulmentData->annulmentVerificationStatus;
				}

                /* Status update */
                if ($result->invoiceStatus == "DONE" &&
					($annulmentVerificationStatus == "VERIFICATION_DONE" || empty($validationMessages))) {
                    $n->result = NavResult::RESULT_SAVED;
                }
                if ($result->invoiceStatus == "ABORTED") {
                    $n->result = NavResult::RESULT_NAVERROR;
                }
                $n->tms = dol_now();
                $n->update($this->user);
                dol_syslog(__METHOD__." Invoice ref $n->ref updated result to ".NavResult::resultToString($n->result), LOG_INFO);
            } catch (Exception $ex) {
                dol_syslog(__METHOD__." Error querying NAV status for invoice ref $n->ref: ".$ex->getMessage(), LOG_ERR);
            }
        }

        $this->db->commit();
    }

    public function sendAnnulment($builder) {
        $ref = $builder->getRef();
        dol_syslog(__METHOD__." Sending invoice annulment ref $ref", LOG_INFO);
        try {
  			// 1. BUILD

			$this->invoiceXml = $builder->buildAnnulment()->getXml();

			// 2. SEND

            $transactionId = $this->reporter->manageAnnulment($this->invoiceXml);

            // 3. PERSIST

			$this->resultCreateOrUpdate($ref, NavResult::RESULT_SENTOK, "OK", "", $transactionId);

			dol_syslog("Invoice ref $ref annulment has been successfully sent. Transaction ID = $transactionId", LOG_INFO);
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
			throw new NavSendException("Unable to query db");
		} else if ($id == 0) {
			$needCreate = true;
        }

        $needCreate = true;

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
			throw new NavSendException("Unable to write db");
		}
	}
}
