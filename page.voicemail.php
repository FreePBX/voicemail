<?php
if (!defined('FREEPBX_IS_AUTH')) { die('No direct script access allowed'); }
/*
  This program is free software; you can redistribute it and/or
  modify it under the terms of the GNU General Public License
  as published by the Free Software Foundation; either version 2
  of the License, or (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.
*/

/* All extensions. */
$extens     = core_users_list();
/* All voicemail.conf settings. */
$uservm     = voicemail_getVoicemail();
/* VMAIL info - needed for rnav menu and other page content. */
$vmail_info["activated_info"]   = array();
$vmail_info["bycontext"]        = array();
$vmail_info["unactivated_info"] = array();
$vmail_info["disabled_list"]    = array();
$vmail_info["contexts"]         = array();
$vmail_info["contexts"] 	= array_keys($uservm);		/* All voicemail contexts. */

$extdisplay 			= isset($_REQUEST["ext"])?$_REQUEST["ext"]:"";
$type				= (isset($_REQUEST["type"]) && $_REQUEST["type"] != "")?$_REQUEST["type"]:"setup";
$display			= (isset($_REQUEST["display"]) && $_REQUEST["display"] != "")?$_REQUEST["display"]:"voicemail";

$rnav_list  			= "";
$rnav_enabled_index 		= array();
$rnav_entries 			= array();

/* Activated mailboxes are those which have a subdirectory on disk. */
global $amp_conf;
$vmail_root = "/" . trim($amp_conf["ASTSPOOLDIR"] , "/") . "/voicemail";

if (isset($extens) && is_array($extens)) {
	$i = 0;
	foreach ($extens as $key => $exten) {
		$vmbox = null;
		/* Voicemail is enabled for this extension when it is associated with a Voicemail context. */
		foreach ($vmail_info["contexts"] as $vmcontext) {
			if (isset($uservm[$vmcontext][$exten[0]])) {
				$vmbox["context"] = $vmcontext;
				break;
			}
		}

		/* FOR RNAV MENU */
		$name = $exten[1];
		$unactivated_style = "";
		$unactivated_txt = "";
		$disabled_style = "";
		$disabled_txt = "";
		$c = "";
		$c = isset($vmbox["context"])?$vmbox["context"]:"";
		if ($vmbox !== null) {
			$vmail_info["bycontext"][$vmbox["context"]][] = $exten[0];
			$vmbox["path"] = $vmail_root . "/" . $vmbox["context"] . "/" . $exten[0];
			$rnav_enabled_index[$vmbox["context"]][] = $i;
			if (is_dir($vmbox["path"])) {
				$vmail_info["activated_info"][$exten[0]] = $vmbox["context"];
			} else {
				$vmail_info["unactivated_info"][$exten[0]] = $vmbox["context"];
				$unactivated_style = " style='background: #abc9ff;'";
				$unactivated_txt = " [unactivated]";
			}
			$link = "config.php?type=" . $type . "&display=" . $display . "&ext=" . $exten[0] . "&action=bsettings#" . $exten[0];
		} else {
			/* Voicemail is disabled for this extension. */
			$vmail_info["disabled_list"][] = $exten[0];
			$disabled_txt = "disabled";
			$disabled_style = " style='background: #ffffcc; text-decoration: line-through;'";
			/* Distinguish between "extensions" and "deviceanduser" modes. */
			if (isset($amp_conf["AMPEXTENSIONS"]) && ($amp_conf["AMPEXTENSIONS"] == "extensions")) {
				$link = "config.php?type=setup&display=extensions&extdisplay=" . $exten[0] . "#" . $exten[0];
			} else {
				$link = "config.php?type=setup&display=users&extdisplay=" . $exten[0] . "#" . $exten[0];
			}
		}
		$rnav_entries[$i] = "\t<li id='voicemail_list_" . $exten[0] . "'${disabled_style}${unactivated_style}><a" . ($extdisplay==$exten[0] ? ' class="current"':'') . "${disabled_style}${unactivated_style} href=\"$link\" onHover='menuUpdatePos();'>{$name} &lt;" . $exten[0] . "&gt;&nbsp;&nbsp;(${c}${disabled_txt})${unactivated_txt}</a></li>\n";
		$i++;
	}
}

/* End VMAIL info processing. */

/* Settings options */
$dlen = 800;	/* default max length on text entry */
$gen_settings = array(		"adsifdn" 			=> array("ver" => 1.2, "len" => 4, "type" => "char", "default" => ""),
				"adsisec" 			=> array("ver" => 1.2, "len" => 4, "type" => "char", "default" => ""),
				"adsiver" 			=> array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"attach" 			=> array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"authpassword" 			=> array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
        			"authuser" 			=> array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"backupdeleted" 		=> array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"callback" 			=> array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"charset"  			=> array("ver" => 1.2, "len" => 32, "type" => "char", "default" => ""),
        			"cidinternalcontexts" 		=> array("ver" => 1.2, "len" => 640, "type" => "char", "default" => ""),
				"dialout"  			=> array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"emailbody" 			=> array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"emaildateformat" 		=> array("ver" => 1.2, "len" => 32, "type" => "char", "default" => ""),
				"emailsubject"                  => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"envelope"                      => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"exitcontext"                   => array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"expungeonhangup"               => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"externnotify"                  => array("ver" => 1.2, "len" => 160, "type" => "char", "default" => ""),
				"externpass"                    => array("ver" => 1.2, "len" => 128, "type" => "char", "default" => ""),
				"externpassnotify"              => array("ver" => 1.6, "len" => 128, "type" => "char", "default" => ""),
				"forcegreetings"                => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "no"),
				"forcename"                     => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"format"                        => array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"fromstring"                    => array("ver" => 1.2, "len" => 100, "type" => "char", "default" => ""),
				"greetingsfolder"               => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"imapclosetimeout"              => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapflags"                     => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapfolder"                    => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapgreetings"                 => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"imapopentimeout"               => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"imapparentfolder"              => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"imapport"                      => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapreadtimeout"               => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapserver"                    => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"imapwritetimeout"              => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-forward-key"    => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-pause-key"      => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-restart-key"    => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-reverse-key"    => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"listen-control-stop-key"       => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"mailcmd"                       => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"maxgreet"                      => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"maxlogins"                     => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"maxmessage"			=> array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"maxmsg"                        => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"maxsecs"                       => array("ver" => 1.6, "len" => $dlen, "type" => "num", "default" => ""),
				"maxsilence"                    => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"minsecs"                       => array("ver" => 1.6, "len" => $dlen, "type" => "num", "default" => ""),
				"moveheard"                     => array("ver" => 1.6, "len" => 0, "type" => "flag", "default" => "no"),
				"nextaftercmd"                  => array("ver" => 1.2, "len" => 0, "type" => "flag", "default" => "no"),
				"obdcstorage"                   => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"odbctable"                     => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"operator"                      => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"pagerbody"                     => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"pagerfromstring"               => array("ver" => 1.2, "len" => 100, "type" => "char", "default" => ""),
				"pagersubject"                  => array("ver" => 1.2, "len" => $dlen, "type" => "char", "default" => ""),
				"pbxskip"                       => array("ver" => 1.2, "len" => 0, "type" => "flag", "default" => "no"),
				"pollfreq"                      => array("ver" => 1.6, "len" => $dlen, "type" => "num", "default" => "30"),
				"pollmailboxes"                 => array("ver" => 1.6, "len" => 0, "type" => "flag", "default" => "yes"),
				"review"                        => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "no"),
				"saycid"                        => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "no"),
				"sayduration"                   => array("ver" => 1.2, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"saydurationm"                  => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"searchcontexts"                => array("ver" => 1.2, "len" => 0, "type" => "flag", "default" => "no"),
				"sendvoicemail"                 => array("ver" => 1.2, "len" => 0, "type" => "flag", "default" => "yes"),
				"serveremail"                   => array("ver" => 1.2, "len" => 80, "type" => "char", "default" => ""),
				"silencethreshold"              => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"skipms"                        => array("ver" => 1.2, "len" => $dlen, "type" => "num", "default" => ""),
				"smdienable"                    => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"smdiport"                      => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"tempgreetwarn"                 => array("ver" => 1.4, "len" => $dlen, "type" => "flag", "default" => "yes"),
				"usedirectory"                  => array("ver" => 1.4, "len" => 0, "type" => "flag", "default" => "yes"),
				"userscontext"                  => array("ver" => 1.4, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-mismatch"                   => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-newpassword"                => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-passchanged"                => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-password"                   => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"vm-reenterpassword"            => array("ver" => 1.6, "len" => $dlen, "type" => "char", "default" => ""),
				"volgain" 			=> array("ver" => 1.4, "len" => $dlen, "type" => "num", "default" => "") 	);

$acct_settings = array(		"attach"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
				"attachfmt"			=> array("ver" => 1.4, "len" => 20, "type" => "char"),
				"backupdeleted"			=> array("ver" => 1.6, "len" => 0,  "type" => "num"),
	               		"callback"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"callmenum"			=> array("ver" => 1.2, "len" => 0,  "type" => "num"),
				"delete"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
				"dialout"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
	               		"email"                         => array("ver" => 1.2, "len" => 80, "type" => "char"),
	               		"envelope"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
	               		"exitcontext"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
	               		"forcegreetings"		=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
	               		"forcename"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
	               		"hidefromdir"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
	               		"imappassword"			=> array("ver" => 1.4, "len" => 80, "type" => "char"),
	               		"imapuser"			=> array("ver" => 1.4, "len" => 80, "type" => "char"),
	               		"language"			=> array("ver" => 1.4, "len" => 20, "type" => "char"),
				"maxmessage"			=> array("ver" => 1.2, "len" => 0, "type" => "num"),
				"maxmsg"			=> array("ver" => 1.2, "len" => 0,  "type" => "num"),
	               		"maxsecs"			=> array("ver" => 1.6, "len" => 0,  "type" => "num"),
	               		"moveheard"			=> array("ver" => 1.6, "len" => 0,  "type" => "flag"),
				"name"		                => array("ver" => 1.2, "len" => 80, "type" => "char"),
	               		"operator"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
	               		"pager"                         => array("ver" => 1.2, "len" => 80, "type" => "char"),
				"pwd"                           => array("ver" => 1.2, "len" => 80, "type" => "char"),
				"review"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
	               		"saycid"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
	               		"sayduration"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
	               		"saydurationm"			=> array("ver" => 1.2, "len" => 0,  "type" => "num"),
	               		"sendvoicemail"			=> array("ver" => 1.2, "len" => 0,  "type" => "flag"),
				"serveremail"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
	               		"tempgreetwarn"			=> array("ver" => 1.4, "len" => 0,  "type" => "flag"),
	               		"tz"				=> array("ver" => 1.2, "len" => 80, "type" => "char"),
				"vmcontext"			=> array("ver" => 1.2, "len" => 80, "type" => "char"),
	               		"volgain"			=> array("ver" => 1.4, "len" => 0,  "type" => "num") );

$tooltips = array("tz" 	    => array("name" 				=> _("Timezone definition name"),
				     "def"				=> _("Time announcement for message playback"),
				     "del"				=> _("Remove the timezone definition")),
	          "general" => array("adsifdn"				=> _("The ADSI feature descriptor number to download to"),
				     "adsisec"				=> _("The ADSI security lock code"),
				     "adsiver"				=> _("The ADSI Voicemail application version number."),
				     "attach" 				=> _("Option to attach Voicemails to email."),
				     "authpassword"	 		=> _("IMAP server master password."),
				     "authuser" 			=> _("IMAP server master username."),
				     "backupdeleted"			=> _("No. of deleted messages saved per mailbox (can be a number or yes/no, yes meaning MAXMSG, no meaning 0)."),
				     "callback"				=> _("Context to call back from; if not listed, calling the sender back will not be permitted."),
				     "charset"				=> _("The character set for Voicemail messages"),
				     "cidinternalcontexts"		=> _("Comma separated list of internal contexts to use caller ID."),
				     "dialout"				=> _("Context to dial out from [option 4 from the advanced menu] if not listed, dialing out will not be permitted."),
				     "emailbody"			=> _("Email body."),
				     "emaildateformat"			=> _("Load date format config for Voicemail mail."),
				     "emailsubject"			=> _("Email subject"),
				     "maxsilence"			=> _("How many seconds of silence before we end the recording"),
				     "envelope"				=> _("Turn on/off envelope playback before message playback. [ON by default] This does NOT affect option 3,3 from the advanced options menu."),
				     "exitcontext"			=> _("Context to check for handling * or 0 calls to operator. \"Operator Context\""),
				     "expungeonhangup"			=> _("Expunge on exit."),
				     "externnotify"			=> _("External Voicemail notify application."),
				     "externpass"			=> _("External password changing command (overrides externpassnotify)."),
				     "externpassnotify"			=> _("Command specified runs after a user changes his password."),
				     "forcegreetings"			=> _("Force new user to record greetings (the same as forcename, except for recording greetings).  The default is \"no\"."),
				     "forcename"			=> _("Force a new user to record their name.  A new user is determined by the password being the same as the mailbox number.  The default is \"no\"."),
				     "format"				=> _("Formats for writing Voicemail.  Note that when using IMAP storage for Voicemail, only the first format specified will be used."),
				     "fromstring"			=> _("From: string for email"),
				     "imapclosetimeout"			=> _("For IMAP storage"),
				     "imapflags"			=> _("IMAP server flags."),
				     "imapfolder"			=> _("IMAP Voicemail folder."),
				     "imapgreetings"			=> _("If using IMAP storage, specify whether Voicemail greetings should be stored via IMAP. If no, then greetings are stored as if IMAP storage were not enabled"),
				     "greetingsfolder"			=> _("(yes/no) If imapgreetings=yes, then specify which folder to store your greetings in. If you do not specify a folder, then INBOX will be used."),
				     "imapopentimeout"			=> _("For IMAP storage - TCP open timeout in seconds"),
				     "imapparentfolder"			=> _("Set the parent folder (default is to have no parent folder set)."),
				     "imapport"				=> _("IMAP server port."),
				     "imapreadtimeout"			=> _("For IMAP storage - TCP read timeout in seconds"),
				     "imapserver"			=> _("IMAP server address."),
				     "imapwritetimeout"			=> _("For IMAP storage - TCP write timeout in seconds"),
				     "listen-control-forward-key"	=> _("Customize the key that fast-forwards message playback"),
				     "listen-control-pause-key"		=> _("Customize the key that pauses/unpauses message playback"),
				     "listen-control-restart-key"	=> _("Customize the key that restarts message playback"),
				     "listen-control-reverse-key"	=> _("Customize the key that rewinds message playback"),
				     "listen-control-stop-key"		=> _("Customize the key that stops message playback"),
				     "mailcmd"				=> _("Mail command."),
				     "maxgreet"				=> _("Max message greeting length."),
				     "maxlogins"			=> _("Max failed login attempts."),
				     "maxmessage" 			=> _("Max message time length."),
				     "maxsecs"				=> _("Max message time length."),
				     "maxmsg"				=> _("Maximum number of messages per folder.  If not specified, a default value (100) is used.  Maximum value for this option is 9999."),
				     "minsecs"				=> _("Min message time length - maxsilence should be less than minsecs or you may get empty messages."),
				     "moveheard"			=> _("Move heard messages to the 'Old' folder automatically.  Defaults to on."),
				     "nextaftercmd"			=> _("Skip to the next message after save/delete."),
				     "obdcstorage"			=> _("The value of odbcstorage is the database connection configured in res_odbc.conf."),
				     "odbctable"			=> _("The default table for ODBC Voicemail storage is voicemessages."),
				     "operator"				=> _("Operator break. Allow sender to hit 0 before/after/during  leaving a Voicemail to reach an operator  [OFF by default]"),
				     "pagerbody"			=> _("Body of message sent to pager."),
				     "pagerfromstring"			=> _("From: string sent to pager."),
				     "pagersubject"			=> _("Subject sent to pager."),
				     "pbxskip"				=> _("Skip the \"[PBX]:\" string from the message title"),
				     "pollfreq"				=> _("If the \"pollmailboxes\" option is enabled, this option sets the polling frequency.  The default is once every 30 seconds."),
				     "pollmailboxes"			=> _("If mailboxes are changed anywhere outside of app_voicemail, then this option must be enabled for MWI to work.  This enables polling mailboxes for changes.  Normally, it will expect that changes are only made when someone called in to one of the Voicemail applications. Examples of situations that would require this option are web interfaces to Voicemail or an email client in the case of using IMAP storage."),
				     "review"				=> _("Allow sender to review/rerecord their message before saving it [OFF by default]"),
				     "saycid"				=> _("Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left. If not described, or set to no, it will be in the envelope."),
				     "sayduration"			=> _("Turn on/off saying duration information before the message playback. [ON by default]"),
				     "saydurationm"			=> _("Specify in minutes the minimum duration to say. Default is 2 minutes."),
				     "searchcontexts"			=> _("Yes to search all contexts, no to search current context (if one is not specified)."),
				     "sendvoicemail"			=> _("Send Voicemail message. If not listed, sending messages from inside Voicemail will not be permitted."),
				     "serveremail"			=> _("Who the e-mail notification should appear to come from"),
				     "silencethreshold"			=> _("Silence threshold (what we consider silence: the lower, the more sensitive)"),
				     "skipms"				=> _("How many milliseconds to skip forward/back when rew/ff in message playback"),
				     "smdienable"			=> _("Enable Simple Message Desk Interface (SMDI) integration"),
				     "smdiport"				=> _("Valid port as specified in smdi.conf for using smdi for external notification."),
				     "tempgreetwarn"			=> _("Temporary greeting reminder."),
				     "usedirectory"			=> _("Permit finding entries for forward/compose from the directory"),
				     "userscontext"			=> _("User context is where entries from users.conf are registered.  The default value is 'default'"),
				     "vm-mismatch"			=> _("Customize which sound file is used instead of the default prompt that says: \"The passwords you entered and re-entered did not match.  Please try again.\""),
				     "vm-newpassword"			=> _("Customize which sound file is used instead of the default prompt that says: \"Please enter your new password followed by the pound key.\""),
				     "vm-passchanged"			=> _("Customize which sound file is used instead of the default prompt that says: \"Your password has been changed.\""),
				     "vm-password"			=> _("Customize which sound file is used instead of the default prompt that says: \"password\""),
				     "vm-reenterpassword"		=> _("Customize which sound file is used instead of the default prompt that says: \"Please re-enter your password followed by the pound key\""),
				     "volgain"				=> _("Emails bearing the Voicemail may arrive in a volume too quiet to be heard.  This parameter allows you to specify how much gain to add to the message when sending a Voicemail. NOTE: sox must be installed for this option to work.")
				     ),
		  "account" => array("pwd" 				=> _("This is the password used to access the Voicemail system.<br /><br />This password can only contain numbers.<br /><br />A user can change the password you enter here after logging into the Voicemail system (*98) with a phone."),
				     "attach" 				=> _("Option to attach Voicemails to email."),
				     "attachfmt"			=> _("Which format of audio file to attach to the email."),
				     "backupdeleted" 			=> _("No. of deleted messages saved per mailbox (can be a number or yes/no, yes meaning MAXMSG, no meaning 0)."),
				     "callback" 			=> _("Context to call back from; if not listed, calling the sender back will not be permitted."),
				     "delete" 				=> _("After notification, the Voicemail is deleted from the server. [per-mailbox only] This is intended for use with users who wish to receive their Voicemail ONLY by email. Note:  deletevoicemail is provided as an equivalent option for Realtime configuration."),
				     "dialout" 				=> _("Context to dial out from [option 4 from the advanced menu] if not listed, dialing out will not be permitted."),
				     "email"				=> _("The email address that Voicemails are sent to."),
				     "envelope" 			=> _("Turn on/off envelope playback before message playback. [ON by default] This does NOT affect option 3,3 from the advanced options menu."),
				     "exitcontext" 			=> _("Context to check for handling * or 0 calls to operator. \"Operator Context\""),
				     "forcegreetings" 			=> _("Force new user to record greetings (the same as forcename, except for recording greetings).  The default is \"no\"."),
				     "forcename" 			=> _("Force a new user to record their name.  A new user is determined by the password being the same as the mailbox number.  The default is \"no\"."),
				     "fullname"				=> _("Name of Voicemail account"),
				     "hidefromdir"			=> _("Hide this mailbox from the directory produced by app_directory. The default is \"no\"."),
				     "imappassword" 			=> _("IMAP password."),
				     "imapuser" 			=> _("IMAP user."),
				     "language" 			=> _("Asterisk language code"),
				     "maxmsg" 				=> _("Maximum number of messages per folder.  If not specified, a default value (100) is used.  Maximum value for this option is 9999."),
				     "maxmessage" 			=> _("Max message time length."),
				     "maxsecs" 				=> _("Max message time length."),
				     "moveheard" 			=> _("Move heard messages to the 'Old' folder automatically.  Defaults to on."),
				     "name"				=> _("Name of account/user"),
				     "operator" 			=> _("Operator break. Allow sender to hit 0 before/after/during  leaving a Voicemail to reach an operator  [OFF by default]"),
				     "pager"				=> _("Pager/mobile email address that short Voicemail notifications are sent to."),
				     "review"				=> _("Allow sender to review/rerecord their message before saving it [OFF by default]"),
				     "saycid" 				=> _("Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left. If not described, or set to no, it will be in the envelope."),
				     "sayduration" 			=> _("Turn on/off saying duration information before the message playback. [ON by default]"),
				     "saydurationm" 			=> _("Specify in minutes the minimum duration to say. Default is 2 minutes."),
				     "sendvoicemail" 			=> _("Send Voicemail message. If not listed, sending messages from inside Voicemail will not be permitted."),
				     "serveremail"			=> _("Who the e-mail notification should appear to come from"),
				     "tempgreetwarn" 			=> _("Remind the user that their temporary greeting is set"),
				     "tz" 				=> _("Timezone from zonemessages context.  Irrelevant if envelope=no."),
				     "vmcontext"			=> _("This is the Voicemail Context which is normally set to default. Do not change unless you understand the implications."),
				     "volgain" 				=> _("Emails bearing the Voicemail may arrive in a volume too quiet to be heard.  This parameter allows you to specify how much gain to add to the message when sending a Voicemail. NOTE: sox must be installed for this option to work."),
				     "callmenum" 			=> _("Call me number. Can be used from within ARI.")
				     )
		 );

/* End settings options */

/* Data needed to display correct page. */
$type		= (isset($_REQUEST["type"]) && $_REQUEST["type"] != "")?$_REQUEST["type"]:"setup";
$display	= (isset($_REQUEST["display"]) && $_REQUEST["display"] != "")?$_REQUEST["display"]:"voicemail";
if (isset($_REQUEST["updated"])) {
	if ($_REQUEST["updated"] == "true") {
		$update_flag = true;
	} else {
		$update_flag = false;
	}
} else {
	$update_flag = null;
}
$action		= isset($_REQUEST["action"])?$_REQUEST["action"]:"";
if (isset($_REQUEST["ext"])) {
		$extension = $_REQUEST["ext"];
		if (isset($vmail_info["activated_info"][$extension])) {
			$context = $vmail_info["activated_info"][$extension];
		} else if (isset($vmail_info["unactivated_info"][$extension])) {
			$context = $vmail_info["unactivated_info"][$extension];
		} else {
			// Force Voicemail to "system" mode by clearing context and extension values
			$context   = "";
			$extension = "";
		}
} else {
	// System mode
	$context   = "";
	$extension = "";
}

/* Special handling for action specified by form submission. */
if ($action == "Go") {
	/* This is for viewing a particular context's usage. */
	$action = "usage";
	/* Clear extension */
	$extension = "";
} else if ($action == "Submit") {
	/* "Submit" is for performing some kind of update to settings (for page type of general, account OR timezone settings) OR to the files on disk. */
	/* page_type can be settings, account, tz or usage. */
	$action = (isset($_REQUEST["page_type"]) && !empty($_REQUEST["page_type"]))?$_REQUEST["page_type"]:"";;
	$need_update = true;
} else {
	$need_update = false;
}

/* If no action specified, default to a view of the entire system's usage. */
if (empty($action)) {
	$context     = "";
	$extension   = "";
	$need_update = false;
	$action      = "usage";
}

/* Need to generate rnav div menu */
/* system-wide rnav menu (lists all accounts) */
$rnav_list = implode("\n", $rnav_entries);

$rnav_menu = "<ul name='voicemail_menu' id='voicemail_menu' style='max-width:400px;'>\n" . $rnav_list . "</ul>";
$title	  = voicemail_get_title($action, $context, $extension);
$output   = "";
$output   .= "<div class='rnav'>$rnav_menu</div>";
$output   .= "<div class='content'>\n";
$output   .= "<form name='frm_voicemail' action='" . $_SERVER['PHP_SELF'] . "' method='post'>";
$output   .= "<input type='hidden' name='type' id='type' value='$type' />";
$output   .= "<input type='hidden' name='display' id='display' value='$display' />";
$output   .= "<input type='hidden' name='ext' id='ext' value='$extension' />";
$output   .= "<input type='hidden' name='page_type' id='page_type' value='$action' />";
/* Javascript for remembering scroll position of rnav menu */
$output .= "<script type='text/javascript'><!--\n";
$output .= "\n
function find_in_menu(id) {
	var objToFind   = document.getElementById(id);
	document.getElementById('voicemail_menu').scrollTop = objToFind.offsetTop - 2 * objToFind.offsetHeight;
}";
if ($extension != "") {
	$output .= "\n\n" . "find_in_menu('voicemail_list_" . $extension . "');\n";
}
$output .= "\n--></script>";
/* END of Javascript for remembering scroll position of rnav menu */

$sys_view_flag = empty($extension)?true:false;
$settings_link = "<a" . (($sys_view_flag && $action == "settings")?" style='color:#ff9933;' ":" ") . "href='config.php?type=$type&display=$display&action=settings'>Settings</a>";
$usage_link    = "<a" . (($sys_view_flag && $action == "usage")?" style='color:#ff9933;' ":" ") . "href='config.php?type=$type&display=$display&action=usage'>Usage</a>";
$tzone_link    = "<a" . (($sys_view_flag && $action == "tz")?" style='color:#ff9933;' ":" ") . "href='config.php?type=$type&display=$display&action=tz'>Timezone Definitions</a>";
$output        .= "<table border='0' cellpadding='0.3px' cellspacing='2px'>";
$output	       .= "<tr><td colspan='3'>$title</td></tr>";
$output        .= "<tr><td><h5>" . _("System View Links:") . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</h5></td><td colspan='2'><h5>$settings_link&nbsp;&nbsp;|&nbsp;&nbsp;$usage_link&nbsp;&nbsp;|&nbsp;&nbsp;$tzone_link</h5></td></tr>";

if ($need_update && $action != 'usage') {
	/* set args */
	$args = array();
	if (voicemail_update_settings($action, $context, $extension, $_REQUEST)) {
		$url = "config.php?type=$type&display=$display&action=$action&ext=$extension&updated=true";
		redirect($url);
	} else {
		$url = "config.php?type=$type&display=$display&action=$action&ext=$extension&updated=false";
		redirect($url);
	}
}
switch ($action) {
	case "tz":
		/* get tz settings */
		$settings = voicemail_get_settings($uservm, $action, $extension);
		$output .= "<tr><td colspan='2'><hr /></td><td></td></tr>";
		$output .= "<tr><td style='max-width: 60px' colspan='2'>" . _("A timezone definition specifies how the Voicemail system announces the time.") . "</td><td></td></tr>";
		$output .= "<tr><td style='max-width: 60px' colspan='2'>" . _("For example, the time a message was left will be announced according to the user's timezone on message playback.") . "</td><td></td></tr>";
		$output .= "<tr><td></td><td></td></tr>";
		$output .= "<tr><td style='max-width: 60px' colspan='2'><b>" . _("Entries below will be written to Voicemail configuration as-is.") . "</b></td><td></td></tr>";
		$output .= "<tr><td style='max-width: 60px' colspan='2'><b>" . _("Please be sure to follow the format for timezone definitions described below.") . "</b></td></tr>";
		$output .= "<tr><td colspan='2'><hr /></td><td></td></tr>";
		$output .= "<tr><td><a href='#' class='info'><h4>" . _("Name") . "</h4><span>" . $tooltips["tz"]["name"] . "</span></a></td><td><a href='#' class='info'><h4>" . _("Timezone Definition") . "</h4><span>" . $tooltips["tz"]["def"] . "</span></a>";
		$output .= "</td></tr>"; 
		if (is_array($settings) && !empty($settings)) {
			foreach ($settings as $key => $val) {
				$output .= "<tr>";
				$output .= "<td>$key</td>";
				$output .= "<td><input size='50' type='text' name='tz__$key' id='tz__$key' tabindex='1' value=\"$val\" />";
				$output .= "&nbsp;&nbsp;&nbsp;&nbsp;<input type='checkbox' name='tzdel__$key' id='tzdel__$key' value='true' />&nbsp;&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . $tooltips["tz"]["del"] . "</span></a></td></tr>";
			}
		}
		$output .= "<tr><td coslpan='2'></td></tr>";
		$output .= "<tr><td><a href='#' class='info'><h4>" . _("New Name") . "</h4><span>" . $tooltips["tz"]["name"] . "</span></a></td><td><a href='#' class='info'><h4>" . _("New Timezone Definition") . "</h4><span>" . $tooltips["tz"]["def"] . "</span></a></td>";
		$output .= "<tr><td><input size='10' type='text' name='tznew_name' id='tznew_name' tabindex='1' value='' /></td>";
		$output .= "<td><input size='50' type='text' name='tznew_def' id='tznew_def' tabindex='1' value='' /></td></tr>";

		$update_notice = ($update_flag == false)?"&nbsp;&nbsp;<b><u>UPDATE FAILED</u></b>":"";
		$update_notice = ($update_flag == true)?"&nbsp;&nbsp;<b><u>UPDATE COMPLETED</u></b>":"";
		$output .= "<tr><td></td><td colspan='2'><input type='submit' name='action' id='action' value='Submit' />" . $update_notice . "</td></tr>";

		$output .= "<tr><td colspan='2'><hr /></td></tr>";
		$output .= "<tr><td style='max-width: 60px' colspan='2'>" . _("Timezone definition format is: ") . "&nbsp;&nbsp;<b style='font-family:courier;'>" . _("timezone|values") . "</b></td><td></td></tr>";
		$output .= "<tr><td style='max-width: 60px' colspan='2'><br /><b>" . _("<i>Timezones</i> are listed in /usr/share/zoneinfo") . "</td></tr>";
		$output .= "<tr><td style='max-width: 60px' colspan='2'><b>" . _("The <i>values</i> supported in the timezone definition string include:") . "</b></td></tr>" .
		"<tr><td>" . _("'filename'") . "</td><td style='max-width: 60px' colspan='2'>" . _("The name of a sound file (the file name must be single-quoted)") . "</td></tr>" .
		"<tr><td>" . _("variable") . "</td><td style='max-width: 60px' colspan='2'>" . _("A variable to be substituted (see below for supported variable values)") . "</td></tr>";
		$output .= "<tr><td style='max-width: 60px' colspan='2'><b>" . _("Supported <i>variables</i>:") . "</b></td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("A or a") 	 . "</td><td style='max-width: 60px' colspan='2'>" . _("Day of week (Saturday, Sunday, ...)") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("B or b or h") . "</td><td style='max-width: 60px' colspan='2'>" . _("Month name (January, February, ...)") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("d or e") 	 . "</td><td style='max-width: 60px' colspan='2'>" . _("numeric day of month (first, second, ..., thirty-first)") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("Y") 		 . "</td><td style='max-width: 60px' colspan='2'>" . _("Year") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("I or l") 	 . "</td><td style='max-width: 60px' colspan='2'>" . _("Hour, 12 hour clock") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("H") 		 . "</td><td style='max-width: 60px' colspan='2'>" . _("Hour, 24 hour clock (single digit hours preceded by \"oh\")") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("k") 		 . "</td><td style='max-width: 60px' colspan='2'>" . _("Hour, 24 hour clock (single digit hours NOT preceded by \"oh\")") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("M") 		 . "</td><td style='max-width: 60px' colspan='2'>" . _("Minute, with 00 pronounced as \"o'clock\"") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("N") 		 . "</td><td style='max-width: 60px' colspan='2'>" . _("Minute, with 00 pronounced as \"hundred\" (US military time)") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("P or p") 	 . "</td><td style='max-width: 60px' colspan='2'>" . _("AM or PM") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("Q") 		 . "</td><td style='max-width: 60px' colspan='2'>" . _("\"today\", \"yesterday\" or ABdY") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("q") 		 . "</td><td style='max-width: 60px' colspan='2'>" . _("\"\" (for today), \"yesterday\", weekday, or ABdY") . "</td></tr>" .
			   "<tr><td style='max-width: 60px'>" . _("R") 		 . "</td><td style='max-width: 60px' colspan='2'>" . _("24 hour time, including minute") . "</td></tr>";
		break;
	case "bsettings":
	case "settings":
		/* get settings */
		$settings = voicemail_get_settings($uservm, $action, $extension);
		/* Get Asterisk version. */
		$ast_info = engine_getinfo();
		$version = $ast_info["version"];
		$text_size = 40;

		if (!empty($extension)) {
			$acct_title_links  = "<tr><td><h5>" . _("Account View Links:") . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</h5></td><td colspan='2'><h5><a href='config.php?type=$type&display=$display&action=bsettings&ext=$extension'>" . _("Settings") . "</a>&nbsp;&nbsp;|&nbsp;&nbsp;";
			$acct_title_links .= "<a href='config.php?type=$type&display=$display&action=usage&ext=$extension'>" . _("Usage") . "</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a style='color:#ff9933;' href='config.php?type=$type&display=$display&action=settings&ext=$extension'>" . _("Advanced Settings") . "</a></h5></td></tr><tr><td colspan='2'><hr /></td></tr>";
			$display_settings = $acct_settings;
			$display_tips     = $tooltips["account"];
			$id_prefix        = "acct";
		} else {
			$acct_title_links = "";
			$output .= "<tr><td colspan='2'><hr /></td></tr>";
			$display_settings = $gen_settings;
			$display_tips     = $tooltips["general"];
			$id_prefix        = "gen";
		}
		$display_name_row = "";
		if ($action == "bsettings") {
			# Overwrite account title links
			$acct_title_links = "<tr><td><h5>" . _("Account View Links:") . "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</h5></td><td colspan='2'><h5><a style='color:#ff9933;' href='config.php?type=$type&display=$display&action=bsettings&ext=$extension'>" . _("Settings") . "</a>&nbsp;&nbsp;|&nbsp;&nbsp;";
			$acct_title_links .= "<a href='config.php?type=$type&display=$display&action=usage&ext=$extension'>" . _("Usage") . "</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href='config.php?type=$type&display=$display&action=settings&ext=$extension'>" . _("Advanced Settings") . "</a></h5></td></tr><tr><td colspan='2'><hr /></td></tr>";
			/* Display account name */
			$display_name = isset($settings["name"])?$settings["name"]:_("No name defined; this is configured from the Extensions or Users page.");
			$display_name_row = "<tr><td><a href='#' class='info'>" . _("Name") . "<span>" . $tooltips["account"]["name"] . "</span></a></td><td>&nbsp;&nbsp;&nbsp;&nbsp;" . $display_name . "</td></tr>";
			# Override display settings, so only the basic account settings appear.
			unset($display_settings);
			$basic_settings["pwd"] 		= isset($settings["pwd"])?$settings["pwd"]:"";
			$basic_settings["email"] 	= isset($settings["email"])?$settings["email"]:"";
			$basic_settings["pager"] 	= isset($settings["pager"])?$settings["pager"]:"";
			$basic_settings["attach"] 	= isset($settings["attach"])?$settings["attach"]:"";
			$basic_settings["saycid"] 	= isset($settings["saycid"])?$settings["saycid"]:"";
			$basic_settings["envelope"] 	= isset($settings["envelope"])?$settings["envelope"]:"";
			$basic_settings["delete"] 	= isset($settings["delete"])?$settings["delete"]:"";
			$basic_settings["callmenum"] 	= isset($settings["callmenum"])?$settings["callmenum"]:"";
			unset($settings);
			$settings			= $basic_settings;
			$display_settings["pwd"] 	= $acct_settings["pwd"];
			$display_settings["email"] 	= $acct_settings["email"];
			$display_settings["pager"] 	= $acct_settings["pager"];
			$display_settings["attach"] 	= $acct_settings["attach"];
			$display_settings["saycid"] 	= $acct_settings["saycid"];
			$display_settings["envelope"] 	= $acct_settings["envelope"];
			$display_settings["delete"] 	= $acct_settings["delete"];
			$display_settings["callmenum"] 	= $acct_settings["callmenum"];
			$opt_headings = $display_settings;
			$opt_headings["pwd"]		= _("Voicemail Password");
			$opt_headings["email"]		= _("Email Address");
			$opt_headings["pager"]		= _("Pager Email Address");
			$opt_headings["attach"]		= _("Email Attachment");
			$opt_headings["saycid"]		= _("Play CID");
			$opt_headings["envelope"]	= _("Play Envelope");
			$opt_headings["delete"]		= _("Delete Voicemail");
			$opt_headings["callmenum"]	= _("Call-Me Number");
		}
		$output .= $acct_title_links . $display_name_row;

		foreach ($display_settings as $key => $descrip) {
			$tooltip = isset($display_tips[$key])?$display_tips[$key]:"";
			$len = ($descrip["len"] > 0)?$descrip["len"]:$dlen;
			$id = $id_prefix . "__" . $key;
			if (isset($settings[$key]) || ($version >= $descrip["ver"])) {
				$val = isset($settings[$key])?$settings[$key]:$descrip["default"];
				unset($settings[$key]);
				$opt_name = ($action == "bsettings")?$opt_headings[$key]:$key;
				$output .= "<tr><td><a href='#' class='info'>$opt_name<span>$tooltip</span></a></td>";
				/* check box or not */
				if ($descrip["type"] == "flag") {
					switch ($val) {
						case "yes":
							$yes_selected = "checked=checked";
							$no_selected  = "";
							$undef_selected = "";
							break;
						case "no":
							$yes_selected = "";
							$no_selected = "checked=checked";
							$undef_selected = "";
							break;
						default:
							$yes_selected = "";
							$no_selected = "";
							$undef_selected = "checked=checked";
							break;
					}
					$output .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;<input type='radio' name='$id' id='$id' tabindex='1' value='yes' $yes_selected />" . _("yes");
					$output .= "<input type='radio' name='$id' id='$id' tabindex='1' value='no' $no_selected />" . _("no");
					$output .= "</td></tr>";
				} else {
					$text_type = ($key == "pwd" || $key == "authpassword")?"password":"text";
					$output .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;<input size='$text_size' maxlength='$len' type='$text_type' name='$id' id='$id' tabindex='1' value=\"$val\" /></td></tr>";
				}
			}
			unset($id);
		}
		/* Any additional setting? */
		unset($settings["enabled"]);	# ignore this value; we will not enable/disable from Voicemail
		if (is_array($settings) && !empty($settings)) {
			foreach ($settings as $key => $val) {
				$id = $id_prefix . "__" . $key;
				# no tooltip available
				$output .= "<tr><td>$key</td>";
				$output .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;<input size='$text_size' type='text' name='$id' id='$id' tabindex='1' value=\"$val\" /></td></tr>";
			}
		}
		$update_notice = ($update_flag == false)?"&nbsp;&nbsp;<b><u>UPDATE FAILED</u></b>":"";
		$update_notice = ($update_flag == true)?"&nbsp;&nbsp;<b><u>UPDATE COMPLETED</u></b>":"";
		$output .= "<tr><td></td><td colspan='2'>&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='action' id='action' value='Submit' />" . $update_notice . "</td></tr>";
		break;
	case "usage":
		/* Usage information and options available for system-wide,
		   and individual account views.
		*/
		$scope = voicemail_get_scope($extension);
		if ($need_update) {
			voicemail_update_usage($vmail_info, $context, $extension, $_REQUEST);
			if (!empty($extension)) {
				$url = "config.php?type=$type&display=$display&ext=$extension&action=$action&updated=true";
			} else {
				$url = "config.php?type=$type&display=$display&action=$action&updated=true";
			}
			redirect($url);
		}

		voicemail_get_usage($vmail_info, $scope, $acts_total, $acts_act, $acts_unact, $disabled_count,
 	                                       $msg_total, $msg_in, $msg_other,
					       $name, $unavail, $busy, $temp, $abandoned,
					       $storage,
					       $context, $extension);
		$lp = "<tr><td colspan='3'><br /></td></tr>";
		if ($scope == "system") {
			$output .= "<tr><td colspan='3'><hr /></td></tr>";
			$accounts_row = "<tr><td><a href='#' class='info'>" . _("Number of Accounts:") . "<span>" . _("Total ( Activated / Unactivated / Disabled )") . "</span></a></td>";
			$accounts_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$acts_total&nbsp;&nbsp;(&nbsp;$acts_act&nbsp;/&nbsp;$acts_unact&nbsp;/&nbsp;$disabled_count&nbsp;)</td></tr>";
			$accounts_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$msg_row = "<tr><td><a href='#' class='info'>" . _("Number of Messages:") . "<span>" . _("Total ( Messages in inboxes / Messages in other folders )") . "</span></a></td>";
			$msg_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$msg_total&nbsp;&nbsp;(&nbsp;$msg_in&nbsp;/&nbsp;$msg_other&nbsp;)</td>";
			$msg_row .= "<td><input type='checkbox' name='del_msgs' id='del_msgs' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove all messages") . "</span></a></td></tr>";
			$msg_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$name_row = "<tr><td><a href='#' class='info'>" . _("Recorded Names:") . "<span>" . _("Number of recorded name greetings") . "</span></a></td>";
			$name_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$name</td>";
			$name_row .= "<td><input type='checkbox' name='del_names' id='del_names' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove all recorded names") . "</span></a></td></tr>";
			$name_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$unavail_row = "<tr><td><a href='#' class='info'>" . _("Unavailable Greetings:") . "<span>" . _("Number of recorded unavailable greetings") . "</span></a></td>";
			$unavail_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$unavail</td>";
			$unavail_row .= "<td><input type='checkbox' name='del_unavail' id='del_unavail' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove all unavailable greetings") . "</span></a></td></tr>";
			$unavail_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$busy_row = "<tr><td><a href='#' class='info'>" . _("Busy Greetings:") . "<span>" . _("Number of recorded busy greetings") . "</span></a></td>";
			$busy_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$busy</td>";
			$busy_row .= "<td><input type='checkbox' name='del_busy' id='del_busy' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove all busy greetings") . "</span></a></td></tr>";
			$busy_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$temp_row = "<tr><td><a href='#' class='info'>" . _("Temporary Greetings:") . "<span>" . _("Number of recorded temporary greetings") . "</span></a></td>";
			$temp_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$temp</td>";
			$temp_row .= "<td><input type='checkbox' name='del_temp' id='del_temp' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove all temporary greetings") . "</span></a></td></tr>";
			$temp_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$abandoned_row = "<tr><td><a href='#' class='info'>" . _("Abandoned Greetings:") . "<span>" . _("Number of abandoned greetings. Such greetings were recorded by the user but were NOT accepted, so the sound file remains on disk but is not used as a greeting.") . "</span></a></td>";
			$abandoned_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$abandoned</td>";
			$abandoned_row .= "<td><input type='checkbox' name='del_abandoned' id='del_abandoned' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove all abandoned greetings (> 1 day old)") . "</span></a></td></tr>";
			$abandoned_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$storage_row = "<tr><td><a href='#' class='info'>" . _("Storage Used:") . "<span>" . _("Disk space currently in use by Voicemail data") . "</span></a></td>";
			$storage_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$storage</td>";
			$storage_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";
			
			$output .= $lp . $accounts_row . $msg_row . $name_row . $unavail_row . $busy_row . $temp_row . $abandoned_row . $storage_row;			
		} else {
			$accounts_row = "";
			$output .= "<tr><td><h5>" . _("Account View Links:") . "</h5></td><td colspan='3'><h5><a href='config.php?type=$type&display=$display&action=bsettings&ext=$extension'>" . _("Settings") . "</a>&nbsp;&nbsp;|&nbsp;&nbsp;";
			$output .= "<a style='color:#ff9933;' href='config.php?type=$type&display=$display&action=usage&ext=$extension'>" . _("Usage") . "</a>&nbsp;&nbsp;|&nbsp;&nbsp;<a href='config.php?type=$type&display=$display&action=settings&ext=$extension'>" . _("Advanced Settings") . "</a></h5></td></tr><tr><td colspan='3'><hr /></td></tr>";

			$msg_row = "<tr><td><a href='#' class='info'>" . _("Number of Messages:") . "<span>" . _("Total ( Messages in inboxes / Messages in other folders )") . "</span></a>&nbsp;&nbsp;&nbsp;</td>";
			$msg_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$msg_total&nbsp;&nbsp;(&nbsp;$msg_in&nbsp;/&nbsp;$msg_other&nbsp;)</td>";
			$msg_row .= "<td><input type='checkbox' name='del_msgs' id='del_msgs' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove all messages") . "</span></a></td></tr>";
			$msg_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			/* Get timestamps, if applicable */
			$ts = voicemail_get_greeting_timestamps($name, $unavail, $busy, $temp, $context, $extension);
			$name_ts = ($ts["name"] > 0)?$ts["name"]:"";
			$unavail_ts = ($ts["unavail"] > 0)?$ts["unavail"]:"";
			$busy_ts = ($ts["busy"] > 0)?$ts["busy"]:"";
			$temp_ts = ($ts["temp"] > 0)?$ts["temp"]:"";

			/* Convert count of greetings to yes/no */
			$name = ($name > 0)?"<a href='#' class='info'>" . _("yes") . "<span>" . _("File timestamp: ") . $name_ts . "</span></a>":_("no");
			$name_row = "<tr><td><a href='#' class='info'>" . _("Recorded Name:") . "<span>" . _("Has a recorded name greeting?") . "</span></a>&nbsp;&nbsp;&nbsp;</td>";
			$name_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$name</td>";
			$name_row .= "<td><input type='checkbox' name='del_names' id='del_names' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove recorded name") . "</span></a>&nbsp;&nbsp;&nbsp;</td></tr>";
			$name_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$unavail = ($unavail > 0)?"<a href='#' class='info'>" . _("yes") . "<span>" . _("File timestamp: ") . $unavail_ts . "</span></a>":_("no");
			$unavail_row = "<tr><td><a href='#' class='info'>" . _("Unavailable Greeting:") . "<span>" . _("Has a recorded unavailable greeting?") . "</span></a>&nbsp;&nbsp;&nbsp;</td>";
			$unavail_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$unavail</td>";
			$unavail_row .= "<td><input type='checkbox' name='del_unavail' id='del_unavail' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove unavailable greeting") . "</span></a>&nbsp;&nbsp;&nbsp;</td></tr>";
			$unavail_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$busy = ($busy > 0)?"<a href='#' class='info'>" . _("yes") . "<span>" . _("File timestamp: ") . $busy_ts . "</span></a>":_("no");
			$busy_row = "<tr><td><a href='#' class='info'>" . _("Busy Greetings:") . "<span>" . _("Has a recorded busy greeting?") . "</span></a>&nbsp;&nbsp;&nbsp;</td>";
			$busy_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$busy</td>";
			$busy_row .= "<td><input type='checkbox' name='del_busy' id='del_busy' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove busy greeting") . "</span></a>&nbsp;&nbsp;&nbsp;</td></tr>";
			$busy_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$temp = ($temp > 0)?"<a href='#' class='info'>" . _("yes") . "<span>" . _("File timestamp: ") . $temp_ts . "</span></a>":_("no");
			$temp_row = "<tr><td><a href='#' class='info'>" . _("Temporary Greeting:") . "<span>" . _("Has a recorded temporary greeting?") . "</span></a>&nbsp;&nbsp;&nbsp;</td>";
			$temp_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$temp</td>";
			$temp_row .= "<td><input type='checkbox' name='del_temp' id='del_temp' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove temporary greeting") . "</span></a></td></tr>";
			$temp_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			# It is conceivable a user has more than one abandoned greeting.
			$abandoned_row = "<tr><td><a href='#' class='info'>" . _("Abandoned Greetings:") . "<span>" . _("Number of abandoned greetings. Such greetings were recorded by the user but were NOT accepted, so the sound file remains on disk but is not used as a greeting.") . "</span></a></td>";
			$abandoned_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$abandoned</td>";
			$abandoned_row .= "<td><input type='checkbox' name='del_abandoned' id='del_abandoned' value='true' />&nbsp;<a href='#' class='info'>" . _("Delete") . "<span>" . _("Remove all abandoned greetings (> 1 day old)") . "</span></a></td></tr>";
			$abandoned_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$storage_row = "<tr><td><a href='#' class='info'>" . _("Storage Used") . "<span>" . _("Disk space currently in use by Voicemail data") . "</span></a></td>";
			$storage_row .= "<td>&nbsp;&nbsp;&nbsp;&nbsp;$storage</td>";
			$storage_row .= "<tr><td colspan='3'><hr style='height:0.1px;' /></td></tr>";

			$output .= $lp . $msg_row . $name_row . $unavail_row . $busy_row . $temp_row . $abandoned_row . $storage_row;
		}

		$update_notice = ($update_flag == false)?"&nbsp;&nbsp;<b><u>UPDATE FAILED</u></b>":"";
		$update_notice = ($update_flag == true)?"&nbsp;&nbsp;<b><u>UPDATE COMPLETED</u></b>":"";
		$output .= "<tr><td></td><td colspan='2'>&nbsp;&nbsp;&nbsp;&nbsp;<input type='submit' name='action' id='action' value='Submit' />" . $update_notice . "</td></tr>";
		break;
	default:
		break;
}

$output .= "</table>";
$output .= "</form>";

echo $output;
?>
