<?php

/*
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
*/
use Oyst\Api\OystOneClickApi;
use Oyst\Classes\OystCategory;
use Oyst\Classes\OystPrice;
use Oyst\Classes\OystProduct;
use Oyst\Classes\OystSize;
use Oyst\Classes\OneClickOrderParams;

class WC_Oyst_One_Click_Request {


	/**
	 * Endpoint for requests from FreePay.
	 * @var string
	 */
	protected $notify_url;

	/**
	 * Constructor.
	 *
	 */
	public function __construct() {
		$this->notify_url = WC()->api_request_url( 'wc_oyst_one_click' );
	}

	/**
	 * Get the FreePay request URL for an order.
	 *
	 * @param  string $product_reference
	 * @param  int $quantity
	 * @param  string $notify_url
	 *
	 * @return string | null
	 */
	public function get_request_url( $product_reference, $quantity = 1, $variation_id = null, $notify_url ) {

		include_once( dirname( __FILE__ ) . '/class-wc-oyst-api.php' );


		$oystClient = WC_Oyst_API::get_one_click_api();
		if ( ! empty( $notify_url ) ) {
			$notification = $notify_url;
		} else {
			$notification = $this->notify_url;
		}
		$oystClient->setNotifyUrl( $notification );
		$product_type_id = ( ! empty( $variation_id ) ) ? $variation_id : $product_reference;
		$productRef      = $product_type_id;
		$product         = $this->get_one_click_product( $product_type_id );
		$orderParams     = $this->get_one_click_order_param( $product_type_id );

		//$context = $this->get_one_click_context( $product_id );; // information User

		$result = $oystClient->authorizeOrder(
			$productRef,
			$quantity,
			$variationRef = null,
			$user = null,
			$version = 2,
			$product,
			$orderParams
		);

		if ( isset( $result['url'] ) && ! empty( $result['url'] ) ) {
			return $result['url'];
		}

		return null;
	}

	/**
	 * Get all data for a product from product ID
	 *
	 * @param $product_ref
	 *
	 * @return  OystProduct
	 */
	public function get_one_click_product( $product_type_id ) {

		$product_obj = wc_get_product( $product_type_id );

		$product_price    = $product_obj->get_price();
		$categories       = $product_obj->get_category_ids();
		$length           = $product_obj->get_length();
		$height           = $product_obj->get_height();
		$width            = $product_obj->get_width();
		$tags             = $product_obj->get_tag_ids();
		$related_products = $product_obj->get_cross_sell_ids();

		/* Use API */
		$product = new OystProduct();
		$product->setAmountIncludingTax( $this->get_one_click_price( $product_price ) ); // OK
		$product->setAvailableQuantity( $product_obj->get_stock_quantity() ); // OK
		$product->setCategories( $this->get_one_click_categories( $categories ) ); // OK
		$product->setDescription( $product_obj->get_description() ); // OK
		$product->setImages( $this->get_one_click_images( $product_obj ) );
		$product->setInformation( '' ); // OK
		$product->setActive( $product_obj->is_purchasable() ); // OK
		$product->setDiscounted( $product_obj->is_on_sale() ); // OK
		$product->setMaterialized( $product_obj->is_virtual() ); // OK
		$product->setRef( $product_type_id ); // OK
		$product->setRelatedProducts( $this->get_related_products( $related_products ) ); // OK
		$product->setShortDescription( $product_obj->get_short_description() ); // OK
		$product->setSize( $this->get_one_click_size( $height, $length, $width ) ); // OK
		$product->setTags( $this->get_one_click_tags( $tags ) ); // Ok
		$product->setWeight( $product_obj->get_weight() ); // OK
		$product->setTitle( $product_obj->get_title() );  // OK
		$product->setUrl( get_permalink( $product_type_id ) ); // OK

		/** Non- native fields **/
		$product->setEan( '' );
		$product->setIsbn( '' );
		$product->setManufacturer( '' );
		$product->setUpc( '' );


		return $product;
	}

	/**
	 * Get formatted price
	 *
	 * @param $product_price
	 *
	 * @return OystPrice
	 */
	public function get_one_click_price( $product_price ) {
		//$product_price = round( $product_price, 2 ) * 100; // In cents
		$total = new OystPrice( $product_price, 'EUR' );

		return $total;
	}

	/**
	 * Get formatted categories
	 *
	 * @param $categories
	 *
	 * @return array of OystCategory Object
	 */
	public function get_one_click_categories( $categories ) {

		$categories_formatted = array();
		$count_categories     = 0;

		foreach ( $categories as $cat ) {
			$is_main      = ( $count_categories === 0 ) ? true : false;
			$title        = get_cat_name( $cat );
			$oystCategory = new OystCategory( $cat, $title, $is_main );
			array_push( $categories_formatted, $oystCategory );
			$count_categories ++;
		}

		return $categories_formatted;
	}

	/**
	 * Get formatted images url
	 *
	 * @param WC_Product $product_obj
	 *
	 * @return array $images
	 */
	public function get_one_click_images( $product_obj ) {
		$images = array();

		$image_url = wp_get_attachment_image_src( get_post_thumbnail_id( $product_obj->get_id() ),
			'single-post-thumbnail' );
		if ( ! empty( $image_url[0] ) ) {
			array_push( $images, $image_url[0] );
		};
		$attachment_ids = $product_obj->get_gallery_image_ids();

		if ( $product_obj->is_type( 'variation' ) && empty( $attachment_ids ) ) {
			$parent_product = wc_get_product( $product_obj->get_parent_id() );
			$attachment_ids = $parent_product->get_gallery_image_ids();
		}

		foreach ( $attachment_ids as $attachment_id ) {
			$image_url = wp_get_attachment_url( $attachment_id );
			array_push( $images, $image_url );
		}

		return $images;
	}

	/**
	 * Get formatted related products
	 *
	 * @param array $related_products
	 *
	 * @return array $related_products_formatted
	 */
	public function get_related_products( $related_products ) {
		$related_products_formatted = array();
		foreach ( $related_products as $related_product ) {

			$crosssell_product      = wc_get_product( $related_product );
			$crosssell_product_name = $crosssell_product->name;
			array_push( $related_products_formatted, $crosssell_product_name );
		}

		return $related_products_formatted;
	}

	/**
	 * Get formatted size
	 *
	 * @param $height
	 * @param $length
	 * @param $width
	 *
	 * @return OystSize
	 */
	public function get_one_click_size( $height, $length, $width ) {
		$size = new OystSize( $height, $length, $width );

		return $size;
	}

	/**
	 * Get formatted tags
	 *
	 * @param $tags
	 *
	 * @return array
	 */
	public function get_one_click_tags( $tags ) {
		$tags_formatted = array();
		foreach ( $tags as $tag ) {
			$tag_object = get_tag( $tag );
			$tag_name   = $tag_object->name;
			array_push( $tags_formatted, $tag_name );
		}

		return $tags_formatted;
	}



	/**
	 * Get is Materialized / virtual
	 *
	 * @param $product_type_id
	 *
	 * @return bool
	 */
	public function get_is_materialized( $product_type_id ) {
		$product_obj = wc_get_product( $product_type_id );
		if ( $product_obj->is_virtual() ) {
			return false;
		} else {
			return true;
		}

	}

	public function get_one_click_order_param( $product_type_id ) {
		$isMaterialized = $this->get_is_materialized( $product_type_id );
		$order_param = new OneClickOrderParams;
		$order_param->setIsMaterialized($isMaterialized);

		return $order_param;
	}

	/**
	 * Get User data of current logged in user
	 *
	 * @return string
	 */
	public function get_one_click_context() {
		if ( is_user_logged_in() ) {
			$user_id = get_current_user_id();
		}

		return 'data';
	}


///// ***** CONTEXT (USER)  ****** //////

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
	 * Get Customer shipping informations
	 *
	 * @param WC_Order $order
	 *
	 * @return array
	 */
	public function get_freepay_user_shipping( $order ) {

		$user_shipping = array();

		$user_shipping[0] = array(
			'first_name' => $order->get_shipping_first_name(),
			'last_name'  => $order->get_shipping_last_name(),
			'country'    => $order->get_shipping_country(),
			'city'       => $order->get_shipping_city(),
			'label'      => $order->get_shipping_city(),
			'postcode'   => $order->get_shipping_postcode(),
			'street'     => $order->get_shipping_address_1() . ' ' . $order->get_shipping_address_2(),
		);

		return $user_shipping;
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

}