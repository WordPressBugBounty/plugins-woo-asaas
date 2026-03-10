<?php
/**
 * Webhook Factory
 *
 * Creates webhook data objects from various sources.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Data;

use InvalidArgumentException;
use stdClass;
use WC_Asaas\Connectivity\Provider\Gateway_Provider;
use WC_Asaas\Connectivity\Provider\Webhook_Events_Provider;
use WC_Asaas\Connectivity\Provider\Webhook_Helper_Provider;
use WC_Asaas\Connectivity\Validator\Api_Webhook_Response_Validator;

/**
 * Factory for creating webhook data objects from different sources.
 */
class Webhook_Factory {
	const WEBHOOK_SUFFIX = '/asaas-webhook';

	/**
	 * Create Registered_Webhook from API response
	 *
	 * @param stdClass $response The API response object.
	 * @return Registered_Webhook The webhook data object.
	 * @throws InvalidArgumentException If response is missing required properties.
	 */
	public function create_from_api_response( stdClass $response ) {
		( new Api_Webhook_Response_Validator() )->validate( $response );

		return new Registered_Webhook(
			$response->id,
			$response->enabled,
			$response->interrupted,
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- API returns camelCase property that cannot be changed.
			$response->penalizedRequestsCount,
			$response->email
		);
	}

	/**
	 * Create an Updatable_Webhook from an existing Registered_Webhook.
	 *
	 * @param Registered_Webhook $webhook The existing registered webhook.
	 * @return Updatable_Webhook
	 */
	public function create_updatable_webhook( Registered_Webhook $webhook ) {
		$webhook_data = $this->create_webhook_with_woocommerce_data();

		return new Updatable_Webhook(
			$webhook->id(),
			true,
			false,
			$webhook_data->email(),
			$webhook_data->auth_token()
		);
	}

	/**
	 * Create a Creatable_Webhook with WooCommerce data.
	 *
	 * @return Creatable_Webhook
	 */
	public function create_webhook_with_woocommerce_data() {
		$name       = __( 'Webhooks from WooCommerce', 'woo-asaas' );
		$url        = home_url() . self::WEBHOOK_SUFFIX;
		$email      = ( new Gateway_Provider() )->gateway()->get_setting( 'email_notification' );
		$send_type  = 'SEQUENTIALLY';
		$auth_token = ( new Webhook_Helper_Provider() )->webhook_helper()->generate_random_token();
		$events     = ( new Webhook_Events_Provider() )->events();

		return new Creatable_Webhook(
			$name,
			$url,
			$email,
			$send_type,
			$auth_token,
			$events
		);
	}
}
