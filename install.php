<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
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
_("Voicemail Admin");
_("Direct Dial Prefix");
_("The Feature Code used to direct dial a users voicemail from their own extension");
-("The Feature Code used to dial any voicemail");
}

global $astman;
global $amp_conf;
global $db;

$fcc = new featurecode('voicemail', 'myvoicemail');
$fcc->setDescription('My Voicemail');
$fcc->setHelpText('The Feature Code used to direct dial a users voicemail from their own extension');
$fcc->setDefault('*97');
$fcc->update();
unset($fcc);

$fcc = new featurecode('voicemail', 'dialvoicemail');
$fcc->setDescription('Dial Voicemail');
$fcc->setHelpText('The Feature Code used to dial any voicemail');
$fcc->setDefault('*98');
$fcc->setProvideDest();
$fcc->update();
unset($fcc);

$sql = " CREATE TABLE IF NOT EXISTS `voicemail_admin` (
  `variable` varchar(30) NOT NULL DEFAULT '',
  `value` varchar(80) NOT NULL DEFAULT '',
  PRIMARY KEY (`variable`)
);";
sql($sql);

$globals_convert['OPERATOR_XTN'] = '';
$globals_convert['VM_OPTS'] = '';
$globals_convert['VM_GAIN'] = ''; 
$globals_convert['VM_DDTYPE'] = 'u';

$globals_convert['VMX_OPTS_TIMEOUT'] = '';

$globals_convert['VMX_OPTS_LOOP'] = '';
$globals_convert['VMX_OPTS_DOVM'] = '';
$globals_convert['VMX_TIMEOUT'] = '2';
$globals_convert['VMX_REPEAT'] = '1';
$globals_convert['VMX_LOOPS'] = '1';

// Migrate the global settings now being managed here
//
$sql = "SELECT `variable`, `value`";
$sql_where = " FROM globals WHERE `variable` IN ('".implode("','",array_keys($globals_convert))."')";
$sql .= $sql_where;
$globals = $db->getAll($sql,DB_FETCHMODE_ASSOC);
if(DB::IsError($globals)) {
  die_freepbx($globals->getMessage());
}
outn(_("Checking for General Setting migrations.."));
if (count($globals)) {
  out(_("preparing"));
  foreach ($globals as $global) {
		unset($globals_convert[$global['variable']]);
		switch ($global['variable']) {
		case 'VMX_OPTS_TIMEOUT':
    	out(sprintf(_("%s no longer used"),$global['variable']));
		break;
		case 'VM_GAIN':
			if (is_numeric($global['value']) && $global['value'] >= 15) {
    		out(sprintf(_("%s changed from %s to max value 15"),$global['variable'], $global['value']));
				$global['value'] = 15;
			} else if (is_numeric($global['value'])) {
				$gain = ceil(round($global['value']) / 3) * 3;
				if ($gain != $global['value']) {
    			out(sprintf(_("%s adjusted from %s to %s"),$global['variable'], $global['value'], $gain));
					$global['value'] = $gain;
				}
			} else if ($global['value'] != '') {
   			out(sprintf(_("%s adjusted from bad value %s to default no gain"), $global['variable'], $global['value']));
				$global['value'] = '';
			}
			// FALL THROUGH TO SAVE
		default:
			$sql = 'INSERT INTO `voicemail_admin` (`variable`, `value`) VALUES ("' . $global['variable'] . '","' . $global['value'] . '")';;
			$result = $db->query($sql);
			if(DB::IsError($result)) {
				out(sprintf(_("ERROR inserting %s into voicemail_admin during migration, it may alreayd exist"), $global['variable']));
			} else {
 				out(sprintf(_("%s migrated"),$global['variable']));
			}
		break;
		}
	}
} else {
  out(_("not needed"));
}

// Now add any defaults not found in the globals table even though that should not happen
// if already there we just ignore
if (isset($globals_convert['VMX_OPTS_TIMEOUT'])) {
	unset($globals_convert['VMX_OPTS_TIMEOUT']);
}
foreach ($global_convert as $key => $value) {
	$sql = 'INSERT INTO `voicemail_admin` (`variable`, `value`) VALUES ("' . $key . '","' . $value . '")';;
	$result = $db->query($sql);
	if(!DB::IsError($result)) {
		out(sprintf(_("%s added"),$key));
	}
}

if (count($globals)) {
	out(_("General Settings migrated"));
	outn(_("Deleting migrated settings.."));
  $sql = "DELETE".$sql_where;
  $globals = $db->query($sql);
  if(DB::IsError($globals)) {
	  out(_("Fatal DB error trying to delete globals, trying to carry on"));
  } else {
	  out(_("done"));
  }
}

// Migrate VM_PREFIX from globals if needed
//
$current_prefix = $default_prefix = '*';
$sql = "SELECT `value` FROM globals WHERE `variable` = 'VM_PREFIX'";
$globals = $db->getAll($sql,DB_FETCHMODE_ASSOC);
if(!DB::IsError($globals)) {
	if (count($globals)) {
		$current_prefix = trim($globals[0]['value']);
		$sql = "DELETE FROM globals WHERE `variable` = 'VM_PREFIX'";
		out(_("migrated VM_PREFIX to feature codes"));
		outn(_("deleting VM_PREFIX from globals.."));
		$res = $db->query($sql);
		if(!DB::IsError($globals)) {
			out(_("done"));
		} else {
			out(_("could not delete"));
		}
	}
}

// Now setup the new feature code, if blank then disable
//
$fcc = new featurecode('voicemail', 'directdialvoicemail');
$fcc->setDescription('Direct Dial Prefix');
$fcc->setDefault($default_prefix);
if ($current_prefix != $default_prefix) {
	if ($current_prefix != '') {
		$fcc->setCode($current_prefix);
	} else {
		$fcc->setEnabled(false);
	}
}
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

// vmailadmin module functionality has been fully incporporated into this module
// so if it is installed we remove and delete it from the repository.
//
outn(_("checking if Voicemail Admin (vmailadmin) is installed.."));
$modules = module_getinfo('vmailadmin');
if (!isset($modules['vmailadmin'])) {
  out(_("not installed, ok"));
} else {
  out(_("installed."));
  out(_("Voicemail Admin being removed and merged with Voicemail"));
  outn(_("Attempting to delete.."));
  $result = module_delete('vmailadmin');
  if ($result === true) {
    out(_("ok"));
  } else {
    out($result);
  }
}

$freepbx_conf =& freepbx_conf::create();

// VM_SHOW_IMAP
//
$set['value'] = false;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 3;
$set['module'] = 'voicemail';
$set['category'] = 'Voicemail Module';
$set['emptyok'] = 0;
$set['sortorder'] = 100;
$set['name'] = 'Provide IMAP Voicemail Fields';
$set['description'] = 'Installations that have configured Voicemail with IMAP should set this to true so that the IMAP username and password fields are provided in the Voicemail setup screen for extensions. If an extension alread has these fields populated, they will be displayed even if this is set to false.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('VM_SHOW_IMAP',$set,true);

// USERESMWIBLF
//
$set['value'] = true;
$set['defaultval'] =& $set['value'];
$set['readonly'] = 0;
$set['hidden'] = 0;
$set['level'] = 3;
$set['module'] = 'voicemail';
$set['category'] = 'Voicemail Module';
$set['emptyok'] = 0;
$set['sortorder'] = 100;
$set['name'] = 'Create Voicemail Hints';
$set['description'] = 'Setting this flag with generate the required dialplan to integrate with res_mwi_blf which is included with the Official FreePBX Distro. It allows users to subscribe to other voicemail box and be notified via BLF of changes.';
$set['type'] = CONF_TYPE_BOOL;
$freepbx_conf->define_conf_setting('USERESMWIBLF',$set,true);

/* 
   update modules.conf to make sure it preloads res_mwi_blf.so if they have it
   This makes sure that the modules.conf has been updated for older systems
   which assures that mwi blf events are captured when Asterisk first starts
*/
if(file_exists($amp_conf['ASTMODDIR'].'/res_mwi_blf.so')) {
	FreePBX::create()->ModulesConf->preload('res_mwi_blf.so');
}