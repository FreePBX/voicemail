<?php

namespace FreePBX\modules\Voicemail\Api\Gql;

use GraphQLRelay\Relay;
use GraphQL\Type\Definition\Type;
use FreePBX\modules\Api\Gql\Base;

class Voicemail extends Base {
	protected $module = 'voicemail';

	public function mutationCallback() {
		global $astman;
		if($this->checkAllWriteScope()) {dbug('checkign Scope');
			return function() {
				return [
					
					'createmailbox' => Relay::mutationWithClientMutationId([
						'name' => 'createmailbox',
						'description' => _('Create/enable a VM account'),
						'inputFields' => $this->getMutationFieldssettings(),
						'outputFields' => [
							'createstatus' => [
								'type' => Type::nonNull(Type::string()),
								'resolve' => function ($payload) {
									return $payload['createstatus'];
								}
							]
						],
						'mutateAndGetPayload' => function ($input) {
							$input['vm'] = isset($input['vm'])?$input['vm']:'enabled';
							$res = $this->freepbx->Voicemail->addMailbox($input['extension'],$input);
							if($res == true){
								return ['createstatus' =>'Vm_created'];
							} else{
								return ['createstatus' =>'Vm_created_failed'];
							}
						}
					]),
					'deletemailbox' => Relay::mutationWithClientMutationId([
						'name' => 'deletemailbox',
						'description' => _('Delete/disable a VM account'),
						'inputFields' => $this->getMutationFielddelete(),
						'outputFields' => [
							'deletestatus' => [
								'type' => Type::nonNull(Type::string()),
								'resolve' => function ($payload) {
									return $payload['deletestatus'];
								}
							]
						],
						'mutateAndGetPayload' => function ($input) {dbug($input);
							$res = $this->freepbx->Voicemail->delMailbox($input['extension']);
							if($res == true){
								return ['deletestatus' =>'VM_deleted'];
							} else{
								return ['deletestatus' =>'VM_delete_failed'];
							}
						}
					]),
				];
			};
		}
	}
	public function queryCallback() {
		if($this->checkAllReadScope()) {
			return [];
		}
	}

	private function getMutationFielddelete() {
		return [
			'extension' => [
				'type' => Type::nonNull(Type::int()),
				'description' => _('The voicemail extension')
			]
		];
	}
	private function getMutationFieldssettings() {
		return [
			'extension' => [
				'type' => Type::nonNull(Type::int()),
				'description' => _('The voicemail extension')
			],
			'vmpwd' => [
				'type' => Type::int(),
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
	}//
}