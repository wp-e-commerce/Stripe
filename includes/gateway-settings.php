<?php
function pw_wpsc_stripe_settings_form() {
	global $wpdb;
	
	$stripe_live_secret_key = get_option( 'wpsc_stripe_live_key' );	
	$stripe_test_secret_key = get_option( 'wpsc_stripe_test_key' );	
	$payment_mode           = get_option( 'wpsc_stripe_payment_mode' );	
	
   // make sure the stores currency is supported by Stripe
	
	if ( ( isset ( $stripe_live_secret_key ) && $stripe_live_secret_key != '' ) || ( isset ( $stripe_test_secret_key ) && $stripe_test_secret_key != '' ) ) {
		include_once(PW_WPSC_PLUGIN_DIR . '/stripe/Stripe.php');

	   $curr_supported = false;
	   $currency_code  = $wpdb->get_var( "SELECT `code` FROM `" . WPSC_TABLE_CURRENCY_LIST .
										"` WHERE `id`='" . get_option( 'currency_type' ) . "' LIMIT 1" );
		
		$secret_key = $payment_mode == 'test' ? $stripe_test_secret_key : $stripe_live_secret_key;
		Stripe::setApiKey($secret_key);
		$account_info = Stripe_Account::retrieve();
		
		if (in_array(strtolower($currency_code), $account_info->currencies_supported) ) {
			$curr_supported = true;
		}	
	}
	
	$output = "<tr>\n\r
				<td>Stripe Live Secret Key</td>
				<td><input type='text' size='30' value='" . $stripe_live_secret_key . "' name='wpsc_stripe_live_key' /></td>
			</tr>
			<tr>
				<td>Stripe Test Secret Key</td>
				<td><input type='text' size='30' value='" . $stripe_test_secret_key . "' name='wpsc_stripe_test_key' /></td>
			</tr>
			<tr>
				<td>Test Mode?</td>
				<td>
					<select name='wpsc_stripe_payment_mode'>
						<option value='test' " . selected('test', $payment_mode, false) . ">Test mode</option>
						<option value='live' " . selected('live', $payment_mode, false) . ">Live Mode</option>
					</select> 
				</td>
			</tr>\n\r";
			
    if( $stripe_live_secret_key == '' || $stripe_test_secret_key == '' )
    {
        $output .='
        <tr>
        	<td colspan="2">
        	<strong style="color:red;"> '.__( 'Please enter API info to validate store currency against Stripe account', 'wpsc' ). ' </strong>
        	</td>
        </tr>
        ';		

    } elseif ( ! $curr_supported ) {
       $output .='
       <tr>
        	<td>
        		<strong style="color:red;">'. __( 'Your Selected Currency is not supported by Stripe,
        		to use Stripe, go the the store general settings and under &quot;Currency Type&quot; select one
        		of the currencies listed on the right.', 'wpsc' ) .' </strong>
         	</td>
        	<td>
       			<ul>';
        $country_list  = $wpdb->get_results( "SELECT `country` FROM `" . WPSC_TABLE_CURRENCY_LIST .
        									 "` WHERE `code` IN( 'USD','CAD') ORDER BY `country` ASC" ,'ARRAY_A');
        foreach($country_list as $country){
            $output .= '<li>'. $country['country'].'</li>';
        }
        $output .= '</ul>
        	</td>
        </tr>';
	} else {
        $output .='
        <tr>
        	<td colspan="2">
        	<strong style="color:green;"> '.__( 'Your Selected Currency will work with Stripe', 'wpsc' ). ' </strong>
        	</td>
        </tr>
        ';
    }
	
	return $output;
}

function pw_wpsc_save_stripe_settings() {
	
	$options = array(
		'wpsc_stripe_live_key',
		'wpsc_stripe_test_key',
		'wpsc_stripe_payment_mode'
	);
	
	foreach ( $options as $option ) {
		if ( ! empty( $_POST[ $option ] ) ) {
			update_option( $option, sanitize_text_field( $_POST[ $option ] ) );
		}
	}
	
	return true;
}