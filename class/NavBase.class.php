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

	const MODUSZ_CREATE = "CREATE";
	const MODUSZ_ANNULMENT = "ANNULMENT";
	const MODUSZ_MODIFY = "MODIFY";
    const MODUSZ_STORNO = "STORNO";
    const MODUSZ_UNKOWN = "UNKNOWN";

	public function __construct($db, $user, $ref = "") {
        $this->db = $db;
        $this->user = $user;
        $this->ref = $ref;
        $this->reporter = ReporterFactory::getReporter();
        $this->reffer = new RefCounterProvider($db);
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

//    protected function refCreateOrUpdate() {
//        $needCreate = false;
//        $navRefDb = new NavReference($this->db);
//        $r = $navRefDb->fetch(null, $this->ref);
//		if ($r < 0) {
//			dol_print_error($this->db, $this->navRefDb->error);
//			throw new NavSendException("Unable to read db: ".$this->navRefDb->error);
//		}
//
//        if ($r == 0) {
//            $needCreate = true;
//            $navRefDb->ref = $this->ref;
//            $navRefDb->counter = 0;
//        }
//
//        switch ($this->getModusz()) {
//            case NavBase::MODUSZ_CREATE:
//                $navRefDb->counter = 0;
//                break;
//            case NavBase::MODUSZ_ANNULMENT:
//                if ($navRefDb->counter > 0) {
//                    $navRefDb->counter--;
//                }
//                break;
//            case NavBase::MODUSZ_MODIFY:
//            case NavBase::MODUSZ_STORNO:
//                $navRefDb->counter++;
//                break;
//        }
//
//        if ($needCreate) {
//    		$r = $this->navRefDb->create($this->user);
//		} else {
//    		$r = $this->navRefDb->update($this->user);
//		}
//		if ($r < 0) {
//			dol_print_error($this->db, $this->navRefDb->error);
//			throw new NavSendException("Unable to write db: ".$this->navRefDb->error);
//		}
//    }
}
