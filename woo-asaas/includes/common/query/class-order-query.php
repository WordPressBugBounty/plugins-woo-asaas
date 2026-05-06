<?php
/**
 * Order query helper class
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Common\Query;

/**
 * Query helper for orders with meta_query support in both HPOS and CPT modes
 */
class Order_Query {

	/**
	 * Get orders with meta_query support for both HPOS and CPT modes
	 *
	 * @param array $args Arguments to pass to wc_get_orders().
	 * @return array Array of WC_Order objects or IDs depending on 'return' argument.
	 */
	public function get_orders_with_meta_query( array $args ) : array {
		if ( wcs_is_custom_order_tables_usage_enabled() ) {
			return wc_get_orders( $args );
		}

		// phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_query
		$meta_query = $args['meta_query'] ?? array();
		unset( $args['meta_query'] );

		$meta_handler = $this->meta_query_handler( $meta_query );

		add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $meta_handler, 10, 2 );
		$orders = wc_get_orders( $args );
		remove_filter( 'woocommerce_order_data_store_cpt_get_orders_query', $meta_handler );

		return $orders;
	}

	/**
	 * Get the meta query handler closure for CPT mode
	 *
	 * @param array $meta_query The meta query arguments.
	 * @return callable The filter callback.
	 */
	private function meta_query_handler( array $meta_query ) : callable {
		return function( $query ) use ( $meta_query ) {
			if ( [] === $meta_query ) {
				return $query;
			}

			// phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_query
			if ( ! isset( $query['meta_query'] ) ) {
				// phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_query
				$query['meta_query'] = $meta_query;

				return $query;
			}

			// phpcs:ignore WordPress.VIP.SlowDBQuery.slow_db_query_meta_query
			$query['meta_query'] = array_merge( $query['meta_query'], $meta_query );

			return $query;
		};
	}
}
