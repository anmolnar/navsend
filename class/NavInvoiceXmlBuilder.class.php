<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . '/NavXmlBuilderBase.class.php';
require_once __DIR__ . "/../../../core/class/ccountry.class.php";
require_once __DIR__ . "/../../../compta/bank/class/account.class.php";
require_once __DIR__ . "/../../../societe/class/societe.class.php";
require_once __DIR__ . "/../../../product/class/product.class.php";
require_once __DIR__ . "/navreference.class.php";
require_once __DIR__ . "/RefCounterProvider.class.php";

class Vat {
	public $tx;
	public $vatRateNetAmount = 0;
	public $vatRateNetAmountHUF = 0;
	public $vatRateVatAmount = 0;
	public $vatRateVatAmountHUF = 0;
	public $vatRateGrossAmount = 0;
	public $vatRateGrossAmountHUF = 0;

	public function __construct($tx) {
		$this->tx = $tx;
	}

	public function update($ligne) { /** @var FactureLigne $ligne */
		$this->vatRateNetAmount += $ligne->total_ht;
		$this->vatRateNetAmountHUF += $ligne->total_ht;
		$this->vatRateVatAmount += $ligne->total_tva;
		$this->vatRateVatAmountHUF += $ligne->total_tva;
		$this->vatRateGrossAmount += $ligne->total_ttc;
		$this->vatRateGrossAmountHUF += $ligne->total_ttc;
	}
}

class NavInvoiceXmlBuilder extends NavXmlBuilderBase {
	const DATE_FORMAT = "Y-m-d";

    const xml_skeleton = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://schemas.nav.gov.hu/OSA/2.0/data" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://schemas.nav.gov.hu/OSA/2.0/data invoiceData.xsd">
</InvoiceData>
XML;

    private $vat = array();
    private $origInvoice;    /** @var Facture $origInvoice */
    private $reffer;

    public function __construct($db, $user, Facture $f) {
		parent::__construct($db, $user, $f);
        $this->reffer = new RefCounterProvider($db);
    }

	public function build()	{
        dol_syslog(__METHOD__." Building create XML for invoice ref ".$this->getRef(), LOG_INFO);
        $this->modusz = $this->calculateModusz();
		$this->root = new SimpleXMLElement(self::xml_skeleton);
		$this->vat = array();
		$this->root->addChild("invoiceNumber", $this->getRef());
		$this->root->addChild("invoiceIssueDate", $this->getFormattedDate($this->invoice->date));
		$invoiceNode = $this->root->addChild("invoiceMain")->addChild("invoice");
		if ($this->modusz == NavBase::MODUSZ_MODIFY || $this->modusz == NavBase::MODUSZ_STORNO) {
            $invoiceRef = $invoiceNode->addChild("invoiceReference");

            $this->reffer->getReferenceCounter($this->origInvoice->ref);

			$invoiceRef->addChild("originalInvoiceNumber", $this->origInvoice->ref);
			$invoiceRef->addChild("modifyWithoutMaster", $this->reffer->getWithoutMaster());
			$invoiceRef->addChild("modificationIndex", $this->reffer->getCounter());
        }
		$invoiceHead = $invoiceNode->addChild("invoiceHead");
		$this->addSupplierInfo($invoiceHead->addChild("supplierInfo"));
		$this->addCustomerInfo($invoiceHead->addChild("customerInfo"));
		$detail = $invoiceHead->addChild("invoiceDetail");
		$detail->addChild("invoiceCategory", "NORMAL");
        $detail->addChild("invoiceDeliveryDate",
            empty($this->invoice->date_pointoftax) ? $this->getFormattedDate($this->invoice->date) : $this->getFormattedDate($this->invoice->date_pointoftax));
		$detail->addChild("currencyCode", $this->invoice->multicurrency_code);
		$detail->addChild("exchangeRate", $this->invoice->multicurrency_tx);
		$detail->addChild("paymentDate", $this->getFormattedDate($this->invoice->date_lim_reglement));
		$detail->addChild("invoiceAppearance", "PAPER");
		//$detail->addChild("additionalInvoiceData"); // TODO
		$this->addInvoiceLines($invoiceNode->addChild("invoiceLines"));
		$this->addSummary($invoiceNode->addChild("invoiceSummary"));
		return $this;
    }

    private function calculateModusz() {
        switch ($this->invoice->type) {
            case Facture::TYPE_STANDARD:
                return NavBase::MODUSZ_CREATE;

            case Facture::TYPE_CREDIT_NOTE:
                $this->origInvoice = new Facture($this->db);
                $res = $this->origInvoice->fetch($this->invoice->fk_facture_source);
                if ($res < 0) {
                    dol_print_error($this->db, $this->origInvoice->error);
                    throw new NavSendException("Unable to fetch original invoice for CREDIT_NOTE " . $this->getRef());
                }
                if ($this->origInvoice->multicurrency_total_ttc + $this->invoice->multicurrency_total_ttc == 0) {
                    return NavBase::MODUSZ_STORNO;
                } else {
                    return NavBase::MODUSZ_MODIFY;
                }

            default:
                return NavBase::MODUSZ_UNKOWN;
        }
    }

	private function addSupplierInfo($node) {
		$this->explodeTaxNumber($node->addChild("supplierTaxNumber"), $this->mysoc->tva_intra);
		$node->supplierName[] = $this->mysoc->name;
		$this->explodeAddress($node->addChild("supplierAddress"), $this->mysoc);
		$bac = new Account($this->db);
        $bac->fetch($this->invoice->fk_account);
        if (!empty($bac->number)) {
            $node->addChild("supplierBankAccountNumber", $bac->number);
        }
	}

	private function addCustomerInfo($node) {
        $soc = new Societe($this->db);
        $soc->fetch($this->invoice->socid);
        if ($soc->typent_code != 'TE_PRIVATE') {
            $this->explodeTaxNumber($node->addChild("customerTaxNumber"), $soc->tva_intra);
        }
        $node->customerName[] = $soc->name;
		$this->explodeAddress($node->addChild("customerAddress"), $soc);
	}

	private function addInvoiceLines($node) {
		$i = 1;
		foreach ($this->invoice->lines as $ligne) { /** @var FactureLigne $ligne */
			if (array_key_exists($ligne->tva_tx, $this->vat)) {
				$tva = $this->vat[$ligne->tva_tx];
			} else {
				$tva = new Vat($ligne->tva_tx);
				$this->vat[$ligne->tva_tx] = $tva;
			}
			$tva->update($ligne);

			$line = $node->addChild("line");
            $line->addChild("lineNumber", $i);
            if ($this->modusz == NavBase::MODUSZ_MODIFY || $this->modusz == NavBase::MODUSZ_STORNO) {
                $modificationRef = $line->addChild("lineModificationReference");
                $j = $this->findOrigInvoiceLigne($ligne);
                $modificationRef->addChild("lineNumberReference", $j > 0 ? $j : $i);
                $modificationRef->addChild("lineOperation", $j > 0 ? "MODIFY" : "CREATE");
            }
            if (!empty($ligne->fk_product)) {
                $p = new Product($this->db);
                $p->fetch($ligne->fk_product);
                $pcode = strtoupper(preg_replace("/[^A-Z0-9]+/i", "", $p->array_options["options_onewebcpacode"]));
                if (!empty($pcode)) {
                    $codes = $line->addChild("productCodes");
                    $code = $codes->addChild("productCode");
                    $code->addChild("productCodeCategory", "TESZOR");
                    $code->addChild("productCodeValue", $pcode);
                }
            }
			$line->addChild("lineExpressionIndicator", "true");
			$line->addChild("lineNatureIndicator", $ligne->product_type == 0 ? "PRODUCT" : "SERVICE");
            if (!empty($ligne->fk_product)) {
				$ligneDesc = $ligne->product_ref." - ".$ligne->product_label;
            } else {
				$ligneDesc = $ligne->desc;
            }
            $ligneDesc = html_entity_decode($ligneDesc);                // 1. decode any encoded character
            $ligneDesc = strip_tags($ligneDesc);                        // 2. remove html tags
            $ligneDesc = preg_replace("/[\r\n]/", " ", $ligneDesc);     // 3. remove newline characters
            $ligneDesc = htmlspecialchars($ligneDesc);                  // 4. encode html special chars to be XML safe
            $ligneDesc = substr($ligneDesc, 0, 512);                    // 5. Max length: 512
			$line->addChild("lineDescription", $ligneDesc);
			$line->addChild("quantity", $ligne->qty);
			$line->addChild("unitOfMeasure", "PIECE");
			$line->addChild("unitPrice", $ligne->multicurrency_subprice);

			$amounts = $line->addChild("lineAmountsNormal");

			$net_amount = $amounts->addChild("lineNetAmountData");
			$net_amount->addChild("lineNetAmount", $ligne->multicurrency_total_ht);
			$net_amount->addChild("lineNetAmountHUF", $ligne->total_ht);

			$amounts->addChild("lineVatRate")->addChild("vatPercentage", $ligne->tva_tx / 100);
			$vatdata = $amounts->addChild("lineVatData");
			$vatdata->addChild("lineVatAmount", $ligne->multicurrency_total_tva);
			$vatdata->addChild("lineVatAmountHUF", $ligne->total_tva);

			$gross = $amounts->addChild("lineGrossAmountData");
			$gross->addChild("lineGrossAmountNormal", $ligne->multicurrency_total_ttc);
            $gross->addChild("lineGrossAmountNormalHUF", $ligne->total_ttc);

			$i++;
		}
	}

	private function addSummary($node) {
		$normal = $node->addChild("summaryNormal");
		foreach($this->vat as $tx => $tva) { /** @var Vat $tva */
			$summary = $normal->addChild("summaryByVatRate");
			$summary->addChild("vatRate")->addChild("vatPercentage", $tx / 100);
			$net = $summary->addChild("vatRateNetData");
			$net->addChild("vatRateNetAmount", $tva->vatRateNetAmount);
			$net->addChild("vatRateNetAmountHUF", $tva->vatRateNetAmountHUF);
			$vat = $summary->addChild("vatRateVatData");
			$vat->addChild("vatRateVatAmount", $tva->vatRateVatAmount);
			$vat->addChild("vatRateVatAmountHUF", $tva->vatRateVatAmountHUF);
			$gross = $summary->addChild("vatRateGrossData");
			$gross->addChild("vatRateGrossAmount", $tva->vatRateGrossAmount);
			$gross->addChild("vatRateGrossAmountHUF", $tva->vatRateGrossAmountHUF);
		}
		$normal->addChild("invoiceNetAmount", $this->invoice->multicurrency_total_ht);
		$normal->addChild("invoiceNetAmountHUF", $this->invoice->total_ht);
		$normal->addChild("invoiceVatAmount", $this->invoice->multicurrency_total_tva);
		$normal->addChild("invoiceVatAmountHUF", $this->invoice->total_tva);
		$grossData = $node->addChild("summaryGrossData");
		$grossData->addChild("invoiceGrossAmount", $this->invoice->multicurrency_total_ttc);
		$grossData->addChild("invoiceGrossAmountHUF", $this->invoice->total_ttc);
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
        $address->streetName[] = $soc->address;
        $address->addChild("publicPlaceCategory", "STREET");
		/* TODO
		 * publicPlaceCategory
		 * number
		 * floor
		 * door
		*/
	}

	private function getFormattedDate($date) {
		$dt = new DateTime();
		$dt->setTimestamp($date);
		return $dt->format(self::DATE_FORMAT);
    }

    private function findOrigInvoiceLigne(FactureLigne $ligne) {
        $j = 1;
        foreach ($this->origInvoice->lines as $origLigne) {
            if (!empty($ligne->fk_product) && $ligne->fk_product == $origLigne->fk_product) {
                return $j;
            }
            if (empty($ligne->fk_product) && $ligne->desc == $origLigne->desc) {
                return $j;
            }
            $j++;
        }
        return -1;
    }
}
