<?php
/**
 * Identifiable Webhook
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Data;

/**
 * Represents the core identifiable properties of a registered webhook.
 */
class Identifiable_Webhook {
	/**
	 * Webhook ID
	 *
	 * @var string
	 */
	private $id;

	/**
	 * Webhook enabled status
	 *
	 * @var bool
	 */
	private $enabled;

	/**
	 * Webhook interrupted status
	 *
	 * @var bool
	 */
	private $interrupted;

	/**
	 * Webhook email
	 *
	 * @var string
	 */
	private $email;

	/**
	 * Constructor.
	 *
	 * @param string $id The webhook ID.
	 * @param bool   $enabled Whether the webhook is enabled.
	 * @param bool   $interrupted Whether the webhook is interrupted.
	 * @param string $email The webhook email address.
	 */
	public function __construct( string $id, bool $enabled, bool $interrupted, string $email ) {
		$this->id          = $id;
		$this->enabled     = $enabled;
		$this->interrupted = $interrupted;
		$this->email       = $email;
	}

	/**
	 * Get webhook ID.
	 *
	 * @return string
	 */
	public function id() {
		return $this->id;
	}

	/**
	 * Get enabled status.
	 *
	 * @return bool
	 */
	public function enabled() {
		return $this->enabled;
	}

	/**
	 * Get interrupted status.
	 *
	 * @return bool
	 */
	public function interrupted() {
		return $this->interrupted;
	}

	/**
	 * Get email address.
	 *
	 * @return string
	 */
	public function email() {
		return $this->email;
	}
}
