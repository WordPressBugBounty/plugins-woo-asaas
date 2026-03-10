<?php
/**
 * Webhook Resource
 *
 * @package WooAsaas
 */

// phpcs:ignore PHPCompatibility.Keywords.ForbiddenNamesAsDeclared.resourceFound, PHPCompatibility.Keywords.ForbiddenNames.resourceFound
namespace WC_Asaas\Connectivity\API\Resource;

use WC_Asaas\Api\Api;
use WC_Asaas\Api\Resources\Webhooks;
use WC_Asaas\Api\Response\Collection_Response;
use WC_Asaas\Api\Response\Error_Response;
use WC_Asaas\Api\Response\Response;
use WC_Asaas\Connectivity\Adapter\Creatable_Webhook_To_Resource_Request_Adapter;
use WC_Asaas\Connectivity\Adapter\Updatable_Webhook_To_Resource_Request_Adapter;
use WC_Asaas\Connectivity\Data\Registered_Webhook;
use WC_Asaas\Connectivity\Adapter\New_Webhook_To_Resource_Request_Adapter;
use WC_Asaas\Connectivity\Adapter\Reenabled_Webhook_To_Resource_Request_Adapter;
use WC_Asaas\Connectivity\Data\Updatable_Webhook;
use WC_Asaas\Connectivity\Data\Creatable_Webhook;
use WC_Asaas\Connectivity\Data\Webhook_Factory;
use WC_Asaas\Connectivity\Exception\API_Error_Response_Exception;
use WC_Asaas\Connectivity\Provider\Gateway_Provider;

/**
 * Handles API resource operations for webhooks.
 */
class Webhook_Resource {
	/**
	 * Webhook resource.
	 *
	 * @var Webhooks
	 */
	private $resource;

	/**
	 * Constructor.
	 *
	 * Initializes the webhook API resource.
	 */
	public function __construct() {
		$api = new Api( ( new Gateway_Provider() )->gateway() );

		$this->resource = $api->webhooks();
	}

	/**
	 * Check if a webhook exists by URL.
	 *
	 * @param string $url The webhook URL to check.
	 * @return Response
	 */
	public function exists( string $url ) {
		return $this->resource->exists( $url );
	}

	/**
	 * Get the existing webhook by URL.
	 *
	 * @param string $url The webhook URL.
	 * @return Registered_Webhook
	 * @throws API_Error_Response_Exception If the webhook is not found.
	 */
	public function existent_webhook( string $url ) {
		$webhooks = $this->resource->exists( $url );

		if ( ! $webhooks instanceof Collection_Response ) {
			throw new API_Error_Response_Exception( esc_html__( 'Webhook not found', 'woo-asaas' ) );
		}

		$webhooks = $webhooks->get_items();

		if ( count( $webhooks ) <= 0 ) {
			throw new API_Error_Response_Exception( esc_html__( 'Webhook not found', 'woo-asaas' ) );
		}

		$webhook = reset( $webhooks );

		return ( ( new Webhook_Factory() )->create_from_api_response( $webhook ) );
	}

	/**
	 * Create a new webhook.
	 *
	 * @param Creatable_Webhook $webhook The webhook data to create.
	 * @return Registered_Webhook
	 * @throws API_Error_Response_Exception If the API returns an error.
	 */
	public function create( Creatable_Webhook $webhook ) {
		$data = ( new Creatable_Webhook_To_Resource_Request_Adapter( $webhook ) )->adapt();

		$response = $this->resource->create( $data );

		if ( $response instanceof Error_Response ) {
			throw new API_Error_Response_Exception(
				esc_html( $response->get_errors()->get_error_message() ),
				$response->code // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Integer value.
			);
		}

		$webhook = $response->get_json();

		return ( ( new Webhook_Factory() )->create_from_api_response( $webhook ) );
	}

	/**
	 * Re-enable an existing webhook.
	 *
	 * @param Updatable_Webhook $webhook The webhook data for re-enabling.
	 * @return Registered_Webhook
	 * @throws API_Error_Response_Exception If the API returns an error.
	 */
	public function reenable_webhook( Updatable_Webhook $webhook ) {
		$data = ( new Updatable_Webhook_To_Resource_Request_Adapter( $webhook ) )->adapt();

		$response = $this->resource->update( $webhook->id(), $data );

		if ( $response instanceof Error_Response ) {
			throw new API_Error_Response_Exception(
				esc_html( $response->get_errors()->get_error_message() ),
				$response->code // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Integer value.
			);
		}

		$webhook = $response->get_json();

		return ( ( new Webhook_Factory() )->create_from_api_response( $webhook ) );
	}

	/**
	 * Remove webhook backoff/penalty
	 *
	 * @param Registered_Webhook $webhook The registered webhook.
	 * @return void
	 * @throws API_Error_Response_Exception If API request fails.
	 */
	public function remove_backoff( Registered_Webhook $webhook ) {
		$response = $this->resource->remove_backoff( $webhook->id() );

		if ( $response instanceof Error_Response ) {
			throw new API_Error_Response_Exception(
				esc_html( $response->get_errors()->get_error_message() ),
				$response->code // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Integer value.
			);
		}
	}

	/**
	 * Update webhook email
	 *
	 * @param Updatable_Webhook $webhook The updatable webhook.
	 * @return Registered_Webhook The updated webhook.
	 * @throws API_Error_Response_Exception If API request fails.
	 */
	public function update_webhook_email( Updatable_Webhook $webhook ) {
		$data = array(
			'email' => $webhook->email()
		);

		$response = $this->resource->update( $webhook->id(), $data );

		if ( $response instanceof Error_Response ) {
			throw new API_Error_Response_Exception(
				esc_html( $response->get_errors()->get_error_message() ),
				$response->code // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Integer value.
			);
		}

		$webhook = $response->get_json();

		return ( ( new Webhook_Factory() )->create_from_api_response( $webhook ) );
	}
}
