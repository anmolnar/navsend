<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . "/../../../core/class/ccountry.class.php";
require_once __DIR__ . "/../../../compta/bank/class/account.class.php";
require_once __DIR__ . "/../../../societe/class/societe.class.php";

class NavInvoiceXmlBuilder
{
	const DATE_FORMAT = "Y-m-d";
	const xml_skeleton = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://schemas.nav.gov.hu/OSA/2.0/data" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://schemas.nav.gov.hu/OSA/2.0/data invoiceData.xsd">
</InvoiceData>
XML;

	private $db;
	private $root;
	private $mysoc;
	private $invoice; /** @var Facture $invoice */

	public function __construct($db, $mysoc, $invoice) {
		$this->db = $db;
		$this->root = new SimpleXMLElement(self::xml_skeleton);
		$this->mysoc = $mysoc;
		$this->invoice = $invoice;
	}

	public function build()	{
		$this->root->addChild("invoiceNumber", $this->invoice->ref);
		$this->root->addChild("invoiceIssueDate", $this->getFormattedDate($this->invoice->date_creation));
		$invoiceNode = $this->root->addChild("invoiceMain")->addChild("invoice");
		$invoiceHead = $invoiceNode->addChild("invoiceHead");
		$this->addSupplierInfo($invoiceHead->addChild("supplierInfo"));
		$this->addCustomerInfo($invoiceHead->addChild("customerInfo"));
		$detail = $invoiceHead->addChild("invoiceDetail");
		$detail->addChild("invoiceCategory", $this->getInvoiceCategory($this->invoice->type));
		$detail->addChild("invoiceDeliveryDate");
		$detail->addChild("currencyCode", $this->invoice->multicurrency_code);
		$detail->addChild("exchangeRate", $this->invoice->multicurrency_tx);
		$detail->addChild("paymentDate", $this->getFormattedDate($this->invoice->date_lim_reglement));
		$detail->addChild("invoiceAppearance", "PAPER");
		return $this;
	}

	public function pprint() {
		$dom = dom_import_simplexml($this->root)->ownerDocument;
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = false;
		print($dom->saveXML());
	}

	private function addSupplierInfo($node) {
		$this->explodeTaxNumber($node->addChild("supplierTaxNumber"), $this->mysoc->tva_intra);
		$node->addChild("supplierName", $this->mysoc->name);
		$this->explodeAddress($node->addChild("supplierAddress"), $this->mysoc);
		$bac = new Account($this->db);
		$bac->fetch($this->invoice->fk_account);
		$node->addChild("supplierBankAccountNumber", $bac->number);
	}

	private function addCustomerInfo($node) {
		$soc = new Societe($this->db);
		$soc->fetch($this->invoice->socid);
		$this->explodeTaxNumber($node->addChild("customerTaxNumber"), $soc->tva_intra);
		$node->addChild("customerName", $soc->name);
		$this->explodeAddress($node->addChild("customerAddress"), $soc);
	}

	private function explodeTaxNumber($node, $tva_intra) {
		$tva = explode("-", $tva_intra);
		$node->addChild("taxpayerId", $tva[0]);
		$node->addChild("vatCode", $tva[1]);
		$node->addChild("countyCode", $tva[2]);
	}

	private function explodeAddress($node, $soc) {
		$address = $node->addChild("detailedAddress");
		$country = new Ccountry($this->db);
		$country->fetch($soc->country_id);
		$address->addChild("countryCode", $country->code);
		$address->addChild("postalCode", $soc->zip);
		$address->addChild("city", $soc->town);
		$address->addChild("streetName", $soc->address);
		/* TODO
		 * publicPlaceCategory
		 * number
		 * floor
		 * door
		*/
	}

	private function getInvoiceCategory($type) {
		switch ($type) {
			case 0:
				return "NORMAL";
			default:
				return "UNKNOWN";
		}
	}

	private function getFormattedDate($date) {
		$dt = new DateTime();
		$dt->setTimestamp($date);
		return $dt->format(self::DATE_FORMAT);
	}
}
