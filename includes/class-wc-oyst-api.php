<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Oyst\Api\OystApiClientFactory;
use Oyst\Classes\OystUserAgent;


class WC_Oyst_API {

	/** @var string Type */
	public static $api_type;

	/** @var string API Key prod */
	public static $api_prod_key;

	/** @var string API Key preprod */
	public static $api_preprod_key;

	/** @var string API Key custom */
	public static $api_custom_key;

	/** @var string API Mode */
	public static $api_mode;

	/** @var string API Custom Url */
	public static $api_custom_url;

	/** @var string userAgent */
	public static $user_agent;


	/**
	 * Init Oyst Api Client
	 *
	 * @param string $api_type
	 *
	 * @return \Oyst\Api\AbstractOystApiClient
	 */
	public static function get_oyst_client( $api_type ) {

		$apiKey    = self::get_api_key();
		$userAgent = self::get_user_agent();
		$env       = self::$api_mode;
		$url       = self::get_custom_url();

		$oystClient = OystApiClientFactory::getClient( $api_type, $apiKey, $userAgent, $env, $url );

		return $oystClient;
	}

	/**
	 * Get Payment API
	 *
	 * @return \Oyst\Api\AbstractOystApiClient
	 */
	public static function get_payment_api() {
		$api_type = OystApiClientFactory::ENTITY_PAYMENT;

		return self::get_oyst_client( $api_type );
	}

	/**
	 * Get One Click API
	 *
	 * @return \Oyst\Api\AbstractOystApiClient
	 */
	public static function get_one_click_api() {
		$api_type = OystApiClientFactory::ENTITY_ONECLICK;

		return self::get_oyst_client( $api_type );
	}

	/**
	 * Get Order API
	 *
	 * @return \Oyst\Api\AbstractOystApiClient
	 */
	public static function get_order_api() {
		$api_type = OystApiClientFactory::ENTITY_ORDER;

		return self::get_oyst_client( $api_type );
	}
	/**
	 * Get Catalog API
	 *
	 * @return \Oyst\Api\AbstractOystApiClient
	 */
	public static function get_catalog_api() {
		$api_type = OystApiClientFactory::ENTITY_CATALOG;

		return self::get_oyst_client( $api_type );
	}

	/**
	 * Get API Key
	 *
	 * @return string
	 */
	public static function get_api_key() {

		switch (self::$api_mode) {
			case 'prod':
				return self::$api_prod_key ;
				break;
			case 'preprod':
				return self::$api_preprod_key;
				break;
			case 'custom':
				return self::$api_custom_key;
				break;
		}

	}

	/**
	 * Format $userAgent
	 *
	 * @return Oyst\Classes\OystUserAgent
	 */
	public static function get_user_agent() {

		$userAgent = new OystUserAgent(
			'WooCommerce',
			WC_OYST_VERSION,
			( empty( WC()->version ) ) ? 'undefined' : WC()->version,
			'PHP',
			phpversion() );

		return $userAgent;
	}

	/**
	 * Get Custom Url
	 *
	 * @return string
	 */
	public static function get_custom_url() {
		$customUrl = ( 'custom' === self::$api_mode && ! empty( self::$api_custom_url ) ) ? self::$api_custom_url : null;

		return $customUrl;
	}


}
