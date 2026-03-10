<?php
/**
 * Registered Webhook
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Data;

/**
 * Represents a webhook as registered and returned by the Asaas API.
 */
class Registered_Webhook extends Identifiable_Webhook {
	/**
	 * Webhook penalized request count
	 *
	 * @var int
	 */
	private $penalized_requests_count;

	/**
	 * Constructor.
	 *
	 * @param string $id The webhook ID.
	 * @param bool   $enabled Whether the webhook is enabled.
	 * @param bool   $interrupted Whether the webhook is interrupted.
	 * @param int    $penalized_requests_count The number of penalized requests.
	 * @param string $email The webhook email address.
	 */
	public function __construct(
		string $id,
		bool $enabled,
		bool $interrupted,
		int $penalized_requests_count,
		string $email
	) {
		parent::__construct( $id, $enabled, $interrupted, $email );
		$this->penalized_requests_count = $penalized_requests_count;
	}

	/**
	 * Get penalized requests count.
	 *
	 * @return int
	 */
	public function penalized_requests_count() {
		return $this->penalized_requests_count;
	}
}
