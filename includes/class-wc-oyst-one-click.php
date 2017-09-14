<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Oyst\Api\OystApiClientFactory;
use Oyst\Classes\OystPrice;


/**
 * WC_Oyst_One_Click class.
 *
 */
if ( ! class_exists( 'WC_Oyst_One_Click' ) ) {
	class WC_Oyst_One_Click {

		/** @var bool Whether or not logging is enabled */
		public static $log_enabled = false;

		/** @var WC_Logger Logger instance */
		public static $log = false;

		/**
		 * Environment mode
		 *
		 * @var string
		 */
		public $mode;

		/**
		 * Production mode API Key
		 *
		 * @var string
		 */
		public $prod_key;

		/**
		 * Pre-production mode API Key
		 *
		 * @var string
		 */
		public $preprod_key;

		/**
		 * Custom mode API Key
		 *
		 * @var string
		 */
		public $custom_key;

		/**
		 * Custom URL
		 *
		 * @var string
		 */
		public $custom_url;

		/**
		 * Notification URL (Callback)
		 *
		 * @var string
		 */
		public $notification_url;

		/**
		 * Enable log
		 *
		 * @var bool | string
		 */
		public $debug;

		/**
		 * Shipping data
		 *
		 * @var mixed|void
		 */
		public $shipping;
		/**
		 * Default location for displaying 1-Click button
		 *
		 * @var string
		 */
		public $button_order = 'woocommerce_before_add_to_cart_button';

		/**
		 * General options
		 *
		 * @var array
		 */
		public $options_one_click;

		/**
		 * Shipment options
		 *
		 * @var array
		 */
		public $options_shipment;

		/**
		 * 1-Click customization
		 *
		 * @var array
		 */
		public $options_customization;

		/**
		 * Constructor
		 */
		public function __construct() {
			$this->id = 'one_click';
			$this->init_form_fields();

			// Get setting values.
			$this->options_one_click = get_option( 'options_one_click' );
			$this->enabled           = isset( $this->options_one_click['enable_one_click'] ) ? $this->options_one_click['enable_one_click'] : false;
			$this->mode              = isset( $this->options_one_click['mode_one_click'] ) ? $this->options_one_click['mode_one_click'] : '';
			$this->prod_key          = isset( $this->options_one_click['prod_key_one_click'] ) ? $this->options_one_click['prod_key_one_click'] : '';
			$this->preprod_key       = isset( $this->options_one_click['preprod_key_one_click'] ) ? $this->options_one_click['preprod_key_one_click'] : '';
			$this->custom_key        = isset( $this->options_one_click['custom_key_one_click'] ) ? $this->options_one_click['custom_key_one_click'] : '';
			$this->custom_url        = isset( $this->options_one_click['custom_url_one_click'] ) ? $this->options_one_click['custom_url_one_click'] : '';
			$this->notification_url  = isset( $this->options_one_click['notification_url_one_click'] ) ? $this->options_one_click['notification_url_one_click'] : '';
			$this->debug             = isset( $this->options_one_click['debug_one_click'] ) ? $this->options_one_click['debug_one_click'] : false;
			$this->shipping          = get_option( 'shipping_one_click' );
			$this->button_order      = $this->get_button_order();


			self::$log_enabled = $this->debug;

			$this->init_api();
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueues' ), 40, 1 );
			add_action( 'wp_ajax_one_click_authorize', array( $this, 'one_click_authorize' ) );
			add_action( 'wp_ajax_nopriv_one_click_authorize', array( $this, 'one_click_authorize' ) );
			add_action( 'wp_ajax_one_click_status_update', array( $this, 'one_click_status_update' ) );

			// Hooks
			add_action( 'admin_notices', array( $this, 'admin_notices' ) );
			add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
				array(
					$this,
					'process_admin_options',
				) );
			add_action( 'woocommerce_admin_order_data_after_order_details',
				array( $this, 'add_one_click_meta_to_order' ) );
			include_once( dirname( __FILE__ ) . '/class-wc-oyst-one-click-gateway-handler.php' );
			new WC_Oyst_One_Click_Gateway_Handler();
			add_action( 'admin_menu', array( $this, 'oyst_one_click_add_page' ) );
			add_action( 'admin_init', array( $this, 'one_click_options_init' ) );
			add_action( $this->button_order, array( $this, 'display_one_click_button' ) );


			// Status action // Default deactivate
			add_action( 'woocommerce_order_status_cancelled', array( $this, 'process_refund' ) );
			add_action( 'woocommerce_order_status_refunded', array( $this, 'process_refund' ) );
			add_action( 'woocommerce_order_status_completed', array( $this, 'process_shipped_notify' ) );
			add_filter( 'woocommerce_product_data_tabs', array( $this, 'custom_product_tabs' ) );
			add_filter( 'woocommerce_product_data_panels', array( $this, 'one_click_options_product_tab_content' ) );
			add_action( 'woocommerce_process_product_meta_variable', array( $this, 'save_one_click_option_fields' ) );
			add_action( 'woocommerce_process_product_meta_simple', array( $this, 'save_one_click_option_fields' ) );

		}

		/**
		 * Initialise 1-Click Settings Form Fields
		 */
		public function init_form_fields() {
			//$this->form_fields = include( dirname( __FILE__ ) . '/settings-one-click.php' );
		}

		/**
		 * Initialise Gateway API
		 *
		 */
		protected function init_api() {
			include_once( dirname( __FILE__ ) . '/class-wc-oyst-api.php' );
			WC_Oyst_API::$api_prod_key    = $this->prod_key;
			WC_Oyst_API::$api_preprod_key = $this->preprod_key;
			WC_Oyst_API::$api_custom_key  = $this->custom_key;
			WC_Oyst_API::$api_mode        = $this->mode;
			WC_Oyst_API::$api_custom_url  = $this->custom_url;

		}

		public function init_shipments() {

			$options_shipment      = get_option( 'options_shipment' );
			$shipment_name         = $options_shipment['shipment_name'];
			$shipment_leader       = $options_shipment['shipment_leader'];
			$shipment_follower     = $options_shipment['shipment_follower'];
			$shipment_freeshipping = $options_shipment['shipment_freeshipping'];
			$shipment_delay        = $options_shipment['shipment_delay'];


			$shipment = new \Oyst\Classes\OneClickShipment();
			$shipment->setFreeShipping( (int) $shipment_freeshipping );
			$shipment->setPrimary( true );
			$shipment->setDelay( (int) $shipment_delay );

			$amountFollower = (int) $shipment_follower;
			$amountLeader   = (int) $shipment_leader;
			$currency       = 'EUR';
			$amount         = new \Oyst\Classes\ShipmentAmount( $amountFollower, $amountLeader, $currency );
			$shipment->setAmount( $amount );

			$id            = 'flat_rate';
			$name          = $shipment_name;
			$type          = 'home_delivery';
			$oyst_shipment = new \Oyst\Classes\OystCarrier( $id, $name, $type );
			$shipment->setCarrier( $oyst_shipment );

			$shipment->setZones( array( 'FR' ) );

			$oystClient = WC_Oyst_API::get_catalog_api();
			$oystClient->postShipments( array( $shipment ) );

		}

		/**
		 * Logging method.
		 *
		 * @param string $message Log message.
		 * @param string $level Optional. Default 'info'.
		 *     emergency|alert|critical|error|warning|notice|info|debug
		 */
		public static function log( $message, $level = 'info' ) {
			if ( 'no' != self::$log_enabled ) {
				if ( empty( self::$log ) ) {
					self::$log = wc_get_logger();
				}
				self::$log->log( $level, $message, array( 'source' => 'one-click' ) );
			}
		}

		public function enqueues() {

			wp_enqueue_script( 'wp_ajax_one_click_frontend_script',
				WC_OYST_PLUGIN_URL . '/front/js/wp-ajax-one-click-frontend.js',
				array( 'jquery' ),
				'',
				true );
			wp_enqueue_script( 'wp_ajax_one_click_lib_script',
				'https://cdn.sandbox.oyst.eu/1click/script/script.min.js',
				'',
				'',
				true );

			wp_localize_script( 'wp_ajax_one_click_frontend_script',
				'wp_ajax_one_click_frontend',
				array(
					'ajaxurl'                          => admin_url( 'admin-ajax.php' ),
					'wp_ajax_one_click_frontend_nonce' => wp_create_nonce( 'wp_ajax_one_click_frontend_nonce' ),
				) );

		}

		/**
		 * Add 1-Click menu page
		 */
		public function oyst_one_click_add_page() {
			add_menu_page(
				'1-Click',
				'1-Click',
				'manage_options',
				'one-click',
				array( $this, 'one_click_settings_page' ),
				'',
				'57'
			);

		}

		/**
		 * Renderer for one_click_settings_page
		 */
		public function one_click_settings_page() {
			$this->options_one_click     = get_option( 'options_one_click' );
			$this->options_shipment      = get_option( 'options_shipment' );
			$this->options_customization = get_option( 'options_customization' );
			if ( isset( $_GET['settings-updated'] ) && $_GET['settings-updated'] == true ) {
				$this->init_shipments();
			}

			$shipment_screen      = ( isset( $_GET['action'] ) && 'shipment' == $_GET['action'] ) ? true : false;
			$customization_screen = ( isset( $_GET['action'] ) && 'customization' == $_GET['action'] ) ? true : false; ?>

            <div class="wrap">
                <h1>1-Click</h1>
                <h2 class="nav-tab-wrapper">
                    <a href="<?php echo admin_url( 'admin.php?page=one-click' ); ?>"
                       class="nav-tab<?php if ( ! isset( $_GET['action'] ) /* || isset( $_GET['action'] ) && 'customization' != $_GET['action'] */ ) {
						   echo ' nav-tab-active';
					   } ?>"><?php esc_html_e( 'Général' ); ?>
                    </a>

                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'shipment' ),
						admin_url( 'admin.php?page=one-click' ) ) ); ?>"
                       class="nav-tab<?php if ( $shipment_screen ) {
						   echo ' nav-tab-active';
					   } ?>"><?php esc_html_e( 'Expédition' ); ?>
                    </a>

                    <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'customization' ),
						admin_url( 'admin.php?page=one-click' ) ) ); ?>"
                       class="nav-tab<?php if ( $customization_screen ) {
						   echo ' nav-tab-active';
					   } ?>"><?php esc_html_e( 'Personnalisation' ); ?>
                    </a>
                </h2>

                <form method="post" action="options.php"><?php
					if ( $customization_screen ) {
						settings_fields( 'options_customization' );
						do_settings_sections( 'one-click-setting-customization' );
						submit_button();
					} elseif ( $shipment_screen ) {
						settings_fields( 'options_shipment' );
						do_settings_sections( 'one-click-setting-shipment' );
						submit_button();
					} else {
						settings_fields( 'options_one_click' );
						do_settings_sections( 'one-click-setting-general' );
						submit_button();
					} ?>
                </form>
            </div> <?php
		}

		/**
		 * Register settings for 1-Click admin page
		 */
		public function one_click_options_init() {
			// General option for 1-Click
			register_setting(
				'options_one_click', // Option group
				'options_one_click', // Option name
				array( $this, 'sanitize' ) // Sanitize
			);
			add_settings_section(
				'setting_section_id', // ID
				'Général', // Title
				array( $this, 'print_section_info' ), // Callback
				'one-click-setting-general' // Page
			);

			add_settings_field(
				'enable_one_click', // ID
				"Activer 1-Click", // Title
				array( $this, 'enable_one_click_callback' ), // Callback
				'one-click-setting-general', // Page
				'setting_section_id' // Section
			);

			add_settings_field(
				'mode_one_click', // ID
				"Environnement", // Title
				array( $this, 'mode_one_click_callback' ), // Callback
				'one-click-setting-general', // Page
				'setting_section_id' // Section
			);

			add_settings_field(
				'prod_key_one_click', // ID
				"Clé d'Api production", // Title
				array( $this, 'prod_key_one_click_callback' ), // Callback
				'one-click-setting-general', // Page
				'setting_section_id' // Section
			);

			add_settings_field(
				'preprod_key_one_click', // ID
				"Clé d'Api pré production", // Title
				array( $this, 'preprod_key_one_click_callback' ), // Callback
				'one-click-setting-general', // Page
				'setting_section_id' // Section
			);

			add_settings_field(
				'custom_key_one_click', // ID
				"Clé d'Api personnalisée", // Title
				array( $this, 'custom_key_one_click_callback' ), // Callback
				'one-click-setting-general', // Page
				'setting_section_id' // Section
			);

			add_settings_field(
				'custom_url_one_click', // ID
				"URL personnalisée", // Title
				array( $this, 'custom_url_one_click_callback' ), // Callback
				'one-click-setting-general', // Page
				'setting_section_id' // Section
			);

			add_settings_field(
				'debug_one_click', // ID
				"Activer le debug", // Title
				array( $this, 'debug_one_click_callback' ), // Callback
				'one-click-setting-general', // Page
				'setting_section_id' // Section
			);

			add_settings_field(
				'notification_url_one_click', // ID
				"URL de notification personnalisée", // Title
				array( $this, 'notification_url_one_click_callback' ), // Callback
				'one-click-setting-general', // Page
				'setting_section_id' // Section
			);


			// Shipment
			register_setting(
				'options_shipment', // Option group
				'options_shipment', // Option name
				array( $this, 'sanitize' ) // Sanitize
			);
			add_settings_section(
				'setting_section_id', // ID
				'Expédition', // Title
				array( $this, 'print_section_info' ), // Callback
				'one-click-setting-shipment' // Page
			);

			add_settings_field(
				'shipment_name', // ID
				"Nom du mode d'expédition", // Title
				array( $this, 'shipment_name_callback' ), // Callback
				'one-click-setting-shipment', // Page
				'setting_section_id' // Section
			);
			add_settings_field(
				'shipment_leader', // ID
				'Montant du premier produit', // Title
				array( $this, 'shipment_leader_callback' ), // Callback
				'one-click-setting-shipment', // Page
				'setting_section_id' // Section
			);
			add_settings_field(
				'shipment_follower', // ID
				'Montant des produits suivants', // Title
				array( $this, 'shipment_follower_callback' ), // Callback
				'one-click-setting-shipment', // Page
				'setting_section_id' // Section
			);
			add_settings_field(
				'shipment_freeshipping', // ID
				'Montant du franco', // Title
				array( $this, 'shipment_freeshipping_callback' ), // Callback
				'one-click-setting-shipment', // Page
				'setting_section_id' // Section

			);
			add_settings_field(
				'shipment_delay', // ID
				"Délai de l'expédition", // Title
				array( $this, 'shipment_delay_callback' ), // Callback
				'one-click-setting-shipment', // Page
				'setting_section_id' // Section
			);

			// customization
			register_setting(
				'options_customization', // Option group
				'options_customization', // Option name
				array( $this, 'sanitize' ) // Sanitize
			);
			add_settings_section(
				'setting_section_id', // ID
				'Personnalisation du 1-Click', // Title
				array( $this, 'print_section_info' ), // Callback
				'one-click-setting-customization' // Page
			);

			add_settings_field(
				'customization_button_place',
				'Emplacement du bouton 1-Click',
				array( $this, 'customization_button_place_callback' ),
				'one-click-setting-customization',
				'setting_section_id'
			);

			add_settings_field(
				'add_button_wrapper',
				'Inclure le bouton dans une DIV',
				array( $this, 'add_button_wrapper_callback' ),
				'one-click-setting-customization',
				'setting_section_id'
			);

			add_settings_field(
				'add_button_wrapper_class',
				'Ajouter une classe au wrapper',
				array( $this, 'add_button_wrapper_class_callback' ),
				'one-click-setting-customization',
				'setting_section_id'
			);

			add_settings_field(
				'add_button_wrapper_id',
				'Ajouter un id au wrapper',
				array( $this, 'add_button_wrapper_id_callback' ),
				'one-click-setting-customization',
				'setting_section_id'
			);
			add_settings_field(
				'add_button_wrapper_css',
				'Ajouter du css au wrapper',
				array( $this, 'add_button_wrapper_css_callback' ),
				'one-click-setting-customization',
				'setting_section_id'
			);
		}

		public function print_section_info() {
			//your code...
		}

		/**
		 * Renderer for enable_one_click
		 */
		public function enable_one_click_callback() {
			printf(
				'<input id="%1$s" name="options_one_click[%1$s]" type="checkbox" %2$s />',
				'enable_one_click',
				checked( isset( $this->options_one_click['enable_one_click'] ), true, false )
			);
		}

		/**
		 * Renderer for mode_one_click
		 */
		public function mode_one_click_callback() {
			$options = $this->options_one_click;
			$items   = array(
				'prod'    => 'Prod',
				'preprod' => 'Preprod',
				'custom'  => 'Custom',
			);

			echo "<select id='mode_one_click' class='wc_oyst_environnement' name='options_one_click[mode_one_click]'>";
			foreach ( $items as $key => $item ) {
				$selected = ( isset( $options['mode_one_click'] ) && $options['mode_one_click'] == $key ) ? 'selected="selected"' : '';
				echo "<option value='$key' $selected>$item</option>";
			}
			echo "</select>";

		}

		/**
		 * Renderer for prod_key_one_click
		 */
		public function prod_key_one_click_callback() {
			printf(
				'<input type="text" id="prod_key_one_click" class="large-text wc_oyst_prod_key" name="options_one_click[prod_key_one_click]" value="%s" />',
				isset( $this->options_one_click['prod_key_one_click'] ) ? esc_attr( $this->options_one_click['prod_key_one_click'] ) : ''
			);
			echo '<p class="description">La clé doit avoir une longueur de 64 caractères</p>';
		}

		/**
		 * Renderer for preprod_key_one_click
		 */
		public function preprod_key_one_click_callback() {
			printf(
				'<input type="text" id="preprod_key_one_click" class="large-text wc_oyst_preprod_key" name="options_one_click[preprod_key_one_click]" value="%s" />',
				isset( $this->options_one_click['preprod_key_one_click'] ) ? esc_attr( $this->options_one_click['preprod_key_one_click'] ) : ''
			);
			echo '<p class="description">La clé doit avoir une longueur de 64 caractères</p>';
		}

		/**
		 * Renderer for custom_key_one_click
		 */
		public function custom_key_one_click_callback() {
			printf(
				'<input type="text" id="custom_key_one_click" class="large-text wc_oyst_custom_key" name="options_one_click[custom_key_one_click]" value="%s" />',
				isset( $this->options_one_click['custom_key_one_click'] ) ? esc_attr( $this->options_one_click['custom_key_one_click'] ) : ''
			);
			echo '<p class="description">La clé doit avoir une longueur de 64 caractères</p>';
		}

		/**
		 * Renderer for custom_key_one_click
		 */
		public function custom_url_one_click_callback() {
			printf(
				'<input type="text" id="custom_url_one_click" class="large-text wc_oyst_custom_url" name="options_one_click[custom_url_one_click]" value="%s" />',
				isset( $this->options_one_click['custom_url_one_click'] ) ? esc_attr( $this->options_one_click['custom_url_one_click'] ) : ''
			);
			echo '<p class="description"></p>';
		}

		/**
		 * Renderer for debug_one_click
		 */
		public function debug_one_click_callback() {
			printf(
				'<input id="%1$s" name="options_one_click[%1$s]" type="checkbox" %2$s />',
				'debug_one_click',
				checked( isset( $this->options_one_click['debug_one_click'] ), true, false )
			);
		}

		/**
		 * Renderer for notification_url_one_click
		 */
		public function notification_url_one_click_callback() {
			printf(
				'<input type="text" id="notification_url_one_click" class="large-text" name="options_one_click[notification_url_one_click]" value="%s" />',
				isset( $this->options_one_click['notification_url_one_click'] ) ? esc_attr( $this->options_one_click['notification_url_one_click'] ) : ''
			);
			echo '<p class="description"></p>';
		}

		/**
		 * Renderer for shipment_name
		 */
		public function shipment_name_callback() {
			printf(
				'<input type="text" id="shipment_name" name="options_shipment[shipment_name]" value="%s" />',
				isset( $this->options_shipment['shipment_name'] ) ? esc_attr( $this->options_shipment['shipment_name'] ) : ''
			);
			echo '<p class="description">Ce nom sera utilisé lors des commande</p>';
		}

		/**
		 * Renderer for shipment_leader
		 */
		public function shipment_leader_callback() {
			printf(
				'<input type="text" id="shipment_leader" name="options_shipment[shipment_leader]" value="%s" />',
				isset( $this->options_shipment['shipment_leader'] ) ? esc_attr( $this->options_shipment['shipment_leader'] ) : ''
			);
			echo '<p class="description">Montant en euros</p>';

		}

		/**
		 * Renderer for shipment_follower
		 */
		public function shipment_follower_callback() {
			printf(
				'<input type="text" id="shipment_follower" name="options_shipment[shipment_follower]" value="%s" />',
				isset( $this->options_shipment['shipment_follower'] ) ? esc_attr( $this->options_shipment['shipment_follower'] ) : ''
			);
			echo '<p class="description">Montant en euros</p>';
		}

		/**
		 * Renderer for shipment_freeshipping
		 */
		public function shipment_freeshipping_callback() {
			printf(
				'<input type="text" id="shipment_freeshipping" name="options_shipment[shipment_freeshipping]" value="%s" />',
				isset( $this->options_shipment['shipment_freeshipping'] ) ? esc_attr( $this->options_shipment['shipment_freeshipping'] ) : ''
			);
			echo '<p class="description">Montant en euros</p>';
		}

		/**
		 * renderer for shipment_delay
		 */
		public function shipment_delay_callback() {
			printf(
				'<input type="text" id="shipment_delay" name="options_shipment[shipment_delay]" value="%s" />',
				isset( $this->options_shipment['shipment_delay'] ) ? esc_attr( $this->options_shipment['shipment_delay'] ) : ''
			);
			echo '<p class="description">Délai en heures</p>';
		}

		/**
		 * Renderer for customization_button_place
		 */
		public function customization_button_place_callback() {
			$options = $this->options_customization;
			$items   = array(
				'woocommerce_before_add_to_cart_form'   => 'Avant le formulaire "Ajouter au panier"',
				'woocommerce_before_variations_form'    => 'Avant le formulaire "variations"',
				'woocommerce_before_add_to_cart_button' => 'Avant le formulaire "Ajouter au panier"',
				'woocommerce_before_single_variation'   => 'Avant le champ "variation"',
				'woocommerce_after_single_variation'    => 'Après le champ "variation"',
				'woocommerce_after_add_to_cart_button'  => 'Après le formulaire "Ajouter au panier"',
				'woocommerce_after_variations_form'     => 'Après le formulaire "variations"',
				'woocommerce_after_add_to_cart_form'    => 'Après le formulaire "Ajouter au panier"',
			);

			echo "<select id='customization_button_place' name='options_customization[customization_button_place]'>";
			foreach ( $items as $key => $item ) {
				$selected = ( $options['customization_button_place'] == $key ) ? 'selected="selected"' : '';
				echo "<option value='$key' $selected>$item</option>";
			}
			echo "</select>";

		}

		/**
		 * Renderer for add_button_wrapper
		 */
		public function add_button_wrapper_callback() {
			printf(
				'<input id="%1$s" name="options_customization[%1$s]" type="checkbox" %2$s />',
				'add_button_wrapper',
				checked( isset( $this->options_customization['add_button_wrapper'] ), true, false )
			);
			echo '<p class="description">Le bouton 1-Click sera entouré par cette <code> div </code></p>';

		}

		/**
		 * Renderer for add_button_wrapper_class
		 */
		public function add_button_wrapper_class_callback() {
			printf(
				'<input type="text" id="add_button_wrapper_class" name="options_customization[add_button_wrapper_class]" value="%s" />',
				isset( $this->options_customization['add_button_wrapper_class'] ) ? esc_attr( $this->options_customization['add_button_wrapper_class'] ) : ''
			);
		}

		/**
		 * Renderer for add_button_wrapper_id
		 */
		public function add_button_wrapper_id_callback() {
			printf(
				'<input type="text" id="add_button_wrapper_id" name="options_customization[add_button_wrapper_id]" value="%s" />',
				isset( $this->options_customization['add_button_wrapper_id'] ) ? esc_attr( $this->options_customization['add_button_wrapper_id'] ) : ''
			);
		}

		/**
		 * Renderer for add_button_wrapper_css
		 */
		public function add_button_wrapper_css_callback() {
			printf(
				'<textarea id="add_button_wrapper_css" name="options_customization[add_button_wrapper_css]" rows="7" cols="50" type="textarea">%s</textarea>',
				isset( $this->options_customization['add_button_wrapper_css'] ) ? esc_attr( $this->options_customization['add_button_wrapper_css'] ) : ''
			);
			echo '<p class="description">par ex: "text-align:center</code></p>';
		}

		/**
		 * Sanitize input fields from 1-Click settings page
		 *
		 * @param $input
		 *
		 * @return array
		 */
		public function sanitize( $input ) {
			$new_input = array();
			if ( isset( $input['enable_one_click'] ) ) {
				$new_input['enable_one_click'] = sanitize_text_field( $input['enable_one_click'] );
			}
			if ( isset( $input['mode_one_click'] ) ) {
				$new_input['mode_one_click'] = sanitize_text_field( $input['mode_one_click'] );
			}
			if ( isset( $input['prod_key_one_click'] ) ) {
				$new_input['prod_key_one_click'] = sanitize_text_field( $input['prod_key_one_click'] );
			}
			if ( isset( $input['preprod_key_one_click'] ) ) {
				$new_input['preprod_key_one_click'] = sanitize_text_field( $input['preprod_key_one_click'] );
			}
			if ( isset( $input['custom_key_one_click'] ) ) {
				$new_input['custom_key_one_click'] = sanitize_text_field( $input['custom_key_one_click'] );
			}
			if ( isset( $input['custom_url_one_click'] ) ) {
				$new_input['custom_url_one_click'] = sanitize_text_field( $input['custom_url_one_click'] );
			}
			if ( isset( $input['notification_url_one_click'] ) ) {
				$new_input['notification_url_one_click'] = sanitize_text_field( $input['notification_url_one_click'] );
			}
			if ( isset( $input['debug_one_click'] ) ) {
				$new_input['debug_one_click'] = sanitize_text_field( $input['debug_one_click'] );
			}
			if ( isset( $input['shipment_name'] ) ) {
				$new_input['shipment_name'] = sanitize_text_field( $input['shipment_name'] );
			}
			if ( isset( $input['shipment_leader'] ) ) {
				$new_input['shipment_leader'] = sanitize_text_field( $input['shipment_leader'] );
			}
			if ( isset( $input['shipment_follower'] ) ) {
				$new_input['shipment_follower'] = sanitize_text_field( $input['shipment_follower'] );
			}
			if ( isset( $input['shipment_freeshipping'] ) ) {
				$new_input['shipment_freeshipping'] = sanitize_text_field( $input['shipment_freeshipping'] );
			}
			if ( isset( $input['shipment_delay'] ) ) {
				$new_input['shipment_delay'] = sanitize_text_field( $input['shipment_delay'] );
			}
			if ( isset( $input['customization_button_place'] ) ) {
				$new_input['customization_button_place'] = sanitize_text_field( $input['customization_button_place'] );
			}
			if ( isset( $input['add_button_wrapper'] ) ) {
				$new_input['add_button_wrapper'] = sanitize_text_field( $input['add_button_wrapper'] );
			}
			if ( isset( $input['add_button_wrapper_class'] ) ) {
				$new_input['add_button_wrapper_class'] = sanitize_text_field( $input['add_button_wrapper_class'] );
			}
			if ( isset( $input['add_button_wrapper_id'] ) ) {
				$new_input['add_button_wrapper_id'] = sanitize_text_field( $input['add_button_wrapper_id'] );
			}
			if ( isset( $input['add_button_wrapper_css'] ) ) {
				$new_input['add_button_wrapper_css'] = sanitize_text_field( $input['add_button_wrapper_css'] );
			}


			return $new_input;
		}

		public function get_button_order() {
			$options_customization = get_option( 'options_customization' );
			$button_order_place    = $options_customization['customization_button_place'];

			return ( ! empty( $button_order_place ) ? $button_order_place : 'woocommerce_after_add_to_cart_form' );
		}

		/**
		 * Get Shipping methods ID
		 */
		public function get_shipping_methods() {
			$shipping_methods = WC()->shipping()->get_shipping_method_class_names();
			echo '<p>Liste des méthodes disponibles, correspondant au champ "Carrier" -> "id"</p>';
			echo '<ul>';
			foreach ( $shipping_methods as $key => $value ) {
				echo '<li>' . $key . '</li>';
			}
			echo '</ul>';
		}


		/**
		 * Check for mode and empty keys
		 */
		public function admin_notices() {
			$level = "error";
			if ( 'no' === $this->enabled ) {
				$level = "warning";
			}

			// Check if key is not empty for each mode
			if ( 'prod' === $this->mode ) {
				if ( ! $this->prod_key ) {
					echo '<div class="notice notice-' . $level . ' is-dismissible"><p>' . sprintf( __( 'Merci d’avoir téléchargé Oyst 1-Click. Veuillez saisir votre clé d’API ou en créer une <a href="%s" target="_blank">ici</a>',
							'woocommerce-oyst' ),
							'https://admin.free-pay.com/login' ) . '</p></div>';

					return;
				}
			} elseif
			( 'preprod' === $this->mode
			) {
				if ( ! $this->preprod_key ) {
					echo '<div class="notice notice-' . $level . ' is-dismissible"><p>' . sprintf( __( 'Merci d’avoir téléchargé Oyst 1-Click. Veuillez saisir votre clé d’API ou en créer une <a href="%s" target="_blank">ici</a>',
							'woocommerce-oyst' ),
							'https://admin.staging.free-pay.eu/login' ) . '</p></div>';

					return;
				}
			} elseif
			( 'custom' === $this->mode
			) {
				if ( ! $this->custom_key ) {
					echo '<div class="notice notice-' . $level . ' is-dismissible"><p>' . sprintf( __( 'Merci d’avoir téléchargé Oyst 1-Click. Veuillez saisir votre clé d’API ou en créer une <a href="%s" target="_blank">ici</a>',
							'woocommerce-oyst' ),
							'https://admin.staging.free-pay.eu/login' ) . '</p></div>';

					return;
				}
			} else {
				if ( ! $this->mode ) {
					echo '<div class="notice notice-' . $level . ' is-dismissible"><p>' . sprintf( __( 'Merci d’avoir téléchargé Oyst 1-Click. Veuillez saisir votre clé d’API ou en créer une <a href="%s" target="_blank">ici</a>',
							'woocommerce-oyst' ),
							'https://admin.staging.free-pay.eu/login' ) . '</p></div>';

					return;
				}
			}

			// Simple check for duplicate keys
			if ( $this->prod_key === $this->preprod_key ) {
				echo '<div class="notice notice-' . $level . ' is-dismissible"><p>' . sprintf( __( 'Erreur Oyst 1-Click: Votre clé d’API de production et de pré-production sont identiques. Merci de vérifier vos réglages.',
						'woocommerce-oyst' ) ) . '</p></div>';

				return;
			}

		}

		public function one_click_authorize() {
			include_once( dirname( __FILE__ ) . '/class-wc-oyst-one-click-request.php' );
			$request    = new WC_Oyst_One_Click_Request();
			$notify_url = $this->notification_url;

			$ref_id       = $_POST['product_reference'];
			$variation_id = $_POST['variation_reference'];
			$quantity     = $_POST['quantity'];
			if ( ! empty( $ref_id ) && ! empty( $quantity ) ) {
				$url = $request->get_request_url( $ref_id, $quantity, $variation_id, $notify_url );
				// Send success message
				wp_send_json( array(
					'status' => 'success',
					'url'    => __( $url, 'woocommerce-oyst' ),
				) );

			}

			exit();
		}

		/**
		 * Add meta to order admin post
		 *
		 * @param WC_Order $order
		 */
		public function add_one_click_meta_to_order( $order ) {

			$order_id       = $order->get_id();
			$payment_method = get_post_meta( $order_id, '_payment_method', true );

			if ( 'one_click' === $payment_method ) {
				//$date = get_post_meta( $order_id, '_freepay_event_date', true );
				?>
                <div class="order_data_column form-field-wide">
                    <h4><?php _e( 'Détails 1-Click' ); ?></h4>
					<?php
					echo '<p><strong>' . __( 'Evénement' ) . ': </strong><span id="event_code">' . esc_attr( get_post_meta( $order_id,
							'_one_click_event_code',
							true ) ) . '</span></p>';
					//echo '<p><strong>' . __( 'Date' ) . ': </strong>' . esc_attr( $this->format_freepay_date( $date ) ) . '</p>';
					echo '<p><strong>' . __( 'ID de la commande' ) . ': </strong><span id="one-click-order-id">' . esc_attr( get_post_meta( $order_id,
							'_one_click_payment_id',
							true ) ) . '</span></p>';
					?>
                    <!--
					<button id="accept-one-click-order" value="accept"><span class="dashicons-before dashicons-yes"> Accepter</span>
					</button>
					<button id="denied-one-click-order" value="denied"><span class="dashicons-before dashicons-no"> Refuser</span></button>
					-->
                </div>
				<?php
				return;
			}

			return;
		}

		/**
		 * Accept or deny order from 1-Click
		 */
		public function one_click_status_update() {

			if ( ! empty( $_POST['status'] ) && ! empty( $_POST['order'] ) ) {
				$status   = $_POST['status'];
				$order_id = $_POST['order'];


				// api order
				include_once( dirname( __FILE__ ) . '/class-wc-oyst-api.php' );

				$oystClient = WC_Oyst_API::get_order_api();

				switch ( $status ) {
					case 'accepted':
						$oystClient->accept( $order_id );
						break;
					case 'denied':
						$oystClient->deny( $order_id );
						break;
				}
			}

			exit();

		}

		/**
		 * Refund a charge
		 *
		 * @param  int $order_id
		 * @param  float $amount
		 * @param  string $reason
		 *
		 * @return bool
		 */
		public function process_refund( $order_id, $amount = null, $reason = '' ) {
			$order          = wc_get_order( $order_id );
			$order_status   = get_post_meta( $order_id, '_one_click_event_code', true );
			$transaction_id = $order->get_transaction_id();

			// get transaction id

			if ( ! $order ) {
				return false;
			}
			/*if ( 'pending' === ! $order_status ) {
				return false;
			} */
			if ( empty( $transaction_id ) ) {
				return false;
			}

			// api order
			include_once( dirname( __FILE__ ) . '/class-wc-oyst-api.php' );

			$oystClient = WC_Oyst_API::get_payment_api();

			if ( ! empty( $amount ) ) {
				$amount = new OystPrice( $amount, 'EUR' );
				$result = $oystClient->cancelOrRefund( $transaction_id, $amount );
			} else {
				$result = $oystClient->cancelOrRefund( $transaction_id );
			}


			if ( is_array( $result['refund'] ) ) {
				if ( $result['refund']['success'] ) {
					update_post_meta( $order_id,
						'_one_click_event_code',
						wc_clean( $result['refund']['status'] ) );
					update_post_meta( $order_id,
						'transaction_id',
						wc_clean( $result['refund']['id'] ) );
					$refund_message = sprintf( __( 'Annulation / Remboursement pour la commande N°%s en cours. %s',
						'woocommerce-oyst' ),
						$order_id,
						$reason );
					$order->add_order_note( $refund_message );
					WC_Oyst_Freepay::log( 'Succès de la réponse synchrone : ' . html_entity_decode( strip_tags( $refund_message ) ) );

					return true;
				}
			}

			return false;

		}

		public function process_shipped_notify( $order_id ) {
			$order         = wc_get_order( $order_id );
			$oyst_order_id = get_post_meta( $order_id, '_one_click_payment_id', true );


			if ( ! $order ) {
				return false;
			}
			if ( empty( $oyst_order_id ) ) {
				return false;
			}

			include_once( dirname( __FILE__ ) . '/class-wc-oyst-api.php' );

			$oystClient = WC_Oyst_API::get_order_api();
			$result     = $oystClient->shipped( $oyst_order_id );

			if ( 'shipped' === $result['order']['current_status'] ) {
				update_post_meta( $order_id, '_one_click_event_code', 'shipped' );

				return true;
			}

		}

		/**
		 * Test for displaying 1-Click Button
		 *
		 */
		public function display_one_click_button() {

			$filter_single_product = $this->filter_single_product();
			if ( 'yes' === $filter_single_product ) {
				return;
			}

			if ( $this->is_available() && ( is_product() || is_checkout() ) ) {
				$this->get_one_click_button();
			}


		}

		/**
		 * Filter single product to disable 1-click feature
		 *
		 * @return mixed
		 */
		public function filter_single_product() {
			global $product;
			$id = $product->get_id();

			return get_post_meta( $id, '_disable_one_click', true );
		}

		/**
		 * Display 1-Click button with or without wrapper
		 *
		 */
		public function get_one_click_button() {
			$customization = get_option( 'options_customization' );
			$wrapper       = isset( $customization['add_button_wrapper'] ) ? $customization['add_button_wrapper'] : false;
			$wrapper_class = ! empty( $customization['add_button_wrapper_class'] ) ? 'class="' . $customization['add_button_wrapper_class'] . '"' : '';
			$wrapper_id    = ! empty( $customization['add_button_wrapper_id'] ) ? 'id="' . $customization['add_button_wrapper_id'] . '"' : '';
			$wrapper_css   = ! empty( $customization['add_button_wrapper_css'] ) ? 'style="' . $customization['add_button_wrapper_css'] . '"' : '';

			if ( $wrapper ) {
				echo '<div ' . $wrapper_class . ' ' . $wrapper_id . ' ' . $wrapper_css . '>';
				echo '<div id="oyst-1click-button"></div>';
				echo '</div>';
			} else {
				echo '<div id="oyst-1click-button"></div>';

			}
		}

		/**
		 * Add a custom product tab with a custom position.
		 */
		public function custom_product_tabs( $tabs ) {
			$tabs['one-click'] = array(
				'label'  => __( '1-Click', 'woocommerce-oyst' ),
				'target' => 'one_click_options',
				'class'  => array( 'show_if_simple', 'show_if_variable' ),
				'value'  => true,
			);

			return $tabs;

		}

		/**
		 * Contents of the 1-Click options product tab.
		 */
		public function one_click_options_product_tab_content() {
			global $post;

			// Note the 'id' attribute needs to match the 'target' parameter set above
			?>
            <div id='one_click_options' class='panel woocommerce_options_panel'><?php
			?>
            <div class='options_group'><?php
				woocommerce_wp_checkbox( array(
					'id'          => '_disable_one_click',
					'label'       => __( 'Désactiver 1-click ', 'woocommerce-oyst' ),
					'desc_tip'    => 'true',
					'description' => __( 'Désactiver 1-click pour ce produit', 'woocommerce-oyst' ),
				) );
				?></div>

            </div><?php
		}


		/**
		 * Save the custom fields.
		 */
		public function save_one_click_option_fields( $post_id ) {

			$disable_one_click = isset( $_POST['_disable_one_click'] ) ? 'yes' : 'no';
			update_post_meta( $post_id, '_disable_one_click', $disable_one_click );

		}


		/**
		 * Check if this 1-Click is enabled
		 *
		 * @return bool
		 */
		public function is_available() {
			if ( 'on' === $this->enabled && $this->check_mode() ) {
				return true;
			}

			return false;
		}

		/**
		 * Check mode
		 *
		 * @return bool
		 */
		private function check_mode() {
			switch ( $this->mode ) {
				case 'preprod':
					return ! empty( $this->preprod_key );
				case 'prod':
					return ! empty( $this->prod_key );
				case 'custom':
					return ! empty( $this->custom_key );
				default:
					return false;
			}
		}

	}
}
