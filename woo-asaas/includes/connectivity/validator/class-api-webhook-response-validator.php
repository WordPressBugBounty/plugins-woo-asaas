<?php
/**
 * API Webhook Response Validator
 *
 * Validates webhook API responses for required properties.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Validator;

use stdClass;
use InvalidArgumentException;

/**
 * API Webhook Response Validator
 *
 * Ensures that API responses contain all required properties for webhook objects.
 */
class Api_Webhook_Response_Validator {

	/**
	 * Validate webhook API response
	 *
	 * @param stdClass $response The API response object to validate.
	 * @return void
	 * @throws InvalidArgumentException If required property is missing.
	 */
	public function validate( stdClass $response ) {
		$required_properties = array( 'id', 'enabled', 'interrupted', 'penalizedRequestsCount', 'email' );

		foreach ( $required_properties as $property ) {
			if ( ! property_exists( $response, $property ) ) {
				throw new InvalidArgumentException( 'Missing required property: ' . esc_html( $property ) );
			}
		}
	}
}
