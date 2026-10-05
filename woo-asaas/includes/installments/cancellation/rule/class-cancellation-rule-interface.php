<?php
/**
 * Cancellation specification contract.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Rule;

use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;
use WC_Order;

/**
 * A single explanatory specification in the cancellation chain.
 */
interface Cancellation_Rule_Interface {

	/**
	 * Evaluate the specification.
	 *
	 * @param Ticket_Installment_Payment $ticket The ticket installment payment.
	 * @param WC_Order                   $order  The WooCommerce order.
	 * @return string|null A Cancellation_Eligibility_Decision reason to reject, or null to pass.
	 */
	public function rejection_reason( Ticket_Installment_Payment $ticket, WC_Order $order );
}
