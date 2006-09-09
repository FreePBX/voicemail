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
	$ext->add($id, $c, '', new ext_macro('get-vmcontext','${CALLERID(num)}')); 
	$ext->add($id, $c, '', new ext_vmmain('${CALLERID(num)}@${VMCONTEXT}')); // n,VoiceMailMain(${VMCONTEXT})
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

function voicemail_configpageinit($dispnum) {
	global $currentcomponent;

	//if ( $dispnum == 'users' || $dispnum == 'extensions' ) {
	if ( $dispnum == 'users' ) {
		// Setup two option lists we need
		// Enable / Disable list
		$currentcomponent->addoptlistitem('vmena', 'enabled', 'Enabled');
		$currentcomponent->addoptlistitem('vmena', 'disabled', 'Disabled');
		$currentcomponent->setoptlistopts('vmena', 'sort', false);
		// Yes / No Radio button list
		$currentcomponent->addoptlistitem('vmyn', 'yes', 'yes');
		$currentcomponent->addoptlistitem('vmyn', 'no', 'no');
		$currentcomponent->setoptlistopts('vmyn', 'sort', false);

		// Add the 'proces' function
		$currentcomponent->addguifunc('voicemail_configpageload');
	}
}

function voicemail_configpageload() {
	global $currentcomponent;

	// Init vars from $_REQUEST[]
	$action = $_REQUEST['action'];
	$extdisplay = $_REQUEST['extdisplay'];
	
	if ($action != 'del') {		
		//read in the voicemail.conf and set appropriate variables for display
		$uservm = getVoicemail();
		$vmcontexts = array_keys($uservm);
		$vm=false;
		foreach ($vmcontexts as $vmcontext) {
			if(isset($uservm[$vmcontext][$extdisplay])){
				//echo $extdisplay.' found in context '.$vmcontext.'<hr>';
				$incontext = $vmcontext;  //the context for the current extension
				$vmpwd = $uservm[$vmcontext][$extdisplay]['pwd'];
				$name = $uservm[$vmcontext][$extdisplay]['name'];
				$email = $uservm[$vmcontext][$extdisplay]['email'];
				$pager = $uservm[$vmcontext][$extdisplay]['pager'];
				//loop through all options
				$options="";
				if (is_array($uservm[$vmcontext][$extdisplay]['options'])) {
					$alloptions = array_keys($uservm[$vmcontext][$extdisplay]['options']);
					if (isset($alloptions)) {
						foreach ($alloptions as $option) {
							if ( ($option!="attach") && ($option!="envelope") && ($option!="saycid") && ($option!="delete") && ($option!='') )
								$options .= $option.'='.$uservm[$vmcontext][$extdisplay]['options'][$option].'|';
						}
						$options = rtrim($options,'|');
						// remove the = sign if there are no options set
						$options = rtrim($options,'=');
						
					}
					extract($uservm[$vmcontext][$extdisplay]['options'], EXTR_PREFIX_ALL, "vmops");
				}
				$vm=true;
			}
		}
		
		$vmcontext = $_SESSION["AMP_user"]->_deptname; //AMP Users can only add to their department's context
		if (empty($vmcontext)) 
			$vmcontext = ($_REQUEST['vmcontext'] ? $_REQUEST['vmcontext'] : $incontext);
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
		$currentcomponent->addguielem($section, new gui_textbox('vmpwd', $vmpwd, 'voicemail password', "This is the password used to access the voicemail system.<br><br>This password can only contain numbers.<br><br>A user can change the password you enter here after logging into the voicemail system ($fc_vm) with a phone.", "isVoiceMailEnabled() && !isInteger()", $msgInvalidVmPwd, false));
		$currentcomponent->addguielem($section, new gui_textbox('email', $email, 'email address', "The email address that voicemails are sent to.", "isVoiceMailEnabled() && !isEmail()", $msgInvalidEmail, true));
		$currentcomponent->addguielem($section, new gui_textbox('pager', $pager, 'pager email address', "Pager/mobile email address that short voicemail notifcations are sent to.", "isVoiceMailEnabled() && !isEmail()", $msgInvalidEmail, true));
		$currentcomponent->addguielem($section, new gui_radio('attach', $currentcomponent->getoptlist('vmyn'), $vmops_attach, 'email attachment', "Option to attach voicemails to email."));
		$currentcomponent->addguielem($section, new gui_radio('saycid', $currentcomponent->getoptlist('vmyn'), $vmops_saycid, 'Play CID', "Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left."));
		$currentcomponent->addguielem($section, new gui_radio('envelope', $currentcomponent->getoptlist('vmyn'), $vmops_envelope, 'Play Envelope', "Envelope controls whether or not the voicemail system will play the message envelope (date/time) before playing the voicemail message. This settng does not affect the operation of the envelope option in the advanced voicemail menu."));
		$currentcomponent->addguielem($section, new gui_radio('delete', $currentcomponent->getoptlist('vmyn'), $vmops_delete, 'Delete Vmail', "If set to \"yes\" the message will be deleted from the voicemailbox (after having been emailed). Provides functionality that allows a user to receive their voicemail via email alone, rather than having the voicemail able to be retrieved from the Webinterface or the Extension handset.  CAUTION: MUST HAVE attach voicemail to email SET TO YES OTHERWISE YOUR MESSAGES WILL BE LOST FOREVER."));
		$currentcomponent->addguielem($section, new gui_textbox('options', $options, 'vm options', 'Separate options with pipe ( | )<br><br>ie: review=yes|maxmessage=60'));
		$currentcomponent->addguielem($section, new gui_textbox('vmcontext', $vmcontext, 'vm context', '', 'isVoiceMailEnabled() && isEmpty()', $msgInvalidVMContext, false));
	}
}

?>
