<?php
/**
 * File for class Webhook_Targets
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook\Data;

use WC_Asaas\Webhook\Data\Missing_Target;
use WC_Order;
use WC_Subscription;

/**
 * Order and subscriptions targeted by a webhook request.
 */
class Webhook_Targets {

	/**
	 * The order that will be updated.
	 *
	 * @var WC_Order|Missing_Target
	 */
	private $order;

	/**
	 * The subscription that will be updated.
	 *
	 * @var WC_Subscription|Missing_Target|null
	 */
	private $subscription;

	/**
	 * The subscription derived from the request, before the canonical payment is considered.
	 *
	 * @var WC_Subscription|Missing_Target|null
	 */
	private $request_subscription;

	/**
	 * Initialize the object.
	 *
	 * @param WC_Order|Missing_Target             $order The order that will be updated.
	 * @param WC_Subscription|Missing_Target|null $subscription The subscription that will be updated.
	 * @param WC_Subscription|Missing_Target|null $request_subscription The subscription derived from the request.
	 */
	public function __construct( $order, $subscription, $request_subscription ) {
		$this->order                = $order;
		$this->subscription         = $subscription;
		$this->request_subscription = $request_subscription;
	}

	/**
	 * The order that will be updated.
	 *
	 * @return WC_Order|Missing_Target The order, or a missing target when no order exists.
	 */
	public function order() {
		return $this->order;
	}

	/**
	 * The subscription that will be updated.
	 *
	 * @return WC_Subscription|Missing_Target|null The subscription, or a missing target when invalid.
	 */
	public function subscription() {
		return $this->subscription;
	}

	/**
	 * The subscription derived from the request.
	 *
	 * @return WC_Subscription|Missing_Target|null The request subscription, or a missing target when invalid.
	 */
	public function request_subscription() {
		return $this->request_subscription;
	}

	/**
	 * Whether the order the request named doesn't exist in WooCommerce.
	 *
	 * @return bool True when the request named an order that doesn't exist.
	 */
	public function order_missing(): bool {
		return $this->order instanceof Missing_Target;
	}

	/**
	 * Whether the subscription the request named doesn't exist in WooCommerce.
	 *
	 * @return bool True when the request named a subscription that doesn't exist.
	 */
	public function subscription_missing(): bool {
		return $this->subscription instanceof Missing_Target;
	}
}
