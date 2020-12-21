<?php

namespace FreePBX\modules\Voicemail\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

class Voicemail extends Base {
	protected $module = 'voicemail';

	public function mutationCallback() {
		global $astman;
		if($this->checkAllWriteScope()) {
			return function() {
				return [			
					'enableVoiceMailExtension' => Relay::mutationWithClientMutationId([
						'name' => 'enableVoiceExtension',
						'description' => _('Create/enable a voice mail account'),
						'inputFields' => $this->getMutationFieldssettings(),
						'outputFields' =>$this->getOutputFields(),
						'mutateAndGetPayload' => function($input){
							return $this->enableVoiceMailExtension($input);
						} 
					]),
					'disableVoiceMailExtension' => Relay::mutationWithClientMutationId([
						'name' => 'disableVoiceExtension',
						'description' => _('Delete/disable a voice mail account'),
						'inputFields' => $this->getMutationFieldDisable(),
						'outputFields' => $this->getOutputFields(),
						'mutateAndGetPayload' =>  function($input){
							 return $this->disableVoiceMailExtension($input);
						}
					]),
				];
			};
		}
	}

	public function queryCallback() {
	 if($this->checkReadScope('voicemail')) {
		return function() {
		return [
			'VoiceMailDetails' => [
				'type' => $this->typeContainer->get('voiceMail')->getObject(),
				'description' => 'Return extension details status',
				'args' => [
					'extension' => [
						'type' => Type::nonNull(Type::id()),
						'description' => 'The ID',
					]
				],
				'resolve' => function($root, $args) {
					try{
						$res = $this->freepbx->voiceMail->getVoicemailBoxByExtension($args['extension']);
						if(isset($res) && $res != null){
							return  ['message' => json_encode($res), 'status' => true] ;
							return;
						}else{
							return ['message' => 'Sorry unable to fetch the status', 'status' => false] ;
						}
					}catch(Exception $ex){
						FormattedError::setInternalErrorMessage($ex->getMessage());
					}		
				}]
			];
		};
	}
}

	private function getMutationFieldDisable() {
		return [
			'extension' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The voicemail extension')
			]
		];
	}
	private function getMutationFieldssettings() {
		return [
			'extension' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The voicemail extension')
			],
			'password' => [
				'type' => Type::nonNull(Type::string()),
				'description' => _('The voicemail password/pin')
			],
			'email' => [
				'type' => Type::string(),
				'description' => _('The voicemail email address')
			],
			'pager' => [
				'type' => Type::string(),
				'description' => _('The voicemail pager number')
			],
			'saycid' => [
				'type' => Type::string(),
				'description' => _('Whether to play the CID to the caller ')
			],
			'envelope' => [
				'type' => Type::string(),
				'description' => _('Whether to play the envelope to the caller')
			],
			'attach' => [
				'type' => Type::string(),
				'description' => _('Whether to attach the voicemail to the outgoing email')
			],
			'delete' => [
				'type' => Type::string(),
				'description' => _('Whether to delete the voicemail from local storage')
			],
		];
	}

	public function disableVoiceMailExtension($input) {
		$res = $this->freepbx->Voicemail->delMailbox($input['extension']);
		if($res == true){
			return ['message' =>'Voice mail has been deleted','status' => true];
		} else{
			return ['message' =>'Sorry,fail to delete voice mail','status' => false];
		}
	}

	public function enableVoiceMailExtension($input){
		$input['vm'] = isset($input['vm'])?$input['vm']:'enabled';
		$input['vmpwd'] = $input['password'];
		$res = $this->freepbx->Voicemail->addMailbox($input['extension'],$input);
			if($res == true){
				return ['message' =>'Voice mail has been created','status' => true];
			} else{
				return ['message' =>'Sorry,fail to create voice mail','status' => false];
		}
	}

	public function getOutputFields(){
		return [
			'status' => [
			'type' => Type::nonNull(Type::string()),
			'resolve' => function ($payload) {
				dbug($payload);
					return $payload['status'];
				}
			],
			'message' => [
			'type' => Type::nonNull(Type::string()),
			'resolve' => function ($payload) {
				return $payload['message'];
			}
			]
		];
	}

	public function initializeTypes() {
		$voiceMail = $this->typeContainer->create('voiceMail');
		$voiceMail->setDescription(_('Read the Voice mail information'));

		$voiceMail->addInterfaceCallback(function() {
			return [$this->getNodeDefinition()['nodeInterface']];
		});

		$voiceMail->addFieldCallback(function() {
			return [
				'status' =>[
					'type' => Type::String(),
					'description' => _('Status of the request')
				],
				'message' =>[
					'type' => Type::String(),
					'description' => _('Message for the request')
				]
			];
		});
	}
}