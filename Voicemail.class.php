<?php
// vim: set ai ts=4 sw=4 ft=php:
namespace FreePBX\modules;
class Voicemail implements \BMO {
	//message to display to client
	public $displayMessage = array(
		"type" => "warning",
		"message" => ""
	);

	//supported playback formats
	public $supportedFormats = array(
		"oga" => "ogg",
		"wav" => "wav"
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

	public function __construct($freepbx = null) {
		if ($freepbx == null) {
			throw new \Exception("Not given a FreePBX Object");
		}

		if(!class_exists('FreePBX\modules\Voicemail\Vmx') && file_exists(__DIR__.'/Vmx.class.php')) {
			include(__DIR__.'/Vmx.class.php');
			$this->Vmx = new Voicemail\Vmx($freepbx);
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

	}
	public function uninstall() {

	}
	public function backup(){

	}
	public function restore($backup){

	}
	public function genConfig() {

	}

	public function processUCPAdminDisplay($user) {
		if(!empty($_POST['ucp|voicemail'])) {
			$this->FreePBX->Ucp->setSetting($user['username'],'Voicemail','assigned',$_POST['ucp|voicemail']);
		} else {
			$this->FreePBX->Ucp->setSetting($user['username'],'Voicemail','assigned',array());
		}
	}

	public function getUsersList() {
		return $this->FreePBX->Core->listUsers(true);
	}

	/**
	 * get the Admin display in UCP
	 * @param array $user The user array
	 */
	public function getUCPAdminDisplay($user) {
		$fpbxusers = array();
		$cul = array();
		foreach(core_users_list() as $list) {
			$cul[$list[0]] = array(
				"name" => $list[1],
				"vmcontext" => $list[2]
			);
		}
		$vmassigned = $this->FreePBX->Ucp->getSetting($user['username'],'Voicemail','assigned');
		$vmassigned = !empty($vmassigned) ? $vmassigned : array();
		foreach($user['assigned'] as $assigned) {
			$fpbxusers[] = array("ext" => $assigned, "data" => $cul[$assigned], "selected" => in_array($assigned,$vmassigned));
		}
		$html['description'] = '<a href="#" class="info">'._("Allowed Voicemail").':<span>'._("These are the assigned and active extensions which will show up for this user to control and edit in UCP").'</span></a>';
		$html['content'] = load_view(dirname(__FILE__)."/views/ucp_config.php",array("fpbxusers" => $fpbxusers));
		return $html;
	}

	/**
	 * Get all known folders
	 */
	public function getFolders() {
		return $this->vmFolders;
	}

	/**
	 * Query the audio file and make sure it's actually audio
	 * @param string $file The full file path to check
	 */
	public function queryAudio($file) {
		if(!file_exists($file) || !is_readable($file)) {
			return false;
		}
		if(in_array($file,$this->validFiles)) {
			return true;
		}
		$last = exec('sox '.$file.' -n stat 2>&1',$output,$ret);
		if($ret > 0 || preg_match('/not sound/',$last)) {
			return false;
		}
		$data = array();
		foreach($output as $o) {
			$parts = explode(":",$o);
			$key = preg_replace("/\W/","",$parts[0]);
			$data[$key] = trim($parts[1]);
		}
		$this->validFiles[] = $file;
		return $data;
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
		$vmfolder = $this->vmPath . '/'.$context.'/'.$ext;
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
			copy($file, $vmfolder."/".$target.".".$extension);
			$this->generateAdditionalMediaFormats($vmfolder."/".$target.".".$extension,false);
		}
		return true;
	}

	/**
	 * Save Voicemail Greeting
	 * @param int $ext      The voicemail extension
	 * @param string $type     The voicemail type
	 * @param string $format   The file format
	 * @param string $contents The binary file data
	 */
	public function saveVMGreeting($ext,$type,$format,$contents) {
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$vmfolder = $this->vmPath . '/'.$context.'/'.$ext;
		if(!file_exists($vmfolder)) {
			mkdir($vmfolder);
		}
		if(isset($this->greetings[$type])) {
			$file = $this->checkFileType($vmfolder, $type);
			$tempf = $vmfolder . "/" . $type . "_tmp.".$format;
			if(file_exists($file)) {
				if(!unlink($file)) {
					return false;
				}
			}
			file_put_contents($tempf,$contents);
			$file = $vmfolder . "/" . $type . ".".$format;
			//convert the file here using sox I guess
			exec("sox " . $tempf . " -r 8000 -c1 " . $file . " > /dev/null 2>&1");
			unlink($tempf);
			$this->generateAdditionalMediaFormats($file,false);
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
			//TODO: Lazy...
			if(!function_exists('voicemail_mailbox_get')) {
				include_once(__DIR__.'/functions.inc.php');
			}
			$this->vmBoxData[$ext] = voicemail_mailbox_get($ext);
		}
		return !empty($this->vmBoxData[$ext]) ? $this->vmBoxData[$ext] : false;
	}

	/**
	 * Get all greetings by extension
	 * @param int $ext   The extension number
	 * @param bool $cache Whether to regenerate html5 assets
	 */
	public function getGreetingsByExtension($ext,$cache = false) {
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
				if(!$cache) {
					$this->generateAdditionalMediaFormats($file);
				}
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
	 * @param string $playcid  Whether to play the CID to the caller
	 * @param string $envelope Whether to play the envelope to the caller
	 */
	public function saveVMSettingsByExtension($ext,$pwd,$email,$page,$playcid,$envelope) {
		$o = $this->getVoicemailBoxByExtension($ext);
		$context = $o['vmcontext'];
		$vmconf = voicemail_getVoicemail();
		if(!empty($vmconf[$context][$ext])) {
			$vmconf[$context][$ext]['pwd'] = $pwd;
			$vmconf[$context][$ext]['email'] = $email;
			$vmconf[$context][$ext]['pager'] = $page;
			$vmconf[$context][$ext]['options']['saycid'] = ($playcid) ? 'yes' : 'no';
			$vmconf[$context][$ext]['options']['envelope'] = ($envelope) ? 'yes' : 'no';
			voicemail_saveVoicemail($vmconf);
			$this->astman->Reload("voicemail");
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
						if($toVM['attach'] == "yes") {
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
					if($toVM['delete'] == "yes") {
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
			$sha1 = sha1_file($data['path'] . "/" . $data['file']);
			foreach($this->supportedFormats as $format => $extension) {
				switch($extension) {
					case "wav":
						$mf = $data['path']."/".$greeting.".".$extension;
					break;
					default:
						$mf = $data['path']."/".$greeting."_".$sha1.".".$extension;
					break;
				}
				if(file_exists($mf)) {
					$data['format'][$format] = array(
						"filename" => basename($mf),
						"path" => $data['path'],
						"length" => filesize($mf)
					);
				}
			}
		}
		return $data;
	}

	/**
	 * Get a message by ID and Extension
	 * @param string $msgid         The message ID
	 * @param int $ext           The voicemail extension
	 * @param bool $generateMedia Whether to generate HTML5 assets
	 */
	public function getMessageByMessageIDExtension($msgid,$ext,$generateMedia = false) {
		if(isset($this->greetings[$msgid])) {
			$out = $this->getGreetingByExtension($msgid,$ext);
			return !empty($out) ? $out : false;
		} else {
			$messages = $this->getMessagesByExtension($ext);
			if(!empty($messages['messages'][$msgid])) {
				$msg = $messages['messages'][$msgid];
				if($generateMedia) {
					$this->generateAdditionalMediaFormats($msg['path']."/".$msg['file'],false);
				}
				return $messages['messages'][$msgid];
			} else {
				return false;
			}
		}
	}

	/**
	 * Read Message Binary Data by message ID
	 * Used during playback to intercommunicate with UCP
	 * @param string  $msgid  The message ID
	 * @param int  $ext    The voicemail extension
	 * @param string  $format The format of the file to use
	 * @param int $start  The starting byte position
	 * @param int $buffer The buffer size to pass
	 */
	public function readMessageBinaryByMessageIDExtension($msgid,$ext,$format,$start=0,$buffer=8192) {
		$message = $this->getMessageByMessageIDExtension($msgid,$ext);
		$fpath = $message['format'][$format]['path']."/".$message['format'][$format]['filename'];
		if(!empty($message) && !empty($message['format'][$format]) && $this->queryAudio($fpath)) {
			$end = $message['format'][$format]['length'] - 1;
			$fp = fopen($fpath, "rb");
			fseek($fp, $start);
			if(!feof($fp) && ($p = ftell($fp)) <= $end) {
				if ($p + $buffer > $end) {
					$buffer = $end - $p + 1;
				}
				$contents = fread($fp, $buffer);
				fclose($fp);
				return $contents;
			}
			fclose($fp);
		}
		return false;
	}

	/**
	 * Get all messages for an extension
	 * @param int $extension The voicemail extension
	 */
	public function getMessagesByExtension($extension) {
		if(!empty($this->messageCache)) {
			return $this->messageCache;
		}
		$o = $this->getVoicemailBoxByExtension($extension);
		$context = $o['vmcontext'];

		$out = array();
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
							$key = rand(0,5000);
						}
						$out['messages'][$key] = $data;
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

						$sha = sha1_file($wav);
						foreach($this->supportedFormats as $format => $extension) {
							$mf = $vfolder."/".$vm."_".$sha.".".$extension;
							if(file_exists($mf)) {
								$out['messages'][$key]['format'][$format] = array(
									"filename" => basename($mf),
									"path" => $folder,
									"length" => filesize($mf)
								);
							}
						}
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
	public function getMessagesByExtensionFolder($extension,$folder,$start,$limit) {
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

		usort($aMsgs['messages'], function($a, $b) {
			return $b['origtime'] - $a['origtime'];
		});
		$aMsgs['messages'] = array_values($aMsgs['messages']);

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
						if(preg_match("/(.*)\_(.*)\./i",$basename,$matches)) {
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
							if(preg_match("/(.*)\_(.*)\./i",$basename,$matches)) {
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
	 * Generate Media Formats for use in HTML5 playback
	 * @param string $file       The filename
	 * @param bool $background Whether to background this process or stall PHP
	 */
	private function generateAdditionalMediaFormats($file,$background = true) {
		$b = ($background) ? '&' : ''; //this is so very important
		$path = dirname($file);
		$filename = pathinfo($file,PATHINFO_FILENAME);
		if(!$this->queryAudio($file)) {
			return false;
		}
		$sha1 = sha1_file($file);
		foreach($this->supportedFormats as $format) {
			switch($format) {
				case "ogg":
					if(!file_exists($path . "/" . $filename . "_".$sha1.".ogg")) {
						exec("sox $file " . $path . "/" . $filename . "_".$sha1.".ogg > /dev/null 2>&1 ".$b);
					}
				break;
			}
		}
		return true;
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
}
