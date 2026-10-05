<?php
/**
 * Billing types vocabulary class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Billing_Type;

/**
 * Known billing types and their classification.
 */
class Billing_Types {

	/**
	 * Billing type name for ticket.
	 *
	 * @var string
	 */
	const BOLETO = 'BOLETO';

	/**
	 * Billing type name for credit card.
	 *
	 * @var string
	 */
	const CREDIT_CARD = 'CREDIT_CARD';

	/**
	 * Billing type name for deposit.
	 *
	 * @var string
	 */
	const DEPOSIT = 'DEPOSIT';

	/**
	 * Billing type name for transfer.
	 *
	 * @var string
	 */
	const TRANSFER = 'TRANSFER';

	/**
	 * Billing type name for pix.
	 *
	 * @var string
	 */
	const PIX = 'PIX';

	/**
	 * Billing types that map to a plugin gateway.
	 *
	 * @var string[]
	 */
	const GATEWAY_TYPES = array( self::BOLETO, self::CREDIT_CARD, self::PIX );

	/**
	 * Billing types that are processed as ticket.
	 *
	 * @var string[]
	 */
	const TICKET_TYPES = array( self::DEPOSIT, self::TRANSFER );

	/**
	 * Converts billing types that aren't a gateway one to ticket.
	 *
	 * The billing type must be DEPOSIT or TRANSFER for it to be converted.
	 *
	 * @param string $billing_type The request billing type.
	 * @return string The billing type, converted to ticket when applicable.
	 */
	public static function normalize( $billing_type ) {
		if ( false === array_search( $billing_type, self::TICKET_TYPES, true ) ) {
			return $billing_type;
		}

		return self::BOLETO;
	}
}
