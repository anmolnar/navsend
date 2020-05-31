<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . "/NavXmlBuilderBase.class.php";
require_once __DIR__ . "/navresult.class.php";

class NavInvoiceSender {

    private $db;
    private $user;
    private $invoiceXml;
    private $navResult; /** @var NavResult @navResult */
	private $builder;
	private $model;

    public function __construct($db, $user, NavXmlBuilderBase $builder, NavBase $model, $result) {
        $this->db = $db;
        $this->user = $user;
        $this->navResult = $result;
        $this->builder = $builder;
        $this->model = $model;
    }

    public function send() {
    	$ref = $this->builder->getRef();

        try {
			// 1. BUILD

			$this->invoiceXml = $this->builder->build()->getXml();

            // 2. SEND

            $transactionId = $this->model->report($ref, $this->invoiceXml);

			// 3. PERSIST

			$this->resultCreateOrUpdate(NavResult::RESULT_SENTOK, "OK", "", $transactionId);

			return true;
		} catch (NavOnlineInvoice\XsdValidationError $ex) {
        	dol_syslog(__METHOD__." ".$ex->getMessage(), LOG_ERR);
			$this->resultCreateOrUpdate(NavResult::RESULT_XSDERROR, $ex->getMessage(), "", "");
		} catch (NavOnlineInvoice\CurlError | NavOnlineInvoice\HttpResponseError $ex) {
			dol_syslog(__METHOD__ . " " . $ex->getMessage(), LOG_ERR);
			$this->resultCreateOrUpdate(NavResult::RESULT_NETERROR, $ex->getMessage(), "", "");
		} catch (NavOnlineInvoice\GeneralErrorResponse | NavOnlineInvoice\GeneralExceptionResponse $ex) {
			dol_syslog(__METHOD__ . " " . $ex->getMessage(), LOG_ERR);
			$this->resultCreateOrUpdate(NavResult::RESULT_NAVERROR, $ex->getMessage(), $ex->getErrorCode(), "");
		} catch (Exception $ex) {
			dol_syslog(__METHOD__." ".$ex->getMessage(), LOG_ERR);
        	$this->resultCreateOrUpdate(NavResult::RESULT_ERROR, $ex->getMessage(), "", "");
		} finally {
        	$this->db->commit();
		}
    }

    private function resultCreateOrUpdate($result, $msg, $errored, $tid) {
        $needCreate = false;

        if (empty($this->navResult)) {
            $this->navResult = new NavResult($this->db);
            $needCreate = true;
        }

		$this->navResult->tms = dol_now();
		$this->navResult->ref = $this->builder->getRef();
    	$this->navResult->result = $result;
    	$this->navResult->message = $msg;
    	$this->navResult->error_code = $errored;
    	$this->navResult->xml = $this->invoiceXml->asXML();
    	$this->navResult->transaction_id = $tid;
    	$this->navResult->modusz = $this->model->getModusz();

    	if ($needCreate) {
    		$r = $this->navResult->create($this->user);
		} else {
    		$r = $this->navResult->update($this->user);
		}
		if ($r < 0) {
			dol_print_error($this->db, $this->navResult->error);
			throw new NavSendException("Unable to write db");
		}
	}
}
