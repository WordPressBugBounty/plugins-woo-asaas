<?php
/**
 * Updatable Webhook To Resource Request Adapter
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Adapter;

use WC_Asaas\Connectivity\Data\Updatable_Webhook;

/**
 * Adapts an Updatable_Webhook into an API resource request payload.
 */
class Updatable_Webhook_To_Resource_Request_Adapter {
	/**
	 * Updatable webhook with auth token.
	 *
	 * @var Updatable_Webhook
	 */
	private $webhook;

	/**
	 * Constructor.
	 *
	 * @param Updatable_Webhook $webhook The webhook data to adapt.
	 */
	public function __construct( Updatable_Webhook $webhook ) {
		$this->webhook = $webhook;
	}

	/**
	 * Adapt Updatable_Webhook to API resource request format.
	 *
	 * @return array
	 */
	public function adapt() {
		return [
			'enabled'     => $this->webhook->enabled(),
			'interrupted' => $this->webhook->interrupted(),
			'authToken'   => $this->webhook->auth_token(),
		];
	}
}
