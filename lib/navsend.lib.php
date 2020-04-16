<?php
/* Copyright (C) 2020 Andor Molnár <andor@apache.org> */

/**
 * Prepare admin pages header
 *
 * @return array
 */
function navsendAdminPrepareHead()
{
	global $langs, $conf;

	$langs->load("navsend@navsend");

	$h = 0;
	$head = array();

	$head[$h][0] = dol_buildpath("/navsend/admin/setup.php", 1);
	$head[$h][1] = $langs->trans("Settings");
	$head[$h][2] = 'settings';
	$h++;
	$head[$h][0] = dol_buildpath("/navsend/admin/about.php", 1);
	$head[$h][1] = $langs->trans("About");
	$head[$h][2] = 'about';
	$h++;

	complete_head_from_modules($conf, $langs, null, $head, $h, 'navsend');

	return $head;
}
