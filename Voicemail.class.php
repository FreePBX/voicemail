<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\modules;
class Voicemail implements \BMO {
	//message to display to client
	public $displayMessage = array(
		"type" => "warning",
		"message" => ""
	);

	//supported greeting names
	public $greetings = array(
		'unavail' => 'Unavailable Greeting',
		'greet' => 'Name Greeting',
		'busy' => 'Busy Greeting',
		'temp' => 'Temporary Greeting',
	);

	//Voicemail folders to search
	private $folders = array(
		"INBOX",
		"Family",
		"Friends",
		"Old",
		"Work",
		"Urgent"
	);

	//limits the messages to process
	private $messageLimit = 3000;
	private $vmBoxData = array();
	private $vmFolders = array();
	private $vmPath = null;
	private $messageCache = array();
	public $Vmx = null;
	private $boxes = array();
	private $validFiles = array();
	private $vmCache = array();

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}

		if(!class_exists('FreePBX\modules\Voicemail\Vmx') && file_exists(__DIR__.'/Vmx.class.php')) {
			include(__DIR__.'/Vmx.class.php');
		}
		if(!is_object($this->Vmx) && class_exists('FreePBX\modules\Voicemail\Vmx')) {
			$this->Vmx = new Voicemail\Vmx($freepbx);
		} elseif(!class_exists('FreePBX\modules\Voicemail\Vmx')) {
			throw new \Exception("Unable to load VmX Locator class");
		}

		$this->FreePBX = $freepbx;
		$this->astman = $this->FreePBX->astman;
		$this->db = $freepbx->Database;
		$this->vmPath = $this->FreePBX->Config->get_conf_setting('ASTSPOOLDIR') . "/voicemail";
		$this->messageLimit = $this->FreePBX->Config->get_conf_setting('UCP_MESSAGE_LIMIT');
		\modgettext::push_textdomain("voicemail");
		foreach($this->folders as $folder) {
			$this->vmFolders[$folder] = array(
				"folder" => $folder,
				"name" => _($folder)
			);
		}
		\modgettext::pop_textdomain();


		//Force translation for later pickup
		if(false) {
			_("INBOX");
			_("Family");
			_("Friends");
			_("Old");
			_("Work");
			_("Urgent");
			_('Unavailable Greeting');
			_('Name Greeting');
			_('Busy Greeting');
			_('Temporary Greeting');
		}
	}

	public function doConfigPageInit($page) {

	}

	public function install() {
		if($this->FreePBX->Modules->checkStatus("userman") && is_object($this->Vmx)) {
		  $users = $this->FreePBX->Userman()->getAllUsers();
		  foreach($users as $user) {
		    if($user['default_extension'] != 'none') {
		      if($this->FreePBX->Modules->checkStatus("ucp") && $this->Vmx->isInitialized($user['default_extension']) && $this->Vmx->isEnabled($user['default_extension'])) {
						$this->FreePBX->Ucp->setSettingByID($user['id'],'Voicemail','vmxlocater',true);
					}
		    }
		  }
		}
	}
	public function uninstall() {

	}
	public function backup(){

	}
	public function restore($backup){

	}
	public function genConfig() {

	}

	public function getQuickCreateDisplay() {
		return array(
			1 => array(
				array(
					'html' => load_view(__DIR__.'/views/quickCreate.php',array()),
					'validate' => 'if($("#vm_on").is(":checked") && !isInteger($("#vmpwd").val())) {warnInvalid($("#vmpwd"),"'._("Voicemail Password must contain only digits").'");return false}'
				)
			)
		);
	}

	/**
	 * Quick Create hook
	 * @param string $tech      The device tech
	 * @param int $extension The extension number
	 * @param array $data      The associated data
	 */
	public function processQuickCreate($tech, $extension, $data) {
		if($data['vm'] == "yes" && trim($data['vmpwd'] !== "")) {
			$this->addMailbox($extension, array(
				"vm" => "enabled",
				"name" => $data['name'],
				"vmpwd" => $data['vmpwd'],
				"email" => $data['email'],
				"pager" => $data['email'],
				"passlogin" => "passlogin=no",
				"attach" => "attach=no",
				"envelope" => "envelope=no",
				"vmdelete" => "vmdelete=no",
				"saycid" => "saycid=no"
			));
			$sql = "UPDATE users SET voicemail = 'default' WHERE extension = ?";
			$sth = $this->db->prepare($sql);
			$sth->execute(array($extension));
			$this->astman->database_put("AMPUSER",$extension."/voicemail",'default');
			$this->setupMailboxSymlinks($extension);
		}
	}

	/**
	 * Setup system symlinks for mailboxes
	 * @param int $mailbox The mailbox number
	 */
	public function setupMailboxSymlinks($mailbox) {
		if($mailbox == 'none'){
			return;
		}
		if(!is_numeric($mailbox)) {
			throw new \Exception(_("Mailbox is not in the proper format"));
		}
		$user = $this->FreePBX->Core->getUser($mailbox);
		if(isset($user['voicemail']) && ($user['voicemail'] != "novm")) {
			$vmcontext = !empty($user['voicemail']) ? $user['voicemail'] : "default";

			//voicemail symlink
			$spooldir = $this->FreePBX->Config->get('ASTSPOOLDIR');
			exec("rm -f ".$spooldir."/voicemail/device/".$mailbox);
			symlink($spooldir."/voicemail/".$vmcontext."/".$mailbox, $spooldir."/voicemail/device/".$mailbox);
		}
	}

	/**
	 * Parse the voicemail.conf file the way we need it to be
	 * @param bool $cached If true then attempt to get cached values
	 * @return array The array of the voicemail.conf file
	 */
	public function getVoicemail($cached = true) {
		if($cached && !empty($this->vmCache)) {
			return $this->vmCache;
		}
		$vm = $this->FreePBX->LoadConfig->getConfig("voicemail.conf");

		//Parse mailbox data into something useful
		$vm = is_array($vm) ? $vm : array();
		foreach($vm as $name => &$context) {
			if($name == "general" || $name == "zonemessages") {
				continue;
			}
			foreach($context as $mailbox => &$data) {
				$options = explode(",",$data);
				$fopts = array();
				if(!empty($options[4])) {
					foreach(explode("|",$options[4]) as $odata) {
						$t = explode("=",$odata);
						$fopts[$t[0]] = $t[1];
					}
				}
				$data = array(
					'mailbox' => $mailbox,
					'pwd' => isset($options[0]) ? $options[0] : '',
					'name' => isset($options[1]) ? $options[1] : '',
					'email' => isset($options[2]) ? $options[2] : '',
					'pager' => isset($options[3]) ? $options[3] : '',
					'options' => isset($fopts) ? $fopts : ''
				);
			}
		}
		$this->vmCache = $vm;
		return $this->vmCache;
	}

	/**
	 * Get the mailbox options from voicemail.conf parsing
	 * @param int $mailbox The mailbox number
	 * @param bool $cached Attempt to get cached voicemail file
	 */
	public function getMailbox($mailbox, $cached = true) {
		$uservm = $this->getVoicemail($cached);
		$vmcontexts = array_keys($uservm);

		foreach ($vmcontexts as $vmcontext) {
			if($vmcontext == "general" || $vmcontext == "zonemessages") {
				continue;
			}
			if(isset($uservm[$vmcontext][$mailbox])){
				$vmbox['vmcontext'] = $vmcontext;
				$vmbox['pwd'] = $uservm[$vmcontext][$mailbox]['pwd'];
				$vmbox['name'] = $uservm[$vmcontext][$mailbox]['name'];
				$vmbox['email'] = str_replace('|',',',$uservm[$vmcontext][$mailbox]['email']);
				$vmbox['pager'] = $uservm[$vmcontext][$mailbox]['pager'];
				$vmbox['options'] = $uservm[$vmcontext][$mailbox]['options'];
				return $vmbox;
			}
		}

		return null;
	}

	/**
	 * Remove the mailbox from the system (hard drive)
	 * @param bool $cached If true then attempt to get cached values
	 * @param int $mailbox The mailbox number
	 */
	public function removeMailbox($mailbox, $cached = true) {
		$uservm = $this->getVoicemail($cached);
		$vmcontexts = array_keys($uservm);

		$return = true;

		foreach ($vmcontexts as $vmcontext) {
			if(isset($uservm[$vmcontext][$mailbox])){
				$vm_dir = $this->FreePBX->Config->get('ASTSPOOLDIR')."/voicemail/$vmcontext/$mailbox";
				exec("rm -rf $vm_dir",$output,$ret);
				if ($ret) {
					$return = false;
					$text   = sprintf(_("Failed to delete vmbox: %s@%s"),$mailbox, $vmcontext);
					$etext  = sprintf(_("failed with retcode %s while removing %s:"),$ret, $vm_dir)."<br>";
					$etext .= implode("<br>",$output);
					$nt =& \notifications::create($db);
					$nt->add_error('voicemail', 'MBOXREMOVE', $text, $etext, '', true, true);
				}
			}
		}
		return $return;
	}

	/**
	 * Delete mailbox from voicemail.conf
	 * @param bool $cached If true then attempt to get cached values
	 * @param int $mailbox The mailbox number
	 */
	public function delMailbox($mailbox, $cached = true) {
		$uservm = $this->getVoicemail($cached);
		$vmcontexts = array_keys($uservm);

		foreach ($vmcontexts as $vmcontext) {
			if(isset($uservm[$vmcontext][$mailbox])){
				unset($uservm[$vmcontext][$mailbox]);
				$this->saveVoicemail($uservm);
				return true;
			}
		}

		return false;
	}

	/**
	 * Save Voicemail.conf file
	 * @param array $vmconf Array of settings which are returned from LoadConfig
	 */
	public function saveVoicemail($vmconf) {
		// just in case someone tries to be sneaky and not call getVoicemail() first..
		if ($vmconf == null) {
			throw new \Exception(_("Null value was sent to saveVoicemail() can not continue"));
		}

		foreach($vmconf as $name => &$context) {
			if($name == "general" || $name == "zonemessages") {
				$cdata = array();
				foreach($context as $key => $value) {
					$cdata[$key] = str_replace(array("\n","\t","\r"),array("\\n","\\t","\\r"),$value);
				}
				$context = $cdata;
				continue;
			}
			$cdata = array();
			foreach($context as $mailbox => $data) {
				$opts = array();
				if(!empty($data['options'])) {
					foreach($data['options'] as $key => $value) {
						$opts[] = $key."=".$value;
					}
				}
				$data['email'] = str_replace(",","|",$data['email']);
				$data['pager'] = str_replace(",","|",$data['pager']);
				$data['options'] = implode("|",$opts);
				$cdata[] = $mailbox ." => " .
									$data['pwd'] . "," .
									$data['name'] . "," .
									$data['email'] . "," .
									$data['pager'] . "," .
									$data['options'];
			}
			$context = $cdata;
		}

		$this->FreePBX->WriteConfig->writeConfig("voicemail.conf", $vmconf, false);
		$this->vmCache = array();
	}

	/**
	 * Add a Mailbox and all of it's settings
	 * @param int $mailbox  The mailbox number
	 * @param array $settings The settings for said mailbox
	 * @param bool $cached If true then attempt to get cached values
	 */
	public function addMailbox($mailbox, $settings, $cached = true) {
		global $astman;
		if(trim($mailbox) == "") {
			throw new \Exception(_("Mailbox can not be empty"));
		}
		$vmconf = $this->getVoicemail($cached);

		$settings['vmcontext'] = !empty($settings['vmcontext']) ? $settings['vmcontext'] : 'default';
		$settings['pwd'] = isset($settings['pwd']) ? $settings['pwd'] : '';
		$settings['name'] = isset($settings['name']) ? $settings['name'] : '';
		$settings['email'] = isset($settings['email']) ? $settings['email'] : '';
		$settings['pager'] = isset($settings['pager']) ? $settings['pager'] : '';

		if (isset($settings['vm']) && $settings['vm'] != 'disabled') {
			$vmoptions = array();
			// need to check if there are any options entered in the text field
			if (!empty($settings['options'])) {
				$options = explode("|",$settings['options']);
				foreach($options as $option) {
					$vmoption = explode("=", $option);
					$vmoptions[$vmoption[0]] = $vmoption[1];
				}
			}
			if (isset($settings['imapuser']) && trim($settings['imapuser']) != '' && isset($settings['imapuser']) && trim($settings['imapuser']) != '') {
				$vmoptions['imapuser'] = $settings['imapuser'];
				$vmoptions['imappassword'] = $settings['imappassword'];
			}
			if(isset($settings['passlogin'])) {
				$vmoption = explode("=",$settings['passlogin']);
				$settings['passlogin'] = $vmoption[1];
			}

			if(isset($settings['novmstar'])) {
				$vmoption = explode("=",$settings['novmstar']);
				$settings['novmstar'] = $vmoption[1];
			}

			if(isset($settings['attach'])) {
				$vmoption = explode("=",$settings['attach']);
				$vmoptions['attach'] = $vmoption[1];
			}

			if(isset($settings['saycid'])) {
				$vmoption = explode("=",$settings['saycid']);
				$vmoptions['saycid'] = $vmoption[1];
			}

			if(isset($settings['envelope'])) {
				$vmoption = explode("=",$settings['envelope']);
				$vmoptions['envelope'] = $vmoption[1];
			}

			if(isset($settings['vmdelete'])) {
				$vmoption = explode("=",$settings['vmdelete']);
				$vmoptions['delete'] = $vmoption[1];
			}

			$vmconf[$settings['vmcontext']][$mailbox] = array(
				'mailbox' => $mailbox,
				'pwd' => $settings['vmpwd'],
				'name' => $settings['name'],
				'email' => str_replace(',','|',$settings['email']),
				'pager' => $settings['pager'],
				'options' => $vmoptions
				);
		}

		$this->saveVoicemail($vmconf);

		if(isset($settings['passlogin']) && $settings['passlogin'] == 'no') {
			//The value doesnt matter, could be yes no f bark
			$this->astman->database_put("AMPUSER", $mailbox."/novmpw", 'yes');
		} else {
			$this->astman->database_del("AMPUSER", $mailbox."/novmpw");
		}

		if(isset($settings['novmstar']) && $settings['novmstar'] == 'yes') {
			//The value doesnt matter, could be yes no f bark
			$this->astman->database_put("AMPUSER", $mailbox."/novmstar", 'yes');
		} else {
			$this->astman->database_del("AMPUSER", $mailbox."/novmstar");
		}

		// Operator extension can be set even without VmX enabled so that it can be
		// used as an alternate way to provide an operator extension for a user
		// without VmX enabled.
		//
		if (isset($settings['vmx_option_0_system_default']) && $settings['vmx_option_0_system_default'] != '') {
			$this->Vmx->setMenuOpt($mailbox,"",0,'unavail');
			$this->Vmx->setMenuOpt($mailbox,"",0,'busy');
			$this->Vmx->setMenuOpt($mailbox,"",0,'temp');
		} else {
			if (!isset($settings['vmx_option_0_number'])) {
				$settings['vmx_option_0_number'] = '';
			}
			$settings['vmx_option_0_number'] = preg_replace("/[^0-9]/" ,"", $settings['vmx_option_0_number']);
			$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_0_number'],0,'unavail');
			$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_0_number'],0,'busy');
			$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_0_number'],0,'temp');
		}

		if (isset($settings['vmx_state']) && $settings['vmx_state'] != 'disabled') {

			if (isset($settings['vmx_unavail_enabled']) && $settings['vmx_unavail_enabled'] != '') {
				$this->Vmx->setState($mailbox,'unavail','enabled');
			} else {
				$this->Vmx->setState($mailbox,'unavail','disabled');
			}

			if (isset($settings['vmx_busy_enabled']) && $settings['vmx_busy_enabled'] != '') {
				$this->Vmx->setState($mailbox,'busy','enabled');
			} else {
				$this->Vmx->setState($mailbox,'busy','disabled');
			}

			if (isset($settings['vmx_temp_enabled']) && $settings['vmx_temp_enabled'] != '') {
				$this->Vmx->setState($mailbox,'temp','enabled');
			} else {
				$this->Vmx->setState($mailbox,'temp','disabled');
			}

			if (isset($settings['vmx_play_instructions']) && $settings['vmx_play_instructions'] == 'yes') {
				$this->Vmx->setVmPlay($mailbox,'unavail',true);
				$this->Vmx->setVmPlay($mailbox,'busy',true);
				$this->Vmx->setVmPlay($mailbox,'temp',true);
			} else {
				$this->Vmx->setVmPlay($mailbox,'unavail',false);
				$this->Vmx->setVmPlay($mailbox,'busy',false);
				$this->Vmx->setVmPlay($mailbox,'temp',false);
			}

			if (isset($settings['vmx_option_1_system_default']) && $settings['vmx_option_1_system_default'] != '') {
				$this->Vmx->setFollowMe($mailbox,1,'unavail');
				$this->Vmx->setFollowMe($mailbox,1,'busy');
				$this->Vmx->setFollowMe($mailbox,1,'temp');
			} else {
				if (!isset($settings['vmx_option_1_number'])) {
					$settings['vmx_option_1_number'] = '';
				}
				$settings['vmx_option_1_number'] = preg_replace("/[^0-9]/" ,"", $settings['vmx_option_1_number']);
				$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_1_number'],1,'unavail');
				$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_1_number'],1,'busy');
				$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_1_number'],1,'temp');
			}
			if (isset($settings['vmx_option_2_number'])) {
				$settings['vmx_option_2_number'] = preg_replace("/[^0-9]/" ,"", $settings['vmx_option_2_number']);
				$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_2_number'],2,'unavail');
				$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_2_number'],2,'busy');
				$this->Vmx->setMenuOpt($mailbox,$settings['vmx_option_2_number'],2,'temp');
			}
		} else {
			if ($this->Vmx->isInitialized($mailbox)) {
				$this->Vmx->disable($mailbox);
			}
		}

		return true;
	}

	/**
	 * Get a list of users
	 */
	public function getUsersList() {
		return $this->FreePBX->Core->listUsers(true);
	}

	public function ucpDelGroup($id,$display,$data) {
	}

	public function ucpAddGroup($id, $display, $data) {
		$this->ucpUpdateGroup($id,$display,$data);
	}

	public function ucpUpdateGroup($id,$display,$data) {
		if($display == 'userman' && isset($_POST['type']) && $_POST['type'] == 'group') {
			if(!empty($_POST['ucp_voicemail'])) {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','assigned',$_POST['ucp_voicemail']);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','assigned',array('self'));
			}
			if(!empty($_POST['voicemail_enable']) && $_POST['voicemail_enable'] == "yes") {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','enable',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','enable',false);
			}
			if(!empty($_POST['voicemail_playback']) && $_POST['voicemail_playback'] == "yes") {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','playback',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','playback',false);
			}
			if(!empty($_POST['voicemail_download']) && $_POST['voicemail_download'] == "yes") {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','download',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','download',false);
			}
			if(!empty($_POST['voicemail_settings']) && $_POST['voicemail_settings'] == "yes") {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','settings',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','settings',false);
			}
			if(!empty($_POST['voicemail_greetings']) && $_POST['voicemail_greetings'] == "yes") {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','greetings',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','greetings',false);
			}
			if(!empty($_POST['vmxlocater']) && $_POST['vmxlocater'] == "yes") {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','vmxlocater',true);
			} else {
				$this->FreePBX->Ucp->setSettingByGID($id,'Voicemail','vmxlocater',false);
			}
		}
	}

	/**
	 * Delete user function, it's run twice because of scemantics with
	 * old freepbx but it's harmless
	 * @param  string $extension The extension number
	 * @param  bool $editmode  If we are in edit mode or not
	 */
	public function delUser($extension, $editmode=false) {
		if(!$editmode) {
			if(!function_exists('voicemail_mailbox_remove')) {
				$this->FreePBX->Modules->loadFunctionsInc('voicemail');
			}
			voicemail_mailbox_remove($extension);
			voicemail_mailbox_del($extension);
		}
	}

	/**
	* Hook functionality from userman when a user is deleted
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpDelUser($id, $display, $ucpStatus, $data) {

	}

	/**
	* Hook functionality from userman when a user is added
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpAddUser($id, $display, $ucpStatus, $data) {
		$this->ucpUpdateUser($id, $display, $ucpStatus, $data);
	}

	/**
	* Hook functionality from userman when a user is updated
	* @param {int} $id      The userman user id
	* @param {string} $display The display page name where this was executed
	* @param {array} $data    Array of data to be able to use
	*/
	public function ucpUpdateUser($id, $display, $ucpStatus, $data) {
		if($display == 'userman' && isset($_POST['type']) && $_POST['type'] == 'user') {
			if(!empty($_POST['ucp_voicemail'])) {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','assigned',$_POST['ucp_voicemail']);
			} else {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','assigned',null);
			}
			if(!empty($_POST['voicemail_enable']) && $_POST['voicemail_enable'] == "yes") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','enable',true);
			} elseif(!empty($_POST['voicemail_enable']) && $_POST['voicemail_enable'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','enable',false);
			} elseif(!empty($_POST['voicemail_enable']) && $_POST['voicemail_enable'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','enable',null);
			}
			if(!empty($_POST['voicemail_playback']) && $_POST['voicemail_playback'] == "yes") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','playback',true);
			} elseif(!empty($_POST['voicemail_playback']) && $_POST['voicemail_playback'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','playback',false);
			} elseif(!empty($_POST['voicemail_playback']) && $_POST['voicemail_playback'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','playback',null);
			}
			if(!empty($_POST['voicemail_download']) && $_POST['voicemail_download'] == "yes") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','download',true);
			} elseif(!empty($_POST['voicemail_download']) && $_POST['voicemail_download'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','download',false);
			} elseif(!empty($_POST['voicemail_download']) && $_POST['voicemail_download'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','download',null);
			}
			if(!empty($_POST['voicemail_settings']) && $_POST['voicemail_settings'] == "yes") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','settings',true);
			} elseif(!empty($_POST['voicemail_settings']) && $_POST['voicemail_settings'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','settings',false);
			} elseif(!empty($_POST['voicemail_settings']) && $_POST['voicemail_settings'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','settings',null);
			}
			if(!empty($_POST['voicemail_greetings']) && $_POST['voicemail_greetings'] == "yes") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','greetings',true);
			} elseif(!empty($_POST['voicemail_greetings']) && $_POST['voicemail_greetings'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','greetings',false);
			} elseif(!empty($_POST['voicemail_greetings']) && $_POST['voicemail_greetings'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','greetings',null);
			}
			if(!empty($_POST['vmxlocater']) && $_POST['vmxlocater'] == "yes") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','vmxlocater',true);
			} elseif(!empty($_POST['vmxlocater']) && $_POST['vmxlocater'] == "no") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','vmxlocater',false);
			} elseif(!empty($_POST['vmxlocater']) && $_POST['vmxlocater'] == "inherit") {
				$this->FreePBX->Ucp->setSettingByID($id,'Voicemail','vmxlocater',null);
			}
		}
	}

	public function ucpConfigPage($mode, $user, $action) {
		if(empty($user)) {
			$enable = ($mode == 'group') ? true : null;
			$playback = ($mode == 'group') ? true : null;
			$download = ($mode == 'group') ? true : null;
			$settings = ($mode == 'group') ? true : null;
			$greetings = ($mode == 'group') ? true : null;
			$vmxlocater = ($mode == 'group') ? true : null;
		} else {
			if($mode == "group") {
				$vmassigned = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Voicemail','assigned');
				$enable = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Voicemail','enable');
				$enable = !($enable) ? false : true;
				$vmassigned = !empty($vmassigned) ? $vmassigned : array('self');
				$playback = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Voicemail','playback');
				$playback = !($playback) ? false : true;
				$download = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Voicemail','download');
				$download = !($download) ? false : true;
				$settings = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Voicemail','settings');
				$settings = !($settings) ? false : true;
				$greetings = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Voicemail','greetings');
				$greetings = !($greetings) ? false : true;
				$vmxlocater = $this->FreePBX->Ucp->getSettingByGID($user['id'],'Voicemail','vmxlocater');
				$vmxlocater = !($vmxlocater) ? false : true;
			} else {
				$vmassigned = $this->FreePBX->Ucp->getSettingByID($user['id'],'Voicemail','assigned');
				$enable = $this->FreePBX->Ucp->getSettingByID($user['id'],'Voicemail','enable');
				$playback = $this->FreePBX->Ucp->getSettingByID($user['id'],'Voicemail','playback');
				$download = $this->FreePBX->Ucp->getSettingByID($user['id'],'Voicemail','download');
				$settings = $this->FreePBX->Ucp->getSettingByID($user['id'],'Voicemail','settings');
				$greetings = $this->FreePBX->Ucp->getSettingByID($user['id'],'Voicemail','greetings');
				$vmxlocater = $this->FreePBX->Ucp->getSettingByID($user['id'],'Voicemail','vmxlocater');
			}
		}
		$vmassigned = !empty($vmassigned) ? $vmassigned : array();

		$ausers = array();
		if($action == "showgroup" || $action == "addgroup") {
			$ausers['self'] = _("User Primary Extension");
		}
		if($action == "addgroup") {
			$vmassigned = array('self');
		}
		foreach(core_users_list() as $list) {
			$cul[$list[0]] = array(
				"name" => $list[1],
				"vmcontext" => $list[2]
			);
			$ausers[$list[0]] = $list[1] . " &#60;".$list[0]."&#62;";
		}
		$html[0] = array(
			"title" => _("Voicemail"),
			"rawname" => "voicemail",
			"content" => load_view(dirname(__FILE__)."/views/ucp_config.php",array("vmxlocater" => $vmxlocater, "playback" => $playback, "download" => $download, "settings" => $settings, "greetings" => $greetings, "mode" => $mode, "enable" => $enable, "ausers" => $ausers, "vmassigned" => $vmassigned))
		);
		return $html;
	}

	/**
	 * Get all known folders
	 */
	public function getFolders() {
		return $this->vmFolders;
	}

	/**
	 * Delete vm greeting from system
	 * @param int $ext  The voicemail extension
	 * @param string $type the type to remove
	 */
	public function deleteVMGreeting($ext,$type) {
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$vmfolder = $this->vmPath . '/'.$context.'/'.$ext;
		$type = basename($type);
		$file = $this->checkFileType($vmfolder, $type);
		if(isset($this->greetings[$type]) && !empty($file)) {
			foreach(glob($vmfolder."/".$type."*.*") as $filename) {
				if(!file_exists($filename)) {
					continue;
				}
				if(!unlink($filename)) {
					return false;
				}
			}
			return true;
		}
		return false;
	}

	/**
	 * Copy a VM Greeting
	 * @param int $ext    The voicemail extension
	 * @param string $source Voicemail source type
	 * @param string $target voicemail destination type
	 */
	public function copyVMGreeting($ext,$source,$target) {
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$vmfolder = $this->vmPath . '/'.$context.'/'.basename($ext);
		if(!file_exists($vmfolder)) {
			mkdir($vmfolder);
		}
		if(isset($this->greetings[$source]) && isset($this->greetings[$target])) {
			$tfile = $this->checkFileType($vmfolder, $target);
			if(!empty($tfile)) {
				$this->deleteVMGreeting($ext, $target);
			}
			$file = $this->checkFileType($vmfolder, $source);
			$extension = $this->getFileExtension($vmfolder, $source);
			copy($file, $vmfolder."/".basename($target).".".$extension);
		}
		return true;
	}

	/**
	 * Save Voicemail Greeting
	 * @param int $ext      The voicemail extension
	 * @param string $type     The voicemail type
	 * @param string $format   The file format
	 * @param string $file		The full path to the file
	 */
	public function saveVMGreeting($ext,$type,$format,$file) {
		$media = $this->FreePBX->Media;
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$vmfolder = $this->vmPath . '/'.$context.'/'.$ext;
		if(!file_exists($vmfolder)) {
			mkdir($vmfolder);
		}
		if(isset($this->greetings[$type])) {
			$media->load($file);
			$media->convert($vmfolder . "/" . $type . ".wav");
			unlink($file);
			return true;
		} else {
			return false;
		}
	}

	/**
	 * Get a voicemail box by extension
	 * @param int $ext The extension
	 */
	public function getVoicemailBoxByExtension($ext) {
		if(empty($this->vmBoxData[$ext])) {
			$this->vmBoxData[$ext] = $this->getMailbox($ext);
		}
		return !empty($this->vmBoxData[$ext]) ? $this->vmBoxData[$ext] : false;
	}

	/**
	 * Get all greetings by extension
	 * @param int $ext   The extension number
	 */
	public function getGreetingsByExtension($ext) {
		$o = $this->getVoicemailBoxByExtension($ext);
		//temp greeting <--overrides (temp.wav)
		//unaval (unavail.wav)
		//busy (busy.wav)
		//name (greet.wav)
		$context = $o['vmcontext'];
		$vmfolder = $this->vmPath . '/'.$context.'/'.$ext;
		$files = array();
		foreach(array_keys($this->greetings) as $greeting) {
			$file = $this->checkFileType($vmfolder, $greeting);
			if(file_exists($file)) {
				$files[$greeting] = $file;
			}
		}
		return $files;
	}

	/**
	 * Save Voicemail Settings for an extension
	 * @param int $ext      The voicemail extension
	 * @param string $pwd      The voicemail password/pin
	 * @param string $email    The voicemail email address
	 * @param string $page     The voicemail pager number
	 * @param bool $playcid  Whether to play the CID to the caller
	 * @param bool $envelope Whether to play the envelope to the caller
	 * @param bool $attach Whether to attach the voicemail to the outgoing email
	 * @param bool $delete Whether to delete the voicemail from local storage
	 */
	public function saveVMSettingsByExtension($ext,$pwd,$email,$page,$playcid,$envelope, $attach, $delete) {
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$vmconf = $this->getVoicemail();
		if(!empty($vmconf[$context][$ext])) {
			$vmconf[$context][$ext]['pwd'] = $pwd;
			$vmconf[$context][$ext]['email'] = $email;
			$vmconf[$context][$ext]['pager'] = $page;
			$vmconf[$context][$ext]['options']['saycid'] = ($playcid) ? 'yes' : 'no';
			$vmconf[$context][$ext]['options']['envelope'] = ($envelope) ? 'yes' : 'no';
			$vmconf[$context][$ext]['options']['attach'] = ($attach) ? 'yes' : 'no';
			$vmconf[$context][$ext]['options']['delete'] = ($delete) ? 'yes' : 'no';
			$this->saveVoicemail($vmconf);
			$this->astman->Command("voicemail reload");
			return true;
		}
		return false;
	}

	/**
	 * Delete a message by ID
	 * @param string $msg The message ID
	 * @param int $ext The extension
	 */
	public function deleteMessageByID($msg,$ext) {
		if(isset($this->greetings[$msg])) {
			return $this->deleteVMGreeting($ext,$msg);
		} else {
			$o = $this->getVoicemailBoxByExtension($ext);
			$message = $this->getMessageByMessageIDExtension($msg,$ext);
			if(!empty($message)) {
				foreach(glob($message['path']."/".$message['fid'].".*") as $filename) {
					if(!unlink($filename)) {
						return false;
					}
				}
				foreach(glob($message['path']."/".$message['fid']."_*.*") as $filename) {
					if(!unlink($filename)) {
						return false;
					}
				}
				$this->renumberAllMessages($message['path']);
				$this->astman->VoicemailRefresh($o['vmcontext'],$ext);
				return true;
			}
		}
		return false;
	}

	/**
	 * Renumber all messages in a voicemail folder
	 * so that asterisk can read the messages properly
	 * @param string $folder the voicemail folder to check
	 */
	public function renumberAllMessages($folder) {
		$count = 0;
		foreach(glob($folder."/*.txt") as $filename) {
			preg_match('/msg([0-9]+).txt/',$filename,$matches);
			$msgnum = (int)$matches[1];
			if($msgnum != $count) {
				$newn = sprintf('%04d', $count);
				foreach(glob($folder."/msg".$matches[1].".*") as $filename2) {
					$newpath = preg_replace('/msg([0-9]+)/','msg'.$newn,$filename2);
					if(file_exists($newpath)) {
						unlink($newpath);
					}
					rename($filename2,$newpath);
				}
				foreach(glob($folder."/msg".$matches[1]."_*.*") as $filename2) {
					$newpath = preg_replace('/msg([0-9]+)/','msg'.$newn,$filename2);
					if(file_exists($newpath)) {
						unlink($newpath);
					}
					rename($filename2,$newpath);
				}
			}
			$count++;
		}
	}

	/**
	* Forward a voicemail message to a new folder
	* @param string $msg    The message ID
	* @param int $ext    The voicemail extension message is coming from
	* @param int $rcpt The recipient, voicemail will wind up in the INBOX
	*/
	public function forwardMessageByExtension($msg,$ext,$to) {
		$fromVM = $this->getVoicemailBoxByExtension($ext);
		$messages = $this->getMessagesByExtension($ext);
		if(isset($messages['messages'][$msg])) {
			$info = $messages['messages'][$msg];
			$txt = $info['path']."/".$info['fid'].".txt";
			if(file_exists($txt) && is_readable($txt)) {
				$toVM = $this->getVoicemailBoxByExtension($to);
				$context = $toVM['vmcontext'];
				$toFolder = $this->vmPath . '/'.$context.'/'.$to.'/INBOX';
				if(file_exists($toFolder) && is_writable($toFolder)) {
					$files = array();
					$files[] = $txt;
					$movedFiles = array();
					foreach($info['format'] as $format) {
						if(file_exists($format['path']."/".$format['filename'])) {
							$files[] = $format['path']."/".$format['filename'];
						}
					}
					//if the folder is empty (meaning we dont have a 0000 file) then set this to 0000
					$tname = preg_replace('/([0-9]+)/','0000',basename($txt));
					$vminfotxt = '';
					if(!file_exists($toFolder."/".$tname)) {
						foreach($files as $file) {
							$fname = preg_replace('/msg([0-9]+)/','msg0000',basename($file));
							copy($file, $toFolder."/".$fname);
							$movedFiles[] = $toFolder."/".$fname;
						}
					} else {
						//Else we have other voicemail data in here so do something else

						//figure out the last file in the directory
						$oldFiles = glob($toFolder."/*.txt");
						$numbers = array();
						foreach($oldFiles as $file) {
							$file = basename($file);
							preg_match('/([0-9]+)/',$file,$matches);
							$numbers[] = $matches[1];
						}
						rsort($numbers);
						$next = sprintf('%04d', ($numbers[0] + 1));

						foreach($files as $file) {
							$fname = preg_replace('/msg([0-9]+)/',"msg".$next,basename($file));
							copy($file, $toFolder."/".$fname);
							$movedFiles[] = $toFolder."/".$fname;
						}
					}

					//send email from/to new mailbox
					$vm = $this->FreePBX->LoadConfig->getConfig("voicemail.conf");
					$emailInfo = array(
						"normal" => array(
							"body" => !empty($vm['general']['emailbody']) ? $vm['general']['emailbody'] : 'Dear ${VM_NAME}:\n\n\tjust wanted to let you know you were just left a ${VM_DUR} long message (number ${VM_MSGNUM})\nin mailbox ${VM_MAILBOX} from ${VM_CALLERID}, on ${VM_DATE}, so you might\nwant to check it when you get a chance.  Thanks!\n\n\t\t\t\t--Asterisk\n',
							"subject" => !empty($vm['general']['emailsubject']) ? $vm['general']['emailsubject'] : ((isset($vm['general']['pbxskip']) && $vm['general']['pbxskip'] == "no") ? "[PBX]: " : "").'New message ${VM_MSGNUM} in mailbox ${VM_MAILBOX}',
							"fromstring" => !empty($vm['general']['fromstring']) ? $vm['general']['fromstring'] : 'The Asterisk PBX'
						),
						"pager" => array(
							"body" => !empty($vm['general']['pagerbody']) ? $vm['general']['pagerbody'] : 'New ${VM_DUR} long msg in box ${VM_MAILBOX}\nfrom ${VM_CALLERID}, on ${VM_DATE}',
							"subject" => !empty($vm['general']['pagersubject']) ? $vm['general']['pagersubject'] : 'New VM',
							"fromstring" => !empty($vm['general']['pagerfromstring']) ? $vm['general']['pagerfromstring'] : 'The Asterisk PBX'
						)
					);
					$processUser = posix_getpwuid(posix_geteuid());
					$from = !empty($vm['general']['serveremail']) ? $vm['general']['serveremail'] : $processUser['name'].'@'.gethostname();
					foreach($emailInfo as &$einfo) {
						$einfo['body'] = str_replace(array(
							'\n',
							'\t'
						),
						array(
							"\n",
							"\t"
						),
						$einfo['body']);
						$einfo['body'] = str_replace(array(
							'${VM_NAME}',
							'${VM_MAILBOX}',
							'${VM_CALLERID}',
							'${VM_DUR}',
							'${VM_DATE}',
							'${VM_MSGNUM}'
						),
						array(
							$toVM['name'],
							$to,
							$info['callerid'],
							$info['duration'],
							$info['origdate'],
							$info['msg_id']
						),$einfo['body']);

						$einfo['subject'] = str_replace(array(
							'${VM_NAME}',
							'${VM_MAILBOX}',
							'${VM_CALLERID}',
							'${VM_DUR}',
							'${VM_DATE}',
							'${VM_MSGNUM}'
						),
						array(
							$toVM['name'],
							$to,
							$info['callerid'],
							$info['duration'],
							$info['origdate'],
							$info['msg_id']
						),$einfo['subject']);
					}

					if(!empty($toVM['email'])) {
						$em = new \CI_Email();
						if($toVM['options']['attach'] == "yes") {
							$em->attach($info['path']."/".$info['file']);
						}
						$em->from($from, $emailInfo['normal']['fromstring']);
						$em->to($toVM['email']);
						$em->subject($emailInfo['normal']['subject']);
						$em->message($emailInfo['normal']['body']);
						$em->send();
					}
					if(!empty($toVM['pager'])) {
						$em = new \CI_Email();
						$em->from($from, $emailInfo['pager']['fromstring']);
						$em->to($toVM['email']);
						$em->subject($emailInfo['pager']['subject']);
						$em->message($emailInfo['pager']['body']);
						$em->send();
					}
					if(isset($toVM['delete']) && $toVM['delete'] == "yes") {
						//now delete the voicemail wtf.
						foreach($movedFiles as $file) {
							unlink($file);
						}
					}
					//Just for sanity sakes recheck the directories hopefully this doesnt take hours though.
					$this->renumberAllMessages($toFolder);
				}
			}
		}
		if(!empty($fromVM['context'])) {
			$this->astman->VoicemailRefresh($fromVM['context'],$ext);
		}
		if(!empty($toVM['context'])) {
			$this->astman->VoicemailRefresh($toVM['context'],$to);
		}
	}

	/**
	* Copy a voicemail message to a new folder
	* @param string $msg    The message ID
	* @param int $ext    The voicemail extension
	* @param string $folder The folder to move the voicemail to
	*/
	public function copyMessageByExtensionFolder($msg,$ext,$folder) {
		if(!$this->folderCheck($folder)) {
			return false;
		}
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$messages = $this->getMessagesByExtension($ext);
		$vmfolder = $this->vmPath . '/'.$context.'/'.$ext;
		$folder = $vmfolder."/".$folder;
		if(isset($messages['messages'][$msg])) {
			$info = $messages['messages'][$msg];
			$txt = $vmfolder."/".$info['folder']."/".$info['fid'].".txt";
			if(file_exists($txt) && is_readable($txt)) {
				$files = array();
				$files[] = $txt;
				if(!file_exists($folder)) {
					mkdir($folder);
				}
				if(is_writable($folder)) {
					foreach($info['format'] as $format) {
						if(file_exists($format['path']."/".$format['filename'])) {
							$files[] = $format['path']."/".$format['filename'];
						}
					}
					//check to make sure the file doesnt already exist first.

					//if the folder is empty (meaning we dont have a 0000 file) then set this to 0000
					$tname = preg_replace('/([0-9]+)/','0000',basename($txt));
					if(!file_exists($folder."/".$tname)) {
						foreach($files as $file) {
							$fname = preg_replace('/msg([0-9]+)/','msg0000',basename($file));
							copy($file, $folder."/".$fname);
						}
					} else {
						//Else we have other voicemail data in here so do something else

						//figure out the last file in the directory
						$oldFiles = glob($folder."/*.txt");
						$numbers = array();
						foreach($oldFiles as $file) {
							$file = basename($file);
							preg_match('/([0-9]+)/',$file,$matches);
							$numbers[] = $matches[1];
						}
						rsort($numbers);
						$next = sprintf('%04d', ($numbers[0] + 1));

						foreach($files as $file) {
							$fname = preg_replace('/msg([0-9]+)/',"msg".$next,basename($file));
							copy($file, $folder."/".$fname);
						}
					}
					//Just for sanity sakes recheck the directories hopefully this doesnt take hours though.
					$this->renumberAllMessages($vmfolder."/".$info['folder']);
					$this->renumberAllMessages($folder);
					$this->astman->VoicemailRefresh($context,$ext);
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Move a voicemail message to a new folder
	 * @param string $msg    The message ID
	 * @param int $ext    The voicemail extension
	 * @param string $folder The folder to move the voicemail to
	 */
	public function moveMessageByExtensionFolder($msg,$ext,$folder) {
		if(!$this->folderCheck($folder)) {
			return false;
		}
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$messages = $this->getMessagesByExtension($ext);
		$vmfolder = $this->vmPath . '/'.$context.'/'.basename($ext);
		$folder = $vmfolder."/".basename($folder);
		if(isset($messages['messages'][$msg])) {
			$info = $messages['messages'][$msg];
			$txt = $vmfolder."/".$info['folder']."/".$info['fid'].".txt";
			if(file_exists($txt) && is_readable($txt)) {
				$files = array();
				$files[] = $txt;
				if(!file_exists($folder)) {
					mkdir($folder);
				}
				if(is_writable($folder)) {
					foreach($info['format'] as $format) {
						if(file_exists($format['path']."/".$format['filename'])) {
							$files[] = $format['path']."/".$format['filename'];
						}
					}
					//check to make sure the file doesnt already exist first.

					//if the folder is empty (meaning we dont have a 0000 file) then set this to 0000
					$tname = preg_replace('/([0-9]+)/','0000',basename($txt));
					if(!file_exists($folder."/".$tname)) {
						foreach($files as $file) {
							$fname = preg_replace('/msg([0-9]+)/','msg0000',basename($file));
							rename($file, $folder."/".$fname);
						}
					} else {
						//Else we have other voicemail data in here so do something else

						//figure out the last file in the directory
						$oldFiles = glob($folder."/*.txt");
						$numbers = array();
						foreach($oldFiles as $file) {
							$file = basename($file);
							preg_match('/([0-9]+)/',$file,$matches);
							$numbers[] = $matches[1];
						}
						rsort($numbers);
						$next = sprintf('%04d', ($numbers[0] + 1));

						foreach($files as $file) {
							$fname = preg_replace('/msg([0-9]+)/',"msg".$next,basename($file));
							rename($file, $folder."/".$fname);
						}
					}
					//Just for sanity sakes recheck the directories hopefully this doesnt take hours though.
					$this->renumberAllMessages($vmfolder."/".$info['folder']);
					$this->renumberAllMessages($folder);
					$this->astman->VoicemailRefresh($context,$ext);
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Get voicemail greeting by extension
	 * @param string $greeting The greeting name
	 * @param int $ext      The voicemail extension
	 */
	public function getGreetingByExtension($greeting,$ext) {
		$greetings = $this->getGreetingsByExtension($ext,true);
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$data = array();
		if(isset($greetings[$greeting])) {
			$data['path'] = $this->vmPath . '/'.$context.'/'.$ext;
			$data['file'] = basename($greetings[$greeting]);
		}
		return $data;
	}

	/**
	 * Get a message by ID and Extension
	 * @param string $msgid         The message ID
	 * @param int $ext           The voicemail extension
	 */
	public function getMessageByMessageIDExtension($msgid,$ext) {
		if(isset($this->greetings[$msgid])) {
			$out = $this->getGreetingByExtension($msgid,$ext);
			return !empty($out) ? $out : false;
		} else {
			$messages = $this->getMessagesByExtension($ext);
			if(!empty($messages['messages'][$msgid])) {
				$msg = $messages['messages'][$msgid];
				return $messages['messages'][$msgid];
			} else {
				return false;
			}
		}
	}

	/**
	 * Get all messages for an extension
	 * @param int $extension The voicemail extension
	 */
	public function getMessagesByExtension($extension) {
		if(!empty($this->messageCache)) {
			return $this->messageCache;
		}
		$extension = basename($extension);
		$o = $this->getVoicemailBoxByExtension($extension);
		$context = $o['vmcontext'];

		$out = array(
			"messages" => array()
		);
		$vmfolder = $this->vmPath . '/'.$context.'/'.$extension;
		if (is_dir($vmfolder) && is_readable($vmfolder)) {
			$count = 1;
			foreach (glob($vmfolder . '/*',GLOB_ONLYDIR) as $folder) {
				foreach (glob($folder."/*.txt") as $filename) {
					//$start = microtime(true);
					if($count > ($this->messageLimit)) {
						$this->displayMessage['message'] = sprintf(_('Warning, You are over the max message display amount of %s only %s messages will be shown'),$this->messageLimit,$this->messageLimit);
						break 2;
					}
					$vm = pathinfo($filename,PATHINFO_FILENAME);
					$vfolder = dirname($filename);
					$txt = $vfolder."/".$vm.".txt";
					$wav = $this->checkFileType($vfolder, $vm);
					if(file_exists($txt) && is_readable($txt) && file_exists($wav)) {
						$data = $this->FreePBX->LoadConfig->getConfig($vm.".txt", $vfolder, 'message');
						$key = !empty($data['msg_id']) ? $data['msg_id'] : basename($folder)."_".$vm;
						if(isset($out['messages'][$key])) {
							$key = $key."_".basename($folder)."_".$vm;
							if(isset($out['messages'][$key])) {
								continue;
							}
						}
						$out['messages'][$key] = $data;
						$out['messages'][$key]['self'] = $filename;
						$out['messages'][$key]['msg_id'] = $key;
						$out['messages'][$key]['file'] = basename($wav);
						$out['messages'][$key]['folder'] = basename($folder);
						$out['messages'][$key]['fid'] = $vm;
						$out['messages'][$key]['context'] = $context;
						$out['messages'][$key]['path'] = $folder;

						$extension = $this->getFileExtension($vfolder, $vm);
						$out['messages'][$key]['format'][$extension] = array(
							"filename" => basename($wav),
							"path" => $folder,
							"length" => filesize($wav)
						);
						$out['total'] = $count++;
					}
				}
			}
		}
		$this->messageCache = $out;
		return $this->messageCache;
	}

	/**
	 * Get messages by extension and folder within
	 * @param int $extension The voicemail extension
	 * @param string $folder    The voicemail folder name
	 * @param int $start     The starting position
	 * @param int $limit     The amount of messages to return
	 */
	public function getMessagesByExtensionFolder($extension,$folder,$order,$orderby,$start,$limit) {
		$messages = $this->getMessagesByExtension($extension);
		$count = 1;
		$aMsgs = array();
		foreach($messages['messages'] as $message) {
			if($message['folder'] != $folder) {
				continue;
			}
			$id = $message['msg_id'];
			$aMsgs['messages'][$id] = $message;
			$count++;
		}
		if(empty($aMsgs)) {
			return $aMsgs;
		}
		$aMsgs['count'] = $count;

		//https://bugs.php.net/bug.php?id=50688
		@usort($aMsgs['messages'], function($a, $b) use ($orderby) {
			return strcmp($a[$orderby],$b[$orderby]);
		});
		$aMsgs['messages'] = array_values($aMsgs['messages']);
		$aMsgs['messages'] = ($order == 'asc') ? array_reverse($aMsgs['messages']) : $aMsgs['messages'];
		$out = array();
		for($i=$start;$i<($start+$limit);$i++) {
			if(empty($aMsgs['messages'][$i])) {
				break;
			}
			$out['messages'][] = $aMsgs['messages'][$i];
		}
		return $out;
	}

	/**
	 * Get the total number of messages in a folder
	 * @param int $extension The voicemail extension
	 * @param string $folder    The voicemail folder
	 */
	public function getMessagesCountByExtensionFolder($extension,$folder) {
		$messages = $this->getMessagesByExtension($extension);
		$count = 0;
		foreach($messages['messages'] as $message) {
			if($message['folder'] != $folder) {
				continue;
			}
			$count++;
		}
		return $count;
	}

	public function myDialplanHooks() {
		return true;
	}

	/**
	 * During Retrieve conf use this to cleanup all orphan greeting conversions
	 */
	public function doDialplanHook(&$ext, $engine, $priority) {
		foreach (glob($this->vmPath."/*",GLOB_ONLYDIR) as $type) {
			foreach (glob($type."/*",GLOB_ONLYDIR) as $directory) {
				//Clean up all orphan greetings
				foreach (glob($directory."/*") as $file) {
					if(!is_dir($file)) {
						$basename = basename($file);
						$dirname = dirname($file);
						if(preg_match("/(.*)\_[0-9a-f]{40}\./i",$basename,$matches)) {
							$sha1 = $matches[2];
							$filename = $matches[1];
							$filepath = $this->checkFileType($dirname,$filename);
							if(empty($filepath) || !file_exists($filepath) || sha1_file($filepath) != $sha1) {
								unlink($file);
							}
						}
					} else {
						//Cleanup all orphan messages
						foreach (glob($file."/*") as $vmfile) {
							$basename = basename($vmfile);
							$dirname = dirname($vmfile);
							if(preg_match("/(.*)\_[0-9a-f]{40}\./i",$basename,$matches)) {
								$sha1 = $matches[2];
								$filename = $matches[1];
								$filepath = $this->checkFileType($dirname,$filename);
								if(empty($filepath) || !file_exists($filepath) || sha1_file($filepath) != $sha1) {
									unlink($vmfile);
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Check for a valid folder name
	 * @param string $folder the provided folder
	 */
	private function folderCheck($folder) {
		return !preg_match('/[\.|\/]/',$folder) && $this->validFolder($folder);
	}

	/**
	 * Checks ot make sure the folder name is in our list of valid folders
	 * @param string $folder the provided folder name
	 */
	private function validFolder($folder) {
		return isset($this->vmFolders[$folder]);
	}

	private function checkFileType($path, $filename) {
		switch(true) {
			case file_exists($path . "/" . $filename.".wav"):
				return $path . "/" . $filename.".wav";
			break;
			case file_exists($path . "/" . $filename.".WAV"):
				return $path . "/" . $filename.".WAV";
			break;
			case file_exists($path . "/" . $filename.".gsm"):
				return $path . "/" . $filename.".gsm";
			break;
			default:
				return false;
			break;
		}
	}

	private function getFileExtension($path, $filename) {
		$file = $this->checkFileType($path, $filename);
		if(empty($file)) {
			return false;
		}
		switch(true) {
			case preg_match("/WAV$/", $file):
				return 'WAV';
			break;
			case preg_match("/wav$/", $file):
				return 'wav';
			break;
			case preg_match("/gsm$/", $file):
				return 'gsm';
			break;
			default:
				return false;
			break;
		}
	}

	/**
	* Get the voicemail count from Asterisk
	* Cache the data after we get it so we dont have to make further requests to Asterisk.
	*/
	public function getMailboxCount($exts = array()) {
		if(!empty($this->boxes)) {
			return $this->boxes;
		}
		$boxes = array();
		$total = 0;
		foreach($exts as $extension) {
			$mailbox = $this->astman->MailboxCount($extension);
			if($mailbox['Response'] == "Success" && !empty($mailbox['Mailbox']) && $mailbox['Mailbox'] == $extension) {
				$total = $total + (int)$mailbox['NewMessages'];
				$boxes['extensions'][$extension] = (int)$mailbox['NewMessages'];
			}
		}
		$boxes['total'] = $total;
		$this->boxes = $boxes;
		return $boxes;
	}
	public function getActionBar($request) {
		$buttons = array();
		switch($request['display']) {
			case 'voicemail':
				$buttons = array(
					'reset' => array(
						'name' => 'reset',
						'id' => 'reset',
						'value' => _('Reset')
					),
					'submit' => array(
						'name' => 'submit',
						'id' => 'submit',
						'value' => _('Submit')
					)
				);
			break;
		}
		return $buttons;
	}

	public function bulkhandlerGetHeaders($type) {
		switch ($type) {
		case 'extensions':
			$headers = array(
				'voicemail_enable' => array(
					'description' => _('Voicemail Enable [Blank to disable]'),
				),
				'voicemail_vmpwd' => array(
					'description' => _('Voicemail Password'),
				),
				'voicemail_email' => array(
					'description' => _('Voicemail E-Mail'),
				),
				'voicemail_options' => array(
					'description' => _('Voicemail Options is a pipe-delimited list of options.  Example: attach=no|delete=no'),
				),
			);

			return $headers;
		}
	}

	public function bulkhandlerImport($type, $rawData) {
		$ret = NULL;

		switch ($type) {
		case 'extensions':
			foreach ($rawData as $data) {
				$mailbox = array();

				$extension = $data['extension'];

				foreach ($data as $key => $value) {
					if (substr($key, 0, 10) == 'voicemail_') {
						$mailbox[substr($key, 10)] = $value;
					}
				}

				if (count($mailbox) > 0 && !empty($mailbox['enable'])) {
					$mailbox['vm'] = 'enabled';
					$mailbox['name'] = $data['name'];
					unset($mailbox['enable']);
					try {
						$this->addMailbox($extension, $mailbox, false);
					} catch (\Exception $e) {
						return array("status" => false, "message" => $e->getMessage());
					}
					$sql = "UPDATE users SET voicemail = 'default' WHERE extension = ?";
					$sth = $this->db->prepare($sql);
					$sth->execute(array($extension));
					$this->astman->database_put("AMPUSER",$extension."/voicemail",'default');
					$this->setupMailboxSymlinks($extension);
					$mailbox = $this->getMailbox($extension, false);
					if(empty($mailbox)) {
						return array("status" => false, "message" => _("Unable to add mailbox!"));
					}
				} else {
					$this->removeMailbox($extension, false);
					$this->delMailbox($extension, false);
				}
			}

			$ret = array(
				'status' => true,
			);

			break;
		}

		return $ret;
	}

	public function bulkhandlerExport($type) {
		$data = NULL;

		switch ($type) {
		case 'extensions':
			$uservm = $this->getVoicemail();
			$vmcontexts = array_keys($uservm);

			foreach ($vmcontexts as $vmcontext) {
				if($vmcontext == "general" || $vmcontext == "zonemessages") {
					continue;
				}

				foreach ($uservm[$vmcontext] as $extension => $mailbox) {
					unset($mailbox['mailbox']);

					$opts = array();
					if(!empty($mailbox['options'])) {
						foreach($mailbox['options'] as $key => $value) {
							$opts[] = $key."=".$value;
						}
					}
					$mailbox['options'] = implode("|",$opts);

					$pmailbox = array(
						"voicemail_enable" => "yes"
					);
					foreach ($mailbox as $key => $value) {
						if($key == "name") {
							continue;
						}
						switch ($key) {
						case 'pwd':
							$settingname = 'vmpwd';
							break;
						default:
							$settingname = $key;
							break;
						}
						$pmailbox['voicemail_' . $settingname] = $value;
					}

					$data[$extension] = $pmailbox;
				}
			}

			break;
		}

		return $data;
	}



	public function constructSettings($level="general") {
		$settings = array(
			"general" => array(
				"name" => _("General"),
				"helptext" => "",
				"settings" => array(
					"name" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("Name of account/user"),
						"helptext" => _("Name of account/user") . " [name]"
					),
					"charset" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "UTF-8",
						"description" => _("Character Set"),
						"helptext" => _("The character set for Voicemail messages") . " [charset]"
					),
					"pager" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("Pager Email Address"),
						"helptext" => _("Pager/mobile email address that short Voicemail notifications are sent to.") . " [pager]"
					),
					"email" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("Email Address"),
						"helptext" => _("The email address that Voicemails are sent to.") . " [email]"
					),
					"backupdeleted" => array(
						"level" => array("general","account"),
						"type" => "text",
						"default" => "",
						"description" => _("Max Number of Deleted Messages"),
						"helptext" => _("No. of deleted messages saved per mailbox (can be a number or yes/no, yes meaning MAXMSG, no meaning 0).") . " [backupdeleted]"
					),
					"externnotify" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("External Notify"),
						"helptext" => _("External Voicemail notify application.") . " [externnotify]"
					),
					"externpass" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("External Password"),
						"helptext" => _("External password changing command (overrides 'External Password Notify'). The arguments passed to the application are: [context] [mailbox] [newpassword] Note: If this is set, the password will NOT be changed in voicemail.conf If you would like to also change the password in voicemail.conf, use the 'External Password Notify' option below instead.") . " [externpass]"
					),
					"externpassnotify" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("External Password Notify"),
						"helptext" => _("Command specified runs after a user changes their password. The arguments passed to the application are: [context] [mailbox] [newpassword] Note: This will also update the voicemail.conf file") . " [externpassnotify]"
					),
					"format" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("File Format"),
						"helptext" => _("Formats for writing Voicemail. Note that when using IMAP storage for Voicemail, only the first format specified will be used."). " [format]"
					),
					"attachfmt" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("File Format"),
						"helptext" => _("Which format to attach to the email.  Normally this is the first format specified in the format parameter above, but this option lets you customize the format sent to particular mailboxes. Useful if Windows users want wav49, but Linux users want gsm."). " [attachfmt]"
					),
					"listen-control-forward-key" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Fast-Foward Keys"),
						"helptext" => _("Customize the key that fast-forwards message playback"). " [listen-control-forward-key]"
					),
					"listen-control-reverse-key" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Reverse Keys"),
						"helptext" => _("Customize the key that fast-forwards message playback"). " [listen-control-reverse-key]"
					),
					"listen-control-pause-key" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Pause Keys"),
						"helptext" => _("Customize the key that fast-forwards message playback"). " [listen-control-pause-key]"
					),
					"listen-control-restart-key" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Restart Keys"),
						"helptext" => _("Customize the key that fast-forwards message playback"). " [listen-control-restart-key]"
					),
					"listen-control-stop-key" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Stop Keys"),
						"helptext" => _("Customize the key that fast-forwards message playback"). " [listen-control-stop-key]"
					),
					"tz" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("Timezone"),
						"helptext" => _("Timezone") . " [tz]"
					),
					"callmenum" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("Call-Me Number"),
						"helptext" => _("Call me number. Can be used from within ARI.") . " [callmenum]"
					),
					"volgain" => array(
						"level" => array("general","account"),
						"type" => "text",
						"default" => "",
						"description" => _("Volume Gain"),
						"helptext" => _("Emails bearing the Voicemail may arrive in a volume too quiet to be heard. This parameter allows you to specify how much gain to add to the message when sending a Voicemail. NOTE: sox must be installed for this option to work.") . " [volgain]"
					),
					"saydurationm" => array(
						"level" => array("general","account"),
						"type" => "number",
						"default" => "",
						"description" => _("Say Duration Minutes"),
						"helptext" => _("Specify in minutes the minimum duration to say. Default is 2 minutes. (in minutes)"). " [saydurationm]"
					),
					"pollfreq" => array(
						"level" => array("general"),
						"type" => "number",
						"default" => "30",
						"description" => _("Poll Frequency"),
						"helptext" => _("External Voicemail notify application.") . " [pollfreq]"
					),
					"pollmailboxes" => array(
						"level" => array("general"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Poll Mailboxes"),
						"helptext" => _("If mailboxes are changed anywhere outside of app_voicemail, then this option must be enabled for MWI to work. This enables polling mailboxes for changes.  Normally, it will expect that changes are only made when someone called in to one of the voicemail applications. Examples of situations that would require this option are web interfaces to voicemail or an email client in the case of using IMAP storage."). " [pollmailboxes]"
					),
					"envelope" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Envelope Playback"),
						"helptext" => _("Turn on/off envelope playback before message playback") . " [envelopw]"
					),
					"delete" => array(
						"level" => array("account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Delete Voicemail"),
						"helptext" => _("After notification, the voicemail is deleted from the server. [per-mailbox only] This is intended for use with users who wish to receive their voicemail ONLY by email.") . " [delete]"
					),
					"forcename" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Force Name"),
						"helptext" => _("Force a new user to record their name. A new user is determined by the password being the same as the mailbox number. Default is 'Yes'"). " [forcename]"
					),
					"forcegreetings" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "no",
						"description" => _("Force Greetings"),
						"helptext" => _("This is the same as Force Name, except for recording greetings. Default is 'No'"). " [forcegreetings]"
					),
					"operator" => array(
						"level" => array("general", "account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Operator"),
						"helptext" => _("Allow sender to hit 0 before/after/during leaving a voicemail to reach an operator"). " [operator]"
					),
					"review" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "no",
						"description" => _("Review Message"),
						"helptext" => _("Allow sender to review/rerecord their message before saving it"). " [review]"
					),
					"saycid" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "no",
						"description" => _("Say CID"),
						"helptext" => _("Read back caller's telephone number prior to playing the incoming message, and just after announcing the date and time the message was left. If not described, or set to no, it will be in the envelope."). " [saycid]"
					),
					"sayduration" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Say Duration"),
						"helptext" => _("Turn on/off saying duration information before the message playback."). " [sayduration]"
					),
					"searchcontexts" => array(
						"level" => array("general"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "no",
						"description" => _("Search Contexts"),
						"helptext" => _("Current default behavior is to search only the default context if one is not specified.  The older behavior was to search all contexts. This option restores the old behavior [DEFAULT=no] Note: If you have this option enabled, then you will be required to have unique mailbox names across all contexts. Otherwise, an ambiguity is created since it is impossible to know which mailbox to retrieve when one is requested."). " [searchcontexts]"
					),
					"sendvoicemail" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Send Voicemail"),
						"helptext" => _("Allow the user to compose and send a voicemail while inside VoiceMailMain() [option 5 from mailbox's advanced menu]. If set to 'no', option 5 will not be listed."). " [sendvoicemail]"
					),
					"tempgreetwarn" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Temporary Greeting Warn"),
						"helptext" => _("Remind the user that their temporary greeting is set"). " [tempgreetwarn]"
					),
					"usedirectory" => array(
						"level" => array("general"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Use Directory"),
						"helptext" => _("Permit finding entries for forward/compose from the directory"). " [usedirectory]"
					),
					"hidefromdir" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "no",
						"description" => _("Hide From Directory"),
						"helptext" => _("Hide this mailbox from the directory produced by app_directory") . " [hidefromdir]"
					),
					"moveheard" => array(
						"level" => array("general","account"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Move Heard"),
						"helptext" => _("Move heard messages to the 'Old' folder automagically.  Defaults to Yes.") . " [moveheard]"
					),
					"smdienable" => array(
						"level" => array("general"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "no",
						"description" => _("Enable SMDI notification"),
						"helptext" => _("Enable SMDI notification"). " [smdienable]"
					),
					"smdiport" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("SMDI Port"),
						"helptext" => _("Set to a valid port as specified in smdi.conf"). " [smdiport]"
					),
					"adsifdn" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("ADSI feature descriptor"),
						"helptext" => _("The ADSI feature descriptor number to download to"). " [adsifdn]"
					),
					"adsisec" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("ADSI Security Lock Code"),
						"helptext" => _("The ADSI security lock code"). " [adsisec]"
					),
				)
			),
			"email" => array(
				"name" => _("Email Config"),
				"helptext" => _("These settings apply to Voicemail Email Configuration"),
				"settings" => array(
					"emailsubject" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => 'PBX Voicemail Notification',
						"description" => _("Email Subject"),
						"helptext" => _("The email subject")." [emailsubject]"
					),
					"emailbody" => array(
						"level" => array("general"),
						"type" => "textbox",
						"default" => '${VM_NAME},\n\nThere is a new voicemail in mailbox ${VM_MAILBOX}:\n\n\tFrom:\t${VM_CALLERID}\n\tLength:\t${VM_DUR} seconds\n\tDate:\t${VM_DATE}\n\nDial *98 to access your voicemail by phone.\nVisit http://AMPWEBADDRESS/ucp to check your voicemail with a web browser.\n',
						"len" => 512,
						"description" => _("Email Body"),
						"helptext" => _('The email body. Change the from, body and/or subject, variables: VM_NAME, VM_DUR, VM_MSGNUM, VM_MAILBOX, VM_CALLERID, VM_CIDNUM, VM_CIDNAME, VM_DATE. Additionally, on forwarded messages, you have the variables: ORIG_VM_CALLERID, ORIG_VM_CIDNUM, ORIG_VM_CIDNAME, ORIG_VM_DATE You can select between two variables by using dialplan functions, e.g. ${IF(${ISNULL(${ORIG_VM_DATE})}?${VM_DATE}:${ORIG_VM_DATE})}.') . " [emailbody]"
					),
					"fromstring" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => 'PBX Phone System',
						"description" => _("Email From String"),
						"helptext" => _("From: string for email")." [fromstring]"
					),
					"emaildateformat" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => '%A, %B %d, %Y at %r',
						"description" => _("Email Date Format"),
						"helptext" => _("Set the date format on outgoing mails. Valid arguments can be found on the strftime(3) man page")." [emaildateformat]"
					),
					"pagersubject" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => 'PBX Voicemail Notification',
						"description" => _("Pager Subject"),
						"helptext" => _("The pager subject")." [pagersubject]"
					),
					"pagerbody" => array(
						"level" => array("general"),
						"type" => "textbox",
						"default" => 'New ${VM_DUR} long msg in box ${VM_MAILBOX}\nfrom ${VM_CALLERID}, on ${VM_DATE}',
						"len" => 512,
						"description" => _("Pager Body"),
						"helptext" => _('The pager body. Change the from, body and/or subject, variables: VM_NAME, VM_DUR, VM_MSGNUM, VM_MAILBOX, VM_CALLERID, VM_CIDNUM, VM_CIDNAME, VM_DATE. Additionally, on forwarded messages, you have the variables: ORIG_VM_CALLERID, ORIG_VM_CIDNUM, ORIG_VM_CIDNAME, ORIG_VM_DATE You can select between two variables by using dialplan functions, e.g. ${IF(${ISNULL(${ORIG_VM_DATE})}?${VM_DATE}:${ORIG_VM_DATE})}.') . " [emailbody]"
					),
					"pagerfromstring" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => 'PBX Phone System',
						"description" => _("Pager From String"),
						"helptext" => _("From: string for email")." [pagerfromstring]"
					),
					"pagerdateformat" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => '%A, %B %d, %Y at %r',
						"description" => _("Pager Date Format"),
						"helptext" => _("Set the date format on outgoing pager mails. Valid arguments can be found on the strftime(3) man page")." [pagerdateformat]"
					),
					"serveremail" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => '',
						"description" => _("Server Email"),
						"helptext" => _("Who the e-mail notification should appear to come from")." [serveremail]"
					),
					"pbxskip" => array(
						"level" => array("general"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "no",
						"description" => _("Skip PBX String"),
						"helptext" => _('Skip the "[PBX]:" string from the message title'). " [pbxskip]"
					),
					"attach" => array(
						"level" => array("general"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Attach Voicemail"),
						"helptext" => _("Option to attach Voicemails to email.") . " [attach]"
					),
					"language" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => '',
						"description" => _("Language"),
						"helptext" => _("Language code for voicemail")." [language]"
					),
					"nextaftercmd" => array(
						"level" => array("general"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("Next after command"),
						"helptext" => _("Skips to the next message after hitting 7 or 9 to delete/save current message.") . " [nextaftercmd]"
					),
					"mailcmd" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => '',
						"description" => _("Mail Command"),
						"helptext" => _("Specify what command is called for outbound E-mail")." [mailcmd]"
					),
				)
			),
			"limits" => array(
				"name" => _("Limits"),
				"helptext" => "",
				"settings" => array(
					"maxgreet" => array(
						"level" => array("general"),
						"type" => "number",
						"default" => '60',
						"description" => _("Max Greeting Length"),
						"helptext" => _("Max message greeting length. (in seconds)")." [maxgreet]"
					),
					"maxlogins" => array(
						"level" => array("general"),
						"type" => "number",
						"default" => '3',
						"description" => _("Max Failed Logins"),
						"helptext" => _("Max failed login attempts.")." [maxlogins]"
					),
					"maxmsg" => array(
						"level" => array("general"),
						"type" => "number",
						"options" => array("0","9999"),
						"default" => '100',
						"description" => _("Max Messages"),
						"helptext" => _("Maximum number of messages per folder. If not specified, a default value (100) is used. Maximum value for this option is 9999.")." [maxmsg]"
					),
					"minpassword" => array(
						"level" => array("general"),
						"type" => "number",
						"options" => array("0","100"),
						"default" => '0',
						"description" => _("Minimum Password"),
						"helptext" => _("Enforce minimum password length")." [minpassword]"
					),
					"maxsecs" => array(
						"level" => array("general"),
						"type" => "number",
						"options" => array("0","9999"),
						"default" => '300',
						"description" => _("Max Message Length"),
						"helptext" => _("Minimum length of a voicemail message in seconds for the message to be kept. The default is 300 (in seconds).")." [maxsecs]"
					),
					"maxsilence" => array(
						"level" => array("general"),
						"type" => "number",
						"options" => array("0","9999"),
						"default" => '10',
						"description" => _("Max Message Silence"),
						"helptext" => _("How many seconds of silence before we end the recording (in seconds).")." [maxsilence]"
					),
					"silencethreshold" => array(
						"level" => array("general"),
						"type" => "number",
						"options" => array("0","9999"),
						"default" => '',
						"description" => _("Silence Threshold"),
						"helptext" => _("Silence threshold (what we consider silence: the lower, the more sensitive)")." [silencethreshold]"
					),
					"minsecs" => array(
						"level" => array("general"),
						"type" => "number",
						"options" => array("0","9999"),
						"default" => '1',
						"description" => _("Min Message Length"),
						"helptext" => _("Minimum length of a voicemail message in seconds for the message to be kept. (in seconds).")." [minsecs]"
					),
					"skipms" => array(
						"level" => array("general"),
						"type" => "number",
						"options" => array("0","9999"),
						"default" => '',
						"description" => _("Skip Milliseconds"),
						"helptext" => _("How many milliseconds to skip forward/back when rew/ff in message playback (in milliseconds)")." [skipms]"
					),
					"skipms" => array(
						"level" => array("general"),
						"type" => "number",
						"options" => array("0","9999"),
						"default" => '',
						"description" => _("Skip Milliseconds"),
						"helptext" => _("How many milliseconds to skip forward/back when rew/ff in message playback (in milliseconds)")." [skipms]"
					),
				)
			),
			"odbc" => array(
				"name" => _("ODBC Storage"),
				"helptext" => _("These settings are only applicable when Asterisk is compiled with IMAP support"),
				"settings" => array(
					"odbcstorage" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("ODBC Storage Name"),
						"helptext" => _("The value of odbcstorage is the database connection configured in res_odbc.conf.") . " [odbcstorage]"
					),
					"odbctable" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("ODBC Table Name"),
						"helptext" => _("The default table for ODBC voicemail storage is voicemessages.") . " [odbctable]"
					),
				)
			),
			"imap" => array(
				"name" => _("IMAP Storage"),
				"helptext" => _("These settings are only applicable when Asterisk is compiled with IMAP support"),
				"settings" => array(
					"imapserver" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("IMAP server address."),
						"helptext" => _("IMAP server address.") . " [imapserver]"
					),
					"imapport" => array(
						"level" => array("general"),
						"type" => "number",
						"default" => "",
						"description" => _("IMAP server port."),
						"helptext" => _("IMAP server port.") . " [imapport]"
					),
					"authuser" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("IMAP Auth User"),
						"helptext" => _("IMAP server master username.") . " [authuser]"
					),
					"authpassword" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("IMAP Auth User Password"),
						"helptext" => _("IMAP server master password.") . " [authpassword]"
					),
					"imapuser" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("IMAP User"),
						"helptext" => _("The IMAP username of the mailbox to access") . " [authuser]"
					),
					"imappassword" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("IMAP Password"),
						"helptext" => _("The IMAP password of the user") . " [authpassword]"
					),
					"imapflags" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("IMAP Flags"),
						"helptext" => _("Optional flags to pass to the IMAP server in the IMAP mailbox name. For example, setting this to 'ssl' will enable OpenSSL encryption, assuming the IMAP libraries were compiled with OpenSSL support."). " [imapflags]"
					),
					"imapfolder" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("IMAP Folder"),
						"helptext" => _("The folder in which to store voicemail messages on the IMAP server. By default, they are stored in INBOX."). " [imapfolder]"
					),
					"imapparentfolder" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("IMAP Parent Folder"),
						"helptext" => _("Some IMAP server implementations store folders under INBOX instead of using a top level folder (ex. INBOX/Friends). In this case, user imapparentfolder to set the parent folder. For example, Cyrus IMAP does NOT use INBOX as the parent. Default is to have no parent folder set."). " [imapparentfolder]"
					),
					"greetingsfolder" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Greetings folder"),
						"helptext" => _("If IMAP Greetings is Yes, then specify which folder to store your greetings in. If you do not specify a folder, then INBOX will be used"). " [greetingsfolder]"
					),
					"imapgreetings" => array(
						"level" => array("general"),
						"type" => "radio",
						"options" => array("yes" => _("Yes"), "no" => _("No")),
						"default" => "yes",
						"description" => _("IMAP Greetings"),
						"helptext" => _("If using IMAP storage, specify whether Voicemail greetings should be stored via IMAP. If not set, then greetings are stored as if IMAP storage were not enabled"). " [imapgreetings]"
					),
					"imapclosetimeout" => array(
						"level" => array("general"),
						"type" => "number",
						"default" => "60",
						"description" => _("Close Timeout"),
						"helptext" => _("The TCP close timeout (in seconds)"). " [imapclosetimeout]"
					),
					"imapopentimeout" => array(
						"level" => array("general"),
						"type" => "number",
						"default" => "60",
						"description" => _("Open Timeout"),
						"helptext" => _("The TCP open timeout (in seconds)"). " [imapopentimeout]"
					),
					"imapreadtimeout" => array(
						"level" => array("general"),
						"type" => "number",
						"default" => "60",
						"description" => _("Read Timeout"),
						"helptext" => _("The TCP read timeout (in seconds)"). " [imapreadimeout]"
					),
					"imapwritetimeout" => array(
						"level" => array("general"),
						"type" => "number",
						"default" => "60",
						"description" => _("Write Timeout"),
						"helptext" => _("The TCP write timeout (in seconds)"). " [imapwritetimeout]"
					),
				)
			),
			"sounds" => array(
				"name" => _("Sound Files"),
				"helptext" => _("Sound files used for Voicemail"),
				"settings" => array(
					"vm-mismatch" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Password Mismatch"),
						"helptext" => _('Customize which sound file is used instead of the default prompt that says: "The passwords you entered and re-entered did not match. Please try again."'). " [vm-mismatch]"
					),
					"vm-newpassword" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("New Password"),
						"helptext" => _('Customize which sound file is used instead of the default prompt that says: "Please enter your new password followed by the pound key."'). " [vm-newpassword]"
					),
					"vm-passchanged" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Password Changed"),
						"helptext" => _('Customize which sound file is used instead of the default prompt that says: "Your password has been changed."'). " [vm-passchanged]"
					),
					"vm-password" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Password"),
						"helptext" => _('Customize which sound file is used instead of the default prompt that says: "password"'). " [vm-password]"
					),
					"vm-reenterpassword" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Re-enter Password"),
						"helptext" => _('Customize which sound file is used instead of the default prompt that says: "Please re-enter your password followed by the pound key"'). " [vm-reenterpassword]"
					),
					"vm-invalid-password" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Invalid Password"),
						"helptext" => _('Customize which sound file is used instead of the default prompt that says: ...'). " [vm-invalid-password]"
					),
					"vm-pls-try-again" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Please Try Again"),
						"helptext" => _('Customize which sound file is used instead of the default prompt that says "Please try again."'). " [vm-pls-try-again]"
					),
					"vm-prepend-timeout" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Prepend Timeout"),
						"helptext" => _('Customize which sound file is used when the user times out while recording a prepend message instead of the default prompt that says "then press pound"'). " [vm-prepend-timeout]"
					),
					"directoryintro" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("Directory Intro"),
						"helptext" => _('For the directory, you can override the intro file if you want'). " [directoryintro]"
					),
				)
			),
			"dialplan" => array(
				"name" => _("Context Config"),
				"helptext" => _("These settings apply to context related operations"),
				"settings" => array(
					"callback" => array(
						"level" => array("general","account"),
						"type" => "text",
						"default" => "",
						"description" => _("Callback Context"),
						"helptext" => _("Context to call back from; if not listed, calling the sender back will not be permitted.") . " [callback]"
					),
					"cidinternalcontexts" => array(
						"level" => array("general"),
						"type" => "text",
						"default" => "",
						"description" => _("CID Internal Context"),
						"helptext" => _("Comma separated list of internal contexts to use caller ID.") . " [charset]"
					),
					"dialout" => array(
						"level" => array("general","account"),
						"type" => "text",
						"default" => "",
						"description" => _("Dialout Context"),
						"helptext" => _("Context to dial out from [option 4 from the advanced menu] if not listed, dialing out will not be permitted.") . " [dialout]"
					),
					"exitcontext" => array(
						"level" => array("general","account"),
						"type" => "text",
						"default" => "",
						"description" => _("Exit Context"),
						"helptext" => _('Context to check for handling * or 0 calls to operator. "Operator Context"') . " [exitcontext]"
					),
					"vmcontext" => array(
						"level" => array("account"),
						"type" => "text",
						"default" => "",
						"description" => _("Voicemail Context"),
						"helptext" => _('Voicemail Context') . " [vmcontext]"
					)
				)
			),
		);
		$finalt = array();
		foreach($settings as $key => $data) {
			$final1 = array();
			foreach($data['settings'] as $s => $d) {
				$final2 = array();
				if(!in_array($level, $d['level'])) {
					continue;
				}
				foreach($d as $item => $v) {
					$final2[$item] = $v;
				}
				$final1[$s] = $final2;
			}
			$data['settings'] = $final1;
			$finalt[$key] = $data;
		}
		$final = array();
		foreach($finalt as $key => $data) {
			if(!empty($data['settings'])) {
				$final[$key] = $data;
			}
		}
		return $final;
	}
}
