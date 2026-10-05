<?php
/**
 * Ticket installment payment validator.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Validator;

use WC_Asaas\Billing_Type\Billing_Type;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;
use WC_Asaas\Installments\Cancellation\Exception\Not_Installment_Payment_Exception;

/**
 * Validator for ticket installment payments.
 */
class Ticket_Installment_Payment_Validator {

	/**
	 * The ticket billing type.
	 *
	 * @var Billing_Type
	 */
	private $billing_type;

	/**
	 * Constructor.
	 *
	 * @param Billing_Type $billing_type The ticket billing type.
	 */
	public function __construct( Billing_Type $billing_type ) {
		$this->billing_type = $billing_type;
	}

	/**
	 * Validate the payment.
	 *
	 * @param Ticket_Installment_Payment $payment The payment to validate.
	 * @throws Not_Installment_Payment_Exception If the payment is not a valid ticket installment.
	 */
	public function validate( Ticket_Installment_Payment $payment ) {
		if ( '' === $payment->installment_id() ) {
			throw new Not_Installment_Payment_Exception( esc_html__( 'Payment has no installment ID; not a ticket installment.', 'woo-asaas' ) );
		}
		if ( $this->billing_type->get_id() !== $payment->billing_type() ) {
			throw new Not_Installment_Payment_Exception( esc_html__( 'Payment billing type does not match the ticket billing type.', 'woo-asaas' ) );
		}
		if ( $payment->installment_number() <= 0 ) {
			throw new Not_Installment_Payment_Exception( esc_html__( 'Payment installment number is not a positive integer.', 'woo-asaas' ) );
		}
	}
}
