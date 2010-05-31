<?php
//This file is part of FreePBX.
//
//    FreePBX is free software: you can redistribute it and/or modify
//    it under the terms of the GNU General Public License as published by
//    the Free Software Foundation, either version 3 of the License, or
//    (at your option) any later version.
//
//    FreePBX is distributed in the hope that it will be useful,
//    but WITHOUT ANY WARRANTY; without even the implied warranty of
//    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//    GNU General Public License for more details.
//
//    You should have received a copy of the GNU General Public License
//    along with FreePBX.  If not, see <http://www.gnu.org/licenses/>.
// Copyright (c) 2006, 2008, 2009 qldrob, rcourtna
//
//for translation only
if (false) {
_("Voicemail");
_("My Voicemail");
_("Dial Voicemail");
}

global $astman;
global $amp_conf;

// Register FeatureCode - Activate
$fcc = new featurecode('voicemail', 'myvoicemail');
$fcc->setDescription('My Voicemail');
$fcc->setDefault('*97');
$fcc->update();
unset($fcc);

// Register FeatureCode - Deactivate
$fcc = new featurecode('voicemail', 'dialvoicemail');
$fcc->setDescription('Dial Voicemail');
$fcc->setDefault('*98');
$fcc->update();
unset($fcc);

//1.6.2
$ver = modules_getversion('voicemail');
if ($ver !== null && version_compare($ver,'1.6.2','lt')) { //we have to fix existing users with wrong values for vm ticket #1697
	if ($astman) {
		$sql = "select * from users where voicemail='disabled' or voicemail='';";
		$users = sql($sql,"getAll",DB_FETCHMODE_ASSOC);
		foreach($users as $user) {
			$astman->database_put("AMPUSER",$user['extension']."/voicemail","\"novm\"");
		}
	} else {
		echo _("Cannot connect to Asterisk Manager with ").$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"];
		return false;
	}
	sql("update users set voicemail='novm' where voicemail='disabled' or voicemail='';");
}

?>
