<?php
/**
 * Settled charge specification.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Rule;

use WC_Asaas\Installments\Cancellation\Data\Cancellation_Eligibility_Decision;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;
use WC_Asaas\Installments\Cancellation\Service\Settled_Charge_Checker;
use WC_Order;

/**
 * Rejects when the first installment's plan already has a settled charge in Asaas.
 */
class Settled_Charge_Rule implements Cancellation_Rule_Interface {

	/**
	 * Settled-charge checker.
	 *
	 * @var Settled_Charge_Checker
	 */
	private $settled_charge;

	/**
	 * Constructor.
	 *
	 * @param Settled_Charge_Checker $settled_charge Settled-charge checker.
	 */
	public function __construct( Settled_Charge_Checker $settled_charge ) {
		$this->settled_charge = $settled_charge;
	}

	/**
	 * Evaluate the specification.
	 *
	 * @param Ticket_Installment_Payment $ticket The ticket installment payment.
	 * @param WC_Order                   $order  The WooCommerce order.
	 * @return string|null
	 * @throws \Exception On transient API failure querying settled charges.
	 */
	public function rejection_reason( Ticket_Installment_Payment $ticket, WC_Order $order ) {
		if ( ! $ticket->is_first_installment() ) {
			return null;
		}
		return $this->settled_charge->has_settled_charge( $ticket )
			? Cancellation_Eligibility_Decision::SETTLED_CHARGE
			: null;
	}
}
