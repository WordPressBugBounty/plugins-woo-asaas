<?php
/**
 * Updatable Webhook
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Data;

/**
 * Represents the data used to update an existing webhook.
 */
class Updatable_Webhook extends Identifiable_Webhook {
	/**
	 * Webhook new auth token
	 *
	 * @var string
	 */
	private $auth_token;

	/**
	 * Constructor.
	 *
	 * @param string $id The webhook ID.
	 * @param bool   $enabled Whether the webhook is enabled.
	 * @param bool   $interrupted Whether the webhook is interrupted.
	 * @param string $email The webhook email address.
	 * @param string $auth_token The webhook authentication token.
	 */
	public function __construct( string $id, bool $enabled, bool $interrupted, string $email, string $auth_token ) {
		$this->auth_token = $auth_token;

		parent::__construct( $id, $enabled, $interrupted, $email );
	}

	/**
	 * Get authentication token.
	 *
	 * @return string
	 */
	public function auth_token() {
		return $this->auth_token;
	}
}
