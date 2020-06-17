<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

abstract class NavXmlBuilderBase {

	protected $db;
	protected $root;
	protected $mysoc;
    protected $invoice; /** @var Facture $invoice */
    protected $modusz;

	public function __construct($db, $mysoc, Facture $invoice) {
		$this->db = $db;
		$this->mysoc = $mysoc;
		$this->invoice = $invoice;
	}

	abstract function build();

	/**
	 * Returns the root simple xml element which is being built here.
	 *
	 * @return SimpleXMLElement XML root node
	 */
	public function getXml() {
		return $this->root;
	}

	/**
	 * Returns the original Facture (invoice) object which the builder is based on.
	 *
	 * @return Facture Invoice object
	 */
	public function getInvoice() {
		return $this->invoice;
	}

	/**
	 * Determine invoice number (ref): 'newref' if not empty, otherwise 'ref'
	 *
	 * @return string Invoice number
	 */
	public function getRef() {
		return empty($this->invoice->newref) ? $this->invoice->ref : $this->invoice->newref;
    }

    /**
     * Returns the calculated invoice sending mode (create, modify, storno, annulment).
     * Only available if XML built.
     */
    public function getModusz() {
        return $this->modusz;
    }

	public function pprint() {
		$dom = dom_import_simplexml($this->root)->ownerDocument;
		$dom->formatOutput = true;
		$dom->preserveWhiteSpace = false;
		print($dom->saveXML());
	}
}
