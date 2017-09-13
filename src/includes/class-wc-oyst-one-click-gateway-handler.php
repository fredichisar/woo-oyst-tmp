<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
//use Oyst\Classes\OystPrice;

include_once( dirname( __FILE__ ) . '/class-wc-oyst-one-click-response.php' );

/**
 * Handles Responses.
 */
class WC_Oyst_One_Click_Gateway_Handler extends WC_Oyst_One_Click_Response {


	/**
	 * Constructor.
	 *
	 * @param bool $sandbox
	 */
	public function __construct( $sandbox = false ) {
		add_action( 'woocommerce_api_wc_oyst_one_click', array( $this, 'check_response' ) );
		add_action( 'valid-oyst-one-click-request', array( $this, 'valid_response' ) );
	}

	/**
	 * Check for 1-Click IPN Response.
	 */
	public function check_response() {

		// To test in live mode ($_post)
		$post = ( file_get_contents( 'php://input' ) );

		if ( ! empty( $post ) ) {
			do_action( 'valid-oyst-one-click-request', $post );
			exit;
		}
		WC_Oyst_One_Click::log( 'Retour One Click vide', 'error' );
		wp_die( 'Erreur de requête One Click', '1-Click', array( 'response' => 500 ) );

	}

	/**
	 * Valid response
	 *
	 * @param $post
	 */
	public function valid_response( $post ) {


		$decoded_post = json_decode( $post );
		$event        = $decoded_post->event;
		$order_id     = $decoded_post->data->order_id;

		if ( ! empty( $event ) ) {

			switch ( $event ) {
				case 'order.v2.new':
					include_once( dirname( __FILE__ ) . '/class-wc-oyst-one-click-get-order.php' );
					$get_order = new WC_Oyst_One_Click_Get_Order();
					$get_order->get_one_click_order( $order_id );
					exit;
					break;
			}
		}

	}


}
