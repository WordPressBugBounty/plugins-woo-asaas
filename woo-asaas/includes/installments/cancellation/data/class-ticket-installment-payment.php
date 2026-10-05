<?php
/**
 * Ticket installment payment value object.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Data;

use WC_Asaas\Installments\Cancellation\Validator\Ticket_Installment_Payment_Validator;

/**
 * Ticket installment payment value object.
 */
class Ticket_Installment_Payment {

	/**
	 * The Asaas installment ID.
	 *
	 * @var string
	 */
	private $installment_id;

	/**
	 * The billing type ID.
	 *
	 * @var string
	 */
	private $billing_type;

	/**
	 * The installment number.
	 *
	 * @var int
	 */
	private $installment_number;

	/**
	 * Constructor.
	 *
	 * @param string                               $installment_id The Asaas installment ID.
	 * @param string                               $billing_type   The billing type ID.
	 * @param int                                  $installment_number The installment number.
	 * @param Ticket_Installment_Payment_Validator $validator      The validator instance.
	 */
	public function __construct( $installment_id, $billing_type, $installment_number, Ticket_Installment_Payment_Validator $validator ) {
		$this->installment_id     = (string) $installment_id;
		$this->billing_type       = (string) $billing_type;
		$this->installment_number = (int) $installment_number;
		$validator->validate( $this );
	}

	/**
	 * Get the installment ID.
	 *
	 * @return string
	 */
	public function installment_id() {
		return $this->installment_id;
	}

	/**
	 * Get the billing type.
	 *
	 * @return string
	 */
	public function billing_type() {
		return $this->billing_type;
	}

	/**
	 * Get the installment number.
	 *
	 * @return int
	 */
	public function installment_number() {
		return $this->installment_number;
	}

	/**
	 * Whether this is the first installment, which governs the whole plan.
	 *
	 * @return bool
	 */
	public function is_first_installment() {
		return 1 === $this->installment_number;
	}
}
