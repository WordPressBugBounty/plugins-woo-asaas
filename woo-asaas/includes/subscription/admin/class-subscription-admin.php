<?php
/**
 * Admin Subscription class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Subscription\Admin;

use Exception;
use WC_Asaas\Helper\Subscriptions_Helper;
use WC_Asaas\Meta_Data\Subscription_Meta;
use WC_Asaas\Admin\Settings;
use WC_Asaas\Api\Api;
use WC_Asaas\Api\Response\Error_Response;

/**
 * Subscription Admin class
 */
class Subscription_Admin {

	/**
	 * Instance of this class
	 *
	 * @var self
	 */
	protected static $instance = null;


	/**
	 * Is not allowed to call from outside to prevent from creating multiple instances.
	 */
	private function __construct() {
	}

	/**
	 * Prevent the instance from being cloned.
	 */
	private function __clone() {
	}

	/**
	 * Prevent from being unserialized.
	 *
	 * @throws Exception If create a second instance of it.
	 */
	public function __wakeup() {
		throw new Exception( esc_html__( 'Cannot unserialize singleton', 'woo-asaas' ) );
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
	 * Checks if date field was changed
	 *
	 * @param string $current_date   Current subscription date property in Unix timestamp format (e.g: 1660668703).
	 * @param string $submitted_date Submitted date property in Unix timestamp format (e.g: 1660668703).
	 * @return bool  True if date was changed. Otherwise, false.
	 */
	public function date_changed( $current_date, $submitted_date ) {
		// Converts both dates into GMT 0.
		$current_date   = gmdate( 'Y-m-d H:i', $current_date );
		$submitted_date = gmdate( 'Y-m-d H:i', $submitted_date );

		return $current_date !== $submitted_date;
	}

	/**
	 * Sends validation erro
	 *
	 * @param string $error_message The error message.
	 * @return void
	 */
	public function send_validation_error( $error_message ) {
		wp_die(
			'<b>' . esc_html__( 'Subscription update error:', 'woo-asaas' ) . '</b> ' . esc_html( $error_message ),
			esc_html__( 'Subscription update error', 'woo-asaas' ),
			array( 'back_link' => true )
		);
	}

	/**
	 * Validates changes when subscription is saved in admin
	 *
	 * @param int $subscription_id Subscription ID.
	 *
	 * @return void
	 */
	public function validate_subscription_on_admin_save( $subscription_id ) {
		$subscription = \wcs_get_subscription( $subscription_id );
		if ( false === $subscription ) {
			return;
		}

		// Is not using Asaas payment gateway?
		$payment_gateway = wc_get_payment_gateway_by_order( $subscription );
		if ( false === $payment_gateway || false === strpos( $payment_gateway->id, 'asaas-' ) ) {
			return;
		}

		// Submitted subscription data.
		$billing_interval = sanitize_text_field(
			// phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- Nonce verified by woocommerce_process_shop_subscription_meta hook.
			wp_unslash( isset( $_POST['_billing_interval'] ) ? $_POST['_billing_interval'] : '' )
		);
		$billing_period = sanitize_text_field(
			// phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- Nonce verified by woocommerce_process_shop_subscription_meta hook.
			wp_unslash( isset( $_POST['_billing_period'] ) ? $_POST['_billing_period'] : '' )
		);
		// Timestamp for start date.
		$start_timestamp = sanitize_text_field(
			// phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- Nonce verified by woocommerce_process_shop_subscription_meta hook.
			wp_unslash( isset( $_POST['start_timestamp_utc'] ) ? $_POST['start_timestamp_utc'] : '' )
		);
		// Timestamp for trial end date.
		$trial_end_timestamp = sanitize_text_field(
			// phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- Nonce verified by woocommerce_process_shop_subscription_meta hook.
			wp_unslash( isset( $_POST['trial_end_timestamp_utc'] ) ? $_POST['trial_end_timestamp_utc'] : '' )
		);
		// Timestamp for subscription next payment.
		$next_payment_timestamp = sanitize_text_field(
			// phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- Nonce verified by woocommerce_process_shop_subscription_meta hook.
			wp_unslash( isset( $_POST['next_payment_timestamp_utc'] ) ? $_POST['next_payment_timestamp_utc'] : '' )
		);
		// Timestamp for subscription end date.
		$end_date_timestamp = sanitize_text_field(
			// phpcs:ignore WordPress.CSRF.NonceVerification.NoNonceVerification,WordPress.VIP.SuperGlobalInputUsage.AccessDetected -- Nonce verified by woocommerce_process_shop_subscription_meta hook.
			wp_unslash( isset( $_POST['end_timestamp_utc'] ) ? $_POST['end_timestamp_utc'] : '' )
		);

		// Validations.
		$updated_subscription_data = array();
		if ( $this->date_changed( $subscription->get_time( 'start' ), $start_timestamp ) ) {
			$this->send_validation_error(
				__( 'Asaas does not support changes on subscription start date.', 'woo-asaas' )
			);
			return;
		}
		if ( $this->date_changed( $subscription->get_time( 'trial_end' ), $trial_end_timestamp ) ) {
			$this->send_validation_error(
				__( 'Asaas does not support changes on trial date.', 'woo-asaas' )
			);
			return;
		}

		$now = gmdate( 'Y-m-d' );
		if ( gmdate( 'Y-m-d', $next_payment_timestamp ) <= $now ) {
			$this->send_validation_error(
				__( 'The next payment date must be greater than the current one.', 'woo-asaas' )
			);
			return;
		}
		if ( '' !== $end_date_timestamp && '0' !== $end_date_timestamp
			&& gmdate( 'Y-m-d', $end_date_timestamp ) <= $now ) {
			$this->send_validation_error(
				__( 'The end subscription date must be greater than the current one.', 'woo-asaas' )
			);
			return;
		}

		$subscriptions_helper   = new Subscriptions_Helper();
		$new_billing_cycle      = $subscriptions_helper->convert_period(
			$billing_interval,
			$billing_period
		);
		$previous_billing_cycle = $subscriptions_helper->convert_period(
			$subscription->get_billing_interval(),
			$subscription->get_billing_period()
		);
		if ( false === $new_billing_cycle ) {
			$this->send_validation_error(
				sprintf(
					/* translators: %s: supported billing cicles  */
					__(
						'Asaas does not support the chosen billing period. Please, choose a valid period: %s.',
						'woo-asaas'
					),
					$subscriptions_helper->get_supported_billing_periods_string()
				)
			);
			return;
		}

		// Needs tokenization and is it unavailable?
		if ( 'asaas-credit-card' === $payment_gateway->id ) {
			if ( $new_billing_cycle !== $previous_billing_cycle
				|| $this->date_changed( $subscription->get_time( 'next_payment' ), $next_payment_timestamp )
				|| $this->date_changed( $subscription->get_time( 'end' ), $end_date_timestamp ) ) {

				$tokenization_available = $payment_gateway->get_admin_settings()->is_tokenization_available();
				if ( false === $tokenization_available ) {
					$this->send_validation_error(
						__(
							// phpcs:ignore Generic.Files.LineLength.MaxExceeded -- Line length exceeds due to translation string.
							'To update the billing cycle, next payment date or subscription end date you need to have tokenization enabled in your Asaas account.',
							'woo-asaas'
						) .
						/* translators: %s: supported billing cicles  */
						__( 'Please, contact your Asaas manager.', 'woo-asaas' )
					);
					return;
				}
			}
		}

		// Updates the Asaas subscription.
		$api               = new Api( $payment_gateway );
		$subscription_meta = new Subscription_Meta( $subscription->get_id() );
		$subscription_id   = $subscription_meta->get_subscription_id();
		if ( $subscription_id ) {
			$updated_subscription_data = array(
				'cycle'       => $new_billing_cycle,
				'nextDueDate' => gmdate( 'Y-m-d', $next_payment_timestamp ),
			);
			if ( '' !== $end_date_timestamp && '0' !== $end_date_timestamp ) {
				$updated_subscription_data['endDate'] = gmdate( 'Y-m-d', $end_date_timestamp );
			}
			$response = $api->subscriptions()->update( $subscription_id, $updated_subscription_data );
			if ( is_a( $response, Error_Response::class ) ) {
				foreach ( $response->get_errors()->get_error_messages() as $message ) {
					$this->send_validation_error( $message );
					return;
				}
			}
		}

	}

}
