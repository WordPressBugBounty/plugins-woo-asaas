<?php
/**
 * Settled charge checker.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Service;

use Exception;
use WC_Asaas\Api\Api;
use WC_Asaas\Api\Response\Error_Response;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;

/**
 * Checks whether a ticket installment plan has settled charges in Asaas.
 */
class Settled_Charge_Checker {

	/**
	 * The API facade.
	 *
	 * @var Api
	 */
	private $api;

	/**
	 * Constructor.
	 *
	 * @param Api $api The API facade.
	 */
	public function __construct( Api $api ) {
		$this->api = $api;
	}

	/**
	 * Whether any charge of the installment plan is already settled in Asaas.
	 *
	 * Queries GET /v3/payments?installment={id} (the REST source of truth) and
	 * returns true when at least one charge is RECEIVED or CONFIRMED. Throws on
	 * transient API failure or invalid response structure so the caller
	 * propagates HTTP 500 and Asaas retries the webhook, instead of silently
	 * treating an unconfirmable state as "no settled charge".
	 *
	 * @param Ticket_Installment_Payment $ticket The ticket installment payment.
	 * @return bool
	 * @throws Exception On transient API failure or invalid response structure.
	 */
	public function has_settled_charge( Ticket_Installment_Payment $ticket ) {
		$response = $this->api->payments()->installment_list( $ticket->installment_id() );
		if ( $response instanceof Error_Response ) {
			throw new Exception(
				sprintf(
					'Error querying settled charge for installment %s in Asaas. Response HTTP status: %s',
					esc_html( $ticket->installment_id() ),
					esc_html( (string) $response->code )
				)
			);
		}
		$json = $response->get_json();
		if ( ! isset( $json->data ) || ! is_array( $json->data ) ) {
			throw new Exception(
				sprintf(
					'Invalid API response structure for installment %s. Missing or invalid data property.',
					esc_html( $ticket->installment_id() )
				)
			);
		}
		foreach ( $json->data as $payment ) {
			if ( isset( $payment->status ) && in_array( $payment->status, array( 'RECEIVED', 'CONFIRMED' ), true ) ) {
				return true;
			}
		}
		return false;
	}
}
