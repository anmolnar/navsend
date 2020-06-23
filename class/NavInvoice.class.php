<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . '/NavBase.class.php';
require_once __DIR__ . '/NavInvoiceXmlBuilder.class.php';
require_once __DIR__ . '/NavInvoiceSender.class.php';

class NavInvoice extends NavBase {

    private $modusz;

    function __construct($db, $user, $ref, $modusz) {
        parent::__construct($db, $user, $ref);
        $this->modusz = $modusz;
    }

    public function report(SimpleXMLElement $xml) {
        dol_syslog(__METHOD__." Sending invoice ref ".$this->ref." modusz ".$this->modusz, LOG_DEBUG);
        $transactionId = $this->reporter->manageInvoice($xml, $this->modusz);
        dol_syslog(__METHOD__." Invoice ref ".$this->ref." modusz $this->modusz has been successfully sent.  Transaction ID = $transactionId", LOG_INFO);
        if ($this->modusz == NavBase::MODUSZ_CREATE) {
        	$this->reffer->resetCounter($this->ref);
		}
        return $transactionId;
    }

    public static function send($db, $user, $mysoc, Facture $f, $result = null) {
        $builder = new NavInvoiceXmlBuilder($db, $mysoc, $f);
        $builder->build();
        $sender = new NavInvoiceSender(new NavInvoice($db, $user, $builder->getRef(), $builder->getModusz()), $result);
        $sender->send($builder->getXml());
    }

	public function getModusz()	{
		return $this->modusz;
    }
}
