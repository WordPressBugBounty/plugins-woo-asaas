<?php
/**
 * WooCommerce order eligibility gateway.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Service;

use WC_Order;

/**
 * Anti-corruption gateway exposing domain predicates over a WC_Order.
 */
class WC_Order_Eligibility {

	/**
	 * Whether the order is already in a paid status.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_paid( WC_Order $order ) {
		return $order->has_status( wc_get_is_paid_statuses() );
	}

	/**
	 * Whether the order is in a terminal status (failed or cancelled).
	 *
	 * @param WC_Order $order The WooCommerce order.
	 * @return bool
	 */
	public function is_terminal( WC_Order $order ) {
		return $order->has_status( array( 'failed', 'cancelled' ) );
	}
}
