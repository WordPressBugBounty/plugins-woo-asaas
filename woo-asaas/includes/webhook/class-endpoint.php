<?php
/**
 * File for class Endpoint
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook;

use Exception;
use stdClass;
use WC_Asaas\Api\Api;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Helper\Subscriptions_Helper;
use WC_Asaas\WC_Asaas;
use WC_Asaas\Billing_Type\Billing_Type_Exception;
use WC_Asaas\Billing_Type\Billing_Types;
use WC_Asaas\Webhook\Data\Webhook_Targets;
use WC_Asaas\Webhook\Event_Exception;
use WC_Asaas\Webhook\Inconsistency_Data_Exception;
use WC_Asaas\Webhook\Invalid_Token_Exception;
use WC_Asaas\Webhook\Service\Payment_Reconciliation_Service;
use WC_Asaas\Webhook\Service\Payment_Status_Service;
use WC_Asaas\Webhook\Service\Webhook_Targets_Service;
use WC_Asaas\Webhook\Validator\Access_Token_Validator;
use WC_Asaas\Webhook\Validator\Webhook_Request_Validator;
use WC_Asaas\Webhook\Webhook;

/**
 * Endpoint
 */
class Endpoint {

	/**
	 * Variable for save value of the query var
	 *
	 * @var string
	 */
	private $query_var = 'asaas-webhook';

	/**
	 * The URL endpoint
	 *
	 * @var string
	 */
	private $url_endpoint = 'asaas-webhook';

	/**
	 * The gateway to load the settings
	 *
	 * @var Gateway
	 */
	protected $gateway;

	/**
	 * The query URL to the endpoint
	 *
	 * @var string
	 */
	protected $query;

	/**
	 * Instance of this class
	 *
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin public actions
	 */
	private function __construct() {
		$this->query = "index.php?{$this->query_var}=1";

		add_action( 'template_redirect', array( $this, 'process_webhook' ) );

		add_filter( 'query_vars', array( $this, 'query_vars' ) );
	}

	/**
	 * Return an instance of this class
	 *
	 * @return self A single instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Add rewrite rule for asaas-webhook
	 */
	public function custom_rewrite_basic() {
		add_rewrite_rule( "{$this->url_endpoint}/?$", $this->query, 'top' );
	}

	/**
	 * Register query_var
	 *
	 * @param  array $qvars Query vars.
	 *
	 * @return array $qvars Query vars with query of the endopoit
	 */
	public function query_vars( $qvars ) {
		$qvars[] = $this->query_var;
		return $qvars;
	}

	/**
	 * Processes webhook and redirects to its specific method
	 *
	 * @throws Exception Returned message to API.
	 */
	public function process_webhook() {
		if ( '1' === get_query_var( $this->query_var ) ) {
			try {
				$raw_data      = file_get_contents( 'php://input' ); // @codingStandardsIgnoreLine WordPress.WP.AlternativeFunctions.file_get_contents wp_remote_get not work with php://input
				$data          = json_decode( $raw_data );
				$this->gateway = $this->get_request_gateway( $data );

				$this->validate_token();

				$request_validator = new Webhook_Request_Validator();
				$request_validator->validate_data( $raw_data );
				$request_validator->validate_event( $data->event );
				$request_validator->validate_billing_type( $data->payment->billingType );
				$request_validator->validate_content();

				$this->gateway->get_logger()->log( 'WEBHOOK REQUEST ' . $raw_data );

				$is_payment_confirmation = in_array(
					$data->event,
					array( Webhook::PAYMENT_CONFIRMED, Webhook::PAYMENT_RECEIVED ),
					true
				);

				$payment_status_service = new Payment_Status_Service( $this->gateway, new Api( $this->gateway ) );
				$confirmed_payment      = ( $is_payment_confirmation
					&& ! $payment_status_service->should_skip( $data ) )
					? $payment_status_service->validate( $data )
					: null;

				$targets = ( new Webhook_Targets_Service( new Subscriptions_Helper() ) )->resolve(
					$data,
					$is_payment_confirmation,
					$confirmed_payment
				);
				$this->ignore_non_woocommerce_targets( $targets );

				if ( ! $is_payment_confirmation && ! $payment_status_service->should_skip( $data ) ) {
					$payment_status_service->validate( $data );
				}

				$webhook_data = $this->canonicalize_webhook_data( $data, $confirmed_payment );

				$should_process = apply_filters(
					'woocommerce_asaas_should_process_webhook',
					true,
					$data,
					$targets->order(),
					$targets->request_subscription()
				);
				if ( $should_process ) {
					$webhook = new Webhook(
						$this->gateway,
						$targets->order(),
						$targets->subscription(),
						$webhook_data,
						new Payment_Reconciliation_Service( $this->gateway, $targets->order(), $webhook_data->payment )
					);
					$webhook->process_event();
					$this->response( 200, __( 'Webhook has been processed with success', 'woo-asaas' ) );
				}

				$this->response( 200, __( 'Webhook was ignored by an external filter in this store', 'woo-asaas' ) );

			} catch ( Billing_Type_Exception $error ) {
				$this->response( 200, $error->getMessage() );
			} catch ( Inconsistency_Data_Exception $error ) {
				$this->response( 200, $error->getMessage() );
			} catch ( Event_Exception $error ) {
				$this->response( 200, $error->getMessage() );
			} catch ( Invalid_Token_Exception $error ) {
				$this->response( 401, $error->getMessage() );
			} catch ( Exception $error ) {
				$this->response( 500, $error->getMessage() );
			}
		}
	}

	/**
	 * Resolve the request gateway by the payment billing type.
	 *
	 * Resolving the gateway before the token validation scopes the token to the
	 * gateway of the payment's billing type. When the billing type isn't known
	 * yet, no gateway is returned and every gateway may validate the request.
	 *
	 * The billing type is converted and stored on the request data, so the
	 * conversion is done only once and reused by the request validation.
	 *
	 * @param mixed $data The request data.
	 * @return Gateway|null The gateway, or null when the billing type isn't a gateway one.
	 */
	private function get_request_gateway( $data ) {
		if (
			! is_object( $data )
			|| ! isset( $data->payment->billingType )
			|| ! is_string( $data->payment->billingType )
		) {
			return null;
		}

		$data->payment->billingType = Billing_Types::normalize( $data->payment->billingType );

		if ( ! in_array( $data->payment->billingType, Billing_Types::GATEWAY_TYPES, true ) ) {
			return null;
		}

		return WC_Asaas::get_instance()->get_gateway_by_billing_type( $data->payment->billingType );
	}

	/**
	 * Build the data the event is processed with.
	 *
	 * When Asaas returned a canonical payment, it replaces the request payment so the
	 * event is processed with the payment Asaas actually holds. The request data is
	 * cloned because the filters still receive the original payload.
	 *
	 * @param stdClass      $data The request data.
	 * @param stdClass|null $confirmed_payment The payment returned by Asaas, when validated.
	 * @return stdClass The data the event must be processed with.
	 */
	private function canonicalize_webhook_data( stdClass $data, ?stdClass $confirmed_payment ) {
		if ( null === $confirmed_payment ) {
			return $data;
		}

		$webhook_data          = clone $data;
		$webhook_data->payment = $confirmed_payment;

		return $webhook_data;
	}

	/**
	 * Ignore the request returning 200 when the payment isn't bound to a WooCommerce object.
	 *
	 * @param Webhook_Targets $targets The targets resolved from the request.
	 */
	private function ignore_non_woocommerce_targets( Webhook_Targets $targets ) {
		if ( $targets->order_missing() ) {
			$this->response( 200, 'This request isn\'t a WooCommerce order.' );
		}

		if ( $targets->subscription_missing() ) {
			$this->response( 200, 'This request isn\'t a WooCommerce subscription.' );
		}
	}

	/**
	 * Validate if the request token is the same set in gateway settings
	 *
	 * The token is scoped to the gateway of the payment's billing type: a token
	 * configured in another gateway doesn't authenticate the request. When the
	 * billing type isn't known yet, every gateway may validate it.
	 *
	 * @throws Invalid_Token_Exception If the token is invalid.
	 */
	private function validate_token() {
		$gateways     = null === $this->gateway ? WC_Asaas::get_instance()->get_gateways() : array( $this->gateway );
		$access_token = isset( $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_ASAAS_ACCESS_TOKEN'] ) )
			: '';

		( new Access_Token_Validator() )->validate( $gateways, $access_token );
	}

	/**
	 * Get the webhook URL to show in settings page
	 *
	 * @return string The webhook URL.
	 */
	public function get_url() {
		$query = $this->query;
		if ( '' !== get_option( 'permalink_structure', '' ) ) {
			$query = $this->url_endpoint;
		}

		return home_url( '/' . $query );
	}

	/**
	 * Log endpoint request response and return it
	 *
	 * When the gateway couldn't be created, it is impossible log some information.
	 *
	 * @param int    $code The HTTP response code.
	 * @param string $message The response message.
	 */
	protected function response( $code, $message ) {
		if ( ! is_null( $this->gateway ) ) {
			$this->gateway->get_logger()->log( 'WEBHOOK RESPONSE ' . $code . ' ' . $message );
		}

		status_header( $code );
		die( wp_kses( $message, array() ) );
	}
}
