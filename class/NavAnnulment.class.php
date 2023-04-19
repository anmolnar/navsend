<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . '/NavBase.class.php';
require_once __DIR__ . '/NavAnnulmentXmlBuilder.class.php';
require_once __DIR__ . '/NavInvoiceSender.class.php';

class NavAnnulment extends NavBase {

    public function report(SimpleXMLElement $xml) {
        dol_syslog(__METHOD__." Sending annulment ref ".$this->ref, LOG_DEBUG);
        $transactionId = $this->reporter->manageAnnulment($xml);
        dol_syslog(__METHOD__." Annulment ref ".$this->ref." has been successfully sent. Transaction ID = $transactionId", LOG_INFO);
        setEventMessages("Technikai érvénytelenítés beküldve, ne felejtsd el jóváhagyni mielőtt módosítasz!", null, 'warnings');
        return $transactionId;
    }

    public static function send($db, $user, $mysoc, $f, $result = null) {
        $builder = new NavAnnulmentXmlBuilder($db, $mysoc, $f);
        $sender = new NavInvoiceSender(new NavAnnulment($db, $user, $builder->getRef()), $result);
        $sender->send($builder->build()->getXml());
    }

	public function getModusz()	{
		return self::MODUSZ_ANNULMENT;
	}
}
