<?php
// vim: set ai ts=4 sw=4 ft=php:

class Voicemail implements BMO {
	private $vmFolders = array();
	private $vmPath = null;
	
	public function __construct($freepbx = null) {
		if ($freepbx == null)
			throw new Exception("Not given a FreePBX Object");

		$this->FreePBX = $freepbx;
		$this->db = $freepbx->Database;
		$this->vmPath = $this->FreePBX->Config->get_conf_setting('ASTSPOOLDIR') . "/voicemail";
		$folders = array("INBOX","Family","Friends","Old","Work","Urgent");
		foreach($folders as $folder) {
			$this->vmFolders[] = array(
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
				}
			}
		}
		return $out;
	}
}