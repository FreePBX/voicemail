<?php
// vim: set ai ts=4 sw=4 ft=php:

class Voicemail implements BMO {
	private $vmFolders = array();
	private $vmPath = null;
	public $supportedFormats = array(
		"ogg",
		"wav"
	);
	
	public function __construct($freepbx = null) {
		if ($freepbx == null)
			throw new Exception("Not given a FreePBX Object");

		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->vmPath = $this->FreePBX->Config->get_conf_setting('ASTSPOOLDIR') . "/voicemail";
		$folders = array("INBOX","Family","Friends","Old","Work","Urgent");
		foreach($folders as $folder) {
			$this->vmFolders[$folder] = array(
				"folder" => $folder,
				"name" => _($folder)
			);
		}
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
	
	public function getFolders() {
		return $this->vmFolders;
	}
	
	public function getVoicemailBoxByExtension($extdisplay) {
		//TODO: this is weirdness right here.
		include_once(__DIR__.'/functions.inc.php');
		return voicemail_mailbox_get($extdisplay);
	}
	
	public function moveMessageByExtensionFolder($ext,$msg,$folder) {
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
							$fname = preg_replace('/([0-9]+)/','0000',basename($file));
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
							$fname = preg_replace('/([0-9]+)/',$next,basename($file));
							rename($file, $folder."/".$fname);
						}
					}
					return true;
				}
			}
		}
		return false;
	}
	
	public function getMessageByMessageIDExtension($msgid,$ext) {
		$messages = $this->getMessagesByExtension($ext);
		return !empty($messages['messages'][$msgid]) ? $messages['messages'][$msgid] : false;
	}
	
	public function readMessageBinaryByMessageIDExtension($msgid,$ext,$format,$start=0,$buffer=8192) {
		$message = $this->getMessageByMessageIDExtension($msgid,$ext);
		$fpath = $message['format'][$format]['path']."/".$message['format'][$format]['filename'];
		if(!empty($message) && !empty($message['format'][$format]) && file_exists($fpath)) {
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
	
	public function getMessagesByExtension($extension) {
		$o = $this->getVoicemailBoxByExtension($extension);
		$context = $o['vmcontext'];
		
		$out = array();
		$vmfolder = $this->vmPath . '/'.$context.'/'.$extension;
		if (is_dir($vmfolder) && is_readable($vmfolder)) {
			$count = 1;
			foreach (glob($vmfolder . '/*',GLOB_ONLYDIR) as $folder) {				
				foreach (glob($folder."/*.txt") as $filename) {
					$vm = pathinfo($filename,PATHINFO_FILENAME);
					$vfolder = dirname($filename);
					$txt = $vfolder."/".$vm.".txt";
					$wav = $vfolder."/".$vm.".wav";
					if(file_exists($txt) && is_readable($txt) && file_exists($wav) && is_readable($wav)) {
						$data = $this->FreePBX->LoadConfig->getConfig($vm.".txt", $vfolder, 'message');
						$key = $data['msg_id'];
						$out['messages'][$key] = $data;
						$out['messages'][$key]['file'] = basename($wav);
						$out['messages'][$key]['folder'] = basename($folder);
						$out['messages'][$key]['fid'] = $vm;
						$out['messages'][$key]['context'] = $context;
						$out['messages'][$key]['path'] = $folder;
						
						foreach($this->supportedFormats as $format) {
							$mf = $vfolder."/".$vm.".".$format;
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
		return $out;
	}
	
	public function getMessagesByExtensionFolder($extension,$folder) {
		$o = $this->getVoicemailBoxByExtension($extension);
		$context = $o['vmcontext'];
		
		$out = array();
		$vmfolder = $this->vmPath . '/'.$context.'/'.$extension . '/'. $folder;
		if (is_dir($vmfolder) && is_readable($vmfolder)) {
			$count = 1;
			foreach (glob($vmfolder."/*.txt") as $filename) {
				$vm = pathinfo($filename,PATHINFO_FILENAME);
				$txt = $vmfolder."/".$vm.".txt";
				$wav = $vmfolder."/".$vm.".wav";
				if(file_exists($txt) && is_readable($txt) && file_exists($wav) && is_readable($wav)) {
					$out['messages'][$vm] = $this->FreePBX->LoadConfig->getConfig($vm.".txt", $vmfolder, 'message');
					$out['messages'][$vm]['file'] = basename($wav);
					$out['total'] = $count++;
					$this->generateAdditionalMediaFormats($wav);
				}
			}
		}
		return $out;
	}
	
	private function generateAdditionalMediaFormats($file) {
		//TODO: This will probably be slow, need to figure out how to do this properly
		$path = dirname($file);
		$filename = pathinfo($file,PATHINFO_FILENAME);
		foreach($this->supportedFormats as $format) {
			switch($format) {
				case "ogg":
					exec("sox $file " . $path . "/" . $filename . ".ogg > /dev/null 2>&1 &");
				break;
			}
		}
	}
	
	private function folderCheck($folder) {
		return !preg_match('/[\.|\/]/',$folder) && $this->validFolder($folder);
	}
	
	private function validFolder($folder) {
		return in_array($folder,$this->vmFolders);
	}
}