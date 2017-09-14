<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WC_Oyst_Freepay_Request {

	/**
	 * Pointer to gateway making the request.
	 * @var WC_Oyst_Freepay
	 */
	protected $gateway;

	/**
	 * Endpoint for requests from FreePay.
	 * @var string
	 */
	protected $notify_url;

	/**
	 * Constructor.
	 *
	 * @param WC_Oyst_Freepay $gateway
	 */
	public function __construct( $gateway ) {
		$this->gateway    = $gateway;
		$this->notify_url = WC()->api_request_url( 'wc_oyst_freepay' );
	}

	/**
	 * Get the FreePay request URL for an order.
	 *
	 * @param  WC_Order $order
	 * @param  bool $sandbox
	 * @param  string $notify_url
	 *
	 * @return string
	 */
	public function get_request_url( $order, $sandbox = false, $notify_url ) {

		$oystClient = WC_Oyst_API::get_payment_api();
		$amount     = $this->get_freepay_total_amount( $order );
		$currency   = $this->get_freepay_currency( $order );
		$cartId     = $this->get_freepay_cart_id( $order );
		$urls       = $this->get_freepay_url( $order, $notify_url );
		$is3d       = false;
		$user       = $this->get_freepay_user( $order );
		$result     = $oystClient->payment( $amount, $currency, $cartId, $urls, $is3d, $user );

		if ( isset( $result['url'] ) && ! empty( $result['url'] ) ) {
			return $result['url'];
		}

		WC_Oyst_Freepay::log( 'Arguments de la requête FreePay pour la commande ' . $order->get_order_number() . ': ' . wc_print_r( $result,
				true ) );
	}

	/**
	 * Get total in FreePay format
	 *
	 * @param WC_Order $order
	 *
	 * @return mixed $freepay_total
	 */
	public function get_freepay_total_amount( $order ) {
		$tmp_total     = $order->get_total();
		$freepay_total = round( $tmp_total, 2 ) * 100; // In cents

		return $freepay_total;
	}


	/**
	 * Get currency
	 *
	 * @param WC_Order $order
	 *
	 * @return string
	 */
	public function get_freepay_currency( $order ) {
		return $order->get_currency();
	}

	/**
	 * Get Order ID
	 *
	 * @param WC_Order $order
	 *
	 * @return mixed
	 */
	public function get_freepay_cart_id( $order ) {
		return $order->get_id();
	}

	/**
	 * Get URL for callback
	 *
	 * @param WC_Order $order
	 * @param string $notify_url
	 *
	 * @return array
	 */
	public function get_freepay_url( $order, $notify_url ) {

		if ( ! empty( $notify_url ) ) {
			$notification = $notify_url;
		} else {
			$notification = $this->notify_url;
		}
		$errorUrl   = esc_url_raw( $order->get_cancel_order_url_raw() );
		$successUrl = esc_url_raw( $this->gateway->get_return_url( $order ) );

		$urls = array(
			'notification' => $notification,
			'cancel'       => $errorUrl,
			'error'        => $errorUrl,
			'return'       => $successUrl,
		);

		return $urls;
	}


	/**
	 * Get Customer informations
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_freepay_user( $order ) {
		$user = array(
			'additional_data'   => array(
				'customer' => $order->get_billing_first_name(),
			),
			'addresses'         => $this->get_freepay_user_shipping( $order ),
			'billing_addresses' => $this->get_freepay_user_billing( $order ),
			'email'             => $order->get_billing_email(),
			'first_name'        => $order->get_billing_first_name(),
			'language'          => 'FR',
			'last_name'         => $order->get_billing_last_name(),
			'phone'             => $order->get_billing_phone(),
		);

		return $user;
	}

	/**
	 * Get Customer billing informations
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_freepay_user_billing( $order ) {
		$user_billing = array();

		$user_billing[0] = array(
			'first_name' => $order->get_billing_first_name(),
			'last_name'  => $order->get_billing_last_name(),
			'country'    => $order->get_billing_country(),
			'city'       => $order->get_billing_city(),
			'label'      => $order->get_billing_city(),
			'postcode'   => $order->get_billing_postcode(),
			'street'     => $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
		);

		return $user_billing;
	}

	/**
	 * Get Customer shipping informations
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_freepay_user_shipping( $order ) {

		$user_shipping = array();

		$user_shipping[0] = array(
			'first_name' => !empty($order->get_shipping_first_name()) ? $order->get_shipping_first_name() : $order->get_billing_first_name(),
			'last_name' => !empty($order->get_shipping_last_name()) ? $order->get_shipping_last_name() : $order->get_billing_last_name(),
			'country' => !empty($order->get_shipping_country()) ? $order->get_shipping_country() : $order->get_billing_country(),
			'city' => !empty($order->get_shipping_city()) ? $order->get_shipping_city() : $order->get_billing_city(),
			'label' => !empty($order->get_shipping_city()) ? $order->get_shipping_city() : $order->get_billing_city(),
			'postcode' => !empty($order->get_shipping_postcode()) ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
			'street' => !empty($order->get_shipping_address_1()) ? $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2() : $order->get_billing_address_1() . ' ' . $order->get_billing_address_2(),
		);

		return $user_shipping;
	}

}