<?php
namespace FreePBX\modules\Voicemail;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore(){
		$configs = $this->getConfigs();
		$files = $this->getFiles();
		$nfiles = 0;
		foreach($files as $file){
			if($file->getType() == 'voicemail'){
				$filename = $file->getPathTo().'/'.$file->getFilename();
				$source = $this->tmpdir.'/files'.$file->getPathTo().'/'.$file->getFilename();
				$dest = $filename;
				if(file_exists($source)){
					@mkdir($file->getPathTo(),0755,true);
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
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables) {
		$this->restoreLegacyAll($pdo);
	}

}
