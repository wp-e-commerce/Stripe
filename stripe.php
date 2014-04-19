<?php
/*
Plugin Name: Stripe Payment Gateway for WP e-Commerce
Plugin URI: http://www.getshopped.org
Description: Integrate the Stripe Payment Gateway into WordPress and WP e-Commerce.
Version: 2.1
Author: GetShopped.org
Author URI:  http://www.getshopped.org
*/

defined( 'WPINC' ) || die;

class WPSC_Stripe {

	private static $instance;

	private function __construct() {}

	public static function get_instance() {

		if  ( ! isset( self::$instance ) && ! ( self::$instance instanceof WPSC_Stripe ) ) {
			self::$instance = new WPSC_Stripe;

			self::define_constants();
			self::includes();

			self::add_actions();
			self::add_filters();
		}

		return self::$instance;
	}

	public static function define_constants() {

		define( 'PW_WPSC_PLUGIN_DIR', dirname( __FILE__ ) );
	}

	public static function includes() {
		include_once PW_WPSC_PLUGIN_DIR . '/includes/stripe-functions.php';
		include_once PW_WPSC_PLUGIN_DIR . '/includes/checkout-fields.php';
		include_once PW_WPSC_PLUGIN_DIR . '/includes/gateway-settings.php';
	}

	public static function add_actions() {
		add_action( 'wpsc_init', array( $this, 'init' ) );

		/* Defined in checkout-fields.php */
		add_action( 'wpsc_init', 'pw_wpsc_stripe_checkout_fields' );
		
		/* Defined in stripe-functions.php */
		add_action( 'wpec_members_deactivate_subscription', 'pw_wpsc_cancel_stripe' );
	}

	public static function add_filters() {
		add_filter( 'wpsc_merchants_modules', array( $this, 'register_gateway' ), 50 );	
	}

	public function init() {
		include_once PW_WPSC_PLUGIN_DIR . '/pw_wpsc_stripe_merchant.php';
	}

	public function register_gateway( $gateways ) {
		$num = max( $gateways ) + 1;
		
		$gateways[ $num ] = array(
			'name'                   => 'Stripe',
			'api_version'            => 2.0,
			'has_recurring_billing'  => true,
			'display_name'           => __( 'Credit Card' ),	
			'image'                  => WPSC_URL . '/images/cc.gif',
			'wp_admin_cannot_cancel' => false,
			'requirements' => array(
				'php_version' => 5.0
			),
			'class_name'      => 'pw_wpsc_merchant_stripe',
			'form'            => 'pw_wpsc_stripe_settings_form',
			'submit_function' => 'pw_wpsc_save_stripe_settings',
			'internalname'    => 'wpsc_stripe'
		);

		return $gateways; 
	}

}

add_action( 'plugins_loaded', 'WPSC_Stripe::get_instance' );