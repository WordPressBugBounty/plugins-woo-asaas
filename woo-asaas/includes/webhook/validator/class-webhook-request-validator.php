<?php
/**
 * File for class Webhook_Request_Validator
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook\Validator;

use Exception;
use WC_Asaas\Billing_Type\Billing_Type_Exception;
use WC_Asaas\Billing_Type\Billing_Types;
use WC_Asaas\Webhook\Event_Exception;
use WC_Asaas\Webhook\Webhook;

/**
 * Validates a webhook request payload.
 *
 * Also centralizes the accepted event vocabulary.
 */
class Webhook_Request_Validator {

	/**
	 * Billing types accepted by the webhook.
	 *
	 * @var string[]
	 */
	const ACCEPTED_BILLING_TYPES = array(
		Billing_Types::BOLETO,
		Billing_Types::CREDIT_CARD,
		Billing_Types::DEPOSIT,
		Billing_Types::TRANSFER,
		Billing_Types::PIX,
	);

	/**
	 * Events accepted by the webhook.
	 *
	 * @var string[]
	 */
	const ACCEPTED_EVENTS = array(
		Webhook::PAYMENT_CONFIRMED,
		Webhook::PAYMENT_CREATED,
		Webhook::PAYMENT_DELETED,
		Webhook::PAYMENT_OVERDUE,
		Webhook::PAYMENT_RECEIVED,
		Webhook::PAYMENT_REFUNDED,
		Webhook::PAYMENT_RESTORED,
		Webhook::PAYMENT_UPDATED,
	);

	/**
	 * Validate if the request data isn't empty.
	 *
	 * @param string $data The request data.
	 * @throws Exception If data is empty.
	 */
	public function validate_data( $data ) {
		if ( '' === $data ) {
			throw new Exception( 'Data is empty.' );
		}
	}

	/**
	 * Validate if the event is accepted.
	 *
	 * @param string $event The request event.
	 * @throws Event_Exception If event is not acceptable.
	 */
	public function validate_event( $event ) {
		if ( false === array_search( $event, self::ACCEPTED_EVENTS, true ) ) {
			throw new Event_Exception(
				/* translators: %s: event name  */
				sprintf( esc_html__( 'Event %s wasn\'t registered.', 'woo-asaas' ), esc_html( $event ) )
			);
		}
	}

	/**
	 * Validate if the billing type is accepted.
	 *
	 * @param string $billing_type The request billing type.
	 * @throws Billing_Type_Exception If billing type is not acceptable.
	 */
	public function validate_billing_type( $billing_type ) {
		if ( false === array_search( $billing_type, self::ACCEPTED_BILLING_TYPES, true ) ) {
			throw new Billing_Type_Exception(
				/* translators: %s: billing type name  */
				sprintf( esc_html__( 'Billing type %s wasn\'t registered.', 'woo-asaas' ), esc_html( $billing_type ) )
			);
		}
	}

	/**
	 * Validate request content type.
	 *
	 * The content type must be `application/json`.
	 *
	 * @throws Exception If the content is invalid.
	 */
	public function validate_content() {
		$content_type = isset( $_SERVER['CONTENT_TYPE'] )
			? sanitize_text_field( wp_unslash( $_SERVER['CONTENT_TYPE'] ) )
			: '';
		if ( 'application/json' !== $content_type ) {
			throw new Exception( 'Content-Type not accepted' );
		}
	}
}
