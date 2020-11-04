<?php 

namespace FreepPBX\voicemail\utests;

require_once('../api/utests/ApiBaseTestCase.php');

use FreePBX\modules\voicemail;
use Exception;
use FreePBX\modules\Api\utests\ApiBaseTestCase;

class VoiceMailGqlApiTest extends ApiBaseTestCase {
    protected static $voicemail;
    
    public static function setUpBeforeClass() {
      parent::setUpBeforeClass();
      self::$voicemail = self::$freepbx->Voicemail;
    }
    
    public static function tearDownAfterClass() {
      parent::tearDownAfterClass();
    }
  
   public function testVoiceMailQueryWhenExtensionDoesNotExistsShouldReturnFalse(){
     $invlalidExtension = 173001111;
      $response = $this->request("query {
        VoiceMailDetails (extension : \"$invlalidExtension\" ){
          status message
        }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"errors":[{"message":"Sorry unable to fetch the status","status":"false"}]}',$json);
   }

   public function testVoiceMailQueryWhenExtensionExistsShouldReturnTrue(){
      $input['extension'] = $extension = 173005967678;
      $input['vm'] = 'enabled';
      $input['vmpwd'] = '123456';
      
      //delete existing 
      self::$voicemail->delMailbox($extension);
      self::$voicemail->delUser($extension);

      //create new vm for the extension
      self::$voicemail->addMailbox($extension,$input);
      
      $response = $this->request("query {
        VoiceMailDetails (extension : \"$extension\" ){
          status message
        }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"data":{"VoiceMailDetails":{"status":"true","message":"{\"vmcontext\":\"default\",\"pwd\":\"123456\",\"name\":\"\",\"email\":\"\",\"pager\":\"\",\"options\":[]}"}}}',$json);
   }

   public function testEnableMailWhenExtensionPassedWithoutPasswordShouldReturnFalse(){
      $extension = 173005967;
      $password = '123456';
      
      //delete existing 
      self::$voicemail->delMailbox($extension);
      self::$voicemail->delUser($extension);
      
      $response = $this->request("mutation {
         enableVoiceMailExtension(input: { extension: \"$extension\"}) {
            status message 
         }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"errors":[{"message":"Field enableVoiceExtensionInput.password of required type String! was not provided.","status":false}]}',$json);
   }

   public function testEnableMailWhenExtensionPassedWithPasswordShouldTrue(){
      $extension = 17300596722;
      $password = '123456';
      
      //delete existing 
      self::$voicemail->delMailbox($extension);
      self::$voicemail->delUser($extension);
      
      $response = $this->request("mutation {
         enableVoiceMailExtension(input: { extension: \"$extension\" password: \"$password\"}) {
            status message 
         }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"data":{"enableVoiceMailExtension":{"status":"true","message":"Voice mail has been created"}}}',$json);
   }

   public function testDiableMailWhenExtensionExistsShouldReturnTrue(){
      $input['extension'] = $extension = "1735967303";
      $input['vm'] = 'enabled';
      $input['vmpwd'] = '123456';
      
      // delete existing 
      self::$voicemail->delMailbox($extension);
      self::$voicemail->delUser($extension);
      
      self::$voicemail->addMailbox($extension,$input);

      $response = $this->request("mutation {
         disableVoiceMailExtension(input: { extension: \"$extension\"}) {
            status message 
         }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"data":{"disableVoiceMailExtension":{"status":"true","message":"Voice mail has been deleted"}}}',$json);
   }

   
   public function testDiableMailWhenExtensionDoesnotExistsShouldReturnFalse(){
      $extension = "173596730";
      
      self::$voicemail->delMailbox($extension);
      self::$voicemail->delUser($extension);
   
      $response = $this->request("mutation {
         disableVoiceMailExtension(input: { extension: \"$extension\"}) {
            status message 
         }
      }");
      
      $json = (string)$response->getBody();

      $this->assertEquals('{"errors":[{"message":"Sorry,fail to delete voice mail","status":"false"}]}',$json);
   }
}