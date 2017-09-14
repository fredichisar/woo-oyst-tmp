<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Oyst\Api\OystApiClientFactory;

/**
 * WC_Oyst_Freepay class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Oyst_Freepay extends WC_Payment_Gateway {

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
	 * Constructor
	 */
	public function __construct() {
		$this->id                 = 'freepay';
		$this->method_title       = __( 'FreePay', 'woocommerce-oyst' );
		$this->method_description = __( '<img src="' . WC_OYST_PLUGIN_URL . '/public/images/freepay.png' . '" alt="Freepay logo"/><br>FreePay, la solution de Paiement 100% gratuite. <a href="https://free-pay.zendesk.com/hc/fr" target="_blank">FAQ</a> | <a href="https://admin.free-pay.com/login" target="_blank">S\'inscrire</a>',
			'woocommerce-oyst' );
		$this->has_fields         = true;
		$this->supports           = array(
			'products',
			'refunds',
		);

		// Load the form fields
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		//$this->init_api();

		// Get setting values.
		$this->enabled            = $this->get_option( 'enabled', 'no' );
		$this->order_button_text  = __( 'Payer par CB', 'wc_oyst_freepay_settings' );
		$this->mode               = $this->get_option( 'mode' );
		$this->prod_key           = $this->get_option( 'prod_key' );
		$this->preprod_key        = $this->get_option( 'preprod_key' );
		$this->custom_key         = $this->get_option( 'custom_key' );
		$this->custom_url         = $this->get_option( 'custom_url', '' );
		$this->notification_url   = $this->get_option( 'notification_url' );
		$this->title              = $this->get_option( 'title', 'Carte bancaire' );
		$this->description        = $this->get_option( 'description', 'Payer avec votre carte bancaire.' );
		$this->debug              = $this->get_option( 'debug', 'no' );

		self::$log_enabled = $this->debug;

		if ( 'preprod' === $this->mode ) {
			$this->description .= ' ' . sprintf( __( 'Mode Test activé.', 'woocommerce-oyst' ) );
			$this->description = trim( $this->description );
		}



		// Hooks
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id,
			array(
				$this,
				'process_admin_options',
			) );
		add_action( 'woocommerce_admin_order_data_after_order_details', array( $this, 'add_freepay_meta_to_order' ) );

		include_once( dirname( __FILE__ ) . '/class-wc-oyst-freepay-gateway-handler.php' );
		new WC_Oyst_Freepay_Gateway_Handler();

		// Status action
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'process_refund' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'process_refund' ) );
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */
	public function init_form_fields() {
		$this->form_fields = include( dirname( __FILE__ ) . '/settings-freepay.php' );
	}

	/**
	 * Initialise Gateway API
	 *
	 */
	public function init_api() {
		include_once( dirname( __FILE__ ) . '/class-wc-oyst-api.php' );
		WC_Oyst_API::$api_prod_key    = $this->prod_key;
		WC_Oyst_API::$api_preprod_key = $this->preprod_key;
		WC_Oyst_API::$api_mode        = $this->mode;
		WC_Oyst_API::$api_custom_url  = $this->custom_url;
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
			self::$log->log( $level, $message, array( 'source' => 'freepay' ) );
		}
	}

	/**
	 * Get cards icon
	 * @return mixed
	 */
	public function get_icon() {
		// basic icon
		$icon = sprintf( '<img src="%s/public/images/freepay_cards.png' . '" alt="Visa" width="150"  />',
			WC_OYST_PLUGIN_URL );

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
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
				echo '<div class="notice notice-' . $level . ' is-dismissible"><p>' . sprintf( __( 'Merci d’avoir téléchargé FreePay. Veuillez saisir votre clé d’API <a href="%s">ici</a> ou en créer une <a href="https://admin.free-pay.com/login" target="_blank">ici</a>',
						'woocommerce-oyst' ),
						admin_url( 'admin.php?page=wc-settings&tab=checkout&section=freepay' ) ) . '</p></div>';

				return;
			}
		} else {
			if ( ! $this->preprod_key ) {
				echo '<div class="notice notice-' . $level . ' is-dismissible"><p>' . sprintf( __( 'Merci d’avoir téléchargé FreePay. Veuillez saisir votre clé d’API <a href="%s">ici</a> ou en créer une <a href="https://admin.staging.free-pay.eu/login" target="_blank">ici</a>',
						'woocommerce-oyst' ),
						admin_url( 'admin.php?page=wc-settings&tab=checkout&section=freepay' ) ) . '</p></div>';

				return;
			}
		}

		// Simple check for duplicate keys
		if ( $this->prod_key === $this->preprod_key ) {
			echo '<div class="notice notice-' . $level . ' is-dismissible"><p>' . sprintf( __( 'Erreur FreePay: Votre clé d’API de production et de pré-production sont identiques. Merci de vérifier vos réglages.',
					'woocommerce-oyst' ),
					admin_url( 'admin.php?page=wc-settings&tab=checkout&section=freepay' ) ) . '</p></div>';

			return;
		}

	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled && is_checkout() && $this->check_mode() ) {
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
	/**
	 * Process the payment
	 *
	 * @param int $order_id
	 *
	 * @return array
	 *
	 */
	public function process_payment( $order_id ) {

		include_once( dirname( __FILE__ ) . '/class-wc-oyst-freepay-request.php' );

		$this->init_api();

		$sandbox_tmp     = false;
		$order           = wc_get_order( $order_id );
		$notify_url      = $this->notification_url;
		$freepay_request = new WC_Oyst_Freepay_Request( $this );

		return array(
			'result'   => 'success',
			'redirect' => $freepay_request->get_request_url( $order, $sandbox_tmp, $notify_url ),
		);
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
		$order            = wc_get_order( $order_id );
		$incoming_request = get_post_meta( $order_id, '_freepay_incoming_request', true );
		$order_status     = get_post_meta( $order_id, '_freepay_event_code', true );

		if ( ! $order ) {
			return false;
		}
		if ( 'yes' === $incoming_request ) {
			return false;
		}
		if ( 'cancel-received' === $order_status || 'refund-received' === $order_status ) {
			return false;
		}

		$this->init_api();

		$freepay_refund_cancel = new WC_Oyst_Freepay_Gateway_Handler( $this );
		$response              = $freepay_refund_cancel->cancel_freepay_payment( $order_id, $amount, $reason );

		if ( $response ) {
			update_post_meta( $order_id, '_freepay_incoming_request', 'false' );

			return true;
		}

		return false;

	}

	/**
	 * Add meta to order admin post
	 *
	 * @param WC_Order $order
	 */
	public function add_freepay_meta_to_order( $order ) {

		$order_id       = $order->get_id();
		$payment_method = get_post_meta( $order_id, '_payment_method', true );

		if ( 'freepay' === $payment_method ) {
			$date = get_post_meta( $order_id, '_freepay_event_date', true );
			?>
            <div class="order_data_column form-field-wide">
                <h4><?php _e( 'Détails FreePay' ); ?></h4>
				<?php
				echo '<p><strong>' . __( 'Evénement' ) . ': </strong><span id="event_code">' . esc_attr( get_post_meta( $order_id,
						'_freepay_event_code',
						true ) ) . '</span></p>';
				echo '<p><strong>' . __( 'Date' ) . ': </strong>' . esc_attr( $this->format_freepay_date( $date ) ) . '</p>';
				echo '<p><strong>' . __( 'ID de paiement' ) . ': </strong>' . esc_attr( get_post_meta( $order_id,
						'_freepay_payment_id',
						true ) ) . '</p>';
				?>
            </div>
			<?php
		}

		return;
	}

	/**
	 * Get formatted date
	 *
	 * @param string $date
	 *
	 * @return string $date
	 */
	public function format_freepay_date( $date ) {
		$date = new DateTime( $date );

		return $date->format( 'd/m/Y H:i:s' );
	}


}
