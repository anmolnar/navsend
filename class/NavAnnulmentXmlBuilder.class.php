<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . '/NavXmlBuilderBase.class.php';

class NavAnnulmentXmlBuilder extends NavXmlBuilderBase {

	const xml_annulment_skeleton = <<<ANNUL
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<InvoiceAnnulment xmlns="http://schemas.nav.gov.hu/OSA/2.0/annul">
</InvoiceAnnulment>
ANNUL;

	function build() {
        dol_syslog(__METHOD__." Building annulment XML for invoice ref ".$this->getRef(), LOG_INFO);
        $this->modusz = NavBase::MODUSZ_ANNULMENT;
		$this->root = new SimpleXMLElement(self::xml_annulment_skeleton);
		$this->root->addChild("annulmentReference", $this->getRef());
		$this->root->addChild("annulmentTimestamp", date('Y-m-d\TH:i:s\Z', dol_now()));
		$this->root->addChild("annulmentCode", "ERRATIC_DATA");
		$this->root->addChild("annulmentReason", "create szamla annul");
		return $this;
	}
}
