<?php
/**
 * Handles the persistence and management of webhook settings and operations.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Service;

use WC_Asaas\Connectivity\API\Resource\Webhook_Resource;
use WC_Asaas\Connectivity\Data\Registered_Webhook;
use WC_Asaas\Connectivity\Data\Webhook_Factory;

/**
 * Webhook persistence service.
 */
class Webhook_Persistence_Service {
	/**
	 * WooCommerce persistence service.
	 *
	 * @var Woocommerce_Persistence_Service
	 */
	private $woocommerce_persistence_service;

	/**
	 * Constructor
	 *
	 * Initializes the WooCommerce persistence service.
	 */
	public function __construct() {
		$this->woocommerce_persistence_service = new Woocommerce_Persistence_Service();
	}

	/**
	 * Retrieve existent webhook
	 *
	 * Gets the existing webhook from the API.
	 *
	 * @return Registered_Webhook The existing webhook.
	 */
	public function retrieve_existent_webhook() {
		$webhook          = ( new Webhook_Factory() )->create_webhook_with_woocommerce_data();
		$existent_webhook = ( new Webhook_Resource() )->existent_webhook( $webhook->url() );

		return $existent_webhook;
	}

	/**
	 * Create webhook
	 *
	 * Creates a new webhook on the API and updates WooCommerce settings.
	 *
	 * @return Registered_Webhook The created webhook.
	 */
	public function create_webhook() {

		$new_webhook = ( new Webhook_Factory() )->create_webhook_with_woocommerce_data();

		$this->woocommerce_persistence_service->update_access_token( $new_webhook->auth_token() );

		$webhook = ( new Webhook_Resource() )->create( $new_webhook );

		$this->woocommerce_persistence_service->update_webhook_connectivity_status( true );

		return $webhook;
	}

	/**
	 * Reenable webhook
	 *
	 * Reenables an existing webhook with a new auth token.
	 *
	 * @param Registered_Webhook $webhook The webhook to reenable.
	 * @return Registered_Webhook The reenabled webhook.
	 */
	public function reenable_webhook( Registered_Webhook $webhook ) {
		$updatable_webhook = ( new Webhook_Factory() )->create_updatable_webhook( $webhook );

		$reenabled_webhook = ( new Webhook_Resource() )->reenable_webhook( $updatable_webhook );

		$this->woocommerce_persistence_service->update_access_token( $updatable_webhook->auth_token() );

		$this->woocommerce_persistence_service->update_webhook_connectivity_status( true );

		return $reenabled_webhook;
	}

	/**
	 * Remove webhook backoff/penalty from API
	 *
	 * Calls the remove backoff endpoint only if webhook has penalties.
	 *
	 * @param Registered_Webhook $webhook The registered webhook.
	 * @return void
	 */
	public function remove_backoff( Registered_Webhook $webhook ) {
		if ( $webhook->penalized_requests_count() === 0 ) {
			return;
		}

		( new Webhook_Resource() )->remove_backoff( $webhook );
	}

	/**
	 * Update webhook email
	 *
	 * Updates the webhook email if it has changed.
	 *
	 * @param Registered_Webhook $webhook The webhook to update.
	 * @return void
	 */
	public function update_webhook_email( Registered_Webhook $webhook ) {
		$updatable_webhook = ( new Webhook_Factory() )->create_updatable_webhook( $webhook );

		if ( $updatable_webhook->email() === $webhook->email() ) {
			return;
		}

		( new Webhook_Resource() )->update_webhook_email( $updatable_webhook );
	}
}
