<?php
/**
 * File for class Webhook_Targets_Service
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook\Service;

use stdClass;
use WC_Asaas\Helper\Subscriptions_Helper;
use WC_Asaas\Webhook\Data\Missing_Target;
use WC_Asaas\Webhook\Data\Webhook_Targets;
use WC_Order;

/**
 * Resolves the order and subscriptions targeted by a webhook request.
 */
class Webhook_Targets_Service {

	/**
	 * Subscription lookup helper.
	 *
	 * @var Subscriptions_Helper
	 */
	private $subscriptions_helper;

	/**
	 * Initialize the service.
	 *
	 * @param Subscriptions_Helper $subscriptions_helper Subscription lookup helper.
	 */
	public function __construct( Subscriptions_Helper $subscriptions_helper ) {
		$this->subscriptions_helper = $subscriptions_helper;
	}

	/**
	 * Resolve the order and subscriptions targeted by a webhook request.
	 *
	 * @param stdClass      $data The request data.
	 * @param bool          $is_payment_confirmation Whether the event is a payment confirmation.
	 * @param stdClass|null $confirmed_payment The payment returned by Asaas, when confirmed.
	 * @return Webhook_Targets The resolved targets.
	 */
	public function resolve(
		stdClass $data,
		bool $is_payment_confirmation,
		?stdClass $confirmed_payment
	): Webhook_Targets {
		$has_request_subscription   = isset( $data->payment->subscription );
		$has_confirmed_subscription = $is_payment_confirmation
			&& null !== $confirmed_payment
			&& isset( $confirmed_payment->subscription );
		$payment_for_resolution     = null !== $confirmed_payment ? $confirmed_payment : $data->payment;

		$order = $this->resolve_order(
			$is_payment_confirmation,
			$confirmed_payment,
			$has_request_subscription,
			$has_confirmed_subscription,
			$payment_for_resolution
		);

		$subscription         = null;
		$request_subscription = null;
		if ( $has_confirmed_subscription ) {
			$subscription = $this->subscriptions_helper->get_subscription_by_id( $confirmed_payment->subscription );
		}
		if ( $has_request_subscription ) {
			$request_subscription = $this->subscriptions_helper->get_subscription_by_id( $data->payment->subscription );
		}
		if ( ! $is_payment_confirmation && $has_request_subscription ) {
			$subscription = $request_subscription;
		}

		return new Webhook_Targets(
			false === $order ? new Missing_Target() : $order,
			false === $subscription ? new Missing_Target() : $subscription,
			false === $request_subscription ? new Missing_Target() : $request_subscription
		);
	}

	/**
	 * Resolve the order that owns the payment.
	 *
	 * A payment confirmation without an Asaas canonical payment (API status validation
	 * skipped) must still be bound to the order that owns this payment id, so a replayed
	 * confirmation cannot target a different order through the request externalReference.
	 *
	 * @param bool          $is_payment_confirmation Whether the event is a payment confirmation.
	 * @param stdClass|null $confirmed_payment The payment returned by Asaas, when confirmed.
	 * @param bool          $has_request_subscription Whether the request names a subscription.
	 * @param bool          $has_confirmed_subscription Whether the canonical payment names a subscription.
	 * @param stdClass      $payment_for_resolution The payment used to resolve the order.
	 * @return WC_Order|false The resolved order, or false when no order exists.
	 */
	private function resolve_order(
		bool $is_payment_confirmation,
		?stdClass $confirmed_payment,
		bool $has_request_subscription,
		bool $has_confirmed_subscription,
		stdClass $payment_for_resolution
	) {
		$resolve_by_payment_id = $has_confirmed_subscription
			|| ( null === $confirmed_payment && ( $has_request_subscription || $is_payment_confirmation ) );

		$order = false;
		if ( $resolve_by_payment_id ) {
			$order = $this->subscriptions_helper->get_order_by_payment_id( $payment_for_resolution->id );
		}
		if ( false === $order && ( ! $is_payment_confirmation || null !== $confirmed_payment ) ) {
			$order = $this->resolve_order_by_external_reference( $payment_for_resolution, $has_confirmed_subscription );
		}

		return $order;
	}

	/**
	 * Resolve an order from the payment's external reference.
	 *
	 * A subscription payment may use its subscription id as the external reference. If that
	 * id happens to resolve to the subscription object itself, it must not be used as the order
	 * that is marked as paid; a renewal order is the valid target.
	 *
	 * @param stdClass $payment The payment whose reference is trusted for this lookup.
	 * @param bool     $is_subscription_payment Whether the payment belongs to a subscription.
	 * @return WC_Order|false The resolved order, or false when no order exists.
	 */
	private function resolve_order_by_external_reference(
		stdClass $payment,
		bool $is_subscription_payment
	) {
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Asaas API property, cannot be renamed.
		if ( ! isset( $payment->externalReference ) ) {
			return false;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Asaas API property, cannot be renamed.
		$order = wc_get_order( $payment->externalReference );
		if ( $is_subscription_payment && is_object( $order ) && is_a( $order, 'WC_Subscription' ) ) {
			return false;
		}

		return $order;
	}
}
