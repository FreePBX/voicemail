<?php
namespace FreePBX\modules\Voicemail\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class Voicemail extends Base {
	protected $module = 'voicemail';
	public function setupRoutes($app) {

		/**
		 * @verb GET
		 * @returns - a mailbox resource
		 * @uri /voicemail/mailboxes/:id
		 */
		$freepbx = $this->freepbx;
		$app->get('/voicemail/mailboxes/{id}', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('voicemail');
			$response->getBody()->write(json_encode(voicemail_mailbox_get($args['id'])));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkAllReadScopeMiddleware());

		/**
		 * @verb PUT
		 * @uri /voicemail/password/:id
		 */
		$app->put('/voicemail/password/{id}', function ($request, $response, $args) use($freepbx) {
			\FreePBX::Modules()->loadFunctionsInc('voicemail');
			$params = $request->getParsedBody();
			if (!isset($params['password'])) {
				$response->getBody()->write(json_encode(false));
			}

			$uservm = voicemail_getVoicemail();
			$vmcontexts = array_keys($uservm);

			foreach ($vmcontexts as $vmcontext) {
				if(isset($uservm[$vmcontext][$params['id']])) {

					$uservm[$vmcontext][$params['id']]['pwd'] = $params['password'];

					voicemail_saveVoicemail($uservm);

					$freepbx->astman->send_request("Command", array("Command" => "voicemail reload"));
					$response->getBody()->write(json_encode(true));
				}
			}
			$response->getBody()->write(json_encode(false));
			return $response->withHeader('Content-Type', 'application/json');
		})->add($this->checkAllWriteScopeMiddleware());
	}
}
