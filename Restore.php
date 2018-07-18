<?php
namespace FreePBX\modules\Voicemail;
use FreePBX\modules\Backup as Base;
class Restore Extends Base\RestoreBase{
    public function runRestore($jobid){
        $configs = reset($this->getConfigs());
        $files = $this->getFiles();
        $voicemail = $this->FreePBX->Voicemail;
        $voicemail->saveVoicemail($configs['voicemailConf']);
        $voicemail->bulkhandlerImport('extensions',$configs['mailboxData']);
        foreach ($files as $file) {
            $filename = $file['pathto'].'/'.$file['filename'];
            $filename = $this->nameTest($filename, $file['base']);
            if (!file_exists($filename)) {
                copy($this->tmpdir.'/files/'.$file['pathto'].'/'.$file['filename'], $filename);
            }
        }
        return $this;
    }
    public function nameTest($path, $var){
        $sysPath = $this->FreePBX->Config->get($var);
        if (!$sysPath) {
            return $path;
        }
        $file = basename($path);
        $pathArr = explode($path, '/');
        $i = array_search('voicemail', $pathArr);
        $pathArr = array_slice($pathArr, $i);
        return $sysPath . '/' . implode('/', $pathArr) . '/' . $file;
    }
}