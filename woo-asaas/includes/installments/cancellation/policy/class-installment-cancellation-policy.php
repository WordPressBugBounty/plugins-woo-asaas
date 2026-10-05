<?php
/**
 * Installment cancellation policy.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Policy;

use WC_Asaas\Installments\Cancellation\Data\Cancellation_Eligibility_Decision;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;
use WC_Asaas\Installments\Cancellation\Rule\Cancellation_Rule_Interface;
use WC_Order;

/**
 * Decides whether a ticket installment plan is eligible to be cancelled (or a webhook
 * processed). Runs the specification chain for a scenario and returns the first rejection,
 * or an eligible verdict when the whole chain passes.
 */
class Installment_Cancellation_Policy {

	const GUARD_OVERDUE  = 'guard_overdue';
	const GUARD_DELETED  = 'guard_deleted';
	const CANCEL_OVERDUE = 'cancel_overdue';
	const CANCEL_EXPIRED = 'cancel_expired';

	/**
	 * Scenario chains, keyed by scenario constant.
	 *
	 * @var array<string, Cancellation_Rule_Interface[]>
	 */
	private $chains;

	/**
	 * Constructor.
	 *
	 * @param Cancellation_Rule_Interface $terminal  Rejects on terminal order.
	 * @param Cancellation_Rule_Interface $paid      Rejects on paid order.
	 * @param Cancellation_Rule_Interface $not_first Rejects on non-first installment.
	 * @param Cancellation_Rule_Interface $settled   Rejects on settled charge for the first installment.
	 */
	public function __construct(
		Cancellation_Rule_Interface $terminal,
		Cancellation_Rule_Interface $paid,
		Cancellation_Rule_Interface $not_first,
		Cancellation_Rule_Interface $settled
	) {
		$this->chains = array(
			self::GUARD_DELETED  => array( $terminal, $paid ),
			self::GUARD_OVERDUE  => array( $paid, $settled ),
			self::CANCEL_OVERDUE => array( $paid, $not_first ),
			self::CANCEL_EXPIRED => array( $paid, $not_first, $settled ),
		);
	}

	/**
	 * Run the scenario's specification chain.
	 *
	 * @param string                     $scenario One of the scenario constants.
	 * @param Ticket_Installment_Payment $ticket   The ticket installment payment.
	 * @param WC_Order                   $order    The WooCommerce order.
	 * @return Cancellation_Eligibility_Decision
	 * @throws \Exception On transient API failure querying settled charges.
	 */
	public function check_eligibility( $scenario, Ticket_Installment_Payment $ticket, WC_Order $order ) {
		foreach ( $this->chains[ $scenario ] as $rule ) {
			$reason = $rule->rejection_reason( $ticket, $order );
			if ( null !== $reason ) {
				return new Cancellation_Eligibility_Decision( $reason );
			}
		}
		return new Cancellation_Eligibility_Decision( Cancellation_Eligibility_Decision::ELIGIBLE );
	}
}
