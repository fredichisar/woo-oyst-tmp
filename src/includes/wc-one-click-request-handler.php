<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


$ref_id = $_POST['product_reference'];


if ( isset( $ref_id ) && ! empty( $ref_id ) ) {

	require_once( "class-wc-oyst-one-click-request.php" );
	$request = new WC_Oyst_One_Click_Request();

	$url = $request->get_request_url( $ref_id );

	return $url;


}
