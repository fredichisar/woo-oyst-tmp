<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use Oyst\Classes\OystPrice;

include_once( dirname( __FILE__ ) . '/class-wc-oyst-freepay-response.php' );

/**
 * Handles Responses.
 */
class WC_Oyst_Freepay_Gateway_Handler extends WC_Oyst_Freepay_Response {


	/**
	 * Constructor.
	 *
	 * @param bool $sandbox
	 */
	public function __construct( $sandbox = false ) {
		add_action( 'woocommerce_api_wc_oyst_freepay', array( $this, 'check_response' ) );
		add_action( 'valid-oyst-feepay-request', array( $this, 'valid_response' ) );
	}

	/**
	 * Check for FreePay IPN Response.
	 */
	public function check_response() {

		// To test in live mode ($_post)
		$post = ( file_get_contents( 'php://input' ) );

		if ( ! empty( $post ) ) {
			do_action( 'valid-oyst-feepay-request', $post );
			exit;
		}
		WC_Oyst_Freepay::log( 'Retour FreePay vide', 'error' );
		wp_die( 'Erreur de requête FreePay', 'FreePay', array( 'response' => 500 ) );

	}

	/**
	 * Valid response
	 *
	 * @param $post
	 */
	public function valid_response( $post ) {

		$decoded_post = json_decode( $post );
		$order_id     = $decoded_post->notification->order_id;

		if ( ! empty( $order_id ) && ( $order = $this->get_freepay_order( $post ) ) ) {

			$success    = $decoded_post->notification->success;
			$event_code = strtolower( $decoded_post->notification->event_code );

			switch ( $event_code ) {
				case 'authorisation':
					$this->handle_freepay_authorisation( $order, $post, $success );
					break;
				/* case 'fraud_validation':
					$this->handle_freepay_fraud_validation( $order, $post, $success );
					break; */
				case 'cancellation':
					$this->handle_freepay_cancellation( $order, $post, $success );
					break;
				case 'capture':
					$this->handle_freepay_capture( $order, $post, $success );
					break;
				case 'refund':
					$this->handle_freepay_refund( $order, $post, $success );
					break;
			}
		}
	}

	/**
	 * Event Autohorisation to processing
	 *
	 * @param WC_Order $order
	 * @param object $post
	 * @param bool $success
	 */
	public function handle_freepay_authorisation( $order, $post, $success ) {
		$this->save_freepay_post_meta( $order, $post );
		if ( $success ) {
			$order_id              = $order->get_id();
			$authorisation_message = sprintf( __( 'Autorisation de la commande N°%s confirmée.',
				'woocommerce-oyst' ),
				$order_id );
			$order->update_status( 'processing', $authorisation_message );
			WC_Oyst_Freepay::log( 'Succès de la réponse asynchrone : ' . html_entity_decode( strip_tags( $authorisation_message ) ) );
		} else {
			$order_id              = $order->get_id();
			$authorisation_message = sprintf( __( "Echec de l'autorisation de la commande N°%s.",
				'woocommerce-oyst' ),
				$order_id );
			$order->update_status( 'failed', $authorisation_message );
			WC_Oyst_Freepay::log( 'Echec de la réponse asynchrone : ' . html_entity_decode( strip_tags( $authorisation_message ) ) );
		}
	}

	/**
	 * Event Fraud Validation to processing
	 *
	 * @param WC_Order $order
	 * @param object $post
	 * @param bool $success
	 */
	public function handle_freepay_fraud_validation( $order, $post, $success ) {
		$this->save_freepay_post_meta( $order, $post );
		if ( $success ) {
			$order_id              = $order->get_id();
			$authorisation_message = sprintf( __( 'Fraud validation de la commande N°%s confirmée.',
				'woocommerce-oyst' ),
				$order_id );
			$order->update_status( 'processing', $authorisation_message );
			WC_Oyst_Freepay::log( 'Succès de la réponse asynchrone : ' . html_entity_decode( strip_tags( $authorisation_message ) ) );
		} else {
			$order_id              = $order->get_id();
			$authorisation_message = sprintf( __( "Echec de Fraud validation de la commande N°%s.",
				'woocommerce-oyst' ),
				$order_id );
			$order->update_status( 'failed', $authorisation_message );
			WC_Oyst_Freepay::log( 'Echec de la réponse asynchrone : ' . html_entity_decode( strip_tags( $authorisation_message ) ) );
		}
	}

	/**
	 * Cancel woocommerce order from FreePay
	 *
	 * @param WC_Order $order
	 * @param object $post
	 * @param bool $success
	 */
	public function handle_freepay_cancellation( $order, $post, $success ) {
		$this->save_freepay_post_meta( $order, $post );
		$order_id = $order->get_id();
		if ( $success ) {
			$order_status = get_post_meta( $order_id, '_freepay_event_code', true );
			if ( 'cancel-received' === $order_status ) {
				$cancel_message = sprintf( __( 'Annulation de la commande N°%s confirmée.',
					'woocommerce-oyst' ),
					$order_id );
				$order->add_order_note( $cancel_message );
				WC_Oyst_Freepay::log( 'Succès de la réponse asynchrone : ' . html_entity_decode( strip_tags( $cancel_message ) ) );
			} else {
				$cancel_message = sprintf( __( 'Annulation de la commande N°%s confirmée.',
					'woocommerce-oyst' ),
					$order_id );
				$order->update_status( 'cancelled', $cancel_message );
				WC_Oyst_Freepay::log( 'Succès de la réponse asynchrone : ' . html_entity_decode( strip_tags( $cancel_message ) ) );
			}
		} else {
			$cancel_message = sprintf( __( "Echec de l'annulation de la commande N°%s.",
				'woocommerce-oyst' ),
				$order_id );
			$order->add_order_note( $cancel_message );
			WC_Oyst_Freepay::log( 'Echec de la réponse asynchrone : ' . html_entity_decode( strip_tags( $cancel_message ) ) );
		}
	}

	/**
	 * Capture payment notice
	 *
	 * @param WC_Order $order
	 * @param $post
	 * @param bool $success
	 */
	public function handle_freepay_capture( $order, $post, $success ) {
		$this->save_freepay_post_meta( $order, $post );
		$order_id = $order->get_id();
		if ( $success ) {
			$capture_message = sprintf( __( 'Capture de la commande N°%s confirmée.',
				'woocommerce-oyst' ),
				$order_id );
			$order->add_order_note( $capture_message );
			WC_Oyst_Freepay::log( 'Succès de la réponse asynchrone : ' . html_entity_decode( strip_tags( $capture_message ) ) );
			if ( 'hold-on' === $order->get_status() ) {
				$order->update_status( 'processing' );
				WC_Oyst_Freepay::log( 'Changement de statut: "en attente" vers "en cours" pour la commande N° : ' . $order_id );
			}
		} else {
			$capture_message = sprintf( __( 'Echec de la capture de la commande N°%s.',
				'woocommerce-oyst' ),
				$order_id );
			$order->update_status( 'cancelled', $capture_message );
			WC_Oyst_Freepay::log( 'Echec de la réponse asynchrone : ' . html_entity_decode( strip_tags( $capture_message ) ) );
		}
	}

	/**
	 * Handle refund from FreePay
	 *
	 * @param   WC_Order $order
	 * @param   $post
	 * @param   bool $success
	 */
	public function handle_freepay_refund( $order, $post, $success ) {
		$order_id       = $order->get_id();
		$order_status   = get_post_meta( $order_id, '_freepay_event_code', true );
		$decoded_post   = json_decode( $post );
		$amount         = $decoded_post->notification->amount->value;
		$decimal_amount = $this->freepay_decimal_format( $amount );


		if ( $success ) {

			if ( 'refund-received' === $order_status ) {
				$this->save_freepay_post_meta( $order, $post );
				$refund_message = sprintf( __( 'Remboursement (%s) validé',
					'woocommerce-oyst' ),
					wc_price( $decimal_amount ) );
				$order->add_order_note( $refund_message );
				WC_Oyst_Freepay::log( 'Succès de la réponse asynchrone : ' . html_entity_decode( strip_tags( $refund_message ) ) );
			} else {
				$to_refund  = $default_args = array(
					'amount'         => $decimal_amount,
					'reason'         => 'Remboursement depuis FreePay',
					'order_id'       => $order_id,
					'refund_id'      => 0,
					'line_items'     => array(),
					'refund_payment' => false,
					'restock_items'  => false,
				);
				$the_refund = wc_create_refund( $to_refund );
				if ( is_wp_error( $the_refund ) ) {
					$error_string = $the_refund->get_error_message();
					$order->add_order_note( $error_string );
					WC_Oyst_Freepay::log( 'Echec du remboursement : ' . html_entity_decode( strip_tags( $error_string ) ) );
				} else {
					$this->save_freepay_post_meta( $order, $post );
					$this->force_update_post_meta( $order_id, '_freepay_incoming_request', 'yes' );
					$refund_message = sprintf( __( 'Remboursement (%s) validé depuis FreePay',
						'woocommerce-oyst' ),
						wc_price( $decimal_amount ) );
					$order->add_order_note( $refund_message );
					WC_Oyst_Freepay::log( 'Succès de la réponse asynchrone : ' . html_entity_decode( strip_tags( $refund_message ) ) );
				}
			}
		} else {
			$refund_message = sprintf( __( 'Echec du remboursement de la commande N°%s.',
				'woocommerce-oyst' ),
				$order_id );
			$order->add_order_note( $refund_message );
			WC_Oyst_Freepay::log( 'Echec de la réponse asynchrone : ' . html_entity_decode( strip_tags( $refund_message ) ) );
		}
	}

	/**
	 * Cancel or refund operation
	 *
	 * @param $order_id
	 * @param null $amount
	 * @param string $reason
	 *
	 * @return bool
	 */
	public function cancel_freepay_payment( $order_id, $amount = null, $reason = '' ) {
		$freepay_payment_id = get_post_meta( $order_id, '_freepay_payment_id', true );


		if ( isset( $freepay_payment_id ) && ! empty( $freepay_payment_id ) ) {

			if ( ! empty( $amount ) ) {
				$amount     = new OystPrice( $amount, 'EUR' );
				$oystClient = WC_Oyst_API::get_payment_api();
				$result     = $oystClient->cancelOrRefund( $freepay_payment_id, $amount );
			} else {
				$oystClient = WC_Oyst_API::get_payment_api();
				$result     = $oystClient->cancelOrRefund( $freepay_payment_id );
			}

			if ( is_array( $result['refund'] ) ) {
				if ( $result['refund']['success'] ) {
					$this->force_update_post_meta( $order_id,
						'_freepay_event_code',
						wc_clean( $result['refund']['status'] ) );
					$refund_message = sprintf( __( 'Annulation / Remboursement pour la commande N°%s en cours. %s',
						'woocommerce-oyst' ),
						$order_id,
						$reason );
					$order          = wc_get_order( $order_id );
					$order->add_order_note( $refund_message );
					WC_Oyst_Freepay::log( 'Succès de la réponse synchrone : ' . html_entity_decode( strip_tags( $refund_message ) ) );

					return true;
				}
			}

			if ( ( 'cancel-received' === $result['response'] ) ) {
				$this->force_update_post_meta( $order_id, '_freepay_event_code', wc_clean( $result['response'] ) );
				$cancel_message = sprintf( __( 'Annulation de la commande N°%s en cours. %s',
					'woocommerce-oyst' ),
					$order_id,
					$reason );
				$order          = wc_get_order( $order_id );
				$order->add_order_note( $cancel_message );
				WC_Oyst_Freepay::log( 'Succès de la réponse synchrone : ' . html_entity_decode( strip_tags( $cancel_message ) ) );

				return true;
			}
		}
		WC_Oyst_Freepay::log( "Echec de la réponse synchrone d'annulation / remboursement pour la commande N° " . $order_id );

		return false;

	}


	/**
	 * Save Data from FreePay to $order
	 *
	 * @param WC_Order $order
	 * @param $post
	 */
	protected function save_freepay_post_meta( $order, $post ) {
		$order_id     = $order->get_id();
		$decoded_post = json_decode( $post );

		$event_code = $decoded_post->notification->event_code;
		$event_date = $decoded_post->notification->event_date;
		$payment_id = $decoded_post->notification->payment_id;

		if ( ! empty( $event_code ) ) {
			$this->force_update_post_meta( $order_id, '_freepay_event_code', strtolower( wc_clean( $event_code ) ) );
		}
		if ( ! empty( $event_date ) ) {
			$this->force_update_post_meta( $order_id, '_freepay_event_date', wc_clean( $event_date ) );
		}
		if ( ! empty( $payment_id ) ) {
			$this->force_update_post_meta( $order_id, '_freepay_payment_id', wc_clean( $payment_id ) );
		}

	}

	/**
	 * Force update_post_meta
	 *
	 * @param int $order_id
	 * @param string $post_meta
	 * @param string $value
	 */
	public function force_update_post_meta( $order_id, $post_meta, $value ) {
		$current_post_meta = get_post_meta( $order_id, $post_meta, true );
		if ( ! empty( $current_post_meta ) && ( $current_post_meta != $value ) ) {
			delete_post_meta( $order_id, $post_meta );
			update_post_meta( $order_id, $post_meta, $value );
		} elseif ( empty( $current_post_meta ) ) {
			update_post_meta( $order_id, $post_meta, $value );
		}
	}

	/**
	 * Format amount in cents
	 *
	 * @param $amount
	 *
	 * @return float
	 */
	public function freepay_amount_format( $amount ) {
		$formatted_amount = round( $amount, 2 ) * 100; // In cents

		return $formatted_amount;
	}

	/**
	 * Format amount in cents
	 *
	 * @param $amount
	 *
	 * @return float
	 */
	public function freepay_decimal_format( $amount, $separator = '.' ) {
		$formatted_amount = number_format( $amount / 100, 2, $separator, ' ' );

		return $formatted_amount;
	}

}
