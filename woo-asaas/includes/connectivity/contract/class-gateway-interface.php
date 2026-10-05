<?php

namespace WC_Asaas\Connectivity\Contract;

use WC_Asaas\Log\Logger;

interface Gateway_Interface {
	/**
	 * Get a gateway option.
	 *
	 * @param string $key The option key.
	 * @return mixed The stored value, or the field default when it was never saved.
	 */
	public function get_option( string $key );

	/**
	 * Check whether the webhook access token is missing.
	 *
	 * @return bool True when no usable webhook access token is configured.
	 */
	public function access_token_is_missing(): bool;

	/**
	 * Get the API key the gateway authenticates with.
	 *
	 * @return string The API key.
	 */
	public function get_api_key(): string;

	/**
	 * Get the gateway logger.
	 *
	 * @return Logger The gateway logger.
	 */
	public function get_logger(): Logger;
}
