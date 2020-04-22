<?php


require_once __DIR__ . "/../class/navinvoicexmlbuilder.class.php";

$xml = new NavInvoiceXmlBuilder();

print($xml->build());

