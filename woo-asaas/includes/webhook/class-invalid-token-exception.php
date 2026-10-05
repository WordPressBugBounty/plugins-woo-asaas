<?php
/**
 * Invalid webhook token exception
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook;

use Exception;

/**
 * This exception must be thrown when a webhook endpoint received a request
 * whose access token is missing or doesn't match the configured one.
 */
class Invalid_Token_Exception extends Exception {
}
