<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . "/../../../core/class/ccountry.class.php";

class NavInvoiceXmlBuilder
{
	const xml_skeleton = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://schemas.nav.gov.hu/OSA/2.0/data" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://schemas.nav.gov.hu/OSA/2.0/data invoiceData.xsd">
</InvoiceData>
XML;

	private $db;
	private $root;
	private $mysoc;

	public function __construct($db, $mysoc)
	{
		$this->db = $db;
		$this->root = new SimpleXMLElement(self::xml_skeleton);
		$this->mysoc = $mysoc;
	}

	public function loadInvoice(Facture $invoice)
	{
		// do stuff
		$this->root->addChild("invoiceNumber", $invoice->ref);
		$date_creation = new DateTime();
		$date_creation->setTimestamp($invoice->date_creation);
		$this->root->addChild("invoiceIssueDate", $date_creation->format('Y-m-d'));
		$invoiceNode = $this->root->addChild("invoiceMain")->addChild("invoice");
		$invoiceHead = $invoiceNode->addChild("invoiceHead");
		$this->addSupplierInfo($invoiceHead->addChild("supplierInfo"));


		return $this;
	}

	public function build()
	{
		return $this->root->asXML();
	}

	public function pprint() {
		$dom = dom_import_simplexml($this->root)->ownerDocument;
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = false;
		print($dom->saveXML());
	}

	private function addSupplierInfo($node)
	{
		$tva = explode("-", $this->mysoc->tva_intra);
		$taxNumber = $node->addChild("supplierTaxNumber");
		$taxNumber->addChild("taxpayerId", $tva[0]);
		$taxNumber->addChild("vatCode", $tva[1]);
		$taxNumber->addChild("countyCode", $tva[2]);
		$node->addChild("supplierName", $this->mysoc->name);
		$address = $node->addChild("supplierAddress")->addChild("detailedAddress");
		$country = new Ccountry($this->db);
		$country->fetch($this->mysoc->country_id);
		$address->addChild("countryCode", $country->code);
		$address->addChild("postalCode", $this->mysoc->zip);
		$address->addChild("city", $this->mysoc->town);
	}
}
