<?php
class pw_wpsc_merchant_stripe extends wpsc_merchant {

	public $name = 'Stripe';

	/**
	* construct value array method, converts the data gathered by the base class code to something acceptable to the gateway
	* @access public
	*/
	function construct_value_array() {
		$this->collected_gateway_data = $this->_construct_value_array();
	}
	
	/**
	* construct value array method, converts the data gathered by the base class code to something acceptable to the gateway
	* @access private
	* @param boolean $aggregate Whether to aggregate the cart data or not. Defaults to false.
	* @return array $paypal_vars The Stripe vars
	*/
	function _construct_value_array( $aggregate = false ) {
		global $wpdb;

		$stripe_vars = array();
		
		$currency_code  = $wpdb->get_var( "SELECT `code` FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `id`='" . get_option( 'currency_type' ) . "' LIMIT 1" );

		// Store settings to be sent to Stripe
		$stripe_vars += array(
			'currency_code' => $currency_code,
			'email'         => $this->cart_data['email_address'],
			'first_name'    => $this->cart_data['billing_address']['first_name'],
			'last_name'     => $this->cart_data['billing_address']['last_name'],
			'address1'      => $this->cart_data['billing_address']['address'],
			'city'          => $this->cart_data['billing_address']['city'],
			'country'       => $this->cart_data['billing_address']['country'],
			'zip'           => $this->cart_data['billing_address']['post_code'],
			'state'         => $this->cart_data['billing_address']['state'],
			'cc_number'     => sanitize_text_field($_POST['card_number']),
			'cc_exp_month'  => sanitize_text_field($_POST['expiry']['month']),
			'cc_exp_year'   => sanitize_text_field($_POST['expiry']['year']),
			'cc_card_cvc'   => sanitize_text_field($_POST['card_code'])
		);

		// Two cases:
		// - We're dealing with a subscription
		// - We're dealing with a normal cart
		if ($this->cart_data['is_subscription']) {
			$stripe_vars += array(
				'is_recurring'=> true,
			);
			
			$reprocessed_cart_data['subscription'] = array(
				'product_name' => false,
				'product_id' => false,
				'price' => 0,
				'length' => 1,
				'unit' => 'D'
			);

			foreach ($this->cart_items as $cart_row) {
				if ($cart_row['is_recurring']) {
					$reprocessed_cart_data['subscription']['product_name'] = $cart_row['name'];
					$reprocessed_cart_data['subscription']['product_id'] = $cart_row['product_id'];
					$reprocessed_cart_data['subscription']['price'] = $cart_row['price'];
					$reprocessed_cart_data['subscription']['length'] = $cart_row['recurring_data']['rebill_interval']['length'];
					$reprocessed_cart_data['subscription']['unit'] = strtoupper($cart_row['recurring_data']['rebill_interval']['unit']);
				} else {
					$item_cost = ($cart_row['price'] + $cart_row['shipping'] + $cart_row['tax']) * $cart_row['quantity'];

					if ($item_cost > 0) {
						$reprocessed_cart_data['shopping_cart']['price'] += $item_cost;
						$reprocessed_cart_data['shopping_cart']['is_used'] = true;
					}
				}
			} // end foreach cart item
			
			$stripe_vars += array(
				'subscription_data' => $reprocessed_cart_data
			);			
			
		} else {
			$stripe_vars += array(
				'is_recurring'=> false,
			);
	
		}
		return apply_filters( 'wpsc_stripe_post_data', $stripe_vars );
	}
	
	/**
	* submit method, sends the received data to the payment gateway
	* @access public
	*/
	function submit() {

		global $purchase_log, $user_ID;
		
		include_once( PW_WPSC_PLUGIN_DIR . '/stripe/Stripe.php' );		
		
		$stripe_live_secret_key = get_option( 'wpsc_stripe_live_key' );	
		$stripe_test_secret_key = get_option( 'wpsc_stripe_test_key' );	
		$payment_mode           = get_option( 'wpsc_stripe_payment_mode' );
		
		$secret_key = $payment_mode == 'test' ? $stripe_test_secret_key : $stripe_live_secret_key;
		
		// get all subscription level info for this plan
		$price = $price * 100;
		
		Stripe::setApiKey($secret_key);			
		
		$paid = false;
			
		if ( $this->cart_data['is_subscription'] ) {
			
			// process a subscription sign up
			
			$subscription_data = $this->collected_gateway_data['subscription_data'];		
			
			$plan_id = strtolower( str_replace(' ', '_', $subscription_data['subscription']['product_name']) ) . '_' . $subscription_data['subscription']['product_id'];
			$plan_name = $subscription_data['subscription']['product_name'];
			
			if(!pw_wpsc_check_stripe_plan_exists($plan_id)) {
				
				// create the plan if it doesn't exist
				pw_wpsc_create_stripe_plan($plan_name, $plan_id, $subscription_data['subscription']['price'], strtolower( $subscription_data['subscription']['unit'] ));
				
			}
			
			try {
		
				if( $this->cart_data['has_discounts'] ) {		
		
					$customer = Stripe_Customer::create(array(
							"card" => array(
								'number' => $this->collected_gateway_data['cc_number'],
								'exp_month' => $this->collected_gateway_data['cc_exp_month'],
								'exp_year' => $this->collected_gateway_data['cc_exp_year'],
								'cvc' => $this->collected_gateway_data['cc_card_cvc']
							),
							'plan' => $plan_id,
							'email' => $this->collected_gateway_data['email'],
							'description' => $subscription_data['subscription']['product_name'] . ' for ' . $this->collected_gateway_data['first_name'] . ' ' . $this->collected_gateway_data['last_name'],
							'coupon' => $this->cart_data['cart_discount_coupon']
						)
					);	
					
				} else {
					
					$customer = Stripe_Customer::create(array(
							"card" => array(
								'number' => $this->collected_gateway_data['cc_number'],
								'exp_month' => $this->collected_gateway_data['cc_exp_month'],
								'exp_year' => $this->collected_gateway_data['cc_exp_year'],
								'cvc' => $this->collected_gateway_data['cc_card_cvc'],
								'name' => $this->collected_gateway_data['first_name'] . ' ' . $this->collected_gateway_data['last_name'],
								'address_line1' => $this->collected_gateway_data['address1'],
								'address_city' => $this->collected_gateway_data['city'],
								'address_zip' => $this->collected_gateway_data['zip'],
								'address_state' => $this->collected_gateway_data['state'],
								'address_country' => $this->collected_gateway_data['country']								
							),
							'plan' => $plan_id,
							'email' => $this->collected_gateway_data['email'],
							'description' => $subscription_data['subscription']['product_name'] . ' for ' . $this->collected_gateway_data['first_name'] . ' ' . $this->collected_gateway_data['last_name']
						)
					);						
															
				}
				
				$payment_id = $customer;				
				
				pw_wpsc_stripe_set_as_customer($user_ID, $customer);			
				
				foreach($this->cart_items as $cart_row) {
					if($cart_row['is_recurring'] == true) {
						do_action( 'wpsc_transaction_result_cart_item', array( "purchase_id" => $purchase_log['id'], "cart_item" => $cart_row, "purchase_log" => $purchase_log ) );
					}
				}				
				
				$paid = true;	
					
			} catch (Exception $e) {
				$body = $e->getJsonBody(); 
				$err = $body['error'];
				$err['message'];
				$message .= "<h3>".__( 'Please Check the Payment Results', 'wpsc_gold_cart' )."</h3>";
				$message .= __( 'Your transaction was not successful.', 'wpsc_gold_cart' ) . '<strong style="color:red">'. $err['message'] . "</strong><br /><br />";
				$errors = wpsc_get_customer_meta( 'checkout_misc_error_messages' );
				if ( ! is_array( $errors ) )
				$errors[] = $message;
				wpsc_update_customer_meta( 'checkout_misc_error_messages', $errors );
				$checkout_page_url = get_option( 'shopping_cart_url' );
				if ( $checkout_page_url ) {
				 header( 'Location: '.$checkout_page_url );
				 exit();
				}
			}
			
		} else {
		
			// process a one time payment signup	
			
			$purchase_summary = '';
			foreach($this->cart_items as $cart_row) {			
				$purchase_summary .= $cart_row['name'] . ' - ';
			}
			$purchase_summary = substr($purchase_summary, 0, -3);
			
			try {
				$charge = Stripe_Charge::create(array(
						"amount" => $this->format_price($this->cart_data['total_price']),
						"currency" => $this->collected_gateway_data['currency_code'],
						"card" => array(
							'number' => $this->collected_gateway_data['cc_number'],
							'exp_month' => $this->collected_gateway_data['cc_exp_month'],
							'exp_year' => $this->collected_gateway_data['cc_exp_year'],
							'cvc' => $this->collected_gateway_data['cc_card_cvc'],
							'name' => $this->collected_gateway_data['first_name'] . ' ' . $this->collected_gateway_data['last_name'],
							'address_line1' => $this->collected_gateway_data['address1'],
							'address_city' => $this->collected_gateway_data['city'],
							'address_zip' => $this->collected_gateway_data['zip'],
							'address_state' => $this->collected_gateway_data['state'],
							'address_country' => $this->collected_gateway_data['country']
						),
						"description" => $purchase_summary
					)
				);
				
				$payment_id = $charge;				
				
				$paid = true;
				
			} catch (Exception $e) {
					$body = $e->getJsonBody(); 
					$err = $body['error'];
					$err['message'];
					$message .= "<h3>".__( 'Please Check the Payment Results', 'wpsc_gold_cart' )."</h3>";
					$message .= __( 'Your transaction was not successful.', 'wpsc_gold_cart' ) . '<strong style="color:red">'. $err['message'] . "</strong><br /><br />";
					$errors = wpsc_get_customer_meta( 'checkout_misc_error_messages' );
					
					if ( ! is_array( $errors ) )
						$errors[] = $message;
					
					wpsc_update_customer_meta( 'checkout_misc_error_messages', $errors );
					
					$checkout_page_url = get_option( 'shopping_cart_url' );
					
					if ( $checkout_page_url ) {
					 	header( 'Location: '.$checkout_page_url );
					 	exit();
					}
			}	
		}		
		
			
		if ( $paid ) {
			$status = 3; // success
		} else {
			$status = 6; // failed
		}		
		
		$this->set_transaction_details( $stripe_payment_id, $status );
		
		transaction_results( $this->cart_data['session_id'], false );		
		
		$redirect = add_query_arg('sessionid', $this->cart_data['session_id'], $this->cart_data['transaction_results_url']);
		
		wp_redirect( $redirect ); 
		exit;
	}
	
	function format_price( $amount ) {
		return $amount * 100;
	}
}