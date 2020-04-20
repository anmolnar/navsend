<?php

$xml_skeleton = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<InvoiceData xmlns="http://schemas.nav.gov.hu/OSA/2.0/data" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://schemas.nav.gov.hu/OSA/2.0/data invoiceData.xsd">
</InvoiceData>
XML;

$root = new SimpleXMLElement($xml_skeleton);
$root->addChild("invoiceNumber", "FA0002/2005");
$root->addChild("invoiceIssueDate", (new DateTime("now"))->format("Y-m-d"));

print($root->asXML());



