<?php
/**
 * File for class Payment_Reconciliation_Service
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook\Service;

use stdClass;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Webhook\Inconsistency_Data_Exception;
use WC_Order;
use WC_Order_Item_Fee;

/**
 * Reconciles the order total with the confirmed payment value.
 */
final class Payment_Reconciliation_Service {

	const PERCENTAGE_CALCULUS_TYPE = 'PERCENTAGE';

	const FIXED_CALCULUS_TYPE = 'FIXED';

	/**
	 * The gateway the service reconciles for.
	 *
	 * @var Gateway
	 */
	private $gateway;

	/**
	 * The order the payment reconciles.
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * The payment the order is reconciled with.
	 *
	 * @var stdClass
	 */
	private $payment;

	/**
	 * Initialize the service.
	 *
	 * @param Gateway  $gateway The gateway the service reconciles for.
	 * @param WC_Order $order The order the payment reconciles.
	 * @param stdClass $payment The payment the order is reconciled with.
	 */
	public function __construct( Gateway $gateway, WC_Order $order, stdClass $payment ) {
		$this->gateway = $gateway;
		$this->order   = $order;
		$this->payment = $payment;
	}

	/**
	 * Reconcile the order total with the confirmed payment value.
	 *
	 * Both sides are normalized to the store's price decimals first:
	 * `$this->order->get_total()` is the same total `Webhook::process_payment()` used
	 * to read through the order data snapshot (`get_data()['total']`, a numeric string
	 * such as "300.00") while `$this->payment->value` is a float (300.0), so comparing
	 * them as they are, or formatting them without a decimal count, never matches even
	 * for equal amounts.
	 *
	 * When the values differ, the order total is reconciled from the payment
	 * adjustments, and a payment that still doesn't match the order total is
	 * rejected before it can complete the order.
	 *
	 * @throws Inconsistency_Data_Exception If the confirmed payment value doesn't match
	 *                                      the order total.
	 */
	public function reconcile(): void {
		$order_total   = $this->normalize_value( $this->order->get_total() );
		$payment_total = $this->normalize_value( $this->payment->value );
		if ( $order_total !== $payment_total ) {
			$this->update_order_values();
		}

		$this->reject_value_mismatch( $payment_total );
	}

	/**
	 * Update order values (fine, interest, discount) to reconcile with the confirmed payment.
	 *
	 * Only reconciles fine/interest/discount against `originalValue`. Asaas sends that key
	 * for every billing type, Pix and boleto included, but leaves it `null` whenever nothing
	 * changed the payment value -- and it may be missing altogether in older or hand-built
	 * payloads. `isset()` covers both shapes: either way there is nothing to reconcile.
	 *
	 * This method itself never rejects a payment for being lower than the order total --
	 * that decision belongs to `reject_value_mismatch()`, called right after this one
	 * from `reconcile()`, which is also what lets a confirmed installment payment
	 * legitimately cover only one installment instead of the full order total.
	 *
	 * Fee items are added to the order before `calculate_totals()` so the recalculated
	 * total includes them. The discount can't take part in that recalculation --
	 * WooCommerce would overwrite it with the coupon discounts -- so it is applied
	 * after it, setting the discount total and subtracting it from the recalculated
	 * total, exactly like the `woocommerce_order_after_calculate_totals` hook used to do.
	 */
	private function update_order_values(): void {
		if ( ! isset( $this->payment->originalValue ) ) {
			return;
		}

		$discount = null;
		$changed  = false;
		if ( $this->payment->value > $this->payment->originalValue ) {
			$changed = $this->add_payment_increase_fees();
		} elseif ( $this->payment->value < $this->payment->originalValue ) {
			$discount = $this->calculate_payment_discount();
			$changed  = null !== $discount;
		}

		if ( false === $changed ) {
			return;
		}

		$this->order->calculate_totals();

		if ( null !== $discount ) {
			$this->apply_payment_discount( $discount );
		}
	}

	/**
	 * Add the fees that explain a payment value higher than `originalValue`
	 *
	 * @return bool True when a fee was added to the order.
	 */
	private function add_payment_increase_fees(): bool {
		$has_interest_value = isset( $this->payment->interestValue ) && is_numeric( $this->payment->interestValue );
		if ( $has_interest_value ) {
			return $this->add_effective_interest_fee();
		}

		return $this->add_fine_and_interest_fees();
	}

	/**
	 * Add the effective sum of fine and interest reported by Asaas as a single fee
	 *
	 * Asaas sends the effective sum in `interestValue`. The configured rates may not
	 * reflect the amount applied to a late payment, so it must not be recalculated.
	 * A value that doesn't explain the difference between the payment and `originalValue`
	 * adds nothing and is left to the value mismatch rejection.
	 *
	 * @return bool True when the fee was added to the order.
	 */
	private function add_effective_interest_fee(): bool {
		$item_total         = $this->normalize_value( $this->payment->interestValue );
		$payment_difference = $this->normalize_value(
			(float) $this->payment->value - (float) $this->payment->originalValue
		);

		if ( $item_total !== $payment_difference || (float) $item_total <= 0 ) {
			return false;
		}

		$item_name = __( 'Interest', 'woo-asaas' );
		$this->add_item_fee_to_order( $item_name, (float) $item_total );

		return true;
	}

	/**
	 * Add a fee for each adjustment (fine, interest) configured in the payment
	 *
	 * @return bool True when at least one fee was added to the order.
	 */
	private function add_fine_and_interest_fees(): bool {
		$values_changed = false;

		if ( isset( $this->payment->fine->value ) ) {
			$item_total = $this->calculate_item_total( $this->payment->fine, $this->payment->originalValue );

			$item_name = __( 'Fine tax', 'woo-asaas' );
			$this->add_item_fee_to_order( $item_name, $item_total );

			$values_changed = true;
		}

		if ( isset( $this->payment->interest->value ) ) {
			$item_total = $this->calculate_item_total( $this->payment->interest, $this->payment->originalValue );

			$item_name = __( 'Interest', 'woo-asaas' );
			$this->add_item_fee_to_order( $item_name, $item_total );

			$values_changed = true;
		}

		return $values_changed;
	}

	/**
	 * Calculate the discount that explains a payment value lower than `originalValue`.
	 *
	 * @return float|null The discount to apply, or null when the payment reports no discount.
	 */
	private function calculate_payment_discount() {
		if ( ! isset( $this->payment->discount->value ) ) {
			return null;
		}

		return (float) $this->calculate_item_total( $this->payment->discount, $this->payment->originalValue );
	}

	/**
	 * Apply the discount to the order after its totals were recalculated.
	 *
	 * @param float $discount The discount to apply.
	 */
	private function apply_payment_discount( float $discount ): void {
		$this->order->set_discount_total( $discount );
		$this->order->set_total( $this->order->get_total() - $discount );
	}

	/**
	 * Reject a confirmed payment whose value doesn't match the order total.
	 *
	 * `update_order_values()` already ran by this point, so a fine/interest
	 * (payment above the order total) or a discount (payment below it, reported through
	 * `originalValue`) already reconciled the order total to match the payment. What is
	 * left uncovered here is a value that still differs: an installment payment
	 * legitimately settles only one installment of the order total, so its presence --
	 * `installment`, the Asaas installment plan id -- is treated as legitimate for a
	 * lower value. Anything else that doesn't match the order total is an inconsistency
	 * and must not reach `payment_complete()`, so both a lower and a higher value fail
	 * closed.
	 *
	 * @param string $payment_total The confirmed payment value, normalized to the store's price
	 *                              decimals.
	 *
	 * @throws Inconsistency_Data_Exception If the payment is lower than the order total
	 *                                       without an installment to explain it, or higher
	 *                                       than the order total.
	 */
	private function reject_value_mismatch( string $payment_total ): void {
		$order_total = $this->normalize_value( $this->order->get_total() );

		if ( $payment_total === $order_total ) {
			return;
		}

		if ( $payment_total < $order_total && isset( $this->payment->installment ) ) {
			return;
		}

		$this->log_value_mismatch( $payment_total, $order_total );

		if ( $payment_total < $order_total ) {
			throw new Inconsistency_Data_Exception(
				esc_html__( 'Payment value is lower than order total.', 'woo-asaas' )
			);
		}

		throw new Inconsistency_Data_Exception(
			esc_html__( 'Payment value is higher than order total.', 'woo-asaas' )
		);
	}

	/**
	 * Log a payment value that doesn't match the order total.
	 *
	 * @param string $payment_total The confirmed payment value, normalized to the store's price
	 *                              decimals.
	 * @param string $order_total The order total, normalized to the store's price decimals.
	 */
	private function log_value_mismatch( string $payment_total, string $order_total ): void {
		$this->gateway->get_logger()->log(
			sprintf(
				'WEBHOOK PAYMENT VALUE MISMATCH Asaas payment value: %s / Order total: %s',
				$payment_total,
				$order_total
			)
		);
	}

	/**
	 * Normalize a monetary value to the store's price decimals.
	 *
	 * @param mixed $value The value to normalize.
	 * @return string The normalized value.
	 */
	private function normalize_value( $value ): string {
		return wc_format_decimal( $value, wc_get_price_decimals() );
	}

	/**
	 * Calculate item total
	 *
	 * @param object $item_object The object value.
	 * @param float  $total The order total value.
	 * @return float The item total.
	 */
	private function calculate_item_total( $item_object, $total ) {
		$fine_type = self::FIXED_CALCULUS_TYPE;
		if ( isset( $item_object->type ) ) {
			$fine_type = $item_object->type;
		}

		$item_total = $this->calculate_item_fee_value( $total, $fine_type, $item_object->value );

		return $item_total;
	}

	/**
	 * Calculate item fee value
	 *
	 * @param float  $current_value The current value.
	 * @param string $type The type of calculus.
	 * @param float  $amount The amount value.
	 * @return float The calculated fee value.
	 */
	private function calculate_item_fee_value( $current_value, $type, $amount ) {
		if ( self::PERCENTAGE_CALCULUS_TYPE === $type ) {
			return ( $current_value * ( $amount / 100 ) );
		}

		return $amount;
	}

	/**
	 * Add a fee to an order
	 *
	 * @param string $name The fee name.
	 * @param float  $total The total fee cost.
	 */
	private function add_item_fee_to_order( $name, $total ) {
		$item_fee = new WC_Order_Item_Fee();
		$item_fee->set_name( $name );
		$item_fee->set_total( $total );
		$this->order->add_item( $item_fee );
	}
}
