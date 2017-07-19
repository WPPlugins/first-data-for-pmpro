<?php
if ( !defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
/**
 * PMProGateway_firstdata_api Class
 *
 * First Data API.
 *
 */
class PMProGateway_firstdata_api {
	private $_url;

	/**
	 * Authorize credit card
	 *
	 */
	public function authorize( $order, $amount ) {
		$payload = $this->get_payload( $order, $amount, 'authOnlyTransaction' );
		$response = json_decode( $this->post_transaction( $payload ) );
		return $response;
	}

	/**
	 * Charge credit card
	 *
	 */
	public function purchase( $order, $amount ) {
		$payload = $this->get_payload( $order, $amount, 'authCaptureTransaction' );
		$response = json_decode( $this->post_transaction( $payload ) );
		return $response;
	}

	/**
	 * Void credit card transaction
	 *
	 */
	public function void( $order ) {
		$payload = $this->get_payload( $order, null, 'voidTransaction' );
		$response = json_decode( $this->post_transaction( $payload ) );
		return $response;
	}

	/**
	 * Create subscription
	 *
	 */
	public function subscribe( $order, $amount ) {
		$payload = $this->get_payload( $order, $amount, 'createSubscription' );
		$response = json_decode( $this->post_transaction( $payload ) );
		return $response;
	}

	/**
	 * Update card details for subscription
	 *
	 */
	public function update( $order ) {
		$payload = $this->get_payload( $order, null, 'updateSubscription' );
		$response = json_decode( $this->post_transaction( $payload ) );
		return $response;
	}

	/**
	 * Cancel subscription
	 *
	 */
	public function cancel( $order ) {
		$payload = $this->get_payload( $order, null, 'cancelSubscription' );
		$response = json_decode( $this->post_transaction( $payload ) );
		return $response;
	}

	/**
	 * Get subscription details
	 *
	 */
	public function get_subscription( $order ) {
		$payload = $this->get_payload( $order, null, 'getSubscription' );
		$response = json_decode( $this->post_transaction( $payload ) );
		return $response;
	}

	/**
	 * Generate JSON payload for api call
	 *
	 */
	public function get_payload( $order, $amount, $transaction_type ) {
		$gateway_environment = pmpro_getOption("gateway_environment");
		if ( 'live' == $gateway_environment ) {
			$this->_url = 'https://api.authorize.net/xml/v1/request.api';
			$api_login = pmpro_getOption('api_login');
			$security_key = pmpro_getOption('security_key');
		} else {
			$this->_url = 'https://apitest.authorize.net/xml/v1/request.api';
			$api_login = '57Uqk3stH8';
			$security_key = '8yy229eR9S643mSz';
		}

		if ( 'authOnlyTransaction' == $transaction_type || 'authCaptureTransaction' == $transaction_type ) {
			$data = array(
				'createTransactionRequest' => array(
					'merchantAuthentication' => array(
						'name' => $api_login,
						'transactionKey' => $security_key,
					),
					'refId' => $order->code,
					'transactionRequest' => array(
						'transactionType' => $transaction_type,
						'amount' => number_format( $amount, '2', '.', '' ),
						'payment' => array(
							'creditCard' => array(
								'cardNumber' => $order->accountnumber,
								'expirationDate' => $order->ExpirationDate,
								'cardCode' => $order->CVV2,
							),
						),
						'order' => array(
							'invoiceNumber' => $order->code,
						),
						'billTo' => array(
							'firstName' => $order->FirstName,
							'lastName' => $order->LastName,
							'address' => substr( $order->Address1, 0, 30 ),
							'zip' => substr( $order->billing->zip, 0, 10 ),
						),
					),
				),
			);
		} elseif ( 'createSubscription' == $transaction_type ) {
			$length = (int)$order->BillingFrequency;
			if ( $order->BillingPeriod == 'Month' ) {
				$unit = 'months';
			} elseif ( $order->BillingPeriod == 'Day' ) {
				$unit = 'days';
			} elseif ( $order->BillingPeriod == 'Year' && $order->BillingFrequency == 1 ) {
				$unit = 'months';
				$length = 12;
			} elseif ( $order->BillingPeriod == 'Week' ) {
				$unit = 'days';
				$length = $length * 7;
			} else {
				return false;	//only months and days are supported
			}
			$start_date = substr( $order->ProfileStartDate, 0, 10 );
			if( !empty( $order->TotalBillingCycles ) ) {
				$total_occurrences = (int)$order->TotalBillingCycles;
			} else {
				$total_occurrences = 9999;
			}
			if ( isset( $order->TrialBillingCycles ) ) {
				$trial_occurrences = (int)$order->TrialBillingCycles;
			} else {
				$trial_occurrences = 0;
			}
			if ( isset( $order->TrialAmount ) ) {
				$trial_amount = $order->TrialAmount;
			} else {
				$trial_amount = NULL;
			}
			$trial_tax = $order->getTaxForPrice( $trial_amount );
			$trial_amount = round( (float)$trial_amount + (float)$trial_tax, 2 );
				
			$data = array(
				'ARBCreateSubscriptionRequest' => array(
					'merchantAuthentication' => array(
						'name' => $api_login,
						'transactionKey' => $security_key,
					),
					'refId' => $order->code,
					'subscription' => array(
						'name' => $order->membership_name,
						'paymentSchedule' => array(
							'interval' => array(
								'length' => $length,
								'unit' => $unit,
							),
							'startDate' => $start_date,
							'totalOccurrences' => $total_occurrences,
							'trialOccurrences' => $trial_occurrences,
						),
						'amount' => number_format( $amount, '2', '.', '' ),
						'trialAmount' => number_format( $trial_amount, '2', '.', '' ),
						'payment' => array(
							'creditCard' => array(
								'cardNumber' => $order->accountnumber,
								'expirationDate' => $order->ExpirationDate_YdashM,
								'cardCode' => $order->CVV2,
							),
						),
						'order' => array(
							'invoiceNumber' => $order->code,
						),
						'billTo' => array(
							'firstName' => $order->FirstName,
							'lastName' => $order->LastName,
							'address' => substr( $order->Address1, 0, 30 ),
							'zip' => substr( $order->billing->zip, 0, 10 ),
						),
					),
				),
			);
		} elseif ( 'updateSubscription' == $transaction_type ) {
			$data = array(
				'ARBUpdateSubscriptionRequest' => array(
					'merchantAuthentication' => array(
						'name' => $api_login,
						'transactionKey' => $security_key,
					),
					'refId' => $order->code,
					'subscriptionId' => $order->subscription_transaction_id,
					'subscription' => array(
						'payment' => array(
							'creditCard' => array(
								'cardNumber' => $order->accountnumber,
								'expirationDate' => $order->ExpirationDate_YdashM,
								'cardCode' => $order->CVV2,
							),
						),
						'billTo' => array(
							'firstName' => $order->FirstName,
							'lastName' => $order->LastName,
							'address' => substr( $order->Address1, 0, 30 ),
							'zip' => substr( $order->billing->zip, 0, 10 ),
						),
					),
				),
			);
		} elseif ( 'cancelSubscription' == $transaction_type ) {
			$data = array(
				'ARBCancelSubscriptionRequest' => array(
					'merchantAuthentication' => array(
						'name' => $api_login,
						'transactionKey' => $security_key,
					),
					'refId' => $order->code,
					'subscriptionId' => $order->subscription_transaction_id,
				),
			);
		} elseif ( 'getSubscription' == $transaction_type ) {
			$data = array(
				'ARBGetSubscriptionStatusRequest' => array(
					'merchantAuthentication' => array(
						'name' => $api_login,
						'transactionKey' => $security_key,
					),
					'refId' => $order->code,
					'subscriptionId' => $order->subscription_transaction_id,
				),
			);
		} else {
			$data = array(
				'createTransactionRequest' => array(
					'merchantAuthentication' => array(
						'name' => $api_login,
						'transactionKey' => $security_key,
					),
					'refId' => $order->code,
					'transactionRequest' => array(
						'transactionType' => $transaction_type,
						'refTransId' => $order->payment_transaction_id,
					),
				),
			);
		}
		return json_encode( $data );
	}

	/**
	 * Send API request to gateway
	 *
	 */
	public function post_transaction( $payload ) {
		$request = curl_init();
		curl_setopt( $request, CURLOPT_URL, $this->_url );
		curl_setopt( $request, CURLOPT_POST, true );
		curl_setopt( $request, CURLOPT_POSTFIELDS, $payload );
		curl_setopt( $request, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $request, CURLOPT_HEADER, false );
		curl_setopt(
			$request, CURLOPT_HTTPHEADER, array(
				'Content-Type: application/json',
			)
		);
		$response = curl_exec( $request );
		if ( false === $response ) {
			return new WP_Error( 'curl_error', __( 'cURL Error: ', 'pmpro-firstdata' ) . curl_error( $request ) );
		}
		curl_close( $request );
		$response = preg_replace( '/\xEF\xBB\xBF/', '', $response );
		return $response;
	}
}
