<?php
/**
 * Creatable Webhook To Resource Request Adapter
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Adapter;

use WC_Asaas\Connectivity\Data\Creatable_Webhook;

/**
 * Adapts a Creatable_Webhook into an API resource request payload.
 */
class Creatable_Webhook_To_Resource_Request_Adapter {
	/**
	 * The webhook data.
	 *
	 * @var Creatable_Webhook
	 */
	private $webhook;

	/**
	 * Constructor.
	 *
	 * @param Creatable_Webhook $webhook The webhook data to adapt.
	 */
	public function __construct( Creatable_Webhook $webhook ) {
		$this->webhook = $webhook;
	}

	/**
	 * Adapt Creatable_Webhook to API resource request format.
	 *
	 * @return array
	 */
	public function adapt() {
		return array(
			'name'        => $this->webhook->name(),
			'url'         => $this->webhook->url(),
			'email'       => $this->webhook->email(),
			'sendType'    => $this->webhook->send_type(),
			'enabled'     => true,
			'interrupted' => false,
			'authToken'   => $this->webhook->auth_token(),
			'events'      => $this->webhook->events(),
		);
	}
}
