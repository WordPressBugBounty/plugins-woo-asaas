<?php
/**
 * File for class Webhook
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Webhook;

use Exception;
use stdClass;
use WC_Asaas\Api\Api;
use WC_Asaas\Gateway\Gateway;
use WC_Asaas\Meta_Data\Order;
use WC_Asaas\Meta_Data\Subscription_Meta;
use WC_Asaas\Subscription\Subscription;
use WC_Asaas\Webhook\Error_Response;
use WC_Asaas\Webhook\Event_Exception;
use WC_Asaas\Webhook\Inconsistency_Data_Exception;
use WC_Asaas\Webhook\Service\Payment_Reconciliation_Service;
use WC_DateTime;
use WC_Order;
use WC_Subscription;

/**
 * Webhook
 */
class Webhook {

	/**
	 * Attributes of the webhook
	 *
	 * @var object $data
	 */
	private $data;

	/**
	 * Order related to webhook
	 *
	 * @var WC_Order
	 */
	private $order;

	/**
	 * Subscription related to webhook
	 *
	 * @var WC_Subscription|null
	 */
	private $subscription;

	/**
	 * Service that reconciles the order total with the confirmed payment value.
	 *
	 * @var Payment_Reconciliation_Service
	 */
	private $payment_reconciliation;

	const PREFIX_LOG = 'Asaas: ';

	const PAYMENT_CREATED = 'PAYMENT_CREATED';

	const PAYMENT_UPDATED = 'PAYMENT_UPDATED';

	const PAYMENT_CONFIRMED = 'PAYMENT_CONFIRMED';

	const PAYMENT_RECEIVED = 'PAYMENT_RECEIVED';

	const PAYMENT_OVERDUE = 'PAYMENT_OVERDUE';

	const PAYMENT_REFUNDED = 'PAYMENT_REFUNDED';

	const PAYMENT_DELETED = 'PAYMENT_DELETED';

	const PAYMENT_RESTORED = 'PAYMENT_RESTORED';

	/**
	 * Initialize the object
	 *
	 * @param Gateway                        $gateway The payment gateway.
	 * @param WC_Order                      $order The webhook that will be processed.
	 * @param WC_Subscription|null          $subscription The subscription related to the webhook.
	 * @param stdClass                      $data The webhook data.
	 * @param Payment_Reconciliation_Service $payment_reconciliation The service that reconciles the
	 *                                                               order total with the confirmed
	 *                                                               payment value.
	 */
	public function __construct(
		Gateway $gateway,
		WC_Order $order,
		WC_Subscription $subscription = null,
		stdClass $data,
		Payment_Reconciliation_Service $payment_reconciliation
	) {
		$this->gateway                = $gateway;
		$this->order                  = $order;
		$this->subscription           = $subscription;
		$this->data                   = $data;
		$this->payment_reconciliation = $payment_reconciliation;

		$this->order->set_date_modified( new WC_DateTime( gmdate( 'Y-m-d H:i:s' ) ) );
	}

	/**
	 * Magic function to access attributes
	 *
	 * @param  string $name name of the attribute.
	 * @return mixed The value of the attribute.
	 */
	public function __get( $name ) {
		return $this->data->{$name};
	}

	/**
	 * Process the event according to its type
	 *
	 * @throws Exception Case event not found.
	 */
	public function process_event() {
		switch ( $this->event ) {
			case Webhook::PAYMENT_CONFIRMED:
				$this->on_payment_confirmed();
				break;

			case Webhook::PAYMENT_CREATED:
				$this->on_payment_created();
				break;

			case Webhook::PAYMENT_DELETED:
				$this->on_payment_deleted();
				break;

			case Webhook::PAYMENT_OVERDUE:
				$this->on_payment_overdue();
				break;

			case Webhook::PAYMENT_RECEIVED:
				$this->on_payment_confirmed();
				break;

			case Webhook::PAYMENT_REFUNDED:
				$this->on_payment_refunded();
				break;

			case Webhook::PAYMENT_RESTORED:
				$this->on_payment_restored();
				break;

			case Webhook::PAYMENT_UPDATED:
				$this->on_payment_updated();
				break;

			default:
				/* translators: %s: event name  */
				die( esc_html( sprintf( __( 'Untreated event: %s', 'woo-asaas' ), $this->event ) ) );
		}
	}

	/**
	 * Method used when payment is confirmed
	 */
	private function on_payment_confirmed() {
		$this->process_payment();

		if ( isset( $this->data->payment->subscription ) ) {
			$api      = new Api( $this->gateway );
			$response = $api->subscriptions()->find( $this->data->payment->subscription );
			if ( 200 === $response->code ) {
				$dates = array(
					/* phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar */
					'next_payment' => gmdate( 'Y-m-d H:i:s', strtotime( $response->nextDueDate . ' 12:00:00' ) ),
				);
				try {
					$this->subscription->update_dates( $dates );
				} catch ( Exception $error ) {
					$this->gateway->get_logger()->log( 'FAILED TO UPDATE SUBSCRIPTION NEXT PAYMENT DATE ' . $error->getMessage() );
				}
			}
		}

		$this->add_order_note( __( 'Payment confirmed.', 'woo-asaas' ) );
	}

	/**
	 * Process the payment object
	 *
	 * @throws Inconsistency_Data_Exception If order is paid, or the confirmed payment value
	 *                                       doesn't match the order total.
	 */
	private function process_payment(): void {
		$paid_statuses = wc_get_is_paid_statuses();
		if ( $this->order->has_status( $paid_statuses ) ) {
			throw new Inconsistency_Data_Exception( esc_html__( 'This order was already paid.', 'woo-asaas' ) );
		}

		$this->payment_reconciliation->reconcile();

		try {
			$this->order->payment_complete( $this->payment->id );
		} catch ( Exception $error ) {
			$this->throw_payment_complete_failure( $error );
		}
	}

	/**
	 * Throw the exception that explains a payment completion failure.
	 *
	 * The exception depends on the subscription state: when the subscription
	 * cannot be updated to active, the failure is an event inconsistency; when
	 * a subscription exists but the update isn't the cause, the failure is an
	 * event error; otherwise the order status couldn't be changed.
	 *
	 * @param Exception $error The original payment completion error.
	 * @return never This method never returns; it always throws.
	 * @throws Event_Exception Depending on the subscription state.
	 * @throws Exception If the order status couldn't be changed.
	 */
	private function throw_payment_complete_failure( Exception $error ) {
		if (
			null !== $this->subscription
			&& 'active' !== $this->subscription->get_status()
			&& false === $this->subscription->can_be_updated_to( 'active' )
		) {
			/* translators: %s: subscription status  */
			throw new Event_Exception( sprintf( esc_html__( 'Prevents 500 error from WooCommerce Subscriptions: unable to change subscription status to %s.', 'woo-asaas' ), 'active' ) );
		}

		if ( null !== $this->subscription ) {
			throw new Event_Exception(
				esc_html__( 'An error occurred', 'woo-asaas' ) . ': ' . esc_html( $error->getMessage() )
			);
		}

		throw new Exception( esc_html__( 'Unable to change the order status.', 'woo-asaas' ) );
	}

	/**
	 * Method used when payment is created.
	 *
	 * @throws Exception If API response is not OK.
	 */
	private function on_payment_created() {
		if ( isset( $this->data->payment->subscription ) ) {
			$api      = new Api( $this->gateway );
			$response = $api->subscriptions()->payments( $this->data->payment->subscription );

			if ( 200 !== $response->code ) {
				throw new Exception( sprintf( 'Error getting payments for a subscription in Asaas. Response HTTP status: %d', esc_html( $response->code ) ) );
			}

			$subscription_meta      = new Subscription_Meta( $this->subscription->get_id() );
			$first_payment_strategy = $subscription_meta->get_first_payment_strategy();
			$create_renewal         = true;
			if ( 1 === $response->get_json()->totalCount ) {
				if ( 0 !== $first_payment_strategy->processed_by_parent_order && false === $first_payment_strategy->included_in_single_transaction ) {
					$create_renewal = false;
				}
			}
			if ( false === $create_renewal ) {
				$transaction_order = $this->subscription->get_parent();
			} else {
				$transaction_order = wcs_create_renewal_order( $this->subscription );
				$transaction_order->set_payment_method( wc_get_payment_gateway_by_order( $this->subscription ) );
				if ( is_callable( array( $transaction_order, 'save' ) ) ) {
					$transaction_order->save();
				}
			}

			$this->gateway->add_payment_id_to_order( $this->data->payment->id, $transaction_order );
			$order_meta = new Order( $transaction_order->get_id() );
			if ( false === $order_meta->get_meta_data() ) {
				if ( 'asaas-credit-card' === $this->gateway->id ) {
					$order_meta->set_meta_data( $this->data->payment );
				} elseif ( 'asaas-ticket' === $this->gateway->id ) {
					if ( property_exists( $this->data->payment, 'installment' ) ) {
						$installments                      = $api->payments()->installment_list( $this->data->payment->installment );
						$this->data->payment->installments = $installments->get_json();
					}
					$order_meta->set_meta_data( $this->data->payment );
				} elseif ( 'asaas-pix' === $this->gateway->id ) {
					$pix_info_response = $api->payments()->pix_info( $this->data->payment->id );
					if ( is_a( $pix_info_response, Error_Response::class ) ) {
						wp_delete_post( $transaction_order->get_id(), true );
						throw new Exception( sprintf( 'Error getting PIX information for a payment subscription in Asaas. Response HTTP status: %d', esc_html( $pix_info_response->code ) ) );
					}

					$pix_info = $pix_info_response->get_json();
					$json     = $this->gateway->join_responses( $this->data->payment, $pix_info );
					$order_meta->set_meta_data( $json );
				}
			}

			$payment_data = array(
				'externalReference' => $transaction_order->get_id(),
			);
			$api->payments()->update( $this->data->payment->id, $payment_data );

			$due_date = date_i18n( get_option( 'date_format' ), strtotime( $this->data->payment->dueDate ) );
			/* translators: 1: The due date, 2: The subscription order id  */
			$note = sprintf( __( 'Payment created. Due date: %1$s / Subscription id: %2$s', 'woo-asaas' ), $due_date, $this->subscription->get_id() );
			$transaction_order->add_order_note( self::PREFIX_LOG . ' ' . $note );
		} else {
			$this->order->update_status( 'pending' );
			$this->add_order_note( __( 'Payment created.', 'woo-asaas' ) );
		}

		if ( ! isset( $this->data->payment->creditCard ) && isset( $this->data->payment->invoiceNumber ) ) {
			$payment_url    = sprintf( 'https://%1$s.asaas.com/payment/show/%2$s', ( false === strpos( $this->data->payment->invoiceUrl, 'sandbox' ) ? 'www' : 'sandbox' ), $this->data->payment->invoiceNumber );
			$payment_anchor = sprintf( '<a href="%1$s" target="_blank">%1$s</a>', $payment_url );

			/* translators: 1: The payment link  */
			$note = sprintf( __( 'Payment link: %1$s', 'woo-asaas' ), $payment_anchor );
			if ( ! isset( $transaction_order ) ) {
				$this->add_order_note( $note );
			} else {
				$transaction_order->add_order_note( self::PREFIX_LOG . ' ' . $note );
			}
		}
	}

	/**
	 * Method used when payment is deleted
	 *
	 * @throws Event_Exception If new subscription status is not allowed.
	 */
	private function on_payment_deleted() {
		$new_subscription_status = apply_filters( 'asaas_webhook_on_payment_deleted_subscription_new_status', 'on-hold', $this->order, $this->event );

		if ( isset( $this->data->payment->subscription ) ) {
			Subscription::get_instance()->update_status( $this->subscription, $new_subscription_status, $this->event );
		} else {
			Subscription::get_instance()->update_subscriptions_related_to_parent_order( $this->order, $new_subscription_status, $this->event );
		}

		try {
			$this->order->update_status( 'cancelled' );
		} catch ( Exception $error ) {
			throw new Event_Exception( esc_html__( 'PAYMENT_DELETED: prevents 500 error from WooCommerce Subscriptions: unable to change subscription/order status to cancelled.', 'woo-asaas' ) );
		}
		$this->add_order_note( __( 'Payment deleted.', 'woo-asaas' ) );
	}

	/**
	 * Method used when payment is overdue
	 *
	 * @throws Event_Exception If new subscription status is not allowed.
	 * @throws \Exception On transient failure deleting installment plan.
	 */
	private function on_payment_overdue() {
		$new_subscription_status = apply_filters( 'asaas_webhook_on_payment_overdue_subscription_new_status', 'on-hold', $this->order, $this->event );

		if ( isset( $this->data->payment->subscription ) ) {
			Subscription::get_instance()->update_status( $this->subscription, $new_subscription_status, $this->event );
		} else {
			Subscription::get_instance()->update_subscriptions_related_to_parent_order( $this->order, $new_subscription_status, $this->event );
		}

		try {
			$this->order->update_status( 'failed' );
		} catch ( Exception $error ) {
			throw new Event_Exception( esc_html__( 'PAYMENT_OVERDUE: prevents 500 error from WooCommerce Subscriptions: unable to change subscription/order status to failed.', 'woo-asaas' ) );
		}
		$this->add_order_note( __( 'Payment overdue.', 'woo-asaas' ) );

		do_action( 'asaas_webhook_payment_overdue', $this->data->payment, $this->order );
	}

	/**
	 * Method used when payment is refunded
	 *
	 * @throws Exception If API response is not OK.
	 * @throws Event_Exception If new subscription status is not allowed.
	 */
	private function on_payment_refunded() {
		$new_subscription_status = apply_filters( 'asaas_webhook_on_payment_refunded_subscription_new_status', 'on-hold', $this->order, $this->event );

		if ( isset( $this->data->payment->subscription ) ) {
			$api      = new Api( $this->gateway );
			$response = $api->subscriptions()->payments( $this->data->payment->subscription );

			if ( 200 !== $response->code ) {
				throw new Exception( sprintf( 'Error getting payments for a subscription in Asaas. Response HTTP status: %d', esc_html( $response->code ) ) );
			}

			if ( $this->data->payment->id === $response->get_json()->data[0]->id ) {
				Subscription::get_instance()->update_status( $this->subscription, $new_subscription_status, $this->event );
			}
		} else {
			Subscription::get_instance()->update_subscriptions_related_to_parent_order( $this->order, $new_subscription_status, $this->event );
		}

		try {
			$this->order->update_status( 'refunded' );
		} catch ( Exception $error ) {
			throw new Event_Exception( esc_html__( 'PAYMENT_REFUNDED: prevents 500 error from WooCommerce Subscriptions: unable to change subscription/order status to refunded.', 'woo-asaas' ) );
		}
		$this->add_order_note( 'Payment refunded.', 'woo-asaas' );
	}

	/**
	 * Method used when payment is restored
	 *
	 * @throws Event_Exception If new subscription status is not allowed.
	 */
	private function on_payment_restored() {
		$new_subscription_status = apply_filters( 'asaas_webhook_on_payment_restored_subscription_new_status', 'on-hold', $this->order, $this->event );

		if ( isset( $this->data->payment->subscription ) ) {
			Subscription::get_instance()->update_status( $this->subscription, $new_subscription_status, $this->event );
		} else {
			Subscription::get_instance()->update_subscriptions_related_to_parent_order( $this->order, $new_subscription_status, $this->event );
		}

		try {
			$this->order->update_status( 'pending' );
		} catch ( Exception $error ) {
			throw new Event_Exception( esc_html__( 'PAYMENT_RESTORED: prevents 500 error from WooCommerce Subscriptions: unable to change subscription/order status to pending.', 'woo-asaas' ) );
		}
		$this->add_order_note( 'Payment restored.', 'woo-asaas' );
	}

	/**
	 * Method used when payment is updated
	 */
	private function on_payment_updated() {
		if ( ! in_array( $this->payment->status, array( 'RECEIVED', 'CONFIRMED' ), true ) ) {
			$this->order->update_status( 'pending' );
		}
		$this->add_order_note( 'Payment updated', 'woo-asaas' );
	}

	/**
	 * Add note
	 *
	 * @param String $note note description.
	 */
	private function add_order_note( $note ) {
		$this->order->add_order_note( self::PREFIX_LOG . ' ' . $note );
	}
}
