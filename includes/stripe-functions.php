<?php

function pw_wpsc_check_stripe_plan_exists( $plan_id ) {
	global $rcp_options;

	require_once(PW_WPSC_PLUGIN_DIR . '/stripe/Stripe.php');

	$stripe_live_secret_key = get_option( 'wpsc_stripe_live_key' );	
	$stripe_test_secret_key = get_option( 'wpsc_stripe_test_key' );	
	$payment_mode           = get_option( 'wpsc_stripe_payment_mode' );
	
	$secret_key = $payment_mode == 'test' ? $stripe_test_secret_key : $stripe_live_secret_key;
		
	Stripe::setApiKey( $secret_key );
	
	try {
		$plan = Stripe_Plan::retrieve($plan_id);
		return true;
	} catch (Exception $e) {
		return false;
	}
}

function pw_wpsc_create_stripe_plan($plan_name, $plan_id, $price, $duration_unit) {

	require_once(PW_WPSC_PLUGIN_DIR . '/stripe/Stripe.php');

	$stripe_live_secret_key = get_option( 'wpsc_stripe_live_key' );	
	$stripe_test_secret_key = get_option( 'wpsc_stripe_test_key' );	
	$payment_mode = get_option( 'wpsc_stripe_payment_mode' );
	
	$secret_key = $payment_mode == 'test' ? $stripe_test_secret_key : $stripe_live_secret_key;
	
	// get all subscription level info for this plan
	$price = $price * 100;
	
	Stripe::setApiKey($secret_key);	
	
	try {
		Stripe_Plan::create(array(
		  		"amount" => $price,
			  	"interval" => $duration_unit,
			  	"name" => $plan_name,
				"currency" => 'usd',
				"id" => $plan_id
			)
		);
		// plann successfully created
		return true;
		
	} catch (Exception $e) {
		// there was a problem
		echo '<pre>'; print_r($e); echo '</pre>';exit;
		return false;
	}
}

function pw_wpsc_cancel_stripe( $user_id ) {

	require_once(PW_WPSC_PLUGIN_DIR . '/stripe/Stripe.php');

	$stripe_live_secret_key = get_option( 'wpsc_stripe_live_key' );	
	$stripe_test_secret_key = get_option( 'wpsc_stripe_test_key' );	
	$payment_mode = get_option( 'wpsc_stripe_payment_mode' );
	
	$secret_key = $payment_mode == 'test' ? $stripe_test_secret_key : $stripe_live_secret_key;
	
	Stripe::setApiKey($secret_key);	
	
	$customer_id = pw_wpsc_get_stripe_customer_id($user_id);	
	
	try {
		
		$cu = Stripe_Customer::retrieve( $customer_id );
		$cu->cancelSubscription(array('at_period_end' => true));
		
	} catch(Exception $e) {
		wp_die('<pre>' . $e . '</pre>', 'Error');
	}
}

function pw_wpsc_stripe_set_as_customer($user_id = null, $customer) {
	
	if ( ! $user_id ) {
		global $user_ID;
		$user_id = $user_ID;
	}
	
	update_user_meta($user_id, '_pw_stripe_is_customer', 'yes');
	update_user_meta($user_id, '_pw_stripe_user_id', $customer->id);
	
}

function pw_wpsc_stripe_is_customer($user_id = null) {
	
	if(!$user_id) {
		global $user_ID;
		$user_id = $user_ID;
	}
	
	if(get_user_meta($user_id, '_pw_stripe_is_customer', true) == 'yes') {
		return true;
	}
	return false;
}

// gets the Stripe customer ID from a WP user ID 
function pw_wpsc_get_stripe_customer_id($user_id) {
	return get_user_meta($user_id, '_pw_stripe_user_id', true);
}

// gets the user object from a Stripe customer ID string
function pw_stripe_get_user_id($customer_id) {
	$user = get_users(
		array(
			'meta_key' => '_pw_stripe_user_id',
			'meta_value' => $customer_id,
			'number' => 1
		)
	);
	if($user)
		return $user[0];
	return false;
}
