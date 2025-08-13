<?php
namespace FreePBX\modules\Voicemail;
use FreePBX\modules\Backup as Base;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Finder\Finder;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$files = $this->getFiles();
		$voiceMail = $this->FreePBX->Voicemail;
		$nfiles = 0;
		$backupinfo = $this->getBackupInfo();
		// lets remove old files from the system
		$bkitems = json_decode($backupinfo['backup_items'] ?? '[]', true);
		$vmsetting =[];
		if (is_array($bkitems)) {
			foreach($bkitems as $i){
				if($i['modulename'] == 'voicemail'){
					$vmsetting = $i['settings'];
				}
			}
		}
		$voicemail_vmrecords ='no'; // default is no
		$voicemail_vmgreetings = 'no';
		if (is_array($vmsetting)) {
			foreach ($vmsetting as $set) {
				if (is_array($set) && isset($set['name'], $set['value'])) {
					if ($set['name'] === 'voicemail_vmrecords') {
						$voicemail_vmrecords = $set['value'];
					} elseif ($set['name'] === 'voicemail_vmgreetings') {
						$voicemail_vmgreetings = $set['value'];
					}
				}
			}
		}
		$vmboxes = $voiceMail->getBaseBackupSettings();
		if($voicemail_vmrecords =='no' || $voicemail_vmgreetings == 'no' ){
			foreach($vmboxes as $exten){
				$fileDirList = $voiceMail->allFileList($exten['extension']);
				foreach ($fileDirList['files'] as $file) {
					if($file['basename'] === 'greet.wav' || $file['basename'] === 'temp.wav' || $file['basename'] === 'busy.wav' || $file['basename'] === 'unavail.wav'){
						continue;
					}
				if($voicemail_vmrecords == 'no' && !is_link($file['path'].'/'.$file['basename']) ){
						unlink($file['path'].'/'.$file['basename']);
					}
				}
				if($voicemail_vmgreetings == 'no'){
					$greetings = $voiceMail->getGreetingsByExtension($exten['extension']);
					foreach($greetings as $greeting){
						$path = pathinfo($greeting,PATHINFO_DIRNAME);
						unlink($path.'/'.basename($greeting));
					}
				}
			}
		}

		foreach($files as $file){
			if($file->getType() == 'voicemail' || $file->getType() == 'greeting'){
				$filename = $file->getPathTo().'/'.$file->getFilename();
				$source = $this->tmpdir.'/files'.$file->getPathTo().'/'.$file->getFilename();
				$dest = $filename;
				if(file_exists($source)){
					if (!file_exists($file->getPathTo())) {
						mkdir($file->getPathTo(),0755,true);
					}
					copy($source, $dest);
					$nfiles++;
				}
			}
			if($file->getType() == 'conf') {
				$filename = $file->getPathTo().'/'.$file->getFilename();
				$source = $this->tmpdir.'/files'.$file->getPathTo().'/'.$file->getFilename();
				$dest = $filename;
				if(file_exists($source)){
					copy($source, $dest);
				}
			}
		}
		$this->log(sprintf(_("%s Files Restored"), $nfiles++),'INFO');
		if(isset($configs['tables'])) {
			$this->importTables($configs['tables']);
		}
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables) {
		$this->restoreLegacyAll($pdo);
		$finder = new Finder();
		$fileSystem = new Filesystem();
		$confdir = $this->FreePBX->Config->get_conf_setting('ASTETCDIR');
		if(file_exists($this->tmpdir.'/etc/asterisk/voicemail.conf')) {
			$fileSystem->copy($this->tmpdir.'/etc/asterisk/voicemail.conf', $confdir.'/voicemail.conf', true);
		}
		if(!file_exists($this->tmpdir.'/var/spool/asterisk/voicemail')) {
			return;
		}
		$vmdir = $this->FreePBX->Config->get_conf_setting('ASTSPOOLDIR') . "/voicemail";
		exec("rm -Rf ".$vmdir);
		foreach ($finder->in($this->tmpdir.'/var/spool/asterisk/voicemail') as $item) {
			if($item->isDir()) {
				$fileSystem->mkdir($vmdir.'/'.$item->getRelativePathname());
				continue;
			}
			$fileSystem->copy($item->getPathname(), $vmdir.'/'.$item->getRelativePathname(), true);
		}

	}

}
