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

class vmxObject {

	var $exten;

	// contstructor
	function vmxObject($myexten) {
		$this->exten = $myexten;
	}
		
	function isInitialized($mode="unavail") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			$vmx_state=trim($astman->database_get("AMPUSER",$this->exten."/vmx/$mode/state"));
			if (isset($vmx_state) && ($vmx_state == 'enabled' || $vmx_state == 'disabled') || $vmx_state == 'blocked') {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}
	function isEnabled($mode="unavail") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			$vmx_state=trim($astman->database_get("AMPUSER",$this->exten."/vmx/$mode/state"));
			if (isset($vmx_state) && ($vmx_state == 'enabled' || $vmx_state == 'disabled')) {
				return true;
			} else {
				return false;
			}
		}
		return false;
	}

	function disable() {
		$ret = $this->setState('blocked','unavail');
		return $this->setState('blocked','busy') && $ret;
	}

	function getState($mode="unavail") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			return trim($astman->database_get("AMPUSER",$this->exten."/vmx/$mode/state"));
		} else {
			return false;
		}
	}

	function setState($state="enabled", $mode="unavail") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			$astman->database_put("AMPUSER", $this->exten."/vmx/$mode/state", "$state");
			return true;
		} else {
			return false;
		}
	}

	function getVmPlay($mode="unavail") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			return (trim($astman->database_get("AMPUSER",$this->exten."/vmx/$mode/vmxopts/timeout")) != 's');
		} else {
			return false;
		}
	}

	function setVmPlay($opts=true, $mode="unavail") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			$val = $opts ? '' : 's';
			$astman->database_put("AMPUSER", $this->exten."/vmx/$mode/vmxopts/timeout", $val);
			return true;
		} else {
			return false;
		}
	}

	function hasFollowMe() {
		global $astman;
		if ($astman) {
			return ($astman->database_get("AMPUSER",$this->exten."/followme/ddial")) == "" ? false : true;
		} else {
			return false;
		}
	}

	function isFollowMe($digit="1", $mode="unavail") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			return $astman->database_get("AMPUSER",$this->exten."/vmx/$mode/$digit/ext") == 'FM'.$this->exten ? true : false;
		} else {
			return false;
		}
	}

	function setFollowMe($digit="1", $mode="unavail", $context='ext-findmefollow', $priority='1') {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			$astman->database_put("AMPUSER", $this->exten."/vmx/$mode/$digit/ext", "FM".$this->exten);
			$astman->database_put("AMPUSER", $this->exten."/vmx/$mode/$digit/context", $context);
			$astman->database_put("AMPUSER", $this->exten."/vmx/$mode/$digit/pri", $priority);
			return true;
		} else {
			return false;
		}
	}

	function getMenuOpt($digit="0", $mode="unavail") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			return trim($astman->database_get("AMPUSER",$this->exten."/vmx/$mode/$digit/ext"));
		} else {
			return false;
		}
	}

	function setMenuOpt($opt="", $digit="0", $mode="unavail", $context="from-internal", $priority="1") {
		global $astman;
		if ($astman && ($mode == "unavail" || $mode == "busy")) {
			if ($opt != "" && ctype_digit($opt)) {
				$astman->database_put("AMPUSER", $this->exten."/vmx/$mode/$digit/ext", $opt);
				$astman->database_put("AMPUSER", $this->exten."/vmx/$mode/$digit/context", $context);
				$astman->database_put("AMPUSER", $this->exten."/vmx/$mode/$digit/pri", $priority);
			} else {
				$astman->database_deltree("AMPUSER/".$this->exten."/vmx/$mode/$digit");
			}
			return true;
		} else {
			return false;
		}
	}
}

function voicemail_get_config($engine) {
	$modulename = 'voicemail';
	
	// This generates the dialplan
	global $ext;  
	switch($engine) {
		case "asterisk":
			if (is_array($featurelist = featurecodes_getModuleFeatures($modulename))) {
				foreach($featurelist as $item) {
					$featurename = $item['featurename'];
					$fname = $modulename.'_'.$featurename;
					if (function_exists($fname)) {
						$fcc = new featurecode($modulename, $featurename);
						$fc = $fcc->getCodeActive();
						unset($fcc);
						
						if ($fc != '')
							$fname($fc);
					} else {
						$ext->add('from-internal-additional', 'debug', '', new ext_noop($modulename.": No func $fname"));
						var_dump($item);
					}	
				}
			}
		break;
	}
}

function voicemail_myvoicemail($c) {
	global $ext;
	global $core_conf;

	$id = "app-vmmain"; // The context to be included

	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal

	$ext->add($id, $c, '', new ext_answer('')); // $cmd,1,Answer
	$ext->add($id, $c, '', new ext_wait('1')); // $cmd,n,Wait(1)
	$ext->add($id, $c, '', new ext_macro('user-callerid')); // $cmd,n,Macro(user-callerid)
	$ext->add($id, $c, '', new ext_macro('get-vmcontext','${AMPUSER}')); 
	$ext->add($id, $c, 'check', new ext_vmexists('${AMPUSER}@${VMCONTEXT}')); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_gotoif('$["${VMBOXEXISTSSTATUS}" = "SUCCESS"]', 'mbexist'));
	$ext->add($id, $c, '', new ext_vmmain('')); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_gotoif('$["${IVR_RETVM}" = "RETURN" & "${IVR_CONTEXT}" != ""]','playret'));
	$ext->add($id, $c, '', new ext_macro('hangupcall')); // $cmd,n,Macro(user-callerid)
	$ext->add($id, $c, 'mbexist', new ext_vmmain('${AMPUSER}@${VMCONTEXT}'),'check',101); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_gotoif('$["${IVR_RETVM}" = "RETURN" & "${IVR_CONTEXT}" != ""]','playret'));
	$ext->add($id, $c, '', new ext_macro('hangupcall')); // $cmd,n,Macro(user-callerid)
	$ext->add($id, $c, 'playret', new ext_playback('beep&you-will-be-transfered-menu&silence/1'));
	$ext->add($id, $c, '', new ext_goto('1','return','${IVR_CONTEXT}'));

	// Now add to sip_general_addtional.conf
	//
	if (isset($core_conf) && is_a($core_conf, "core_conf")) {
		$core_conf->addSipGeneral('vmexten',$c);
	}
}

function voicemail_dialvoicemail($c) {
	global $ext;

	$id = "app-dialvm"; // The context to be included

	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal

	$ext->add($id, $c, '', new ext_answer(''));
	$ext->add($id, $c, 'start', new ext_wait('1'));
	$ext->add($id, $c, '', new ext_noop($id.': Asking for mailbox'));
	$ext->add($id, $c, '', new ext_read('MAILBOX', 'vm-login', '', '', 3, 2));
	$ext->add($id, $c, 'check', new ext_noop($id.': Got Mailbox ${MAILBOX}'));
	$ext->add($id, $c, '', new ext_macro('get-vmcontext','${MAILBOX}')); 
	$ext->add($id, $c, '', new ext_vmexists('${MAILBOX}@${VMCONTEXT}'));
	$ext->add($id, $c, '', new ext_gotoif('$["${VMBOXEXISTSSTATUS}" = "SUCCESS"]', 'good', 'bad'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));
	$ext->add($id, $c, 'good', new ext_noop($id.': Good mailbox ${MAILBOX}@${VMCONTEXT}'));
	$ext->add($id, $c, '', new ext_vmmain('${MAILBOX}@${VMCONTEXT}'));
	$ext->add($id, $c, '', new ext_gotoif('$["${IVR_RETVM}" = "RETURN" & "${IVR_CONTEXT}" != ""]','playret'));
	$ext->add($id, $c, '', new ext_macro('hangupcall'));
	$ext->add($id, $c, 'bad', new ext_noop($id.': BAD mailbox ${MAILBOX}@${VMCONTEXT}'));
	$ext->add($id, $c, '', new ext_wait('1'));
	$ext->add($id, $c, '', new ext_noop($id.': Asking for password so people can\'t probe for existence of a mailbox'));
	$ext->add($id, $c, '', new ext_read('FAKEPW', 'vm-password', '', '', 3, 2));
	$ext->add($id, $c, '', new ext_noop($id.': Asking for mailbox again'));
	$ext->add($id, $c, '', new ext_read('MAILBOX', 'vm-incorrect-mailbox', '', '', 3, 2));
	$ext->add($id, $c, '', new ext_goto('check'));
 	$ext->add($id, $c, '', new ext_macro('hangupcall'));
	$ext->add($id, $c, 'playret', new ext_playback('beep&you-will-be-transfered-menu&silence/1'));
	$ext->add($id, $c, '', new ext_goto('1','return','${IVR_CONTEXT}'));

	// Note that with this one, it has paramters. So we have to add '_' to the start and '.' to the end
	// of $c
	$c = "_$c.";
	$ext->add($id, $c, '', new ext_answer('')); // $cmd,1,Answer
	$ext->add($id, $c, '', new ext_wait('1')); // $cmd,n,Wait(1)
	// How long is the command? We need to strip that off the front
	$clen = strlen($c)-2;
	$ext->add($id, $c, '', new ext_macro('get-vmcontext','${EXTEN:'.$clen.'}')); 
	$ext->add($id, $c, '', new ext_vmmain('${EXTEN:'.$clen.'}@${VMCONTEXT}')); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_gotoif('$["${IVR_RETVM}" = "RETURN" & "${IVR_CONTEXT}" != ""]','${IVR_CONTEXT},return,1'));
	$ext->add($id, $c, '', new ext_macro('hangupcall')); // $cmd,n,Macro(user-callerid)
}

function voicemail_configpageinit($pagename) {
	global $currentcomponent;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

       // We only want to hook 'users' or 'extensions' pages. 
	if ($pagename != 'users' && $pagename != 'extensions')  {
		return true; 
	}

	if ($tech_hardware != null || $extdisplay != '' || $pagename == 'users') {
		// JS function needed for checking voicemail = Enabled
		$js = 'return (theForm.vm.value == "enabled");';
		$currentcomponent->addjsfunc('isVoiceMailEnabled(notused)',$js);
		// JS for verifying an empty password is OK
		$msg = _('Voicemail is enabled but the Voicemail Password field is empty.  Are you sure you wish to continue?');
		$js = 'if (theForm.vmpwd.value == "") { if(confirm("'.$msg.'")) { return true; } else { return false; }  };';
		$currentcomponent->addjsfunc('verifyEmptyVoiceMailPassword(notused)', $js);
		$js = "
		if (document.getElementById('vm').value == 'disabled') {
			var dval=true;
			document.getElementById('vmx_state').value='';
		} else {
			var dval=false;
		}
		document.getElementById('vmpwd').disabled=dval;
		document.getElementById('email').disabled=dval;
		document.getElementById('pager').disabled=dval;
		document.getElementById('attach0').disabled=dval;
		document.getElementById('attach1').disabled=dval;
		document.getElementById('saycid0').disabled=dval;
		document.getElementById('saycid1').disabled=dval;
		document.getElementById('envelope0').disabled=dval;
		document.getElementById('envelope1').disabled=dval;
		document.getElementById('delete0').disabled=dval;
		document.getElementById('delete1').disabled=dval;
		document.getElementById('imapuser').disabled=dval; 
		document.getElementById('imappassword').disabled=dval; 
		document.getElementById('options').disabled=dval;
		document.getElementById('vmcontext').disabled=dval;
		document.getElementById('vmx_state').disabled=dval;
		return true;
		";
		$currentcomponent->addjsfunc('voicemailEnabled(notused)', $js);
	
		$js = "
			if (document.getElementById('vmx_state').value == 'checked') {
				var dval=false;
			} else {
				var dval=true;
			}
			document.getElementById('vmx_unavail_enabled').disabled=dval;
			document.getElementById('vmx_busy_enabled').disabled=dval;
			document.getElementById('vmx_play_instructions').disabled=dval;
		";
		$vmxobj = new vmxObject($extdisplay);
		$follow_me_disabled = !$vmxobj->hasFollowMe();

		if (!$follow_me_disabled) {
		$js .= "
			document.getElementById('vmx_option_1_system_default').disabled=dval;
		";
		}
		$js .= "
			document.getElementById('vmx_option_1_number').disabled=dval;
			document.getElementById('vmx_option_2_number').disabled=dval;
	
			if (document.getElementById('vm').value == 'disabled') {
				document.getElementById('vmx_option_0_number').disabled = true;
				document.getElementById('vmx_option_0_system_default').disabled=true;
			} else {
				document.getElementById('vmx_option_0_system_default').disabled=false;
				if (document.getElementById('vmx_option_0_system_default').checked) {
					document.getElementById('vmx_option_0_number').disabled = true;
				} else {
					document.getElementById('vmx_option_0_number').disabled = false;
				}
			}
		";
					
		if (!$follow_me_disabled) {
			$js .= "
			if (document.getElementById('vmx_state').value == 'checked') {
				if (document.getElementById('vmx_option_1_system_default').checked) {
					document.getElementById('vmx_option_1_number').disabled = true;
				} else {
					document.getElementById('vmx_option_1_number').disabled = false;
				}
			}
			";
		}

		$js .= 
			"
			return true;
		";
		$currentcomponent->addjsfunc('vmx_disable_fields(notused)', $js);
	}

	// On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page. 
	if ($tech_hardware != null ) { 
		voicemail_applyhooks(); 
	} elseif ($action=="add") { 
	// We don't need to display anything on an 'add', but we do need to handle returned data. 
		// ** WARNING **
		// Mailbox must be processed before adding / deleting users, therefore $sortorder = 1
		//
		// More hacky-ness from components, since this is called first, we need to determine if
		// it there is a conclict indpenendent from the user component so we know if we should
		// redisplay the or not. While we are at it, we won't add the process function if there
		// is a conflict
		//
		if ($_REQUEST['display'] == 'users') {
			$usage_arr = framework_check_extension_usage($_REQUEST['extension']);
			if (empty($usage_arr)) {
				$currentcomponent->addprocessfunc('voicemail_configprocess', 1);
			} else {
				voicemail_applyhooks(); 
			}
		} else {
			$currentcomponent->addprocessfunc('voicemail_configprocess', 1);
		}
	} elseif ($extdisplay != '' || $pagename == 'users') { 
	// We're now viewing an extension, so we need to display _and_ process. 
		voicemail_applyhooks(); 
		$currentcomponent->addprocessfunc('voicemail_configprocess', 1);
	} 
}

function voicemail_applyhooks() {
	global $currentcomponent;

	// Setup two option lists we need
	// Enable / Disable list
	$currentcomponent->addoptlistitem('vmena', 'enabled', _('Enabled'));
	$currentcomponent->addoptlistitem('vmena', 'disabled', _('Disabled'));
	$currentcomponent->setoptlistopts('vmena', 'sort', false);
	// Enable / Disable vmx list
	$currentcomponent->addoptlistitem('vmxena', '', _('Disabled'));
	$currentcomponent->addoptlistitem('vmxena', 'checked', _('Enabled'));
	$currentcomponent->setoptlistopts('vmxena', 'sort', false);
	// Yes / No Radio button list
	$currentcomponent->addoptlistitem('vmyn', 'yes', _('yes'));
	$currentcomponent->addoptlistitem('vmyn', 'no', _('no'));
	$currentcomponent->setoptlistopts('vmyn', 'sort', false);

	// Add the 'proces' function
	$currentcomponent->addguifunc('voicemail_configpageload');
}

function voicemail_configpageload() {
	global $currentcomponent;

	// Init vars from $_REQUEST[]
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extn = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$display = isset($_REQUEST['display'])?$_REQUEST['display']:null;

	if ($ext==='') {
		$extdisplay = $extn;
	} else {
		$extdisplay = $ext;
	}

	if ($action != 'del') {
		$vmbox = voicemail_mailbox_get($extdisplay);
		if ( $vmbox == null ) {
			$vm = false;
			$incontext = 'default';
			$vmpwd = null;
			$name = null;
			$email = null;
			$pager = null;
			$vmoptions = null;

			$vmx_state = '';
		} else {
			$incontext = isset($vmbox['vmcontext'])?$vmbox['vmcontext']:'default';
			$vmpwd = $vmbox['pwd'];
			$name = $vmbox['name'];
			$email = $vmbox['email'];
			$pager = $vmbox['pager'];
			$vmoptions = $vmbox['options'];
			$vm = true;

			$vmxobj = new vmxObject($extdisplay);
			$vmx_state = ($vmxobj->isEnabled()) ? 'checked' : '';
			unset($vmxobj);
		}

		//loop through all options
		$options="";
		if ( isset($vmoptions) && is_array($vmoptions) ) {
			$alloptions = array_keys($vmoptions);
			if (isset($alloptions)) {
				foreach ($alloptions as $option) {
					if ( ($option!="attach") && ($option!="envelope") && ($option!="saycid") && ($option!="delete") && ($option!="imapuser") && ($option!="imappassword") && ($option!='') )
					    $options .= $option.'='.$vmoptions[$option].'|';
				}
				$options = rtrim($options,'|');
				// remove the = sign if there are no options set
				$options = rtrim($options,'=');
				
			}
			extract($vmoptions, EXTR_PREFIX_ALL, "vmops");
		} else {
			$vmops_attach = 'no';
			$vmops_saycid = 'no';
			$vmops_envelope = 'no';
			$vmops_delete = 'no';
			$vmops_imapuser = null;
			$vmops_imappassword = null;
		}

		if (empty($vmcontext)) 
			$vmcontext = (isset($_REQUEST['vmcontext']) ? $_REQUEST['vmcontext'] : $incontext);
		if (empty($vmcontext))
			$vmcontext = 'default';
		
		if ( $vm==true ) {
			$vmselect = "enabled";
		} else {
			$vmselect = "disabled";
		}
		
		$fc_vm = featurecodes_getFeatureCode('voicemail', 'dialvoicemail');

		$msgInvalidVmPwd = _("Please enter a valid Voicemail Password, using digits only");
		$msgInvalidEmail = _("Please enter a valid Email Address");
		$msgInvalidPager = _("Please enter a valid Pager Email Address");
		$msgInvalidVMContext = _("VM Context cannot be blank");
		$vmops_imapuser = '';
		$vmops_imappassword = '';

		$section = _("Voicemail & Directory");
		$currentcomponent->addguielem($section, new gui_selectbox('vm', $currentcomponent->getoptlist('vmena'), $vmselect, _('Status'), '', false,"frm_${display}_voicemailEnabled() && frm_${display}_vmx_disable_fields()"));
		$disable = ($vmselect == 'disabled');
		$currentcomponent->addguielem($section, new gui_textbox('vmpwd', $vmpwd, _('Voicemail Password'), sprintf(_("This is the password used to access the voicemail system.%sThis password can only contain numbers.%sA user can change the password you enter here after logging into the voicemail system (%s) with a phone."),"<br /><br />","<br /><br />",$fc_vm), "frm_${display}_isVoiceMailEnabled() && !frm_${display}_verifyEmptyVoiceMailPassword() && !isInteger()", $msgInvalidVmPwd, false,0,$disable));
		$currentcomponent->addguielem($section, new gui_textbox('email', $email, _('Email Address'), _("The email address that voicemails are sent to."), "frm_${display}_isVoiceMailEnabled() && !isEmail()", $msgInvalidEmail, true, 0, $disable));
		$currentcomponent->addguielem($section, new gui_textbox('pager', $pager, _('Pager Email Address'), _("Pager/mobile email address that short voicemail notifications are sent to."), "frm_${display}_isVoiceMailEnabled() && !isEmail()", $msgInvalidEmail, true, 0, $disable));
		$currentcomponent->addguielem($section, new gui_radio('attach', $currentcomponent->getoptlist('vmyn'), $vmops_attach, _('Email Attachment'), _("Option to attach voicemails to email."),$disable));
		$currentcomponent->addguielem($section, new gui_radio('saycid', $currentcomponent->getoptlist('vmyn'), $vmops_saycid, _('Play CID'), _("Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left."), $disable));
		$currentcomponent->addguielem($section, new gui_radio('envelope', $currentcomponent->getoptlist('vmyn'), $vmops_envelope, _('Play Envelope'), _("Envelope controls whether or not the voicemail system will play the message envelope (date/time) before playing the voicemail message. This setting does not affect the operation of the envelope option in the advanced voicemail menu."), $disable));
		$currentcomponent->addguielem($section, new gui_radio('delete', $currentcomponent->getoptlist('vmyn'), $vmops_delete, _('Delete Voicemail'), _("If set to \"yes\" the message will be deleted from the voicemailbox (after having been emailed). Provides functionality that allows a user to receive their voicemail via email alone, rather than having the voicemail able to be retrieved from the Webinterface or the Extension handset.  CAUTION: MUST HAVE attach voicemail to email SET TO YES OTHERWISE YOUR MESSAGES WILL BE LOST FOREVER."), $disable));
		$currentcomponent->addguielem($section, new gui_textbox('imapuser', $vmops_imapuser, _('IMAP Username'), sprintf(_("This is the IMAP username, if using IMAP storage"),"<br /><br />"),'','',true,0,$disable));
		$currentcomponent->addguielem($section, new gui_textbox('imappassword', $vmops_imappassword, _('IMAP Password'), sprintf(_("This is the IMAP password, if using IMAP storage"),"<br /><br />"),'','',true,0,$disable));
		$currentcomponent->addguielem($section, new gui_textbox('options', $options, _('VM Options'), sprintf(_("Separate options with pipe ( | )%sie: review=yes|maxmessage=60"),"<br /><br />"),'','',true,0,$disable));
		$currentcomponent->addguielem($section, new gui_textbox('vmcontext', $vmcontext, _('VM Context'), _("This is the Voicemail Context which is normally set to default. Do not change unless you understand the implications."), "frm_${display}_isVoiceMailEnabled() && isEmpty()", $msgInvalidVMContext, false,0,$disable));

		$section = _("VmX Locater");
		$currentcomponent->addguielem($section, new gui_selectbox('vmx_state', $currentcomponent->getoptlist('vmxena'), $vmx_state, _('VmX Locater&trade;'), _("Enable/Disable the VmX Locater feature for this user. When enabled all settings are controlled by the user in the User Portal (ARI). Disabling will not delete any existing user settings but will disable access to the feature"), false, "frm_{$display}_vmx_disable_fields()",$disable),5,6);

		$vmxhtml = voicemail_draw_vmxgui($extdisplay, $disable);
		$vmxhtml = '<tr><td colspan="2"><table>'.$vmxhtml.'</table></td></tr>';

		$msgValidNumber = _("Please enter a valid phone number using number digits only");
		$vmxcustom_validate = "
		defaultEmptyOK = true;
		if (!theForm.vmx_option_0_system_default.checked && !isInteger(theForm.vmx_option_0_number.value)) 
			return warnInvalid(theForm.vmx_option_0_number, '$msgValidNumber');
		if (theForm.vmx_option_1_system_default != undefined && !theForm.vmx_option_1_system_default.checked && !isInteger(theForm.vmx_option_1_number.value)) 
			return warnInvalid(theForm.vmx_option_1_number, '$msgValidNumber');
		if (!isInteger(theForm.vmx_option_2_number.value)) 
			return warnInvalid(theForm.vmx_option_2_number, '$msgValidNumber');
		";

		$currentcomponent->addguielem($section, new guielement('vmxcustom', $vmxhtml, "$vmxcustom_validate"),6,6);
	}
}

function voicemail_draw_vmxgui($extdisplay, $disable) {
	global $display;

	$vmxobj = new vmxObject($extdisplay);

	$dval = $vmxobj->isEnabled() ? '' : 'disabled="true"';

	$vmx_unavail_enabled_value = $vmxobj->getState("unavail") == "enabled" ? "checked" : "";
	$vmx_unavail_enabled_text_box_options = $dval;

	$vmx_busy_enabled_value = $vmxobj->getState("busy") == "enabled" ? "checked" : "";
	$vmx_busy_enabled_text_box_options = $dval;

	$vmx_play_instructions= $vmxobj->getVmPlay() ? "checked" : "";
	$vmx_play_instructions_text_box_options = $dval;

	$follow_me_disabled = !$vmxobj->hasFollowMe();
	if (!$follow_me_disabled) {
		$vmx_option_1_system_default_text_box_options = $dval;
		if ($vmxobj->isFollowMe()) {
			$vmx_option_1_number_text_box_options = 'disabled="true"';
			$vmx_option_1_number = '';
			$vmx_option_1_system_default = 'checked';
		} else {
			$vmx_option_1_number_text_box_options = $dval;
			$vmx_option_1_number = $vmxobj->getMenuOpt(1);
			$vmx_option_1_system_default = '';
		}
	} else {
		$vmx_option_1_number_text_box_options = $dval;
		$vmx_option_1_number = $vmxobj->getMenuOpt(1);
	}
 
	$vmx_option_0_system_default_text_box_options = ($disable) ? 'disabled="true"' : '';
	$vmx_option_0_number = $vmxobj->getMenuOpt(0);
	if ($vmx_option_0_number == "") {
		$vmx_option_0_number_text_box_options = 'disabled="true"';
		$vmx_option_0_system_default = 'checked';
	} else {
		$vmx_option_0_number_text_box_options = ($disable) ? 'disabled="true"' : '';
		$vmx_option_0_system_default = '';
	}
	$vmx_option_2_number_text_box_options = $dval;
	$vmx_option_2_number = $vmxobj->getMenuOpt(2);

	$tabindex = guielement::gettabindex();
	$tabindex_text = "tabindex='$tabindex'";
	$set_vmx_text = 
		"
			<tr>
				<td><a href='#' class='info'>" . _("Use When:") . "<span>" . _("Menu options below are available during your personal voicemail greeting playback. <br/><br/>Check both to use at all times.") . "<br></span></a></td> <td>
					<input $tabindex_text $vmx_unavail_enabled_text_box_options $vmx_unavail_enabled_value type=checkbox name='vmx_unavail_enabled' id='vmx_unavail_enabled' value='checked'>
					<small>" . _("unavailable") . "</small>&nbsp;&nbsp;
					<input $tabindex_text $vmx_busy_enabled_text_box_options $vmx_busy_enabled_value type=checkbox name='vmx_busy_enabled' id='vmx_busy_enabled' value='checked'>
					<small>" . _("busy") . "</small>
				</td>
			</tr>
			<tr>
				<td><a href='#' class='info'>" . _("Voicemail Instructions:") ."<span>" . _("Uncheck to play a beep after your personal voicemail greeting.") . "<br></span></a></td>
				<td>
					<input $tabindex_text $vmx_play_instructions_text_box_options $vmx_play_instructions type=checkbox name='vmx_play_instructions' id='vmx_play_instructions' value='checked'>
					<small>" . _("Standard voicemail prompts.") . "</small>
				</td>
			</tr>
		</table>
		<br>
		<br>
		<table class='settings'>
			<tr>
				<td><a href='#' class='info'>" . _("Press 0:") . "<span>" . _("Pressing 0 during your personal voicemail greeting goes to the Operator. Uncheck to enter another destination here. This feature can be used while still disabling VmX to allow an alternative Operator extension without requiring the VmX feature for the user.") . "<br></span></a>
				</td>
				<td>
					<input $tabindex_text $vmx_option_0_number_text_box_options name='vmx_option_0_number' id='vmx_option_0_number' type='text' size=24 value='$vmx_option_0_number'>
				</td>
				<td>
					<input $tabindex_text $vmx_option_0_system_default_text_box_options $vmx_option_0_system_default type=checkbox name='vmx_option_0_system_default' id='vmx_option_0_system_default' value='checked' OnClick=\"frm_{$display}_vmx_disable_fields();\">
					<small>" . _("Go To Operator") . "</small>
				</td>
			</tr>
			<tr>
				<td><a href='#' class='info'>" . _("Press 1:") . "<span>";
			
	if ($follow_me_disabled) {
		$set_vmx_text .= _("The remaining options can have internal extensions, ringgroups, queues and external numbers that may be rung. It is often used to include your cell phone. You should run a test to make sure that the number is functional any time a change is made so you don't leave a caller stranded or receiving invalid number messages.");
		} else {
		$set_vmx_text .= _("Enter an alternate number here, then change your personal voicemail greeting to let callers know to press 1 to reach that number. <br/><br/>If you'd like to use your Follow Me List, check \"Send to Follow Me\" and disable Follow Me above.");
		}
	
	$set_vmx_text .=  
		"			<br></span></a>
				</td>
				<td>
					<input $tabindex_text $vmx_option_1_number_text_box_options  name='vmx_option_1_number' id='vmx_option_1_number' type='text' size=24 value='$vmx_option_1_number'>
				</td>
				<td>";
				
	if (!$follow_me_disabled) {
		$set_vmx_text .=  "<input $tabindex_text $vmx_option_1_system_default_text_box_options $vmx_option_1_system_default type=checkbox name='vmx_option_1_system_default' id='vmx_option_1_system_default' value='checked' OnClick=\"frm_{$display}_vmx_disable_fields(); \"><small>" . _("Send to Follow-Me") . "</small>";
	}

	$set_vmx_text .=  
				"	
				</td>
			</tr>
			<tr>
				<td><a href='#' class='info'>" . _("Press 2:") . "<span>" . _("Use any extensions, ringgroups, queues or external numbers. <br/><br/>Remember to re-record your personal voicemail greeting and include instructions. Run a test to make sure that the number is functional.") . "<br></span></a></td>
				<td>
					<input $tabindex_text $vmx_option_2_number_text_box_options name='vmx_option_2_number' id='vmx_option_2_number' type='text' size=24 value='$vmx_option_2_number'>
				</td>
			</tr>
		";
	return $set_vmx_text;
}

function voicemail_configprocess() {
	//create vars from the request
	extract($_REQUEST);
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	
	//if submitting form, update database
	switch ($action) {
		case "add":
			if (!isset($GLOBALS['abort']) || $GLOBALS['abort'] !== true) {
				$usage_arr = framework_check_extension_usage($_REQUEST['extension']);
				if (!empty($usage_arr)) {
					$GLOBALS['abort'] = true;
				} else {
					voicemail_mailbox_add($extdisplay, $_REQUEST);
					needreload();
				}
			}
		break;
		case "del":
			// call remove before del, it needs to know context info
			//
			voicemail_mailbox_remove($extdisplay);
			voicemail_mailbox_del($extdisplay);
			needreload();
		break;
		case "edit":
			if (!isset($GLOBALS['abort']) || $GLOBALS['abort'] !== true) {
				voicemail_mailbox_del($extdisplay);
				if ( $vm != 'disabled' )
					voicemail_mailbox_add($extdisplay, $_REQUEST);
				needreload();
			}
		break;
	}
}

function voicemail_mailbox_get($mbox) {
	$uservm = voicemail_getVoicemail();
	$vmcontexts = array_keys($uservm);

	foreach ($vmcontexts as $vmcontext) {
		if(isset($uservm[$vmcontext][$mbox])){
			$vmbox['vmcontext'] = $vmcontext;
			$vmbox['pwd'] = $uservm[$vmcontext][$mbox]['pwd'];
			$vmbox['name'] = $uservm[$vmcontext][$mbox]['name'];
			$vmbox['email'] = $uservm[$vmcontext][$mbox]['email'];
			$vmbox['pager'] = $uservm[$vmcontext][$mbox]['pager'];
			$vmbox['options'] = $uservm[$vmcontext][$mbox]['options'];
			return $vmbox;
		}
	}
	
	return null;
}

function voicemail_mailbox_remove($mbox) {
	global $amp_conf;
	$uservm = voicemail_getVoicemail();
	$vmcontexts = array_keys($uservm);

	$return = true;

	foreach ($vmcontexts as $vmcontext) {
		if(isset($uservm[$vmcontext][$mbox])){

			$vm_dir = $amp_conf['ASTSPOOLDIR']."/voicemail/$vmcontext/$mbox";
			exec("rm -rf $vm_dir",$output,$ret);
			if ($ret) {
				$return = false;
				$text   = sprintf(_("Failed to delete vmbox: %s@%s"),$mbox, $vmcontext);
				$etext  = sprintf(_("failed with retcode %s while removing %s:"),$ret, $vm_dir)."<br>";
				$etext .= implode("<br>",$output);
				$nt =& notifications::create($db);
				$nt->add_error('voicemail', 'MBOXREMOVE', $text, $etext, '', true, true);
				//
				// TODO: this does not work but we should give some sort of feedback that id did not work
				//
				// echo "<script>javascript:alert('$text\n"._("See notification panel for details")."')</script>";
			}
		}
	}
	return $return;	
}

function voicemail_mailbox_del($mbox) {
	$uservm = voicemail_getVoicemail();
	$vmcontexts = array_keys($uservm);

	foreach ($vmcontexts as $vmcontext) {
		if(isset($uservm[$vmcontext][$mbox])){
			unset($uservm[$vmcontext][$mbox]);
			voicemail_saveVoicemail($uservm);
			return true;
		}
	}
	
	return false;	
}

function voicemail_mailbox_add($mbox, $mboxoptsarray) {
	global $astman;

	//check if VM box already exists
	if ( voicemail_mailbox_get($mbox) != null ) {
		trigger_error("Voicemail mailbox '$mbox' already exists, call to voicemail_maibox_add failed");
		die_freepbx();
	}
	
	$uservm = voicemail_getVoicemail();
	extract($mboxoptsarray);
	
	if ($vm != 'disabled')
	{ 
		// need to check if there are any options entered in the text field
		if ($options!=''){
			$options = explode("|",$options);
			foreach($options as $option) {
				$vmoption = explode("=",$option);
				$vmoptions[$vmoption[0]] = $vmoption[1];
			}
		}
		if ($imapuser!='' && $imapuser!='') { 
			$vmoptions['imapuser'] = $imapuser; 
			$vmoptions['imappassword'] = $imappassword; 
		} 
		$vmoption = explode("=",$attach);
			$vmoptions[$vmoption[0]] = $vmoption[1];
		$vmoption = explode("=",$saycid);
			$vmoptions[$vmoption[0]] = $vmoption[1];
		$vmoption = explode("=",$envelope);
			$vmoptions[$vmoption[0]] = $vmoption[1];
		$vmoption = explode("=",$delete);
			$vmoptions[$vmoption[0]] = $vmoption[1];
			
		$uservm[$vmcontext][$extension] = array(
			'mailbox' => $extension, 
			'pwd' => $vmpwd,
			'name' => $name,
			'email' => $email,
			'pager' => $pager,
			'options' => $vmoptions
			);
		// Update $_REQUEST with 'devinfo_mailbox, so MWI works.
		if (empty($_REQUEST['devinfo_mailbox'])) {
			$_REQUEST['devinfo_mailbox']="$extension@$vmcontext";
		}
	}
	voicemail_saveVoicemail($uservm);

	$vmxobj = new vmxObject($extension);

	// Operator extension can be set even without VmX enabled so that it can be
	// used as an alternate way to provide an operator extension for a user
	// without VmX enabled.
	//
	if (isset($vmx_option_0_system_default) && $vmx_option_0_system_default != '') {
		$vmxobj->setMenuOpt("",0,'unavail');
		$vmxobj->setMenuOpt("",0,'busy');
	} else {
    if (!isset($vmx_option_0_number)) {
		  $vmx_option_0_number = '';
    }
		$vmx_option_0_number = preg_replace("/[^0-9]/" ,"", $vmx_option_0_number);
		$vmxobj->setMenuOpt($vmx_option_0_number,0,'unavail');
		$vmxobj->setMenuOpt($vmx_option_0_number,0,'busy');
	}

	if (isset($vmx_state) && $vmx_state) {

		if (isset($vmx_unavail_enabled) && $vmx_unavail_enabled != '') {
			$vmxobj->setState('enabled','unavail');
		} else {
			$vmxobj->setState('disabled','unavail');
		}

		if (isset($vmx_busy_enabled) && $vmx_busy_enabled != '') {
			$vmxobj->setState('enabled','busy');
		} else {
			$vmxobj->setState('disabled','busy');
		}

		if (isset($vmx_play_instructions) && $vmx_play_instructions== 'checked') {
			$vmxobj->setVmPlay(true,'unavail');
			$vmxobj->setVmPlay(true,'busy');
		} else {
			$vmxobj->setVmPlay(false,'unavail');
			$vmxobj->setVmPlay(false,'busy');
		}

		if (isset($vmx_option_1_system_default) && $vmx_option_1_system_default != '') {
			$vmxobj->setFollowMe(1,'unavail');
			$vmxobj->setFollowMe(1,'busy');
		} else {
			$vmx_option_1_number = preg_replace("/[^0-9]/" ,"", $vmx_option_1_number);
			$vmxobj->setMenuOpt($vmx_option_1_number,1,'unavail');
			$vmxobj->setMenuOpt($vmx_option_1_number,1,'busy');
		}
		if (isset($vmx_option_2_number)) {
			$vmx_option_2_number = preg_replace("/[^0-9]/" ,"", $vmx_option_2_number);
			$vmxobj->setMenuOpt($vmx_option_2_number,2,'unavail');
			$vmxobj->setMenuOpt($vmx_option_2_number,2,'busy');
		}
	} else {
		if ($vmxobj->isInitialized()) {
			$vmxobj->disable();
		}
	}
}

function voicemail_saveVoicemail($vmconf) {
	global $amp_conf;

	// just in case someone tries to be sneaky and not call getVoicemail() first..
	if ($vmconf == null) die_freepbx('Error: Trying to write null voicemail file! I refuse to contiune!');
	
	// yes, this is hardcoded.. is this a bad thing?
	write_voicemailconf(rtrim($amp_conf["ASTETCDIR"],"/")."/voicemail.conf", $vmconf, $section);
}

function voicemail_getVoicemail() {
	global $amp_conf;

	$vmconf = null;
	$section = null;
	
	// yes, this is hardcoded.. is this a bad thing?
	parse_voicemailconf(rtrim($amp_conf["ASTETCDIR"],"/")."/voicemail.conf", $vmconf, $section);
	
	return $vmconf;
}

?>
