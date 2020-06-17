<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . '/navreference.class.php';
require_once __DIR__ . '/ReporterFactory.class.php';

class RefCounterProvider {
    private $db;
    private $reporter;

    private $navref;	/** @var NavReference $navref */

    public function __construct($db) {
        $this->db = $db;
        $this->reporter = ReporterFactory::getReporter();
    }

    public function getReferenceCounter($ref) {
        global $user;
        $this->navref = new NavReference($this->db);
        $r = $this->navref->fetch(null, $ref);
        if ($r < 0) {
            dol_print_error($this->db, $this->navref->error);
            throw new NavSendException("Unable to read db: ".$this->navref->error);
        }
        if ($r == 0) {
            // Double-check with NAV
			$this->navref->without_master = 1;
			$this->navref->counter = 1;
            $chainLength = $this->navInvoiceChainQuery($ref);
			$this->navref->ref = $ref;
			$this->navref->create($user);
			dol_syslog(sprintf(__METHOD__." Counter restored for ref %s with value=%d without_master=%d based on NAV chainLength=%d",
				$ref, $this->getCounter(), $this->getWithoutMaster(), $chainLength),
				LOG_INFO);
        } else {
            $this->navref->counter += 1;
            $this->navref->update($user);
			dol_syslog(__METHOD__." Counter has been updated for ref ".$ref." to ".$this->navref->counter, LOG_INFO);
        }
    }

    public function resetCounter($ref) {
    	global $user;
    	$this->navref = new NavReference($this->db);
		$r = $this->navref->fetch(null, $ref);
		if ($r < 0) {
			dol_print_error($this->db, $this->navref->error);
			throw new NavSendException("Unable to read db: ".$this->navref->error);
		}
		if ($r == 0) {
			$this->navref->ref = $ref;
			$this->navref->without_master = 0;
			$this->navref->counter = 0;
			$this->navref->create($user);
			dol_syslog(sprintf(__METHOD__." Counter created for ref %s with value=%d without_master=%d",
				$ref, $this->getCounter(), $this->getWithoutMaster()),
				LOG_INFO);
		} else {
			$this->navref->counter = 0;
			$this->navref->update($user);
			dol_syslog(__METHOD__." Counter has been reset to 0 for ref ".$ref, LOG_INFO);
		}
	}

    public function navInvoiceChainQuery($ref) {
		$invoiceChainQuery = [
			"invoiceNumber" => $ref,
			"invoiceDirection" => "OUTBOUND" // OUTBOUND or INBOUND
		];
		$currentPage = 1;
		$chainLength = 0;
		do {
			$invoiceChainDigestResult = $this->reporter->queryInvoiceChainDigest($invoiceChainQuery, $currentPage);
			$availablePage = $invoiceChainDigestResult->availablePage;
			$chainLength += count($invoiceChainDigestResult->invoiceChainElement);
		} while ($currentPage++ < $availablePage);
		if (!empty($invoiceChainDigestResult->invoiceChainElement)) {
			$chain = $invoiceChainDigestResult->invoiceChainElement;
			$last = $chain[count($chain)-1];
			$this->navref->without_master = $last->invoiceReferenceData->modifyWithoutMaster == "true" ? 1 : 0;
			$this->navref->counter = $last->invoiceReferenceData->modificationIndex + 1;
		}
		return $chainLength;
	}

	public function getRef() {
    	return $this->navref->ref;
	}

	public function getCounter() {
    	return $this->navref->counter;
	}

	public function getWithoutMaster() {
    	return $this->navref->without_master;
	}
}
