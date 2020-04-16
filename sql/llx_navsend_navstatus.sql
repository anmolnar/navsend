-- Copyright (C) 2020 Andor Molnár <andor@apache.org>

CREATE TABLE llx_natsend_natstatus(
	rowid integer AUTO_INCREMENT PRIMARY KEY NOT NULL,
	ref varchar(128) NOT NULL, 
	description text, 
	date_creation datetime NOT NULL, 
	tms timestamp, 
	fk_user_creat integer NOT NULL, 
	fk_user_modif integer, 
	fk_invoice integer NOT NULL, 
	status smallint NOT NULL
) ENGINE=innodb;
