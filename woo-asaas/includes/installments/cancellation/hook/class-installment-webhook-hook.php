<?php
/**
 * Installment webhook hook.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Hook;

use Exception;
use stdClass;
use WC_Asaas\Installments\Cancellation\Data\Cancellation_Eligibility_Decision;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment_Factory;
use WC_Asaas\Installments\Cancellation\Exception\Not_Installment_Payment_Exception;
use WC_Asaas\Installments\Cancellation\Notice\Installment_Order_Note;
use WC_Asaas\Installments\Cancellation\Policy\Installment_Cancellation_Policy;
use WC_Asaas\Installments\Cancellation\Service\Installment_Canceller;
use WC_Asaas\Webhook\Webhook;
use WC_Order;

/**
 * Handles the ticket installment webhooks that share the same collaborators.
 *
 * Guards which PAYMENT_OVERDUE and PAYMENT_DELETED webhooks are processed and
 * cancels the whole installment plan when the first installment goes overdue.
 */
class Installment_Webhook_Hook {

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
		add_filter(
			'woocommerce_asaas_should_process_webhook',
			array( $this, 'maybe_reject_installment_webhook' ),
			10,
			3
		);
		add_action( 'asaas_webhook_payment_overdue', array( $this, 'maybe_cancel_installment' ), 10, 2 );
	}

	/**
	 * Maybe reject an unsafe installment webhook.
	 *
	 * Hooked to the `woocommerce_asaas_should_process_webhook` filter. Guards
	 * PAYMENT_OVERDUE and PAYMENT_DELETED webhooks for ticket installment plans
	 * and rejects them in specific conditions: cascade redundancy, paid order, or
	 * settled charge in Asaas. Returns the incoming value unchanged for safe webhooks.
	 *
	 * @param bool           $process Whether the webhook should be processed.
	 * @param stdClass       $data    The webhook data.
	 * @param WC_Order|false $order   The WooCommerce order, or false.
	 * @return bool
	 * @throws Exception On transient API failure querying settled charges.
	 */
	public function maybe_reject_installment_webhook( $process, $data, $order ) {
		if ( ! $process || false === $order || ! $this->is_guarded_event( $data->event ) ) {
			return $process;
		}

		try {
			$ticket = $this->payment_factory->from_payment( $data->payment );
		} catch ( Not_Installment_Payment_Exception $e ) {
			return $process;
		}

		$scenario = Webhook::PAYMENT_DELETED === $data->event
			? Installment_Cancellation_Policy::GUARD_DELETED
			: Installment_Cancellation_Policy::GUARD_OVERDUE;

		$decision = $this->policy->check_eligibility( $scenario, $ticket, $order );
		if ( $decision->is_eligible() ) {
			return $process;
		}

		if ( Cancellation_Eligibility_Decision::PAID_ORDER === $decision->reason() ) {
			$this->note->ignored_paid_order( $order, $ticket );
		}
		if ( Cancellation_Eligibility_Decision::SETTLED_CHARGE === $decision->reason() ) {
			$this->note->ignored_settled_charge( $order );
		}

		return false;
	}

	/**
	 * Cancel the ticket installment plan when the first installment is overdue.
	 *
	 * Hooked to the `asaas_webhook_payment_overdue` action. A transient API
	 * failure throws, propagating through `do_action` so the endpoint returns
	 * HTTP 500 and Asaas retries the webhook.
	 *
	 * @param stdClass $payment The webhook payment object.
	 * @param WC_Order $order   The WooCommerce order.
	 * @throws Exception On transient API failure deleting the installment plan.
	 */
	public function maybe_cancel_installment( $payment, $order ) {
		try {
			$ticket = $this->payment_factory->from_payment( $payment );
		} catch ( Not_Installment_Payment_Exception $e ) {
			return;
		}

		$decision = $this->policy->check_eligibility(
			Installment_Cancellation_Policy::CANCEL_OVERDUE,
			$ticket,
			$order
		);
		if ( ! $decision->is_eligible() ) {
			return;
		}

		$this->canceller->cancel( $ticket );
		$this->note->installment_cancelled( $order );
	}

	/**
	 * Whether the event is one this guard inspects.
	 *
	 * @param string $event The webhook event name.
	 * @return bool
	 */
	private function is_guarded_event( $event ) {
		return Webhook::PAYMENT_OVERDUE === $event || Webhook::PAYMENT_DELETED === $event;
	}
}
