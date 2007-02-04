<?php
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
$modinfo = module_getinfo('voicemail');
if (is_array($modinfo)) {
	$ver = $modinfo['voicemail']['dbversion'];
	if (version_compare($ver,'1.6.2','lt')) { //we have to fix existing users with wrong values for vm ticket #1697
		checkAstMan();
		if ($astman) {
			$sql = "select * from users where voicemail='disabled' or voicemail='';";
			$users = sql($sql,"getAll",DB_FETCHMODE_ASSOC);
			foreach($users as $user) {
				$astman->database_put("AMPUSER",$user['extension']."/voicemail","\"novm\"");
			}
		} else {
			echo _("Cannot connect to Asterisk Manager with ").$amp_conf["AMPMGRUSER"]."/".$amp_conf["AMPMGRPASS"];
		}
		sql("update users set voicemail='novm' where voicemail='disabled' or voicemail='';");
	}
}

?>