<?php
/**
 * File for class Payment_Status_Service
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook\Service;

use Exception;
use stdClass;
use WC_Asaas\Api\Api;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Webhook\Api_Skip;
use WC_Asaas\Webhook\Inconsistency_Data_Exception;
use WC_Asaas\Webhook\Webhook;

/**
 * Validates the payment status of a webhook request against the Asaas API.
 */
class Payment_Status_Service {

	/**
	 * The gateway the service validates for.
	 *
	 * @var Gateway
	 */
	private $gateway;

	/**
	 * The API used to fetch the canonical payment.
	 *
	 * @var Api
	 */
	private $api;

	/**
	 * Initialize the service.
	 *
	 * @param Gateway $gateway The gateway the service validates for.
	 * @param Api     $api The API used to fetch the canonical payment.
	 */
	public function __construct( Gateway $gateway, Api $api ) {
		$this->gateway = $gateway;
		$this->api     = $api;
	}

	/**
	 * Validate if the payment exists and its status is the same as the request.
	 *
	 * Re-fetches the payment from Asaas by id and checks the request against
	 * that response, instead of trusting the request body alone.
	 *
	 * The caller must have already decided not to skip the validation through
	 * {@see Payment_Status_Service::should_skip()}.
	 *
	 * @param stdClass $data The request data.
	 * @throws Exception If the response is an error or the status in request doesn't match with the Asaas one.
	 * @throws Inconsistency_Data_Exception If is a PAYMENT_CREATED event without a subscription associated.
	 * @return stdClass The payment returned by Asaas.
	 */
	public function validate( stdClass $data ): stdClass {
		$response = $this->api->payments()->find( $data->payment->id );

		if ( 200 !== $response->code ) {
			throw new Exception(
				sprintf(
					'Error verifying payment status in Asaas. Response HTTP status: %d',
					esc_html( $response->code )
				)
			);
		}

		if ( Webhook::PAYMENT_CREATED === $data->event && ! isset( $data->payment->subscription ) ) {
			throw new Inconsistency_Data_Exception( 'PAYMENT_CREATED status ignored' );
		}

		$confirmed_payment = $response->get_json();

		if ( $data->payment->status !== $confirmed_payment->status ) {
			throw new Inconsistency_Data_Exception( 'Status doesn\'t match with Asaas' );
		}

		return $confirmed_payment;
	}

	/**
	 * Check if the request asks to skip the API validation and the environment allows it.
	 *
	 * @param stdClass $data The request data.
	 * @return bool True when the API status validation must be skipped.
	 */
	public function should_skip( stdClass $data ): bool {
		$want_skip = isset( $data->skip_api_status_validation ) ? $data->skip_api_status_validation : false;

		return true === ( new Api_Skip() )->can_skip() && true === $want_skip;
	}
}
