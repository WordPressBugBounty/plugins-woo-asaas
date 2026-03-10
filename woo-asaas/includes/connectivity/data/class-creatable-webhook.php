<?php
/**
 * Creatable Webhook
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Data;

/**
 * Represents the data required to create a new webhook.
 */
class Creatable_Webhook {
	/**
	 * Webhook name
	 *
	 * @var string
	 */
	private $name;

	/**
	 * Webhook URL
	 *
	 * @var string
	 */
	private $url;

	/**
	 * Webhook email
	 *
	 * @var string
	 */
	private $email;

	/**
	 * Webhook send type
	 *
	 * @var string
	 */
	private $send_type;

	/**
	 * Webhook auth token
	 *
	 * @var string
	 */
	private $auth_token;

	/**
	 * Webhook events
	 *
	 * @var array
	 */
	private $events;

	/**
	 * Constructor.
	 *
	 * @param string $name The webhook name.
	 * @param string $url The webhook URL.
	 * @param string $email The webhook email address.
	 * @param string $send_type The webhook send type.
	 * @param string $auth_token The webhook authentication token.
	 * @param array  $events The webhook events.
	 */
	public function __construct(
		string $name,
		string $url,
		string $email,
		string $send_type,
		string $auth_token,
		array $events
	) {
		$this->name       = $name;
		$this->url        = $url;
		$this->email      = $email;
		$this->send_type  = $send_type;
		$this->auth_token = $auth_token;
		$this->events     = $events;
	}

	/**
	 * Get webhook name.
	 *
	 * @return string
	 */
	public function name() {
		return $this->name;
	}

	/**
	 * Get webhook URL.
	 *
	 * @return string
	 */
	public function url() {
		return $this->url;
	}

	/**
	 * Get email address.
	 *
	 * @return string
	 */
	public function email() {
		return $this->email;
	}

	/**
	 * Get send type.
	 *
	 * @return string
	 */
	public function send_type() {
		return $this->send_type;
	}

	/**
	 * Get authentication token.
	 *
	 * @return string
	 */
	public function auth_token() {
		return $this->auth_token;
	}

	/**
	 * Get events.
	 *
	 * @return array
	 */
	public function events() {
		return $this->events;
	}
}
