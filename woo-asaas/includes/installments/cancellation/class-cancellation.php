<?php
/**
 * Installment cancellation module.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation;

use Exception;
use WC_Asaas\Api\Api;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment_Factory;
use WC_Asaas\Installments\Cancellation\Hook\Expired_Ticket_Cancellation_Hook;
use WC_Asaas\Installments\Cancellation\Hook\Installment_Webhook_Hook;
use WC_Asaas\Installments\Cancellation\Notice\Installment_Order_Note;
use WC_Asaas\Installments\Cancellation\Policy\Installment_Cancellation_Policy;
use WC_Asaas\Installments\Cancellation\Rule\Not_First_Installment_Rule;
use WC_Asaas\Installments\Cancellation\Rule\Order_Paid_Rule;
use WC_Asaas\Installments\Cancellation\Rule\Order_Terminal_Rule;
use WC_Asaas\Installments\Cancellation\Rule\Settled_Charge_Rule;
use WC_Asaas\Installments\Cancellation\Service\Installment_Canceller;
use WC_Asaas\Installments\Cancellation\Service\Settled_Charge_Checker;
use WC_Asaas\Installments\Cancellation\Service\WC_Order_Eligibility;
use WC_Asaas\Installments\Cancellation\Validator\Ticket_Installment_Payment_Validator;
use WC_Asaas\WC_Asaas;

/**
 * Wires the webhook hooks for ticket installment plans.
 *
 * Plays the role of a composition root: instantiates and wires the single-responsibility
 * components of the cancellation module, each of which registers its own hook.
 * Keeps the Webhook class free of installment logic.
 */
class Cancellation {

	/**
	 * Instance of this class
	 *
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * Instantiate the hook classes, which register their own hooks.
	 *
	 * Private to prevent creating multiple instances.
	 */
	private function __construct() {
		$gateway           = WC_Asaas::get_instance()->get_gateway_by_id( 'asaas-ticket' );
		$api               = new Api( $gateway );
		$validator         = new Ticket_Installment_Payment_Validator( $gateway->get_type() );
		$payment_factory   = new Ticket_Installment_Payment_Factory( $validator );
		$canceller         = new Installment_Canceller( $api );
		$settled_charge    = new Settled_Charge_Checker( $api );
		$order_eligibility = new WC_Order_Eligibility();

		$terminal_rule  = new Order_Terminal_Rule( $order_eligibility );
		$paid_rule      = new Order_Paid_Rule( $order_eligibility );
		$not_first_rule = new Not_First_Installment_Rule();
		$settled_rule   = new Settled_Charge_Rule( $settled_charge );

		$policy = new Installment_Cancellation_Policy( $terminal_rule, $paid_rule, $not_first_rule, $settled_rule );
		$note   = new Installment_Order_Note();

		( new Installment_Webhook_Hook( $payment_factory, $policy, $canceller, $note ) );
		( new Expired_Ticket_Cancellation_Hook( $payment_factory, $policy, $canceller, $note ) );
	}

	/**
	 * Prevent the instance from being cloned.
	 */
	private function __clone() {
	}

	/**
	 * Prevent from being unserialized.
	 *
	 * @throws Exception If create a second instance of it.
	 */
	public function __wakeup() {
		throw new Exception( esc_html__( 'Cannot unserialize singleton', 'woo-asaas' ) );
	}

	/**
	 * Return an instance of this class
	 *
	 * @return self A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
