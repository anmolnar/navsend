<?php

require_once __DIR__ . '/NavBase.class.php';
require_once __DIR__ . '/exception/NavSendException.class.php';

class NavUpdater extends NavBase {

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

    public function report($ref, $xml) {        
        // Not implemented
    }
}