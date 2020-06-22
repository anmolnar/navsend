<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . '/ReporterFactory.class.php';
require_once __DIR__ . '/NavAnnulment.class.php';
require_once __DIR__ . '/NavInvoice.class.php';
require_once __DIR__ . '/exception/NavSendException.class.php';
require_once __DIR__ . '/../../../compta/facture/class/facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/comm/action/class/actioncomm.class.php';

class NavUpdater {

    private $db;
    private $reporter;

    public $errors = array();
    public $output = "";

    public function __construct($db) {
        $this->db = $db;
        $this->reporter = ReporterFactory::getReporter();
    }

    public function updateAll() {
        $this->output = "";
        $nav = new NavResult($this->db);
        $result = $nav->fetchAll('','', 100,0,
            array("customsql" => "result IN (2, 3, 4, 5)"));

        if (!is_array($result)) {
            dol_print_error($this->db, $nav->error);
            array_push($this->errors, "DB error: ".$nav->error);
            return 1;
        }

        dol_syslog(__METHOD__." Checking ".count($result)." row(s) in NAV result table", LOG_INFO);

        if (count($result) <= 0) {
            $this->output =  "No row to update";
			return 0;
		}

        $i = 0;

        foreach ($result as $n) { /** @var NavResult $n */
            try {
                switch ($n->result) {
                    case NavResult::RESULT_XSDERROR:
					case NavResult::RESULT_ERROR:
                        $this->resend($n);
                        break;

					case NavResult::RESULT_NETERROR:
						$this->retransfer($n);
						break;

                    case NavResult::RESULT_SENTOK:
                        $this->queryNavStatus($n);
                        if ($n->result == NavResult::RESULT_SAVED) {
                            $this->addAgenda($n);
                        }
                        break;
                }
                $i++;
            } catch (Exception $ex) {
                dol_syslog(__METHOD__." Error checking invoice ref $n->ref: ".$ex->getMessage(), LOG_ERR);
                array_push($this->errors, $n->ref.": ".$ex->getMessage());
            } finally {
                $this->db->commit();
            }
        }

        $this->output = "$i row(s) updated";

        return count($this->errors);
    }

    public function queryNavStatus(NavResult $n) {
        global $user;

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
            if (empty($n->error_code)) $n->error_code = $annulmentVerificationStatus;
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
        $n->update($user);
        dol_syslog(__METHOD__." Invoice ref $n->ref updated result to ".NavResult::resultToString($n->result), LOG_INFO);
    }

    public function resend(NavResult $n) {
        global $mysoc, $user;

        $f = new Facture($this->db);
        dol_syslog(__METHOD__." Resending invoice ref $n->ref", LOG_INFO);
        $r = $f->fetch(null, $n->ref);
		if ($r < 0) {
			dol_print_error($this->db, $f->error);
			throw new NavSendException("Unable to query db");
		}
		switch ($n->modusz) {
			case NavBase::MODUSZ_ANNULMENT:
				NavAnnulment::send($this->db, $user, $mysoc, $f, $n);
				break;
            case NavBase::MODUSZ_CREATE:
            case NavBase::MODUSZ_MODIFY:
            case NavBase::MODUSZ_STORNO:
				NavInvoice::send($this->db, $user, $mysoc, $f, $n);
                break;
            case NavBase::MODUSZ_UNKOWN:
                dol_syslog(__METHOD__." Ignoring unsupported modusz UNKOWN for ref ".$n->ref, LOG_INFO);
                $n->result = NavResult::RESULT_UNSUPPORTED;
                $n->message = "Unsupported modusz UNKNOWN";
                $n->update($user);
                break;
			default:
				throw new NavSendException("Unsupported modusz: ".$n->modusz);
		}
    }

    public function retransfer(NavResult $n) {
        global $user;

		dol_syslog(__METHOD__." Retrying transmission of invoice ref $n->ref modusz $n->modusz", LOG_INFO);
		switch ($n->modusz) {
			case NavBase::MODUSZ_ANNULMENT:
				$model = new NavAnnulment($this->db, $user, $n->ref);
				break;
            case NavBase::MODUSZ_CREATE:
            case NavBase::MODUSZ_MODIFY:
            case NavBase::MODUSZ_STORNO:
				$model = new NavInvoice($this->db, $user, $n->ref, $n->modusz);
                break;
            case NavBase::MODUSZ_UNKOWN:
                dol_syslog(__METHOD__." Ignoring unsupported modusz UNKOWN for ref ".$n->ref, LOG_INFO);
                $n->result = NavResult::RESULT_UNSUPPORTED;
                $n->message = "Unsupported modusz UNKNOWN";
                $n->update($user);
                break;
            default:
				throw new NavSendException("Unsupported modusz: ".$n->modusz);
		}
		$sender = new NavInvoiceSender($model, $n);
		$sender->send(new SimpleXMLElement($n->xml));
	}

    public function report($xml) {
        // Not implemented
    }

	public function getModusz()	{
		// Not implemented
    }
    
    private function addAgenda(NavResult $n) {
        global $user;
        $f = new Facture($this->db);
        $f->fetch(null, $n->ref);
        $now = dol_now();
		$actioncomm = new ActionComm($this->db);
		$actioncomm->type_code   = 'AC_OTH_AUTO';		// Type of event ('AC_OTH', 'AC_OTH_AUTO', 'AC_XXX'...)
		$actioncomm->code        = 'AC_NAV_'.$n->modusz;
		$actioncomm->label       = 'NAV action '.$n->modusz.' txn id '.$n->transaction_id;
		$actioncomm->note_private= $n->message;
		$actioncomm->fk_project  = 0;
		$actioncomm->datep       = $now;
		$actioncomm->datef       = $now;
		$actioncomm->percentage  = -1;   // Not applicable
		$actioncomm->authorid    = $user->id;   // User saving action
        $actioncomm->userownerid = $user->id;	// Owner of action
        $actioncomm->elementtype = 'invoice';
        $actioncomm->fk_element  = $f->id;
        $actioncomm->create($user);       // User creating action
    }
}
