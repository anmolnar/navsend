<?php

require_once __DIR__ . '/NavBase.class.php';
require_once __DIR__ . '/NavInvoiceXmlBuilder.class.php';
require_once __DIR__ . '/NavInvoiceSender.class.php';

class NavInvoice extends NavBase {

    public function report(SimpleXMLElement $xml) {
        dol_syslog(__METHOD__." Sending invoice ref ".$this->ref." modusz CREATE", LOG_INFO);
        $transactionId = $this->reporter->manageInvoice($xml, "CREATE");
        dol_syslog(__METHOD__." Invoice ref ".$this->ref." has been successfully sent. Transaction ID = $transactionId", LOG_INFO);
        return $transactionId;
    }

    public static function send($db, $user, $mysoc, Facture $f, $result = null) {
        $builder = new NavInvoiceXmlBuilder($db, $mysoc, $f);
        $sender = new NavInvoiceSender(new NavInvoice($db, $user, $builder->getRef()), $result);
        $sender->send($builder->build()->getXml());
    }

	public function getModusz()	{
		return self::MODUSZ_CREATE;
	}
}
