<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Main class used to prepare a case for Signifyd according to requirements, get response from Signifyd on the case and update WooCommerce order based on case details received from Signifyd

class SignifyInit {
	
	public function __construct() {
        add_action('woocommerce_thankyou', array($this, 'checkpayment'), 10, 1); 
        $logger = new Logger();
	}

    // Declare function to check payment method when "woocommerce_thankyou" action is triggered

     public function checkpayment($order_id){
		plugin_log("Signifyd Initiated for order ID".$order_id."<br>");
        $order = wc_get_order($order_id);
        $payment_method = $order->get_payment_method();
		 
        if ($payment_method === 'nmi') {
            $this->get_order_details($order_id);
        }

    }

    // Declare function to fetch NMI transaction details through transaction ID

     public function getNmiResp($transaction_id) {
         
        // Perform NMI request here
        $settings = get_option('signfyd_opt');
        $nmiKey = $settings["NmiKey"];
        plugin_log("Starting the NMI call through NMI Key ".$nmiKey." & Transaction ID ".$transaction_id. "<br>");
        //ready curl request 
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://secure.nmi.com/api/query.php?security_key=".$nmiKey."&transaction_id=".$transaction_id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        ));
        $response = curl_exec($curl);
        plugin_log("NMI unfiltered response back from the getNmiResp function");
        plugin_log($response);
        curl_close($curl);

        $xml_response = simplexml_load_string($response);
        $json_encoded_response = json_encode($xml_response);
        $get_response = json_decode($json_encoded_response,true);

        // Return the response
        plugin_log("NMI response back from the getNmiResp function");
        plugin_log($get_response);
        return $get_response;  
    }

    // Declare function to create a case for Signifyd

     public function execute_sales_request($jsonRequest) {        
        $settings = get_option('signfyd_opt');
        $signifydKey = $settings["signifyApi"];
        $curl = curl_init();
        curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.signifyd.com/v3/orders/events/sales',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS =>$jsonRequest,
        CURLOPT_HTTPHEADER => array(
            "Authorization: Basic ".base64_encode($signifydKey.": "),
            "Content-Type: application/json",
            "Cookie: SIG_SESSION=1083a665064b0e5c377dc2a1bf528701dd8c3049-pac4jSessionId=".$settings['signifySessionId']
            ),
        ));

        $response = curl_exec($curl);
        curl_close($curl);
        $get_response = json_decode($response,true);

        return $get_response;
    }

    // Declare function to fetch decision made by Signifyd for the case

    public function execute_decisions_request($data) {
        plugin_log("Checking if data got populated inside execute_decisions_request function. <br>");
        plugin_log($data);
        $curl = curl_init();
        $settings = get_option('signfyd_opt');
        $apiKey = $settings["signifyApi"];
        plugin_log("Checking if apiKey got populated inside execute_decisions_request function. <br>");
        plugin_log($apiKey);
        curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.signifyd.com/v3/orders/".$data."/decisions",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(      
            "Authorization: Basic ".base64_encode($apiKey.": "),
        ),
        ));
        $response = curl_exec($curl);
        curl_close($curl);
        $get_response = json_decode($response,true);
        plugin_log("Logging the api response inside execute_decisions_request function. <br>");
        plugin_log($get_response);
        return $get_response;
    }

    // Declare function to update order according to the decision made by the Signifyd case

     public function update_order_meta($order_id, $jsonRequest){
        $order_data = get_post_meta( $order_id, '_save_order_id', true );
        
        if($order_data || $order_id){
            $reponse  = $this->execute_sales_request($jsonRequest);
        
        if(isset($reponse['orderId']) && !empty($reponse['orderId'])){
            $order = wc_get_order($order_id);
            update_post_meta($order_id, '_save_order_id', $reponse['orderId']);
            $signifyResponse =  $this->execute_decisions_request($order_id);
            $obj = new Essentials(Signifyd_Meta);
                    $signifyResponseValue = $signifyResponse[0];
                if(is_array($signifyResponseValue['decision'])){
                if($signifyResponseValue['decision']['checkpointActionReason']=="SIGNIFYD_APPROVED"){
                    $order->update_status('completed');
                    $obj->insert_data($order_id, 'completed',$signifyResponseValue['decision']['checkpointActionReason'], $signifyResponse);
                    plugin_log("Status Completed for ".$order_id."<br>");

                }
                else if($signifyResponseValue['decision']['checkpointActionReason']=="SIGNIFYD_DECLINED"){
                    $order->update_status('cancelled');
                    $obj->insert_data($order_id, 'cancelled',$signifyResponseValue['decision']['checkpointActionReason'], $signifyResponse);
                    plugin_log("Status cancelled for ".$order_id."<br>");
                }
                else{
                        $order->update_status('pending');
                        $obj->insert_data($order_id, 'pending',$signifyResponseValue['decision']['checkpointActionReason'], $signifyResponse);
                        plugin_log("Status pending for ".$order_id."<br>");

                   }
                }
                $logger->saveLogsToFile($order_id);
            }
                
        } 
        return;
    }

    // Declare function to fetch order details through order ID from WooCommerce

     public function get_order_details($order_id){
		$options = get_option("signfyd_opt"); 
        extract($options);
        
        $order = new WC_Order($order_id);
        $getdata = get_post_meta($order->id, '_payment_method_token', true);
        
        $get_coupen = $order->get_used_coupons();
        $coupen_code = "";
        $coupen_amount = "";
        if ($get_coupen) {
            $coupen_code = $get_coupen[0];
            $get_coupen_detail = new WC_Coupon($coupen_code);
            $coupen_code = $get_coupen_detail->code;
            $coupen_amount = $get_coupen_detail->amount;
        }
        
        $order_data = $order->get_data();
		plugin_log("Order data from WC object for order ID inside get_order_details function".$order_id."<br>");
		plugin_log($order_data);
		 
		$payment_name =	$order_data['payment_method'];
		$transaction_id = $order_data['transaction_id'];
		plugin_log("Getting transaction_id inside get_order_details function".$transaction_id."<br>");
		plugin_log($order_data);
        $billing = $order_data['billing'];
        $shipping = $order_data['shipping'];
        $items = $order->get_items();
		 
        extract($order_data);
		 
        foreach ( $items as $item ) {
            $product_name = $item->get_name();
            $product_id = $item->get_product_id();
            $getproduct_detail = wc_get_product( $product_id );
            $product_variation_id = $item->get_variation_id();
            $Item_quantity = $item['quantity'];
            $sub_total = $item->get_subtotal();
            $get_itemImage = get_the_post_thumbnail_url($product_id);
            $item_price	 = $getproduct_detail->price;
            $terms = get_the_terms($product_id, 'product_cat');
            $get_account_size = $item->get_meta( 'pa_account', true );
            $item_url = get_permalink( $product_id );
            $categoryname = $terms['0']->name;
        }

        $get_item_name_data = $product_name.'-'.$get_account_size;
        
        $user_id = get_post_meta($id, '_customer_user', true);
        $customer = new WC_Customer($user_id);
        $customer_data = get_userdata($order->get_customer_id());
        $user_register = $customer_data->user_registered;
        $order_created_date_format = date('Y-m-d\TH:i:s-h:00', strtotime($date_created));
        $username = $customer->get_username();
        $user_email = $customer->get_email();
        $first_name = $customer->get_first_name();
        $last_name = $customer->get_last_name();
        $display_name = $customer->get_display_name();
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
			$ipaddress = $_SERVER['HTTP_CLIENT_IP'];
		} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
			$ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} else {
			$ipaddress = $_SERVER['REMOTE_ADDR'];
		}

        $created_userdata = date( 'Y-m-d\TH:i:s-h:00', strtotime( wp_get_current_user()->user_registered ) ) ;
        
        // Get response from NMI by passing transaction_id to fetch card details

        $data_response = $this->getNmiResp($transaction_id);
		plugin_log("Details of NMI Transaction ID ".$transaction_id." for order ID".$order_id." inside get_order_details function are follows. <br>");
		$data_response = $data_response['transaction'];
		plugin_log($data_response);        
        $card_last_digit = substr($data_response['cc_number'], -4);
        $card_expiry = substr($data_response['cc_exp'],0,2);
        $card_expiry_yr = substr($data_response['cc_exp'], -2);

        $card_ex_year = '20'.$card_expiry_yr;
		$sessionId = wp_get_session_token() ? wp_get_session_token() : '82ce5fca-c2b2-429b-b487-6c3053469f85';
        
        // Prepare json request using order details from WooCommerce and NMI data received through transaction ID

        $jsonRequest = '{
            "orderId": "'.$order_id.'",
            "purchase": {
                "createdAt": "'.$order_created_date_format.'",
                "orderChannel": "WEB",
                "totalPrice": "'.$total.'",
                "currency": "'.$currency.'",
                "confirmationEmail": "'.$billing['email'].'",
                "products": [{
                    "itemName": "'.$get_item_name_data.'",
                    "itemPrice": "'.$order->get_subtotal().'",
                    "itemQuantity": '.$Item_quantity.',
                    "itemIsDigital": false,
                    "itemCategory": null,
                    "itemSubCategory": null,
                    "itemId": "'.$product_id.'",
                    "itemImage": "'.$get_itemImage.'",
                    "itemUrl": "'.$item_url.'",
                    "itemWeight": "",
                    "shipmentId": ""
                }],
                "shipments": [{
                    "destination": {
                        "fullName": "'.$billing['first_name'].' '.$billing['last_name'].'",
                        "organization": "'.$billing['company'].'",
                        "email": null,
                        "address": {
                            "streetAddress": "'.$billing['address_1'].'",
                            "unit": "'.$billing['address_2'].'",
                            "postalCode": "'.$billing['postcode'].'",
                            "city": "'.$billing['city'].'",
                            "provinceCode": null,
                            "countryCode": "'.$billing['country'].'"
                        }
                    },
                    "origin": {
                        "locationId": "",
                        "address": {
                            "streetAddress": "'.$billing['address_1'].'",
                            "unit": "'.$billing['address_2'].'",
                            "postalCode": "'.$billing['postcode'].'",
                            "city": "'.$billing['city'].'",
                            "provinceCode": null,
                            "countryCode": "'.$billing['country'].'"
                        }
                    },
                    "carrier": null,
                    "minDeliveryDate": null,
                    "maxDeliveryDate": null,
                    "shipmentId": null,
                    "fulfillmentMethod": null
                }],
                "confirmationPhone": "'.$billing['phone'].'",
                "totalShippingCost": null,
                "receivedBy": "string"
            },
            "userAccount": {
                "username": "'.$username.'",
                "createdDate": "'.$order_created_date_format.'",
                "accountNumber": "",
                "aggregateOrderCount": "1",
                "aggregateOrderDollars": '.$total.',
                "email": "'.$user_email.'",
                "phone": "'.$billing['phone'].'",
                "lastOrderId": "string",
                "lastUpdateDate": "'.$order_created_date_format.'",
                "emailLastUpdateDate": "'.$order_created_date_format.'",
                "phoneLastUpdateDate": "'.$order_created_date_format.'",
                "passwordLastUpdateDate": "'.$order_created_date_format.'"
            },
            "coverageRequests": [
                "NONE"
            ],
            "merchantCategoryCode": "1111",
            "decisionDelivery": "SYNC",
            "device": {
                "clientIpAddress": "'.$ipaddress.'",
                "sessionId": "'.$sessionId.'",
                "fingerprint": {
                    "provider": "threatmetrix",
                    "payload": "aSBnZXQgYnkgd2l0aCBhIGxpdHRsZSBoZWxwIGZyb20gbXkgZnJpZW5kcw==",
                    "payloadEncoding": "UTF8",
                    "payloadVersion": "string"
                }
            },
            "merchantPlatform": {
                "name": "Funded Trader - Live",
                "version": "1.2.4"
            },
            "signifydClient": {
                "application": "Signifyd Plugin",
                "version": "2.0.1"
            },
            "transactions": [{
                "transactionId": "'.$transaction_id.'",
                "gatewayStatusCode": "SUCCESS",
                "paymentMethod": "Credit_Card",
                "checkoutPaymentDetails": {
                    "billingAddress": {
                        "streetAddress": "'.$billing['address_1'].'",
                        "unit": "'.$billing['address_2'].'",
                        "postalCode": "'.$billing['postal_code'].'",
                        "city": "'.$billing['city'].'",
                        "provinceCode": null,
                        "countryCode": "'.$billing['country'].'"
                    },
                    "accountHolderName": "'.$billing['first_name'].' '.$billing['last_name'].'",
                    "accountHolderTaxId": "'.$data_response['customertaxid'].'",
                    "accountHolderTaxIdCountry": "'.$billing['country'].'",
                    "accountLast4": "'.$card_last_digit.'",
                    "abaRoutingNumber": null,
                    "cardToken": null,
                    "cardTokenProvider": null,
                    "cardBin": "'.$data_response['cc_bin'].'",
                    "cardExpiryMonth": "'.$card_expiry.'",
                    "cardExpiryYear": "'.$card_ex_year.'",
                    "cardLast4": "'.$card_last_digit.'",
                    "cardBrand": "'.$data_response['cc_type'].'",
                    "cardFunding": null,
                    "cardInstallments": {
                        "interval": null,
                        "count": null,
                        "totalValue": null,
                        "installmentValue": null
                    }
                },
                "amount": '.$total.',
                "currency": "'.$currency.'",
                "gateway": "NMI",
                "acquirerDetails": {
                    "countryCode": "'.$billing['country'].'",
                    "bin": null
                },
                "gatewayStatusMessage": "string",
                "createdAt": "'.$order_created_date_format.'",
                "parentTransactionId": null,
                "verifications": {
                    "cvvResponseCode": "'.$data_response['csc_response'].'",
                    "avsResponseCode": "'.$data_response['avs_response'].'"
                }
            }]
        }';
        return  $this->update_order_meta($order_id, $jsonRequest);
    }

}

new SignifyInit();