<?php
/**
 * Webhook Connectivity Status Service
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Connectivity\Service;

use WC_Asaas\Connectivity\Data\Registered_Webhook;

/**
 * Determines the connectivity health status of a webhook.
 */
class Webhook_Connectivity_Status_Service {
	/**
	 * Check if a webhook is healthy.
	 *
	 * @param Registered_Webhook $webhook The webhook to evaluate.
	 * @return bool True if the webhook is enabled, not interrupted, and has no penalized requests.
	 */
	public function is_healthy( Registered_Webhook $webhook ) {
		return $webhook->enabled() && ! $webhook->interrupted() && $webhook->penalized_requests_count() === 0;
	}
}
