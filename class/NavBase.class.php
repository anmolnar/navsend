<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

require_once __DIR__ . '/ReporterFactory.class.php';
require_once __DIR__ . '/navresult.class.php';
require_once __DIR__ . '/navreference.class.php';
require_once __DIR__ . '/RefCounterProvider.class.php';

abstract class NavBase {

    protected $db;
    protected $user;
    protected $ref;
    protected $reporter; /** @var NavReporter $reporter */
	protected $reffer; /** @var RefCounterProvider $reffer */
    protected ?NavResult $result;


	const MODUSZ_CREATE = "CREATE";
	const MODUSZ_ANNULMENT = "ANNULMENT";
	const MODUSZ_MODIFY = "MODIFY";
    const MODUSZ_STORNO = "STORNO";
    const MODUSZ_UNKOWN = "UNKNOWN";

	public function __construct($db, $user, $ref = "", $result = null) {
        $this->db = $db;
        $this->user = $user;
        $this->ref = $ref;
        $this->reporter = ReporterFactory::getReporter();
        $this->reffer = new RefCounterProvider($db);
        $this->result = $result;
    }

    public abstract function report(SimpleXMLElement $xml);

    public abstract function getModusz();

    public function getDb() {
    	return $this->db;
	}

	public function getUser() {
    	return $this->user;
	}

	public function getRef() {
    	return $this->ref;
    }
}
