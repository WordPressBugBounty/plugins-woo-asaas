<?php
/**
 * Installment canceller service.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Installments\Cancellation\Service;

use Exception;
use WC_Asaas\Api\Api;
use WC_Asaas\Api\Response\Error_Response;
use WC_Asaas\Installments\Cancellation\Data\Ticket_Installment_Payment;

/**
 * Service for cancelling ticket installment plans.
 */
class Installment_Canceller {

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
	 * Cancel all payments of the installment plan in Asaas.
	 *
	 * Treats 404 and empty responses as success (installment already removed).
	 * Throws Exception on transient failures so the caller propagates HTTP 500
	 * and Asaas retries the webhook.
	 *
	 * @param Ticket_Installment_Payment $ticket The ticket installment payment.
	 * @throws Exception On transient API failure.
	 */
	public function cancel( Ticket_Installment_Payment $ticket ) {
		$response = $this->api->installments()->delete( $ticket->installment_id() );
		if ( $response instanceof Error_Response && 404 !== $response->code ) {
			throw new Exception(
				sprintf(
					'Error deleting installment %s in Asaas. Response HTTP status: %s',
					esc_html( $ticket->installment_id() ),
					esc_html( (string) $response->code )
				)
			);
		}
	}
}
