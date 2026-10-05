<?php
/**
 * File for class Access_Token_Validator
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook\Validator;

use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Webhook\Invalid_Token_Exception;

/**
 * Validates the access token sent by Asaas on webhook requests.
 */
class Access_Token_Validator {

	/**
	 * Validate the received token against the tokens configured in the gateways.
	 *
	 * A gateway without a configured token never matches, so a store that hasn't
	 * set up a webhook token yet rejects every request instead of accepting
	 * requests sent without a token header. Tokens are compared in constant time.
	 *
	 * @param Gateway[] $gateways The gateways whose configured tokens may validate the request.
	 * @param string    $received_token The token received in the request.
	 * @throws Invalid_Token_Exception If no gateway has a configured token equal to the received one.
	 */
	public function validate( array $gateways, string $received_token ) {
		foreach ( $gateways as $gateway ) {
			if ( $gateway->access_token_is_missing() ) {
				continue;
			}

			$configured_token = $gateway->get_option( 'webhook_access_token' );
			$tokens_match     = hash_equals( $configured_token, $received_token )
				|| hash_equals( html_entity_decode( $configured_token ), $received_token );
			if ( $tokens_match ) {
				return;
			}
		}

		throw new Invalid_Token_Exception( 'Invalid Token' );
	}
}
