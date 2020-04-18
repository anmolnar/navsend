-- Copyright (C) 2020 Andor Molnár <andor@apache.org>

CREATE TABLE llx_navsend_navstatus(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref varchar(128) NOT NULL, 
	fk_invoice integer NOT NULL,
	errcode smallint NOT NULL,
	msg varchar(255),
    ts timestamp
) ENGINE=innodb;
