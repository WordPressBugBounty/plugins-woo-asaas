<?php
/**
 * File for class Webhook Ajax
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Hook;

use Exception;
use WC_Asaas\Connectivity\Service\Webhook_Connectivity_Status_Service;
use WC_Asaas\Connectivity\Service\Webhook_Persistence_Service;
use WC_Asaas\Connectivity\Service\Woocommerce_Persistence_Service;

/**
 * Handles AJAX actions for webhook connection management.
 */
/**
 * Webhook Connection AJAX
 *
 * Handles AJAX requests for webhook connectivity operations.
 */
class Webhook_Connection_Ajax extends Connection_Ajax {
	/**
	 * Webhook connectivity status service
	 *
	 * @var Webhook_Connectivity_Status_Service
	 */
	private $webhook_connectivity_status_service;
	/**
	 * Webhook persistence service
	 *
	 * @var Webhook_Persistence_Service
	 */
	private $webhook_persistence_service;
	/**
	 * WooCommerce Persistence Service
	 *
	 * @var Woocommerce_Persistence_Service
	 */
	private $woocommerce_persistence_service;

	/**
	 * Constructor.
	 *
	 * Initializes services and registers AJAX action hooks.
	 */
	public function __construct() {
		$this->webhook_connectivity_status_service = new Webhook_Connectivity_Status_Service();
		$this->webhook_persistence_service         = new Webhook_Persistence_Service();
		$this->woocommerce_persistence_service     = new Woocommerce_Persistence_Service();

		add_action( 'wp_ajax_check_webhook_status', array( $this, 'check_webhook_status' ) );
		add_action( 'wp_ajax_webhook_health_check', array( $this, 'webhook_health_check' ) );
		add_action( 'wp_ajax_reenable_webhook', array( $this, 'reenable_webhook' ) );
		add_action( 'wp_ajax_update_existing_webhook_email', array( $this, 'update_existing_webhook_email' ) );
	}

	/**
	 * Check existing webhook on gateway settings page.
	 *
	 * @return void
	 */
	public function check_webhook_status() {
		try {
			$this->use_requested_connection_parameters();
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage(), $e->getCode() );
		}

		try {
			$this->check_existent_webhook_status();
		} catch ( Exception $e ) {
			$this->create_webhook();
		}
	}

	/**
	 * Webhook health check AJAX handler
	 *
	 * Checks if the webhook is healthy and creates one if not found.
	 *
	 * @return void Sends JSON response.
	 */
	public function webhook_health_check() {
		try {
			$this->check_existent_webhook_status();
		} catch ( Exception $e ) {
			$this->create_webhook();
		}
	}

	/**
	 * Reenable webhook AJAX handler
	 *
	 * Handles AJAX request to reenable a webhook queue.
	 *
	 * @return void Sends JSON response.
	 */
	public function reenable_webhook() {
		try {
			$webhook = $this->webhook_persistence_service->retrieve_existent_webhook();

			$this->webhook_persistence_service->reenable_webhook( $webhook );
			$this->webhook_persistence_service->remove_backoff( $webhook );

			wp_send_json_success();
		} catch ( Exception $e ) {
			$this->woocommerce_persistence_service->update_webhook_connectivity_status( false );

			wp_send_json_error( $e->getMessage(), $e->getCode() );
		}
	}

	/**
	 * Update existing webhook email AJAX handler
	 *
	 * Handles AJAX request to update webhook email.
	 *
	 * @return void Sends JSON response.
	 */
	public function update_existing_webhook_email() {
		try {
			$webhook = $this->webhook_persistence_service->retrieve_existent_webhook();

			$this->webhook_persistence_service->update_webhook_email( $webhook );

			wp_send_json_success();
		} catch ( Exception $e ) {
			wp_send_json_error( $e->getMessage(), $e->getCode() );
		}
	}

    /**
     * Create a new webhook and send the result as a JSON response.
     *
     * @return void
     */
	private function create_webhook() {
		try {
			$this->webhook_persistence_service->create_webhook();

			wp_send_json_success( true );
		} catch ( Exception $e ) {
			$this->woocommerce_persistence_service->update_webhook_connectivity_status( false );

			wp_send_json_error( $e->getMessage(), $e->getCode() );
		}
	}

	/**
	 * Check existent webhook status
	 *
	 * Retrieves the existing webhook and updates the connectivity status.
	 *
	 * @return void Sends JSON response.
	 */
	private function check_existent_webhook_status() {
		$existent_webhook    = $this->webhook_persistence_service->retrieve_existent_webhook();
		$connectivity_status = $this->webhook_connectivity_status_service->is_healthy(
			$existent_webhook
		);
		$this->woocommerce_persistence_service->update_webhook_connectivity_status( $connectivity_status );

		wp_send_json_success( $connectivity_status );
	}
}
