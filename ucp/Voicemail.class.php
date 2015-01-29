<?php
/**
* This is the User Control Panel Object.
*
* Copyright (C) 2013 Schmooze Com, INC
* Copyright (C) 2013 Andrew Nagy <andrew.nagy@schmoozecom.com>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see <http://www.gnu.org/licenses/>.
*
* @package   FreePBX UCP BMO
* @author   Andrew Nagy <andrew.nagy@schmoozecom.com>
* @license   AGPL v3
*/
namespace UCP\Modules;
use \UCP\Modules as Modules;

class Voicemail extends Modules{
	protected $module = 'Voicemail';
	private $limit = 15;
	private $break = 5;

	function __construct($Modules) {
		$this->Modules = $Modules;
		$this->astman = $this->UCP->FreePBX->astman;
		$this->Vmx = $this->UCP->FreePBX->Voicemail->Vmx;
		if($this->UCP->Session->isMobile) {
			$this->limit = 7;
		}
	}

	function getDisplay() {
		$ext = !empty($_REQUEST['sub']) ? $_REQUEST['sub'] : '';
		if(!empty($ext) && !$this->_checkExtension($ext)) {
			return _("Forbidden");
		}
		$reqFolder = !empty($_REQUEST['folder']) ? $_REQUEST['folder'] : 'INBOX';
		$view = !empty($_REQUEST['view']) ? $_REQUEST['view'] : 'folder';
		$page = !empty($_REQUEST['page']) ? $_REQUEST['page'] : 1;
		$folders = $this->UCP->FreePBX->Voicemail->getFolders();
		$messages = array();

		foreach($folders as $folder) {
			$folders[$folder['folder']]['count'] = $this->UCP->FreePBX->Voicemail->getMessagesCountByExtensionFolder($ext,$folder['folder']);
		}

		$displayvars = array();
		$displayvars['ext'] = $ext;
		$displayvars['folders'] = $folders;

		$html = "<script>var supportedMediaFormats = '".implode(",",array_keys($this->UCP->FreePBX->Voicemail->supportedFormats))."'; var extension = ".$ext."</script>";
		$html .= $this->load_view(__DIR__.'/views/header.php',$displayvars);

		if(!empty($this->UCP->FreePBX->Voicemail->displayMessage['message'])) {
			$displayvars['message'] = $this->UCP->FreePBX->Voicemail->displayMessage;
		}

		switch($view) {
			case "settings":
				$displayvars['settings'] = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
				$mainDisplay= $this->load_view(__DIR__.'/views/settings.php',$displayvars);
				$displayvars['activeList'] = 'settings';
			break;
			case "greetings":
				$displayvars['settings'] = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($ext);
				$displayvars['greetings'] = $this->UCP->FreePBX->Voicemail->getGreetingsByExtension($ext);
				$displayvars['short_greetings'] = $this->UCP->FreePBX->Voicemail->greetings;

				$mainDisplay= $this->load_view(__DIR__.'/views/greetings.php',$displayvars);
				$displayvars['activeList'] = 'greetings';
			break;
			case "folder":
				$start = (($page - 1) * $this->limit);
				$msgs = $this->UCP->FreePBX->Voicemail->getMessagesByExtensionFolder($ext,$reqFolder,$start,$this->limit);
				$displayvars['messages'] = !empty($msgs['messages']) ? $msgs['messages'] : array();
				$final = array();
				foreach($displayvars['messages'] as &$m) {
					$final[] = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($m['msg_id'], $ext, true);
				}
				$c = $folders[$reqFolder]['count'];
				$displayvars['messages'] = $final;
				$totalPages = (ceil($c/$this->limit) > 0) ? ceil($c/$this->limit) : 1;
				$displayvars['pagnation'] = $this->UCP->Template->generatePagnation($totalPages,$page,"?display=dashboard&mod=voicemail&sub=".$ext."&folder=".$reqFolder."&view=folder",$this->break);
				$mainDisplay = $this->load_view(__DIR__.'/views/mailbox.php',$displayvars);
				$displayvars['activeList'] = $reqFolder;
			default:
			break;
		}


		$html .= $this->load_view(__DIR__.'/views/nav.php',$displayvars);
		$html .= $mainDisplay;
		$html .= $this->load_view(__DIR__.'/views/footer.php',$displayvars);
		return $html;
	}

		function poll() {
				$total = 0;
				$boxes = array();
				foreach($this->Modules->getAssignedDevices() as $extension) {
						$mailbox = $this->UCP->FreePBX->astman->MailboxCount($extension);
						$total = $total + $mailbox['NewMessages'];
						$boxes[$extension] = $mailbox['NewMessages'];
				}
				return array("status" => true, "total" => $total, "boxes" => $boxes);
		}

	public function getSettingsDisplay($ext) {
		if($this->Vmx->isInitialized($ext) && $this->Vmx->isEnabled($ext)) {
			$displayvars = array(
				'settings' => $this->Vmx->getSettings($ext),
				'fmfm' => 'FM'.$ext
			);
			$out = array(
				array(
					"title" => _('VmX Locator'),
					"content" => $this->load_view(__DIR__.'/views/vmx.php',$displayvars),
					"size" => 6,
					"order" => 1
				)
			);
			return $out;
		} else {
			return array();
		}
	}

	/**
	* Determine what commands are allowed
	*
	* Used by Ajax Class to determine what commands are allowed by this class
	*
	* @param string $command The command something is trying to perform
	* @param string $settings The Settings being passed through $_POST or $_PUT
	* @return bool True if pass
	*/
	function ajaxRequest($command, $settings) {
		switch($command) {
			case 'download':
			case 'listen':
			case 'moveToFolder':
			case 'delete':
			case 'savesettings':
			case 'upload':
			case 'copy':
			case 'record':
				return $this->_checkExtension($_REQUEST['ext']);
			break;
			case 'vmxsettings':
				$ext = $_REQUEST['ext'];
				return $this->_checkExtension($ext) && $this->Vmx->isInitialized($ext) && $this->Vmx->isEnabled($ext);
			break;
			case 'checkboxes':
				return true;
			default:
				return false;
			break;
		}
	}

	/**
	* The Handler for all ajax events releated to this class
	*
	* Used by Ajax Class to process commands
	*
	* @return mixed Output if success, otherwise false will generate a 500 error serverside
	*/
	function ajaxHandler() {
		$return = array("status" => false, "message" => "");
		switch($_REQUEST['command']) {
			case 'vmxsettings':
				switch($_POST['settings']['key']) {
					case 'vmx-usewhen-unavailable':
						$m = ($_POST['settings']['value'] == 'true') ? 'enabled' : 'disabled';
						$this->Vmx->setState($_POST['ext'],'unavail',$m);
					break;
					case 'vmx-usewhen-busy':
						$m = ($_POST['settings']['value'] == 'true') ? 'enabled' : 'disabled';
						$this->Vmx->setState($_POST['ext'],'busy',$m);
					break;
					case 'vmx-usewhen-temp':
						$m = ($_POST['settings']['value'] == 'true') ? 'enabled' : 'disabled';
						$this->Vmx->setState($_POST['ext'],'temp',$m);
					break;
					case 'vmx-opt0':
						$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'0','unavail');
						$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'0','busy');
						$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'0','temp');
					break;
					case 'vmx-opt1':
						if(empty($_POST['settings']['value'])) {
							$this->Vmx->setFollowMe($_POST['ext'],'1','unavail');
							$this->Vmx->setFollowMe($_POST['ext'],'1','busy');
							$this->Vmx->setFollowMe($_POST['ext'],'1','temp');
						} else {
							$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'1','unavail');
							$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'1','busy');
							$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'1','temp');
						}
					break;
					case 'vmx-opt2':
						$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'2','unavail');
						$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'2','busy');
						$this->Vmx->setMenuOpt($_POST['ext'],$_POST['settings']['value'],'2','temp');
					break;
					default:
						dbug($_POST['settings']['key']);
						return false;
					break;
				}
				$return = array("status" => true, "message" => "Saved", "alert" => "success");
			break;
						case 'checkboxes':
								$total = 0;
								$boxes = array();
								foreach($this->Modules->getAssignedDevices() as $extension) {
										$mailbox = $this->UCP->FreePBX->astman->MailboxCount($extension);
										$total = $total + $mailbox['NewMessages'];
										$boxes[$extension] = $mailbox['NewMessages'];
								}
								$return = array("status" => true, "total" => $total, "boxes" => $boxes);
						break;
			case 'moveToFolder':
				$ext = $_POST['ext'];
				$status = $this->UCP->FreePBX->Voicemail->moveMessageByExtensionFolder($_POST['msg'],$ext,$_POST['folder']);
				$return = array("status" => $status, "message" => "");
			break;
			case 'delete':
				$ext = $_POST['ext'];
				$status = $this->UCP->FreePBX->Voicemail->deleteMessageByID($_POST['msg'],$ext);
				$return = array("status" => $status, "message" => "");
			break;
			case 'savesettings':
				$ext = $_POST['ext'];
				$saycid = ($_POST['saycid'] == 'true') ? true : false;
				$envelope = ($_POST['envelope'] == 'true') ? true : false;
				$status = $this->UCP->FreePBX->Voicemail->saveVMSettingsByExtension($ext,$_POST['pwd'],$_POST['email'],$_POST['pager'],$saycid,$envelope);
				$return = array("status" => $status, "message" => "");
			break;
			case "upload":
				foreach ($_FILES["files"]["error"] as $key => $error) {
					if ($error == UPLOAD_ERR_OK) {
						$tmp_path = sys_get_temp_dir();
						$tmp_path = !empty($tmp_path) ? $tmp_path : '/tmp';

						$extension = pathinfo($_FILES["files"]["name"][$key], PATHINFO_EXTENSION);
						if($extension == 'wav' || $extension == 'ogg') {
							$tmp_name = $_FILES["files"]["tmp_name"][$key];
							$name = $_FILES["files"]["name"][$key];
							if(!file_exists($tmp_path."/vmtmp")) {
								mkdir($tmp_path."/vmtmp");
							}
							move_uploaded_file($tmp_name, $tmp_path."/vmtmp/$name");
							if(!file_exists($tmp_path."/vmtmp/$name")) {
								$return = array("status" => false, "message" => "Voicemail not moved to ".$tmp_path."/vmtmp/$name");
								break;
							}
							$contents = file_get_contents($tmp_path."/vmtmp/$name");
							if(empty($contents)) {
								$return = array("status" => false, "message" => "Voicemail was empty ".$tmp_path."/vmtmp/$name");
								break;
							}
							unlink($tmp_path."/tmp/$name");
							$this->UCP->FreePBX->Voicemail->saveVMGreeting($_REQUEST['ext'],$_REQUEST['type'],$extension,$contents);
						} else {
							$return = array("status" => false, "message" => "unsupported file format");
							break;
						}
					}
				}
				$return = array("status" => true, "message" => "");
			break;
			case "copy":
				$status = $this->UCP->FreePBX->Voicemail->copyVMGreeting($_POST['ext'],$_POST['source'],$_POST['target']);
				$return = array("status" => $status, "message" => "");
			break;
			case "record":
				if ($_FILES["file"]["error"] == UPLOAD_ERR_OK) {
					$tmp_path = sys_get_temp_dir();
					$tmp_path = !empty($tmp_path) ? $tmp_path : '/tmp';

					$tmp_name = $_FILES["file"]["tmp_name"];
					$name = $_FILES["file"]["name"];
					if(!file_exists($tmp_path."/vmtmp")) {
						mkdir($tmp_path."/vmtmp");
					}
					move_uploaded_file($tmp_name, $tmp_path."/vmtmp/$name");
					if(!file_exists($tmp_path."/vmtmp/$name")) {
						$return = array("status" => false, "message" => "Voicemail not moved to ".$tmp_path."/vmtmp/$name");
						break;
					}
					$contents = file_get_contents($tmp_path."/vmtmp/$name");
					if(empty($contents)) {
						$return = array("status" => false, "message" => "Voicemail was empty ".$tmp_path."/vmtmp/$name");
						break;
					}
					unlink($tmp_path."/vmtmp/$name");
					$this->UCP->FreePBX->Voicemail->saveVMGreeting($_REQUEST['ext'],$_REQUEST['type'],'wav',$contents);
				}	else {
					$return = array("status" => false, "message" => "unknown error");
					break;
				}
				$return = array("status" => true, "message" => "");
			break;
			default:
				return false;
			break;
		}
		return $return;
	}

	/**
	* The Handler for quiet events
	*
	* Used by Ajax Class to process commands in which custom processing is needed
	*
	* @return mixed Output if success, otherwise false will generate a 500 error serverside
	*/
	function ajaxCustomHandler() {
		switch($_REQUEST['command']) {
			case "download":
				$msgid = $_REQUEST['msgid'];
				$format = $_REQUEST['format'];
				$ext = $_REQUEST['ext'];
				$this->readRemoteFile($msgid,$ext,$format,true);
				return true;
			break;
			case "listen":
				$msgid = $_REQUEST['msgid'];
				$format = $_REQUEST['format'];
				$ext = $_REQUEST['ext'];
				$this->readRemoteFile($msgid,$ext,$format);
				return true;
			break;
			default:
				return false;
			break;
		}
		return false;
	}

	public function getBadge() {
		$total = 0;
		foreach($this->Modules->getAssignedDevices() as $extension) {
			$mailbox = $this->UCP->FreePBX->astman->MailboxCount($extension);
			$total = $total + $mailbox['NewMessages'];
		}
		return !empty($total) ? $total : 0;
	}

	public function getMenuItems() {
		$user = $this->UCP->User->getUser();
		$extensions = $this->UCP->getSetting($user['username'],$this->module,'assigned');
		$menu = array();
		if(!empty($extensions)) {
			$menu = array(
				"rawname" => "voicemail",
				"name" => _("Voicemail"),
				"badge" => $this->getBadge()
			);
			foreach($extensions as $extension) {
				$data = $this->UCP->FreePBX->Core->getDevice($extension);
				if(empty($data) || empty($data['description'])) {
					$data = $this->UCP->FreePBX->Core->getUser($extension);
					$name = $data['name'];
				} else {
					$name = $data['description'];
				}
				$o = $this->UCP->FreePBX->Voicemail->getVoicemailBoxByExtension($extension);
				if(!empty($o)) {
					$mailbox = $this->UCP->FreePBX->astman->MailboxCount($extension);
					$menu["menu"][] = array(
						"rawname" => $extension,
						"name" => $extension . " - " . $name,
						"badge" => $mailbox['NewMessages']
					);
				}
			}
		}
		return !empty($menu["menu"]) ? $menu : array();
	}

	private function readRemoteFile($msgid,$ext,$format,$download=false) {
		if(!$this->_checkExtension($ext)) {
			header("HTTP/1.0 403 Forbidden");
			echo _("Forbidden");
			exit;
		}
		$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($msgid,$ext);
		if(!empty($message) && !empty($message['format'][$format]) && !empty($message['format'][$format]['length'])) {
			$msg = $message['format'][$format];
			$parts = pathinfo($msg['path']."/".$msg['filename']);
			$file = $msg['path'] . "/" . $parts['basename'];
			$format = $parts['extension'];

			if (is_file($file)){
				switch($format) {
					case "m4a":
						$ct = "audio/mp4";
					break;
					case "ulaw":
						$ct = "audio/basic";
						$format = "wav";
					break;
					case "alaw":
						$ct = "audio/x-alaw-basic";
						$format = "wav";
					break;
					case "sln":
						$ct = "audio/x-wav";
						$format = "wav";
					break;
					case "gsm":
						$ct = "audio/x-gsm";
						$format = "wav";
					break;
					case "g729":
						$ct = "audio/x-g729";
						$format = "wav";
					break;
					case "wav":
						$ct = "audio/wav";
					break;
					case "oga":
					case "ogg":
						$ct = "audio/ogg";
						$format = "oga";
					break;
				}
				header("Content-type: ".$ct); // change mimetype

				if (!$download && isset($_SERVER['HTTP_RANGE'])){ // do it for any device that supports byte-ranges not only iPhone
					$this->rangeDownload($msgid,$message,$ext,$format,$file);
				} else {
					header("Content-length: " . $message['format'][$format]['length']);
					header("Cache-Control: no-cache, must-revalidate"); // HTTP/1.1
					header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past
					header('Content-Disposition: attachment;filename="' . $message['format'][$format]['filename'].'"');
					$buffer = 1024 * 8;
					$wstart = 0;
					ob_end_clean();
					ob_start();
					while(true) {
						$content = $this->UCP->FreePBX->Voicemail->readMessageBinaryByMessageIDExtension($msgid,$ext,$format,$wstart,$buffer);
						if(!$content) {
							break;
						}
						echo $content;
						ob_flush();
						flush();
						$wstart = $wstart + $buffer;
						set_time_limit(0);
					}
				}
			}
		}
	}

	/**
	* Much of the below functionality was taken (including comments) from
	* http://stackoverflow.com/questions/3128906/mp4-plays-when-accessed-directly-but-not-when-read-through-php-on-ios
	* @param {[type]} $msgid  [description]
	* @param {[type]} $ext    [description]
	* @param {[type]} $format [description]
	* @param {[type]} $file   [description]
	*/
	private function rangeDownload($msgid,$message,$ext,$format,$file){
		$size   = $message['format'][$format]['length']; // File size
		$length = $size;           // Content length
		$start  = 0;               // Start byte
		$end    = $size - 1;       // End byte
		// Now that we've gotten so far without errors we send the accept range header
		/* At the moment we only support single ranges.
		* Multiple ranges requires some more work to ensure it works correctly
		* and comply with the specifications: http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
		*
		* Multirange support annouces itself with:
		* header('Accept-Ranges: bytes');
		*
		* Multirange content must be sent with multipart/byteranges mediatype,
		* (mediatype = mimetype)
		* as well as a boundry header to indicate the various chunks of data.
		*/
		header("Accept-Ranges: 0-$length");
		// header('Accept-Ranges: bytes');
		// multipart/byteranges
		// http://www.w3.org/Protocols/rfc2616/rfc2616-sec19.html#sec19.2
		if (isset($_SERVER['HTTP_RANGE'])){
			$c_start = $start;
			$c_end   = $end;

			// Extract the range string
			list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
			// Make sure the client hasn't sent us a multibyte range
			if (strpos($range, ',') !== false){
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				exit;
			}
			// If the range starts with an '-' we start from the beginning
			// If not, we forward the file pointer
			// And make sure to get the end byte if specified
			if ($range{0} == '-'){
				// The n-number of the last bytes is requested
				$c_start = $size - substr($range, 1);
			} else {
				$range  = explode('-', $range);
				$c_start = $range[0];
				$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
			}
			/* Check the range and make sure it's treated according to the specs.
			* http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html
			*/
			// End bytes can not be larger than $end.
			$c_end = ($c_end > $end) ? $end : $c_end;
			// Validate the requested range and return an error if it's not correct.
			if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size){
				header('HTTP/1.1 416 Requested Range Not Satisfiable');
				header("Content-Range: bytes $start-$end/$size");
				// (?) Echo some info to the client?
				exit;
			}

			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1; // Calculate new content length
			header('HTTP/1.1 206 Partial Content');
		}

		// Notify the client the byte range we'll be outputting
		header("Content-Range: bytes $start-$end/$size");
		header("Content-Length: $length");
		header('Content-Disposition: attachment;filename="' . $message['format'][$format]['filename'].'"');

		$buffer = 1024 * 8;
		$wstart = $start;
		ob_end_clean();
		ob_start();
		while(true) {
			$content = $this->UCP->FreePBX->Voicemail->readMessageBinaryByMessageIDExtension($msgid,$ext,$format,$wstart,$buffer);
			if(!$content) {
				break;
			}
			echo $content;
			ob_flush();
			flush();
			$wstart = $wstart + $buffer;
			set_time_limit(0);
		}
	}

	private function _checkExtension($extension) {
		$user = $this->UCP->User->getUser();
		$extensions = $this->UCP->getSetting($user['username'],$this->module,'assigned');
		return in_array($extension,$extensions);
	}
}
