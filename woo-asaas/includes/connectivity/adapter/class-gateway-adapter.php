<?php

namespace WC_Asaas\Connectivity\Adapter;

use WC_Asaas\Connectivity\Contract\Gateway_Interface;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Log\Logger;

class Gateway_Adapter implements Gateway_Interface {

	/**
	 * The gateway that will call the API
	 *
	 * @var Gateway
	 */
	private $gateway;

	public function __construct( Gateway $gateway ) {
		$this->gateway = $gateway;
	}

	/**
	 * Get a gateway option.
	 *
	 * Reads through the WooCommerce accessor instead of the `settings` array: it
	 * initializes the settings when they were not loaded yet and falls back to the
	 * field default, so a key that was never saved returns a value instead of a warning.
	 *
	 * @param string $key The option key.
	 * @return mixed The stored value, or the field default when it was never saved.
	 */
	public function get_option( string $key ) {
		return $this->gateway->get_option( $key );
	}

	/**
	 * Check whether the webhook access token is missing.
	 *
	 * @return bool True when no usable webhook access token is configured.
	 */
	public function access_token_is_missing(): bool {
		return $this->gateway->access_token_is_missing();
	}

	/**
	 * Get the API key the gateway authenticates with.
	 *
	 * @return string The API key.
	 */
	public function get_api_key(): string {
		return $this->gateway->get_api_key();
	}

	/**
	 * Get the gateway logger.
	 *
	 * @return Logger The gateway logger.
	 */
	public function get_logger(): Logger {
		return $this->gateway->get_logger();
	}
}
