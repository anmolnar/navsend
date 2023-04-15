<?php

class ActionsNavSend {
	/**
	 * Overriding the pdf_getlinevatrate function : Use vat_src_code field in the case of 0% VAT items (FAD, AAM, etc.)
	 *
	 * @param   array()         $parameters     Hook metadatas (context, etc...)
	 * @param   CommonObject    &$object        The object to process (an invoice if you are in invoice module, a propale in propale's module, etc...)
	 * @param   string          &$action        Current action (if set). Generally create or edit or null
	 * @param   HookManager     $hookmanager    Hook manager propagated to allow calling another hook
	 * @return  int                             < 0 on error, 0 on success, 1 to replace standard code
	 */
	function pdf_getlinevatrate($parameters, &$object, &$action, $hookmanager) {

		$line_num = $parameters['i'];
		$ligne = $object->lines[$line_num];

		if ($ligne->tva_tx == 0 && !empty($ligne->vat_src_code)) {
			$this->resprints = $ligne->vat_src_code;
			return 1;	// Replace original logic
		}

		return 0; // Otherwise keep original logic
	}
}

?>
