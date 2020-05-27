<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

/**
 * \file    core/triggers/interface_99_modNavSend_NavSendTriggers.class.php
 * \ingroup navsend
 * \brief   Example trigger.
 *
 * Put detailed description here.
 *
 * \remarks You can create other triggers by copying this one.
 * - File name should be either:
 *      - interface_99_modNavSend_MyTrigger.class.php
 *      - interface_99_all_MyTrigger.class.php
 * - The file must stay in core/triggers
 * - The class name must be InterfaceMytrigger
 * - The constructor method must be named InterfaceMytrigger
 * - The name property name must be MyTrigger
 */

require_once DOL_DOCUMENT_ROOT.'/core/triggers/dolibarrtriggers.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/navsend/class/NavInvoiceXmlBuilder.class.php';
require_once DOL_DOCUMENT_ROOT.'/custom/navsend/class/NavInvoiceSender.class.php';

/**
 *  Class of triggers for NavSend module
 */
class InterfaceNavSendTriggers extends DolibarrTriggers
{
    /**
     * @var DoliDB Database handler
     */
    protected $db;

    /**
     * Constructor
     *
     * @param DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        $this->name = preg_replace('/^Interface/i', '', get_class($this));
        $this->family = "demo";
        $this->description = "NavSend triggers.";
        // 'development', 'experimental', 'dolibarr' or version
        $this->version = 'development';
        $this->picto = 'navsend@navsend';
    }

    /**
     * Trigger name
     *
     * @return string Name of trigger file
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Trigger description
     *
     * @return string Description of trigger file
     */
    public function getDesc()
    {
        return $this->description;
    }


    /**
     * Function called when a Dolibarrr business event is done.
     * All functions "runTrigger" are triggered if file
     * is inside directory core/triggers
     *
     * @param string 		$action 	Event action code
     * @param CommonObject 	$object 	Object
     * @param User 			$user 		Object user
     * @param Translate 	$langs 		Object langs
     * @param Conf 			$conf 		Object conf
     * @return int              		<0 if KO, 0 if no triggered ran, >0 if OK
     */
    public function runTrigger($action, $object, User $user, Translate $langs, Conf $conf)
    {
        if (empty($conf->navsend->enabled)) return 0;     // If module is not enabled, we do nothing

        // Put here code you want to execute when a Dolibarr business events occurs.
        // Data and type of action are stored into $object and $action

        switch ($action) {

            // Bills
            case 'BILL_CREATE':
            	dol_syslog("BILL created: id=".$object->id . ", ref=".$object->ref . ", newref=" . $object->newref . ", status = " . $object->statut);
            	return 1;

            //case 'BILL_MODIFY':
            case 'BILL_VALIDATE':
            	global $mysoc;
				dol_syslog("BILL validated: id=" . $object->id . ", ref=" . $object->ref . ", newref=" . $object->newref . ", status = " . $object->statut);
				$f = $object; /** @var Facture $f */
				$builder = new NavInvoiceXmlBuilder($this->db, $mysoc, $f);
				$sender = new NavInvoiceSender($this->db, $user);
				$sender->send($builder);
				return 1;

			case 'BILL_UNVALIDATE':
                dol_syslog("BILL unvalidated: id=".$object->id . ", ref=" . $object->ref . ", newref=" . $object->newref . ", status = " . $object->statut);
                $f = $object; /** @var Facture $f */
                $builder = new NavInvoiceXmlBuilder($this->db, $mysoc, $f);
                $sender = new NavInvoiceSender($this->db, $user);
                $sender->sendAnnulment($builder);
				return 1;

            //case 'BILL_SENTBYMAIL':
            //case 'BILL_CANCEL':
            //case 'BILL_DELETE':
            //case 'BILL_PAYED':
            //case 'LINEBILL_INSERT':
            //case 'LINEBILL_UPDATE':
            //case 'LINEBILL_DELETE':

            //Supplier Bill
            //case 'BILL_SUPPLIER_CREATE':
            //case 'BILL_SUPPLIER_UPDATE':
            //case 'BILL_SUPPLIER_DELETE':
            //case 'BILL_SUPPLIER_PAYED':
            //case 'BILL_SUPPLIER_UNPAYED':
            //case 'BILL_SUPPLIER_VALIDATE':
            //case 'BILL_SUPPLIER_UNVALIDATE':
            //case 'LINEBILL_SUPPLIER_CREATE':
            //case 'LINEBILL_SUPPLIER_UPDATE':
            //case 'LINEBILL_SUPPLIER_DELETE':

            // Payments
            //case 'PAYMENT_CUSTOMER_CREATE':
            //case 'PAYMENT_SUPPLIER_CREATE':
            //case 'PAYMENT_ADD_TO_BANK':
            //case 'PAYMENT_DELETE':

            // Online
            //case 'PAYMENT_PAYBOX_OK':
            //case 'PAYMENT_PAYPAL_OK':
            //case 'PAYMENT_STRIPE_OK':

            default:
                //dol_syslog("Trigger '".$this->name."' for action '$action' launched by ".__FILE__.". id=".$object->id);
                break;
        }

        return 0;
    }
}
