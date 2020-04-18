-- Copyright (C) 2020 Andor Molnár <andor@apache.org>

ALTER TABLE llx_navsend_navstatus ADD INDEX idx_navsend_navstatus_rowid (rowid);
ALTER TABLE llx_navsend_navstatus ADD INDEX idx_navsend_navstatus_ref (ref);
ALTER TABLE llx_navsend_navstatus ADD CONSTRAINT llx_navsend_navstatus_fk_invoice FOREIGN KEY (fk_invoice) REFERENCES llx_facture(rowid);
