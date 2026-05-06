<?php
/**
 * Subscriptions helper class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Helper;

use WC_Asaas\Common\Query\Order_Query;
use WC_Order;
use WC_Subscription;

/**
 * Subscriptions helper functions
 */
class Subscriptions_Helper {

	/**
	 * Allowed period combinations.
	 *
	 * @var array
	 */
	private $allowed_period_combinations = array();

	/**
	 * Allowed discount coupon types.
	 *
	 * @var array
	 */
	private $allowed_discount_coupon_types = array();

	/**
	 * Discount coupon types with limitations.
	 * The plugin has restrictions for the following types: recurring_fee, recurring_percent
	 *
	 * @var array
	 */
	private $discount_coupon_types_with_limitations = array();

	/**
	 * Subscription product types.
	 *
	 * @var array
	 */
	public $subscription_product_types = array( 'variable-subscription', 'subscription', 'subscription_variation' );

	/**
	 * Init the Subscription Helper class
	 */
	public function __construct() {
		$this->allowed_period_combinations = array(
			'1 week'  => array(
				'period'      => 'WEEKLY',
				'description' => __( 'WEEKLY', 'woo-asaas' ),
			),
			'2 week'  => array(
				'period'      => 'BIWEEKLY',
				'description' => __( 'BIWEEKLY', 'woo-asaas' ),
			),
			'1 month' => array(
				'period'      => 'MONTHLY',
				'description' => __( 'MONTHLY', 'woo-asaas' ),
			),
			'4 week'  => array(
				'period'      => 'MONTHLY',
				'description' => __( 'MONTHLY', 'woo-asaas' ),
			),
			'2 month' => array(
				'period'      => 'BIMONTHLY',
				'description' => __( 'BIMONTHLY', 'woo-asaas' ),
			),
			'3 month' => array(
				'period'      => 'QUARTERLY',
				'description' => __( 'QUARTERLY', 'woo-asaas' ),
			),
			'6 month' => array(
				'period'      => 'SEMIANNUALLY',
				'description' => __( 'SEMIANNUALLY', 'woo-asaas' ),
			),
			'1 year'  => array(
				'period'      => 'YEARLY',
				'description' => __( 'YEARLY', 'woo-asaas' ),
			),
		);

		$this->allowed_discount_coupon_types = array(
			'percent'             => __( 'Percentage discount', 'woo-asaas' ),
			'fixed_cart'          => __( 'Fixed cart discount', 'woo-asaas' ),
			'fixed_product'       => __( 'Fixed product discount', 'woo-asaas' ),
			'sign_up_fee'         => __( 'Sign Up Fee Discount', 'woo-asaas' ),
			'sign_up_fee_percent' => __( 'Sign Up Fee % Discount', 'woo-asaas' ),
			'recurring_fee'       => __( 'Recurring Product Discount*', 'woo-asaas' ),
			'recurring_percent'   => __( 'Recurring Product % Discount*', 'woo-asaas' ),
		);

		$this->discount_coupon_types_with_limitations = array( 'recurring_fee', 'recurring_percent' );
	}

	/**
	 * Return supported billing period string
	 *
	 * @return string.
	 */
	public function get_supported_billing_periods_string() {
		$periods = [];
		foreach ( $this->allowed_period_combinations as $key => $period ) {
			if ( false === in_array( $period['description'], $periods, true ) ) {
				$periods[] = $period['description'];
			}
		}

		return implode( ', ', $periods );
	}

	/**
	 * Convert combined period to allowed billing cycle
	 *
	 * @link https://asaasv3.docs.apiary.io/#reference/0/assinaturas/criar-nova-assinatura
	 *
	 * @param string $interval The subscription product billing interval.
	 * @param string $period The subscription product billing period.
	 * @return string|false The billing cycle or false if fails.
	 */
	public function convert_period( $interval = '', $period = '' ) {
		$combined_period = $interval . ' ' . $period;
		if ( array_key_exists( $combined_period, $this->allowed_period_combinations ) ) {
			return $this->allowed_period_combinations[ $combined_period ]['period'];
		}

		return false;
	}

	/**
	 * Checks if discount coupon is supported
	 *
	 * @param \WC_Coupon $coupon The discount coupon.
	 * @return bool True if coupon is supported.
	 */
	public function discount_coupon_supported( $coupon ) {
		if ( array_key_exists( $coupon->get_discount_type(), $this->allowed_discount_coupon_types ) ) {
			if ( false === in_array( $coupon->get_discount_type(), $this->discount_coupon_types_with_limitations, true ) ) {
				return true;
			}

			// Discount coupon type with limitations.
			if ( 0 === (int) $coupon->get_meta( '_wcs_number_payments' ) ) {
				return true;
			}

			return false;
		}

		return false;
	}

	/**
	 * Return supported coupon types string
	 *
	 * @return string
	 */
	public function get_supported_coupon_types_string() {
		$coupon_types = [];
		foreach ( $this->allowed_discount_coupon_types as $key => $coupon_type ) {
			if ( false === in_array( $coupon_type, $coupon_types, true ) ) {
				$coupon_types[] = $coupon_type;
			}
		}

		return implode( ', ', $coupon_types );
	}

	/**
	 * Gets Subscription object by Asaas subscription id
	 *
	 * @param string $subscription_id The Asaas subscription id.
	 *
	 * @return WC_Subscription|bool WC_Subscription object if it found. Otherwise, false.
	 */
	public function get_subscription_by_id( $subscription_id ) {
		if ( ! function_exists( '\wcs_get_subscriptions' ) ) {
			return false;
		}

		$cache_key              = "subscription_$subscription_id";
		$cached_subscription_id = wp_cache_get( $cache_key );

		if ( false === $cached_subscription_id ) {
			$subscriptions = \wcs_get_subscriptions(
				array(
					// phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_query
					'meta_query' => array(
						array(
							'key'   => '_asaas_subscription_id',
							'value' => $subscription_id,
						),
					),
					'orderby'    => 'ID',
					'order'      => 'ASC',
					'limit'      => 1,
				)
			);

			$cached_subscription_id = count( $subscriptions ) > 0 ? key( $subscriptions ) : 0;
			wp_cache_set( $cache_key, $cached_subscription_id, '', HOUR_IN_SECONDS );
		}

		return $cached_subscription_id ? wcs_get_subscription( $cached_subscription_id ) : false;
	}

	/**
	 * Gets order by Asaas payment id
	 *
	 * @param string $payment_id The Asaas payment id.
	 *
	 * @return WC_Order|bool WC_Order object if it found. Otherwise, false.
	 */
	public function get_order_by_payment_id( $payment_id ) {
		$cache_key       = "order_{$payment_id}";
		$cached_order_id = wp_cache_get( $cache_key );

		if ( false === $cached_order_id ) {
			$args = array(
				// phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_query
				'meta_query' => array(
					array(
						'key'   => '_asaas_id',
						'value' => $payment_id,
					),
				),
				'orderby'    => 'ID',
				'order'      => 'ASC',
				'limit'      => 1,
				'return'     => 'ids',
			);

			$orders = ( new Order_Query() )->get_orders_with_meta_query( $args );

			$cached_order_id = count( $orders ) > 0 ? current( $orders ) : 0;
			wp_cache_set( $cache_key, $cached_order_id, '', HOUR_IN_SECONDS );
		}

		return $cached_order_id ? wc_get_order( $cached_order_id ) : false;
	}
}
