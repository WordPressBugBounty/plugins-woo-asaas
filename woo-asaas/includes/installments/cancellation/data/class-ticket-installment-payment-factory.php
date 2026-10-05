<?php
/**
 * Ticket installment payment factory.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Data;

use stdClass;
use WC_Asaas\Installments\Cancellation\Exception\Not_Installment_Payment_Exception;
use WC_Asaas\Installments\Cancellation\Validator\Ticket_Installment_Payment_Validator;

/**
 * Factory for creating ticket installment payment value objects.
 */
class Ticket_Installment_Payment_Factory {

	/**
	 * The validator instance.
	 *
	 * @var Ticket_Installment_Payment_Validator
	 */
	private $validator;

	/**
	 * Constructor.
	 *
	 * @param Ticket_Installment_Payment_Validator $validator The validator instance.
	 */
	public function __construct( Ticket_Installment_Payment_Validator $validator ) {
		$this->validator = $validator;
	}

	/**
	 * Create a Ticket_Installment_Payment from a webhook payment.
	 *
	 * @param stdClass $payment The webhook payment object.
	 * @return Ticket_Installment_Payment
	 * @throws Not_Installment_Payment_Exception If validation fails.
	 */
	public function from_payment( stdClass $payment ) {
		if ( ! isset( $payment->installment ) ) {
			throw new Not_Installment_Payment_Exception(
				esc_html__( 'Payment has no installment ID; not a ticket installment.', 'woo-asaas' )
			);
		}

		return new Ticket_Installment_Payment(
			$payment->installment,
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- API returns camelCase property that cannot be changed.
			$payment->billingType,
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- API returns camelCase property that cannot be changed.
			$payment->installmentNumber,
			$this->validator
		);
	}
}
