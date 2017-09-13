<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
return apply_filters( 'woocommerce-oyst-freepay',
	array(
		'enabled'          => array(
			'title'       => __( 'Activer / Désactiver', 'woocommerce-oyst' ),
			'label'       => __( 'Activer Freepay', 'woocommerce-oyst' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'yes',
		),
		'mode'             => array(
			'title'       => __( 'Environnement', 'woocommerce-oyst' ),
			'type'        => 'select',
			'class'       => 'wc_oyst_environnement',
			'description' => __( 'Choix du mode: production ou pré-production', 'woocommerce-oyst' ),
			'default'     => 'prod',
			'desc_tip'    => true,
			'options'     => array(
				'prod'    => __( 'prod', 'woocommerce-oyst' ),
				'preprod' => __( 'preprod', 'woocommerce-oyst' ),
				'custom'  => __( 'custom', 'woocommerce-oyst' ),
			),
		),
		'prod_key'         => array(
			'title'       => __( "Clé d'Api production", 'woocommerce-oyst' ),
			'type'        => 'text',
			'class'       => 'wc_oyst_prod_key',
			'description' => __( 'Retrouvez votre clé d\'API sur votre compte FreePay.', 'woocommerce-oyst' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'preprod_key'      => array(
			'title'       => __( "Clé d'Api préproduction", 'woocommerce-oyst' ),
			'type'        => 'text',
			'class'       => 'wc_oyst_preprod_key',
			'description' => __( 'Retrouvez votre clé d\'API sur votre compte FreePay.', 'woocommerce-oyst' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'custom_key'       => array(
			'title'       => __( "Clé d'Api custom", 'woocommerce-oyst' ),
			'type'        => 'text',
			'class'       => 'wc_oyst_custom_key',
			'description' => __( 'Retrouvez votre clé d\'API sur votre compte FreePay.', 'woocommerce-oyst' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'custom_url'       => array(
			'title'       => __( 'URL personnalisée', 'woocommerce-oyst' ),
			'type'        => 'text',
			'class'       => 'wc_oyst_custom_url',
			'description' => __( 'URL use for Freepay test.', 'woocommerce-oyst' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'title'            => array(
			'title'       => __( 'Titre', 'woocommerce-oyst' ),
			'type'        => 'text',
			'description' => __( "Titre que l'utilisateur verra durant la commande.", 'woocommerce-oyst' ),
			'default'     => __( 'Carte bancaire', 'woocommerce-oyst' ),
			'desc_tip'    => true,
		),
		'description'      => array(
			'title'       => __( 'Description', 'woocommerce-oyst' ),
			'type'        => 'text',
			'description' => __( "Description que l'utilisateur verra durant la commande.", 'woocommerce-oyst' ),
			'default'     => __( 'Payer avec votre carte bancaire.', 'woocommerce-oyst' ),
			'desc_tip'    => true,
		),
		'debug'            => array(
			'title'       => __( 'Log', 'woocommerce-oyst' ),
			'label'       => __( 'Sauvegarder les message de debug', 'woocommerce-oyst' ),
			'type'        => 'checkbox',
			'description' => __( 'Sauvegarder les message de debug dans Woocommerce.', 'woocommerce-oyst' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'notification_url' => array(
			'title'       => __( 'URL de notification (laisser vide par défaut)', 'woocommerce-oyst' ),
			'type'        => 'text',
			'description' => __( 'URL de notification pour les retours de FreePay, laisser vide par défaut.',
				'woocommerce-oyst' ),
			'default'     => '',
			'desc_tip'    => true,
		),
	)
);
