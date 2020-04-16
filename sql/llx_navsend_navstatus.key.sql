-- Copyright (C) 2020 Andor Molnár <andor@apache.org>


ALTER TABLE llx_natsend_natstatus ADD INDEX idx_natsend_natstatus_rowid (rowid);
ALTER TABLE llx_natsend_natstatus ADD INDEX idx_natsend_natstatus_ref (ref);
ALTER TABLE llx_natsend_natstatus ADD CONSTRAINT llx_natsend_natstatus_fk_user_creat FOREIGN KEY (fk_user_creat) REFERENCES llx_user(rowid);
ALTER TABLE llx_natsend_natstatus ADD INDEX idx_natsend_natstatus_status (status);

--ALTER TABLE llx_natsend_natstatus ADD UNIQUE INDEX uk_natsend_natstatus_fieldxy(fieldx, fieldy);

--ALTER TABLE llx_natsend_natstatus ADD CONSTRAINT llx_natsend_natstatus_fk_field FOREIGN KEY (fk_field) REFERENCES llx_natsend_myotherobject(rowid);

