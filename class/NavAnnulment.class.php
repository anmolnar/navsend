<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . '/NavBase.class.php';
require_once __DIR__ . '/NavAnnulmentXmlBuilder.class.php';
require_once __DIR__ . '/NavInvoiceSender.class.php';
require_once __DIR__ . '/exception/NavSendException.class.php';
require_once __DIR__ . '/exception/NavNetErrorException.class.php';

class NavAnnulment extends NavBase {

    public function report(SimpleXMLElement $xml) {
        // Check invoice send status
        $this->checkLastInvoiceStatus();

        dol_syslog(__METHOD__." Sending annulment ref ".$this->ref, LOG_DEBUG);
        $transactionId = $this->reporter->manageAnnulment($xml);
        dol_syslog(__METHOD__." Annulment ref ".$this->ref." has been successfully sent. Transaction ID = $transactionId", LOG_INFO);
        return $transactionId;
    }

    public static function send($db, $user, $mysoc, $f, $result = null) {
        $builder = new NavAnnulmentXmlBuilder($db, $mysoc, $f);
        $sender = new NavInvoiceSender(new NavAnnulment($db, $user, $builder->getRef(), $result), $result);
        $sender->send($builder->build()->getXml());
    }

	public function getModusz()	{
		return self::MODUSZ_ANNULMENT;
	}

    private function checkLastInvoiceStatus() {
        $nav = new NavResult($this->db);
        $where = "AND modusz='CREATE'";
        if ($this->result != null) {
            $where .= "AND rowid < ".$this->result->id;
        }
        $res = $nav->fetchCommon(null, $this->ref, $where." ORDER BY date_creation DESC");

        if ($res < 0) {
            // Hiba
            throw new NavSendException("DB error: ".$nav->error);
        }

        if ($res == 0) {
            // Nincs számla
            throw new NavSendException($this->ref.": No invoice for annulment");
        }

        if ($nav->result != 0 && $nav->result != 4) {
            throw new NavNetErrorException($this->ref.": Invoice not reported yet");
        }
    }
}
