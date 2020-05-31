<?php

require_once __DIR__ . '/NavBase.class.php';
require_once __DIR__ . '/NavAnnulmentXmlBuilder.class.php';
require_once __DIR__ . '/NavInvoiceSender.class.php';

class NavAnnulment extends NavBase {

    public function report($ref, $xml) {
        dol_syslog(__METHOD__." Sending annulment ref $ref", LOG_INFO);
        $transactionId = $this->reporter->manageAnnulment($xml);
        dol_syslog(__METHOD__." Annulment ref $ref has been successfully sent. Transaction ID = $transactionId", LOG_INFO);
        return $transactionId;
    }

    public static function send($db, $user, $mysoc, $f, $result = null) {
        $builder = new NavAnnulmentXmlBuilder($db, $mysoc, $f);
        $sender = new NavInvoiceSender($db, $user, $result);
        $sender->send($builder, new NavAnnulment($db, $user));
    }

}
