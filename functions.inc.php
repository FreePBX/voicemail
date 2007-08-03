<?php

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

	$id = "app-vmmain"; // The context to be included

	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal

	$ext->add($id, $c, '', new ext_answer('')); // $cmd,1,Answer
	$ext->add($id, $c, '', new ext_wait('1')); // $cmd,n,Wait(1)
	$ext->add($id, $c, '', new ext_macro('user-callerid')); // $cmd,n,Macro(user-callerid)
	$ext->add($id, $c, '', new ext_macro('get-vmcontext','${AMPUSER}')); 
	$ext->add($id, $c, 'check', new ext_vmexists('${AMPUSER}@${VMCONTEXT}|j')); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_vmmain('')); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_macro('hangupcall')); // $cmd,n,Macro(user-callerid)
	$ext->add($id, $c, '', new ext_vmmain('${AMPUSER}@${VMCONTEXT}'),'check',101); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_macro('hangupcall')); // $cmd,n,Macro(user-callerid)
}

function voicemail_dialvoicemail($c) {
	global $ext;

	$id = "app-dialvm"; // The context to be included

	$ext->addInclude('from-internal-additional', $id); // Add the include from from-internal

	$ext->add($id, $c, '', new ext_answer('')); // $cmd,1,Answer
	$ext->add($id, $c, '', new ext_wait('1')); // $cmd,n,Wait(1)
	$ext->add($id, $c, '', new ext_vmmain('')); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_macro('hangupcall'));

	// Note that with this one, it has paramters. So we have to add '_' to the start and '.' to the end
	// of $c
	$c = "_$c.";
	$ext->add($id, $c, '', new ext_answer('')); // $cmd,1,Answer
	$ext->add($id, $c, '', new ext_wait('1')); // $cmd,n,Wait(1)
	// How long is the command? We need to strip that off the front
	$clen = strlen($c)-2;
	$ext->add($id, $c, '', new ext_macro('get-vmcontext','${EXTEN:'.$clen.'}')); 
	$ext->add($id, $c, '', new ext_vmmain('${EXTEN:'.$clen.'}@${VMCONTEXT}')); // n,VoiceMailMain(${VMCONTEXT})
	$ext->add($id, $c, '', new ext_macro('hangupcall')); // $cmd,n,Macro(user-callerid)
}

function voicemail_configpageinit($pagename) {
	global $currentcomponent;

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

       // We only want to hook 'users' or 'extensions' pages. 
	if ($pagename != 'users' && $pagename != 'extensions')  
		return true; 
	// On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page. 
	if ($tech_hardware != null ) { 
		voicemail_applyhooks(); 
	} elseif ($action=="add") { 
	// We don't need to display anything on an 'add', but we do need to handle returned data. 
		// ** WARNING **
		// Mailbox must be processed before adding / deleting users, therefore $sortorder = 1
		$currentcomponent->addprocessfunc('voicemail_configprocess', 1);
		// JS function needed for checking voicemail = Enabled
		$js = 'return (theForm.vm.value == "enabled");';
	} elseif ($extdisplay != '' || $pagename == 'users') { 
	// We're now viewing an extension, so we need to display _and_ process. 
		voicemail_applyhooks(); 
		$currentcomponent->addprocessfunc('voicemail_configprocess', 1);
		$js = 'return (theForm.vm.value == "enabled");';
		$currentcomponent->addjsfunc('isVoiceMailEnabled(notused)',$js);
	} 
} 

function voicemail_applyhooks() {
	global $currentcomponent;

	// Setup two option lists we need
	// Enable / Disable list
	$currentcomponent->addoptlistitem('vmena', 'enabled', 'Enabled');
	$currentcomponent->addoptlistitem('vmena', 'disabled', 'Disabled');
	$currentcomponent->setoptlistopts('vmena', 'sort', false);
	// Enable / Disable vmx list
	$currentcomponent->addoptlistitem('vmxena', '', 'Disabled');
	$currentcomponent->addoptlistitem('vmxena', 'checked', 'Enabled');
	$currentcomponent->setoptlistopts('vmxena', 'sort', false);
	// Yes / No Radio button list
	$currentcomponent->addoptlistitem('vmyn', 'yes', 'yes');
	$currentcomponent->addoptlistitem('vmyn', 'no', 'no');
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

			$vmx_state = voicemail_vmxGet($extdisplay);
		}

		//loop through all options
		$options="";
		if ( isset($vmoptions) && is_array($vmoptions) ) {
			$alloptions = array_keys($vmoptions);
			if (isset($alloptions)) {
				foreach ($alloptions as $option) {
					if ( ($option!="attach") && ($option!="envelope") && ($option!="saycid") && ($option!="delete") && ($option!='') )
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

		$msgInvalidVmPwd = 'Please enter a valid Voicemail Password, using digits only';
		$msgInvalidEmail = 'Please enter a valid Email Address';
		$msgInvalidPager = 'Please enter a valid Pager Email Address';
		$msgInvalidVMContext = 'VM Context cannot be blank';

		$section = 'Voicemail & Directory';
		$currentcomponent->addguielem($section, new gui_selectbox('vm', $currentcomponent->getoptlist('vmena'), $vmselect, 'Status', '', false));
		$currentcomponent->addguielem($section, new gui_textbox('vmpwd', $vmpwd, 'voicemail password', "This is the password used to access the voicemail system.<br><br>This password can only contain numbers.<br><br>A user can change the password you enter here after logging into the voicemail system ($fc_vm) with a phone.", "frm_${display}_isVoiceMailEnabled() && !isInteger()", $msgInvalidVmPwd, false));
		$currentcomponent->addguielem($section, new gui_textbox('email', $email, 'email address', "The email address that voicemails are sent to.", "frm_${display}_isVoiceMailEnabled() && !isEmail()", $msgInvalidEmail, true));
		$currentcomponent->addguielem($section, new gui_textbox('pager', $pager, 'pager email address', "Pager/mobile email address that short voicemail notifcations are sent to.", "frm_${display}_isVoiceMailEnabled() && !isEmail()", $msgInvalidEmail, true));
		$currentcomponent->addguielem($section, new gui_radio('attach', $currentcomponent->getoptlist('vmyn'), $vmops_attach, 'email attachment', "Option to attach voicemails to email."));
		$currentcomponent->addguielem($section, new gui_radio('saycid', $currentcomponent->getoptlist('vmyn'), $vmops_saycid, 'Play CID', "Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left."));
		$currentcomponent->addguielem($section, new gui_radio('envelope', $currentcomponent->getoptlist('vmyn'), $vmops_envelope, 'Play Envelope', "Envelope controls whether or not the voicemail system will play the message envelope (date/time) before playing the voicemail message. This settng does not affect the operation of the envelope option in the advanced voicemail menu."));
		$currentcomponent->addguielem($section, new gui_radio('delete', $currentcomponent->getoptlist('vmyn'), $vmops_delete, 'Delete Vmail', "If set to \"yes\" the message will be deleted from the voicemailbox (after having been emailed). Provides functionality that allows a user to receive their voicemail via email alone, rather than having the voicemail able to be retrieved from the Webinterface or the Extension handset.  CAUTION: MUST HAVE attach voicemail to email SET TO YES OTHERWISE YOUR MESSAGES WILL BE LOST FOREVER."));
		$currentcomponent->addguielem($section, new gui_textbox('options', $options, 'vm options', 'Separate options with pipe ( | )<br><br>ie: review=yes|maxmessage=60'));
		$currentcomponent->addguielem($section, new gui_textbox('vmcontext', $vmcontext, 'vm context', '', "frm_${display}_isVoiceMailEnabled() && isEmpty()", $msgInvalidVMContext, false));
		$currentcomponent->addguielem($section, new gui_selectbox('vmx_state', $currentcomponent->getoptlist('vmxena'), $vmx_state, 'VmX IVR', 'Enable/Disable the Extended Voicemail IVR ability on this extension. Defaults will be set an d the user can make changes in the ARI or equivalent portal. Unchecking will disabled the feature but not delete any existing settings', false));
	}
}

function voicemail_configprocess() {
	//create vars from the request
	extract($_REQUEST);
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	
	//if submitting form, update database
	switch ($action) {
		case "add":
			voicemail_mailbox_add($extdisplay, $_REQUEST);
			needreload();
		break;
		case "del":
			// call remove before del, it needs to know context info
			//
			voicemail_mailbox_remove($extdisplay);
			voicemail_mailbox_del($extdisplay);
			needreload();
		break;
		case "edit":
			voicemail_mailbox_del($extdisplay);
			if ( $vm != 'disabled' )
				voicemail_mailbox_add($extdisplay, $_REQUEST);
			needreload();
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
	//check if VM box already exists
	if ( voicemail_mailbox_get($mbox) != null ) {
		trigger_error("Voicemail mailbox '$mbox' already exists, call to voicemail_maibox_add failed");
		die();
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
}

function voicemail_saveVoicemail($vmconf) {
	global $amp_conf;

	// just in case someone tries to be sneaky and not call getVoicemail() first..
	if ($vmconf == null) die('Error: Trying to write null voicemail file! I refuse to contiune!');
	
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

function voicemail_vmxGet($extension) {
	global $astman;                                                                                                                                                                                                    
		                                                                                                                                                                                                                     
	// Retrieve the state
	$vmx_state=$astman->database_get("AMPUSER",$extension."/vmx/unavail/state");
	if (isset($vmx_state) && (trim($vmx_state) == 'enabled' || trim($vmx_state) == 'disabled')) {
		$vmx_state='checked';
	} else {
		$vmx_state='';
	}

	return $vmx_state;
}                                                                                                                                                                                                                    


?>
