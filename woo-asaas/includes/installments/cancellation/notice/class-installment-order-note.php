<?php
/**
 * Installment order note writer.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Notice;

use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;
use WC_Asaas\Webhook\Webhook;
use WC_Order;

/**
 * Writer for installment order notes.
 */
class Installment_Order_Note {

	/**
	 * Add a note recording that the installment plan was cancelled.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 */
	public function installment_cancelled( WC_Order $order ) {
		$order->add_order_note(
			Webhook::PREFIX_LOG . __( 'Installment payment plan cancelled. The ticket expired without payment and no installment had been paid, so all installments were removed.', 'woo-asaas' )
		);
	}

	/**
	 * Add a note recording that the cancellation was skipped on a paid order.
	 *
	 * @param WC_Order                   $order  The WooCommerce order.
	 * @param Ticket_Installment_Payment $ticket The ticket installment payment.
	 */
	public function ignored_paid_order( WC_Order $order, Ticket_Installment_Payment $ticket ) {
		$order->add_order_note(
			Webhook::PREFIX_LOG . sprintf(
				/* translators: %d: installment number. */
				__( 'Installment %d was not paid, but the order has paid installments, so its status was kept and the installment plan was not removed.', 'woo-asaas' ),
				$ticket->installment_number()
			)
		);
	}

	/**
	 * Add a note recording that the cancellation was skipped due to a settled charge.
	 *
	 * @param WC_Order $order The WooCommerce order.
	 */
	public function ignored_settled_charge( WC_Order $order ) {
		$order->add_order_note(
			Webhook::PREFIX_LOG . __( 'There is already a paid installment in this payment plan, so the order status was kept and no installment was removed.', 'woo-asaas' )
		);
	}
}
