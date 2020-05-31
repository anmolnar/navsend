<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . "/NavXmlBuilderBase.class.php";

class NavInvoiceSender {

    private $db;
    private $user;
    private $invoiceXml;

    public function __construct($db, $user) {
        $this->db = $db;
        $this->user = $user;
    }

    public function send(NavXmlBuilderBase $builder, NavBase $model) {
        $ref = $builder->getRef();

        try {
			// 1. BUILD

			$this->invoiceXml = $builder->build()->getXml();

            // 2. SEND
            
            $transactionId = $model->report($ref, $this->invoiceXml);

			// 3. PERSIST

			$this->resultCreateOrUpdate($ref, NavResult::RESULT_SENTOK, "OK", "", $transactionId);

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
