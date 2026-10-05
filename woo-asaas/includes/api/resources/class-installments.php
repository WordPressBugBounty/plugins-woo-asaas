<?php
/**
 * API '/installments' resource.
 *
 * @package WooAsaas
 */

namespace WC_Asaas\Api\Resources;

use WC_Asaas\Api\Client\Client;
use WC_Asaas\Api\Response\Response;

/**
 * API '/installments' resource.
 */
class Installments extends Resource {

	/**
	 * Resource path.
	 *
	 * @var string
	 */
	const PATH = '/installments/';

	/**
	 * Delete all payments of an installment plan.
	 *
	 * @param string $id Installment id.
	 * @return Response The HTTP response.
	 */
	public function delete( $id ) {
		$client = new Client( $this->gateway );
		return $client->delete( self::PATH . $id . '/payments' );
	}
}
