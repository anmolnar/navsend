<?php

require_once __DIR__ . '/NavBase.class.php';
require_once __DIR__ . '/NavInvoiceXmlBuilder.class.php';
require_once __DIR__ . '/NavInvoiceSender.class.php';

class NavInvoice extends NavBase {

    public function report(string $ref, SimpleXMLElement $xml) {
        dol_syslog(__METHOD__." Sending invoice ref $ref modusz CREATE", LOG_INFO);
        $transactionId = $this->reporter->manageInvoice($xml, "CREATE");
        dol_syslog(__METHOD__." Invoice ref $ref has been successfully sent. Transaction ID = $transactionId", LOG_INFO);
        return $transactionId;
    }

    public static function send($db, $user, $mysoc, $f, $result = null) {
        $builder = new NavInvoiceXmlBuilder($db, $mysoc, $f);
        $sender = new NavInvoiceSender($db, $user, $builder, new NavInvoice($db, $user), $result);
        $sender->send();
    }

	public function getModusz()	{
		return "CREATE";
	}
}
