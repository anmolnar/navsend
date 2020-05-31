<?php

require_once __DIR__ . '/NavBase.class.php';
require_once __DIR__ . '/NavInvoice.class.php';
require_once __DIR__ . '/exception/NavSendException.class.php';
require_once __DIR__ . '/../../../compta/facture/class/facture.class.php';

class NavUpdater extends NavBase {

    public function updateAll() {
        $nav = new NavResult($this->db);
        $result = $nav->fetchAll('','', 100,0,
            array("customsql" => "result IN (2, 4)"));

        if (!is_array($result)) {
            dol_print_error($this->db, $nav->error);
            throw new NavSendException("Unable to query db");
        }

        dol_syslog(__METHOD__." Checking ".count($result)." row(s) in NAV result table", LOG_INFO);

        if (count($result) <= 0) {
            return;
        }

        foreach ($result as $n) { /** @var NavResult $n */
            try {
                switch ($n->result) {
                    case NavResult::RESULT_XSDERROR:
                        $this->resend($n);
                        break;

                    case NavResult::RESULT_SENTOK:
                        $this->queryNavStatus($n);
                        break;
                }
            } catch (Exception $ex) {
                dol_syslog(__METHOD__." Error checking invoice ref $n->ref: ".$ex->getMessage(), LOG_ERR);
            } finally {
                $this->db->commit();
            }
        }

    }

    public function queryNavStatus(NavResult $n) {
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
    }

    public function resend(NavResult $n) {
        global $mysoc;
        $f = new Facture($this->db);
        dol_syslog(__METHOD__." Resending invoice ref $n->ref", LOG_INFO);
        $r = $f->fetch(null, $n->ref);
		if ($r < 0) {
			dol_print_error($this->db, $this->navResult->error);
			throw new NavSendException("Unable to query db");
		}
        NavInvoice::send($this->db, $this->user, $mysoc, $f, $n);
    }

    public function report($ref, $xml) {        
        // Not implemented
    }
}
