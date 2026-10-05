<?php
/**
 * Not-first-installment specification.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Rule;

use WC_Asaas\Installments\Cancellation\Data\Cancellation_Eligibility_Decision;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;
use WC_Order;

/**
 * Rejects when the payment is not the first installment (only the first governs the plan).
 */
class Not_First_Installment_Rule implements Cancellation_Rule_Interface {

	/**
	 * Evaluate the specification.
	 *
	 * @param Ticket_Installment_Payment $ticket The ticket installment payment.
	 * @param WC_Order                   $order  The WooCommerce order.
	 * @return string|null
	 */
	public function rejection_reason( Ticket_Installment_Payment $ticket, WC_Order $order ) {
		return $ticket->is_first_installment()
			? null
			: Cancellation_Eligibility_Decision::NOT_FIRST;
	}
}
