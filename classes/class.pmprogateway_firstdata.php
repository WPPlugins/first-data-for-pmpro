<?php
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
//load classes init method
add_action( 'init', array( 'PMProGateway_firstdata', 'init' ) );

/**
 * PMProGateway_firstdata Class
 *
 * Handles First Data integration.
 *
 */
class PMProGateway_firstdata extends PMProGateway
{
	function PMProGateway( $gateway = NULL ) {
		$this->gateway = $gateway;
		return $this->gateway;
	}

	/**
	 * Run on WP init
	 *
	 */
	static function init() {
		//make sure First Data is a gateway option
		add_filter( 'pmpro_gateways', array( 'PMProGateway_firstdata', 'pmpro_gateways' ) );

		//add fields to payment settings
		add_filter( 'pmpro_payment_options', array( 'PMProGateway_firstdata', 'pmpro_payment_options' ) );
		add_filter( 'pmpro_payment_option_fields', array( 'PMProGateway_firstdata', 'pmpro_payment_option_fields' ), 10, 2 );
	}

	/**
	 * Make sure First Data is in the gateways list
	 *
	 */
	static function pmpro_gateways( $gateways ) {
		if ( empty( $gateways['firstdata'] ) )
			$gateways['firstdata'] = __( 'First Data', 'pmpro-firstdata' );

		return $gateways;
	}

	/**
	 * Get a list of payment options that the First Data gateway needs/supports.
	 *
	 */
	static function getGatewayOptions() {
		$options = array(
			'sslseal',
			'nuclear_HTTPS',
			'gateway_environment',
			'api_login',
			'security_key',
			'currency',
			'use_ssl',
			'tax_state',
			'tax_rate',
			'accepted_credit_cards'
		);

		return $options;
	}

	/**
	 * Set payment options for payment settings page.
	 *
	 */
	static function pmpro_payment_options( $options ) {
		//get example options
		$firstdata_options = PMProGateway_firstdata::getGatewayOptions();

		//merge with others.
		$options = array_merge( $firstdata_options, $options );

		return $options;
	}

	/**
	 * Display fields for First Data options.
	 *
	 */
	static function pmpro_payment_option_fields( $values, $gateway ) {
		?>
		<tr class="pmpro_settings_divider gateway gateway_firstdata" <?php if ( $gateway != 'firstdata' ) { ?>style="display: none;"<?php } ?>>
			<td colspan="2">
				<?php _e( 'First Data Settings', 'pmpro-firstdata' ); ?>
			</td>
		</tr>
		<tr class="gateway gateway_firstdata">
			<td><img src="<?php echo PMPRO_FIRSTDATAGATEWAY_URL ?>/images/fd_logo.png" /></td>
			<td>
				First Data makes accepting credit cards simple.  Accept all major credit cards including Visa, MasterCard, American Express, Discover, JCB, and Diners Club.<br>
				A compatible First Data merchant account is required for this plugin to function properly.<br>
				<a href="http://www.authnetsource.com/pmpro?payeezy=hide&pid=df63ff78f8d6a3e8" target="_blank" class="button-primary" style="margin-top:10px;">Click Here To Sign Up!</a>
			</td>
		</tr>
		<tr class="gateway gateway_firstdata" <?php if ( $gateway != 'firstdata' ) { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="api_login"><?php _e( 'API Login', 'pmpro-firstdata' );?>:</label>
			</th>
			<td>
				<input type="text" id="api_login" name="api_login" size="60" value="<?php echo esc_attr( $values['api_login'] )?>" />
			</td>
		</tr>
		<tr class="gateway gateway_firstdata" <?php if ( $gateway != 'firstdata' ) { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label for="security_key"><?php _e( 'Security Key', 'pmpro-firstdata' );?>:</label>
			</th>
			<td>
				<input type="text" id="security_key" name="security_key" size="60" value="<?php echo esc_attr( $values['security_key'] )?>" />
			</td>
		</tr>
		<tr class="gateway gateway_firstdata" <?php if ( $gateway != 'firstdata' ) { ?>style="display: none;"<?php } ?>>
			<th scope="row" valign="top">
				<label><?php _e( 'Silent Post URL', 'pmpro-firstdata' );?>:</label>
			</th>
			<td>
				<p><?php _e( 'To fully integrate with First Data, be sure to set your Silent Post URL to', 'pmpro-firstdata' );?> <pre><?php echo admin_url( 'admin-ajax.php' ) . "?action=authnet_silent_post";?></pre></p>
			</td>
		</tr>
		<?php
	}

	function process( &$order ) {
		//check for initial payment
		if ( floatval( $order->InitialPayment ) == 0 ) {
			//auth first, then process
			if ( $this->authorize( $order ) ) {
				$this->void( $order );
				if ( !pmpro_isLevelTrial( $order->membership_level ) ) {
					//subscription will start today with a 1 period trial (initial payment charged separately)
					$order->ProfileStartDate = date( "Y-m-d" ) . "T0:0:0";
					$order->TrialBillingPeriod = $order->BillingPeriod;
					$order->TrialBillingFrequency = $order->BillingFrequency;
					$order->TrialBillingCycles = 1;
					$order->TrialAmount = 0;

					//add a billing cycle to make up for the trial, if applicable
					if ( !empty( $order->TotalBillingCycles ) ) {
						$order->TotalBillingCycles++;
					}
				} elseif ( $order->InitialPayment == 0 && $order->TrialAmount == 0 ) {
					//it has a trial, but the amount is the same as the initial payment, so we can squeeze it in there
					$order->ProfileStartDate = date( "Y-m-d" ) . "T0:0:0";
					$order->TrialBillingCycles++;

					//add a billing cycle to make up for the trial, if applicable
					if ( $order->TotalBillingCycles ) {
						$order->TotalBillingCycles++;
					}
				} else {
					//add a period to the start date to account for the initial payment
					$order->ProfileStartDate = date( "Y-m-d", strtotime( "+ " . $order->BillingFrequency . " " . $order->BillingPeriod, current_time( "timestamp" ) ) ) . "T0:0:0";
				}

				$order->ProfileStartDate = apply_filters( 'pmpro_profile_start_date', $order->ProfileStartDate, $order );
				return $this->subscribe( $order );
			} else {
				if ( empty( $order->error ) ) {
					$order->error = __( 'Unknown error: Authorization failed.', 'pmpro-firstdata' );
				}
				return false;
			}
		} else {
			//charge first payment
			if ( $this->charge( $order ) ) {
				//set up recurring billing
				if ( pmpro_isLevelRecurring($order->membership_level ) ) {
					if ( !pmpro_isLevelTrial( $order->membership_level ) ) {
						//subscription will start today with a 1 period trial
						$order->ProfileStartDate = date( "Y-m-d" ) . "T0:0:0";
						$order->TrialBillingPeriod = $order->BillingPeriod;
						$order->TrialBillingFrequency = $order->BillingFrequency;
						$order->TrialBillingCycles = 1;
						$order->TrialAmount = 0;

						//add a billing cycle to make up for the trial, if applicable
						if ( !empty( $order->TotalBillingCycles ) ) {
							$order->TotalBillingCycles++;
						}
					} elseif ( $order->InitialPayment == 0 && $order->TrialAmount == 0 ) {
						//it has a trial, but the amount is the same as the initial payment, so we can squeeze it in there
						$order->ProfileStartDate = date( "Y-m-d" ) . "T0:0:0";
						$order->TrialBillingCycles++;

						//add a billing cycle to make up for the trial, if applicable
						if ( !empty( $order->TotalBillingCycles ) ) {
							$order->TotalBillingCycles++;
						}
					} else {
						//add a period to the start date to account for the initial payment
						$order->ProfileStartDate = date( "Y-m-d", strtotime( "+ " . $this->BillingFrequency . " " . $this->BillingPeriod, current_time( "timestamp" ) ) ) . "T0:0:0";
					}

					$order->ProfileStartDate = apply_filters( 'pmpro_profile_start_date', $order->ProfileStartDate, $order );
					if ( $this->subscribe( $order ) ) {
						return true;
					} else {
						if ( $this->void( $order ) ) {
							if ( !$order->error ) {
								$order->error = __( 'Unknown error: Payment failed.', 'pmpro-firstdata' );
							}
						} else {
							if ( !$order->error ) {
								$order->error = __( 'Unknown error: Payment failed.', 'pmpro-firstdata' );
							}

							$order->error .= " " . __( 'A partial payment was made that we could not void. Please contact the site owner immediately to correct this.', 'pmpro-firstdata' );
						}

						return false;
					}
				} else {
					//only a one time charge
					$order->status = 'success';	//saved on checkout page
					return true;
				}
			} else {
				if ( empty( $order->error ) ) {
					$order->error = __( 'Unknown error: Payment failed.', 'pmpro-firstdata' );
				}

				return false;
			}
		}
	}

	/*
		Run an authorization at the gateway.

		Required if supporting recurring subscriptions
		since we'll authorize $1 for subscriptions
		with a $0 initial payment.
	*/
	function authorize( &$order ) {
		//create a code for the order
		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}

		//code to authorize with gateway and test results would go here
		$amount = '1.00';
		$firstdata = new PMProGateway_firstdata_api();
		$response = $firstdata->authorize( $order, $amount );

		if ( isset( $response->transactionResponse->responseCode ) && '1' == $response->transactionResponse->responseCode ) {
			$order->payment_transaction_id = $response->transactionResponse->transId;
			$order->updateStatus( 'authorized' );
			return true;
		} else {
			if ( isset( $response->transactionResponse->messages ) ) {
				$order->errorcode = $response->transactionResponse->messages[0]->code;
				$order->error = $response->transactionResponse->messages[0]->description;
				$order->shorterror = $response->transactionResponse->messages[0]->description;
			} else {
				$order->errorcode = $response->messages->message[0]->code;
				$order->error = $response->messages->message[0]->text;
				$order->shorterror = $response->messages->message[0]->text;
			}
			return false;
		}
	}

	/*
		Void a transaction at the gateway.

		Required if supporting recurring transactions
		as we void the authorization test on subs
		with a $0 initial payment and void the initial
		payment if subscription setup fails.
	*/
	function void( &$order ) {
		//need a transaction id
		if ( empty( $order->payment_transaction_id ) ) {
			return false;
		}

		//code to void an order at the gateway and test results would go here
		$firstdata = new PMProGateway_firstdata_api();
		$response = $firstdata->void( $order );

		if ( isset( $response->transactionResponse->responseCode ) && '1' == $response->transactionResponse->responseCode ) {
			$order->payment_transaction_id = $response->transactionResponse->transId;
			$order->updateStatus( 'voided' );
			return true;
		} else {
			if ( isset( $response->transactionResponse->messages ) ) {
				$order->errorcode = $response->transactionResponse->messages[0]->code;
				$order->error = $response->transactionResponse->messages[0]->description;
				$order->shorterror = $response->transactionResponse->messages[0]->description;
			} else {
				$order->errorcode = $response->messages->message[0]->code;
				$order->error = $response->messages->message[0]->text;
				$order->shorterror = $response->messages->message[0]->text;
			}
			return false;
		}
	}

	/*
		Make a charge at the gateway.

		Required to charge initial payments.
	*/
	function charge( &$order ) {
		//create a code for the order
		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}

		//code to charge with gateway and test results would go here
		//what amount to charge?
		$amount = $order->InitialPayment;

		//tax
		$order->subtotal = $amount;
		$tax = $order->getTax( true );
		$amount = round( (float)$order->subtotal + (float)$tax, 2 );

		$firstdata = new PMProGateway_firstdata_api();
		$response = $firstdata->purchase( $order, $amount );

		if ( isset( $response->transactionResponse->responseCode ) && '1' == $response->transactionResponse->responseCode ) {
			$order->payment_transaction_id = $response->transactionResponse->transId;
			$order->updateStatus( 'success' );
			return true;
		} else {
			if ( isset( $response->transactionResponse->messages ) ) {
				$order->errorcode = $response->transactionResponse->messages[0]->code;
				$order->error = $response->transactionResponse->messages[0]->description;
				$order->shorterror = $response->transactionResponse->messages[0]->description;
			} else {
				$order->errorcode = $response->messages->message[0]->code;
				$order->error = $response->messages->message[0]->text;
				$order->shorterror = $response->messages->message[0]->text;
			}
			return false;
		}
	}

	/*
		Setup a subscription at the gateway.

		Required if supporting recurring subscriptions.
	*/
	function subscribe( &$order ) {
		//create a code for the order
		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}

		//filter order before subscription. use with care.
		$order = apply_filters( 'pmpro_subscribe_order', $order, $this );

		//code to setup a recurring subscription with the gateway and test results would go here
		$amount = $order->PaymentAmount;
		$amount_tax = $order->getTaxForPrice($amount);
		$amount = round((float)$amount + (float)$amount_tax, 2);
		
		$firstdata = new PMProGateway_firstdata_api();
		$response = $firstdata->subscribe( $order, $amount );

		if ( isset( $response->messages->resultCode ) && 'Ok' == $response->messages->resultCode ) {
			$order->status = 'success';
			$order->subscription_transaction_id = $response->subscriptionId;
			return true;
		} else {
			if ( isset( $response->messages->resultCode ) ) {
				$order->status = 'error';
				$order->errorcode = $response->messages->message[0]->code;
				$order->error = $response->messages->message[0]->text;
				$order->shorterror = $response->messages->message[0]->text;
			} else {
				$order->status = 'error';
				$order->error = 'Could not connect to payment gateway';
				$order->shorterror = 'Could not connect to payment gateway';
			}
			return false;
		}
	}

	/*
		Update billing at the gateway.

		Required if supporting recurring subscriptions and
		processing credit cards on site.
	*/
	function update( &$order ) {
		//code to update billing info on a recurring subscription at the gateway and test results would go here
		$firstdata = new PMProGateway_firstdata_api();
		$response = $firstdata->update( $order );

		if ( isset( $response->messages->resultCode ) && 'Ok' == $response->messages->resultCode ) {
			return true;
		} else {
			if ( isset( $response->messages->resultCode ) ) {
				$order->status = 'error';
				$order->errorcode = $response->messages->message[0]->code;
				$order->error = $response->messages->message[0]->text;
				$order->shorterror = $response->messages->message[0]->text;
			} else {
				$order->status = 'error';
				$order->error = 'Could not connect to payment gateway';
				$order->shorterror = 'Could not connect to payment gateway';
			}
			return false;
		}
	}

	/*
		Cancel a subscription at the gateway.

		Required if supporting recurring subscriptions.
	*/
	function cancel( &$order ) {
		//require a subscription id
		if ( empty( $order->subscription_transaction_id ) ) {
			return false;
		}

		//code to cancel a subscription at the gateway and test results would go here
		$firstdata = new PMProGateway_firstdata_api();
		$response = $firstdata->cancel( $order );

		if ( isset( $response->messages->resultCode ) && 'Ok' == $response->messages->resultCode ) {
			$order->updateStatus('cancelled');
			return true;
		} else {
			if ( isset( $response->messages->resultCode ) ) {
				$order->errorcode = $response->messages->message[0]->code;
				$order->error = $response->messages->message[0]->text;
				$order->shorterror = $response->messages->message[0]->text;
			} else {
				$order->status = 'error';
				$order->error = 'Could not connect to payment gateway';
				$order->shorterror = 'Could not connect to payment gateway';
			}
			return false;
		}
	}

	/*
		Get subscription status at the gateway.

		Optional if you have code that needs this or
		want to support addons that use this.
	*/
	function getSubscriptionStatus( &$order ) {
		//require a subscription id
		if ( empty( $order->subscription_transaction_id ) ) {
			return false;
		}

		//code to get subscription status at the gateway and test results would go here
		$firstdata = new PMProGateway_firstdata_api();
		$response = $firstdata->get_subscription( $order );

		if ( isset( $response->messages->resultCode ) && 'Ok' == $response->messages->resultCode ) {
			$status = $response->status;
			return $status;
		} else {
			return false;
		}
	}
}
