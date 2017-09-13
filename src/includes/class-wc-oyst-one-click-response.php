<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles Responses.
 */
abstract class WC_Oyst_One_Click_Response {


	/**
	 * Get the Order from FreePay
	 *
	 *
	 * @return bool | WC_Order object
	 */
	protected function get_freepay_order() {

		/*
		//Decode raw format to json to get the Order_id
		if ( ( $freepay_data = json_decode( $data ) ) ) {

			$order_id = $freepay_data->notification->order_id;
		} else {

			WC_Oyst_Freepay::log( "L'ID de la commande n'a pas été trouvé dans l'objet 'FreePay Data'.",
				'error' );

			return false;
		}

		$order = wc_get_order( $order_id );

		if ( ! $order ) {
			WC_Oyst_Freepay::log( 'La commande pas été trouvé grâce à son ID depuis order_id : ' . $order_id . '',
				'error' );

			return false;
		}

		return $order;
		*/

		return true;
	}
}
