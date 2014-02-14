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
	function __construct($Modules) {
		$this->Modules = $Modules;
	}
	
	function getDisplay() {
		
		$ext = !empty($_REQUEST['sub']) ? $_REQUEST['sub'] : '';
		$reqFolder = !empty($_REQUEST['folder']) ? $_REQUEST['folder'] : 'INBOX';
		$view = !empty($_REQUEST['view']) ? $_REQUEST['view'] : 'folder';
		$folders = $this->UCP->FreePBX->Voicemail->getFolders();
		$messages = array();
		
		foreach($folders as $folder) {
			$messages[$folder['folder']] = $this->UCP->FreePBX->Voicemail->getMessagesByExtensionFolder($ext,$folder['folder']);
			$folders[$folder['folder']]['count'] = !empty($messages) && isset($messages[$folder['folder']]['messages']) ? count($messages[$folder['folder']]['messages']) : '0';
		}
		
		$displayvars = array();
		$displayvars['ext'] = $ext;
		$displayvars['folders'] = $folders;
		$displayvars['messages'] = isset($messages[$reqFolder]['messages']) ? $messages[$reqFolder]['messages'] : array();
		$displayvars['supportedMediaFormats'] = $this->UCP->FreePBX->Voicemail->supportedFormats;
		
		$html = $this->loadScript().$this->loadCSS();
		$html .= load_view(__DIR__.'/views/header.php',$displayvars);
		
		switch($view) {
			case "settings":
				$mainDisplay= load_view(__DIR__.'/views/settings.php',$displayvars);
				$displayvars['activeList'] = 'settings';
			break;
			case "folder":
				$mainDisplay = load_view(__DIR__.'/views/mailbox.php',$displayvars);
				$displayvars['activeList'] = $reqFolder;
			default:
			break;
		}
		
		
		$html .= load_view(__DIR__.'/views/nav.php',$displayvars);
		$html .= $mainDisplay;
		$html .= load_view(__DIR__.'/views/footer.php',$displayvars);
		return $html;
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
			case 'listen':
			case 'moveToFolder':
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
			case 'moveToFolder':
				$ext = '1000';
				$this->UCP->FreePBX->Voicemail->moveMessageByExtensionFolder($ext,$_POST['msg'],$_POST['folder']);
				$return = array("status" => true, "message" => "");
				break;
			default:
				return false;
			break;
		}
		return $return;
	}
	
	function ajaxCustomHandler() {
		switch($_REQUEST['command']) {
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
	
	public function doConfigPageInit($display) {
	}
	
	public function myShowPage() {
	}
	
	public function loadScript() {
		$contents = '';
		foreach (glob(__DIR__."/assets/js/*.js") as $filename) {
			$contents .= file_get_contents($filename);
		}
		return "<script>".$contents."</script>";
	}
	
	public function readRemoteFile($msgid,$ext,$format) {
		$message = $this->UCP->FreePBX->Voicemail->getMessageByMessageIDExtension($msgid,$ext);
		dbug("Using format: ".$format);
		if(!empty($message) && !empty($message['format'][$format])) {
			//$format = $message['format'][$format];
			$size   = $message['format'][$format]['length']; // File size
			$length = $size;           // Content length
			$start  = 0;               // Start byte
			$end    = $size - 1;       // End byte

			header('Content-Description: File Transfer');
			header("Content-Transfer-Encoding: binary");
			$ct = null;
			switch($format) {
				case "wav":
					$ct = "audio/x-wav, audio/wav";
				break;
				case "ogg":
					$ct = "audio/ogg";
				break;
			}
			header('Content-Type: '.$ct);
			header("Accept-Ranges: 0-".$size);
			if (isset($_SERVER['HTTP_RANGE'])) {
				$c_start = $start;
				$c_end   = $end;

				list(, $range) = explode('=', $_SERVER['HTTP_RANGE'], 2);
				if (strpos($range, ',') !== false) {
					header('HTTP/1.1 416 Requested Range Not Satisfiable');
					header("Content-Range: bytes $start-$end/$size");
					exit;
				}
				if ($range == '-') {
					$c_start = $size - substr($range, 1);
				}else{
					$range  = explode('-', $range);
					$c_start = $range[0];
					$c_end   = (isset($range[1]) && is_numeric($range[1])) ? $range[1] : $size;
				}
				$c_end = ($c_end > $end) ? $end : $c_end;
				if ($c_start > $c_end || $c_start > $size - 1 || $c_end >= $size) {
					header('HTTP/1.1 416 Requested Range Not Satisfiable');
					header("Content-Range: bytes $start-$end/$size");
					exit;
				}
				$start  = $c_start;
				$end    = $c_end;
				$length = $end - $start + 1;
				header('HTTP/1.1 206 Partial Content');
			} else {
				header("HTTP/1.1 200 OK");
			}

			header("Content-Range: bytes $start-$end/$size");
			header('Content-length: ' . $size);
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
			exit;
		} else {
			header("HTTP/1.0 404 Not Found");
		}
	}
	
	public function loadCSS() {
		$contents = '';
		foreach (glob(__DIR__."/assets/css/*.css") as $filename) {
			$contents .= file_get_contents($filename);
		}
		return "<style>".$contents."</style>";
	}
	
	public function getBadge() {
		$total = 0;
		foreach($this->Modules->getAssignedDevices() as $extension) {
			$mailbox = $this->UCP->FreePBX->astman->MailboxCount($extension);
			$total = $total + $mailbox['NewMessages'];
		}
		return !empty($total) ? $total : false;
	}
	
	public function getMenuItems() {
		$menu = array(
			"rawname" => "voicemail",
			"name" => "Vmail",
			"badge" => $this->getBadge()
		);
		foreach($this->Modules->getAssignedDevices() as $extension) {
			$mailbox = $this->UCP->FreePBX->astman->MailboxCount($extension);
			$menu["menu"][] = array(
				"rawname" => $extension,
				"name" => $extension,
				"badge" => $mailbox['NewMessages']
			);
		}
		return $menu; 
	}
}