<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


/**
 * Handles Responses.
 */
class WC_Oyst_One_Click_Get_Order {

	/** @var string Oyst Order ID */
	public $oyst_order_id;


	/**
	 * Get Oyst order by order_id
	 *
	 * @param string $oyst_order_id
	 *
	 * return object $oyst_order
	 */
	public function get_one_click_order( $oyst_order_id ) {
		// api order
		include_once( dirname( __FILE__ ) . '/class-wc-oyst-api.php' );

		$oystClient = WC_Oyst_API::get_order_api();
		$oystClient->setNotifyUrl( 'http://a9395b77.ngrok.io' );

		$oyst_order = $oystClient->getOrder( $oyst_order_id );

		if ( isset( $oyst_order ) && ! empty( $oyst_order ) ) {
			$this->create_one_click_order( $oyst_order );
		}
	}

	public function create_one_click_order( $oyst_order ) {

		$oyst_id        = $oyst_order['id'];
		$status         = $oyst_order['current_status'];
		$transaction_id = $oyst_order['transaction']['id'];
		$carrier        = $oyst_order['shipment']['carrier']['id'];

		// Product
		$items = $oyst_order['items'];

		// Shipment
		$shipment_amount = $oyst_order['shipment']['amount']['value'];
		$shipment_name   = $oyst_order['shipment']['carrier']['name'];
		$shipment_id     = $oyst_order['shipment']['carrier']['id'];

		$shipping_rate = $this->one_click_add_shipping_to_order( $shipment_amount, $shipment_name, $shipment_id );

		// User
		$user_id         = $oyst_order['user']['id'];
		$user_email      = $oyst_order['user']['email'];
		$user_phone      = $oyst_order['user']['phone'];
		$user_last_name  = $oyst_order['user']['last_name'];
		$user_first_name = $oyst_order['user']['first_name'];

		// User address
		$user_city               = $oyst_order['user']['address']['city'];
		$user_label              = $oyst_order['user']['address']['label'];
		$user_street             = $oyst_order['user']['address']['street'];
		$user_country            = $oyst_order['user']['address']['country'];
		$user_postcode           = $oyst_order['user']['address']['postcode'];
		$user_address_last_name  = $oyst_order['user']['address']['last_name'];
		$user_address_first_name = $oyst_order['user']['address']['first_name'];


		// Check if the user already exists.
		$userId = email_exists( $user_email );

		// Create a new user and notify him.
		if ( ! username_exists( $user_email ) ) {
			$password = wp_generate_password( 8, false );
			$userId   = wc_create_new_customer( $user_email, '', $password );
		}

		// If we can't find/create an user, it logs an error.
		if ( is_wp_error( $userId ) ) {
			error_log( "Failed creating user {$user_email}." );
		}

		// Prepare the customer address for the order.
		$address = array(
			'first_name' => $user_first_name,
			'last_name'  => $user_last_name,
			'company'    => '',
			'email'      => $user_email,
			'phone'      => $user_phone,
			'address_1'  => $user_street,
			'address_2'  => '',
			'city'       => $user_city,
			'state'      => '',
			'postcode'   => $user_postcode,
			'country'    => $user_country,
		);


		// Create a new order, and set the billing and shipping addresses.
		$order = wc_create_order( [ 'customer_id' => $userId ] );
		$order->set_address( $address, 'billing' );
		$order->set_address( $address, 'shipping' );

		foreach ( $items as $item ) {

			$product_reference = $item['product_reference'];
			$quantity          = $item['quantity'];


			if ( $product_reference ) {
				// Fetch the product from the database.
				$product = wc_get_product( $product_reference );
				$type    = $product->get_type();


				// check is simple product or variation product
				if ( 'simple' === $type ) {
						$order->add_product( $product, $quantity );
				} else {
					$product_variation = new WC_Product_Variation( $product_reference );
					$args              = array();
					foreach ( $product_variation->get_variation_attributes() as $attribute => $attribute_value ) {
						$args['variation'][ $attribute ] = $attribute_value;
					}
					$order->add_product( $product_variation, $item['quantity'], $args );
				}


			}
		}

		// Add shipping to order total
		$order->add_shipping( $shipping_rate );

		// Persist the order.
		$order->calculate_totals(false);

		// Add payment method
		$order->set_payment_method( 'one_click' );

		// Confirm the order payment with transaction_id and add a note to it.
		$order->add_order_note( 'Commande créé automatiquement par Oyst 1-click.' );
		$order->payment_complete( $transaction_id );

		// Add post meta to $order
		add_post_meta( $order->get_id(), '_one_click_event_code', $status );
		add_post_meta( $order->get_id(), '_one_click_payment_id', $oyst_id );

		// Auto Accept order from Oyst 1-Click
		$this->one_click_auto_accepted($order->get_id(), $oyst_id );

	}

	/**
	 * Get Shipping rate
	 *
	 * @param $shipping_amount
	 * @param $shipping_name
	 * @param $shipping_method
	 *
	 * @return WC_Shipping_Rate
	 */
	public function one_click_add_shipping_to_order( $shipping_amount, $shipping_name, $shipping_method ) {
		$amount        = number_format( ( $shipping_amount / 100 ), 2, ',', ' ' );
		$tax           = array(
			'total' => '0',
		);
		$shipping_rate = new WC_Shipping_Rate( '', $shipping_name,
			$amount, $tax,
			$shipping_method );

		return $shipping_rate;

	}

	public function one_click_auto_accepted( $order_id , $oyst_id) {
		include_once( dirname( __FILE__ ) . '/class-wc-oyst-api.php' );

		$oystClient = WC_Oyst_API::get_order_api();
		$oystClient->setNotifyUrl( 'http://a9395b77.ngrok.io' );
		$oystClient->accept( $oyst_id );
		update_post_meta( $order_id,
			'_one_click_event_code',
			'accepted');
	}


}
