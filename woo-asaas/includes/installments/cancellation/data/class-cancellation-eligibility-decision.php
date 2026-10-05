<?php
/**
 * Cancellation eligibility decision.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Data;

/**
 * Immutable verdict produced by the cancellation policy.
 */
class Cancellation_Eligibility_Decision {

	const ELIGIBLE       = 'eligible';
	const PAID_ORDER     = 'paid_order';
	const SETTLED_CHARGE = 'settled_charge';
	const TERMINAL_ORDER = 'terminal_order';
	const NOT_FIRST      = 'not_first_installment';

	/**
	 * The reason backing this decision.
	 *
	 * @var string
	 */
	private $reason;

	/**
	 * Constructor.
	 *
	 * @param string $reason One of the class constants.
	 */
	public function __construct( $reason ) {
		$this->reason = $reason;
	}

	/**
	 * Whether the plan is eligible (no specification rejected it).
	 *
	 * @return bool
	 */
	public function is_eligible() {
		return self::ELIGIBLE === $this->reason;
	}

	/**
	 * The reason backing this decision.
	 *
	 * @return string
	 */
	public function reason() {
		return $this->reason;
	}
}
