<?php
/**
 * Expired ticket cancellation hook.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Hook;

use Exception;
use stdClass;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment_Factory;
use WC_Asaas\Installments\Cancellation\Exception\Not_Installment_Payment_Exception;
use WC_Asaas\Installments\Cancellation\Notice\Installment_Order_Note;
use WC_Asaas\Installments\Cancellation\Policy\Installment_Cancellation_Policy;
use WC_Asaas\Installments\Cancellation\Service\Installment_Canceller;

/**
 * Hook for handling expired ticket cancellation in the cron job.
 */
class Expired_Ticket_Cancellation_Hook {

	/**
	 * The payment factory.
	 *
	 * @var Ticket_Installment_Payment_Factory
	 */
	private $payment_factory;

	/**
	 * The cancellation policy.
	 *
	 * @var Installment_Cancellation_Policy
	 */
	private $policy;

	/**
	 * The installment canceller.
	 *
	 * @var Installment_Canceller
	 */
	private $canceller;

	/**
	 * The order note writer.
	 *
	 * @var Installment_Order_Note
	 */
	private $note;

	/**
	 * Constructor.
	 *
	 * @param Ticket_Installment_Payment_Factory $payment_factory The payment factory.
	 * @param Installment_Cancellation_Policy    $policy          The cancellation policy.
	 * @param Installment_Canceller              $canceller       The installment canceller.
	 * @param Installment_Order_Note             $note            The order note writer.
	 */
	public function __construct(
		Ticket_Installment_Payment_Factory $payment_factory,
		Installment_Cancellation_Policy $policy,
		Installment_Canceller $canceller,
		Installment_Order_Note $note
	) {
		$this->payment_factory = $payment_factory;
		$this->policy          = $policy;
		$this->canceller       = $canceller;
		$this->note            = $note;
		add_filter( 'asaas_should_delete_expired_ticket', array( $this, 'maybe_cancel_installment' ), 10, 3 );
	}

	/**
	 * Maybe cancel an expired ticket installment plan.
	 *
	 * Hooked to the `asaas_should_delete_expired_ticket` filter. For a standalone
	 * ticket, returns the incoming value so the gateway deletes the individual
	 * payment as before. For an installment, the gateway must never delete the
	 * individual charge (`DELETE /v3/payments/{id}`): when eligible, cancels the
	 * whole plan in a single call; either way returns false so the gateway skips
	 * the per-payment delete.
	 *
	 * @param bool     $should_delete Whether the gateway should delete the payment.
	 * @param stdClass $payment       The Asaas payment object.
	 * @param int      $order_id      The WooCommerce order id matched to the payment.
	 * @return bool
	 * @throws Exception On transient API failure.
	 */
	public function maybe_cancel_installment( $should_delete, stdClass $payment, $order_id ) {
		try {
			$ticket = $this->payment_factory->from_payment( $payment );
		} catch ( Not_Installment_Payment_Exception $e ) {
			return $should_delete;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			throw new Exception(
				sprintf( 'Could not load order %d matched to installment payment %s.', $order_id, $payment->id )
			);
		}

		$decision = $this->policy->check_eligibility(
			Installment_Cancellation_Policy::CANCEL_EXPIRED,
			$ticket,
			$order
		);
		if ( $decision->is_eligible() ) {
			$this->canceller->cancel( $ticket );
			$this->note->installment_cancelled( $order );
		}

		return false;
	}
}
