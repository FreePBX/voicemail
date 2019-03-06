<?php
namespace FreePBX\modules\Voicemail;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
	public function runRestore($jobid){
		$configs = $this->getConfigs();
		$this->log("Voicemail does not work",'ERROR');
	}

	public function processLegacy($pdo, $data, $tables, $unknownTables) {
		$this->restoreLegacyAll($pdo);
	}

}