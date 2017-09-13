<?php
/*
 * Plugin Name: WooCommerce OYST
 * Plugin URI:
 * Description: OYST plugin for woocommerce
 * Author: O Y S T
 * Author URI: http://oyst.com/
 * Version: 1.0.0
 * Text Domain: woocommerce-oyst
 * Domain Path: /languages
 * License:
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_OYST_VERSION', '1.0.0' );
define( 'WC_OYST_MIN_PHP_VER', '5.6.0' );
define( 'WC_OYST_MIN_WC_VER', '3.0.0' );
define( 'WC_OYST_PLUGIN_URL',
	untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_OYST_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );

if ( ! class_exists( 'WC_Oyst' ) ) :

	class WC_Oyst {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;
		/** @noinspection PhpUndefinedClassInspection */


		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {
		}


		/**
		 * Notices (array)
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}
			include_once( dirname( __FILE__ ) . '/lib/oyst-php-master/vendor/autoload.php' );
			// Init the gateway itself
			$this->init_gateways();
			$this->init_one_click();
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_oyst_script_enqueue' ) );
		}

		/**
		 * Admin script enqueue
		 */
		public function admin_oyst_script_enqueue() {
			// Change JS (#div)
			wp_enqueue_script( 'woocommerce-oyst',
				WC_OYST_PLUGIN_URL . '/admin/js/woocommerce-oyst.js',
				array( 'jquery' ),
				'1.0.0',
				true );
		}


		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
		 *
		 * @param string $slug
		 * @param string $class
		 * @param string $message
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * Check environment after activation
		 */
		public function check_environment() {
			$environment_warning = self::get_environment_warning();
			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		static function get_environment_warning() {
			if ( version_compare( phpversion(), WC_OYST_MIN_PHP_VER, '<' ) ) {
				$message = __( 'WooCommerce OYST - The minimum PHP version required for this plugin is %1$s. You are running %2$s.',
					'woocommerce-oyst' );

				return sprintf( $message, WC_OYST_MIN_PHP_VER, phpversion() );
			}
			if ( ! class_exists( 'WooCommerce' ) ) {
				return __( 'WooCommerce OYST requires WooCommerce to be activated to work.', 'woocommerce-oyst' );
			}
			if ( version_compare( WC()->version, WC_OYST_MIN_WC_VER, '<' ) ) {
				$message = __( 'WooCommerce OYST - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.',
					'woocommerce-oyst' );

				return sprintf( $message, WC_OYST_MIN_WC_VER, WC()->version );
			}

			return false;
		}

		/**
		 * Adds plugin action links
		 *
		 * @param string $links
		 *
		 * @return array $links
		 */

		// May be remove or change to main plugin file
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();
			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-oyst' ) . '</a>',
			);

			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @since 1.0.0
		 *
		 * @return string
		 */
		public function get_setting_link() {
			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;
			$section_slug      = $use_id_as_section ? 'freepay' : strtolower( 'WC_Gateway_Freepay' );

			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Add descriptions links
		 *
		 * @param $links
		 * @param $file
		 *
		 * @return array
		 */
		public function plugin_row_meta( $links, $file ) {
			if ( strpos( $file, 'woocommerce-oyst.php' ) !== false ) {
				$faq_link    = 'https://free-pay.zendesk.com/hc/fr';
				$signup_link = 'https://admin.free-pay.com/login';
				$new_links   = array(
					'faq'    => '<a href="' . $faq_link . '" target="_blank">' . __( 'FAQ',
							'woocommerce-oyst' ) . '</a>',
					'signup' => '<a href="' . $signup_link . '" target="_blank">' . __( 'S\'inscrire',
							'woocommerce-oyst' ) . '</a>',
				);

				$links = array_merge( $links, $new_links );
			}

			return $links;
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway.
		 */
		public function init_gateways() {
			if ( class_exists( 'WC_Payment_Gateway' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-oyst-freepay.php' );
			}

			load_plugin_textdomain( 'woocommerce-oyst', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );
		}

		/**
		 * Initialize 1-Click
		 */
		public function init_one_click(){
			include_once( dirname( __FILE__ ) . '/includes/class-wc-oyst-one-click.php' );
			new WC_Oyst_One_Click();
		}
		/**
		 * Add the gateways to WooCommerce
		 *
		 * @param array $methods
		 *
		 * @return array $methods
		 */
		public function add_gateways( $methods ) {
			$methods[] = 'WC_Oyst_Freepay';
			//$methods[] = 'WC_Oyst_One_Click';

			return $methods;
		}

	}

	$GLOBALS['wc_oyst'] = WC_Oyst::get_instance();
endif;
