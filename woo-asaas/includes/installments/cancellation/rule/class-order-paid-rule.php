<?php
/**
 * Order paid specification.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Rule;

use WC_Asaas\Installments\Cancellation\Data\Cancellation_Eligibility_Decision;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;
use WC_Asaas\Installments\Cancellation\Service\WC_Order_Eligibility;
use WC_Order;

/**
 * Rejects when the order is already in a paid status.
 */
class Order_Paid_Rule implements Cancellation_Rule_Interface {

	/**
	 * Order-side gateway.
	 *
	 * @var WC_Order_Eligibility
	 */
	private $order_eligibility;

	/**
	 * Constructor.
	 *
	 * @param WC_Order_Eligibility $order_eligibility Order-side gateway.
	 */
	public function __construct( WC_Order_Eligibility $order_eligibility ) {
		$this->order_eligibility = $order_eligibility;
	}

	/**
	 * Evaluate the specification.
	 *
	 * @param Ticket_Installment_Payment $ticket The ticket installment payment.
	 * @param WC_Order                   $order  The WooCommerce order.
	 * @return string|null
	 */
	public function rejection_reason( Ticket_Installment_Payment $ticket, WC_Order $order ) {
		return $this->order_eligibility->is_paid( $order )
			? Cancellation_Eligibility_Decision::PAID_ORDER
			: null;
	}
}
