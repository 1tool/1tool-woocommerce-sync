<?php 

if ( ! function_exists( 'firsttool_create_order' ) ):
	/**
	 * Creates order on 1Tool account using API call.
	 *
	 * This calls ShopOrders endpoint on 1Tool API to create Sales order there.
	 *
	 * @since 1.0.0
	 *
	 */
	function firsttool_create_order( $order_id ) {
	
		$obj_kdm = new FIRSTTOOL_API\FirstTool_API;
	
		$created_in_kundenmeister = get_post_meta( $order_id, 'created_in_kundenmeister', true );
		if ( empty( $created_in_kundenmeister ) ) {
		
			$order = wc_get_order( $order_id );
			$kdm_order = array();
			$orderId = $order_id;
			$customer_id = $order->get_customer_id();
			$kdm_customer_id = 0;
			if ( $customer_id > 0 ) {
				$kdm_customer = get_user_meta( $customer_id, 'kundenmeister_customer', true );
				$kdm_customer_id = isset( $kdm_customer["Customer.id"] ) ? $kdm_customer["Customer.id"] : 0;
			}
		
			$orderDate = $order->get_date_created()->format( "Y-m-d" );
			$customer_note = $order->get_customer_note();
			$firstName = $order->get_billing_first_name();
			$lastName = $order->get_billing_last_name();
			$company = $order->get_billing_company();
			$address = $order->get_billing_address_1();
			$address_a = $order->get_billing_address_2();
			$city = $order->get_billing_city();
			$state = $order->get_billing_state();
			$postalCode = $order->get_billing_postcode();
		
			$email = $order->get_billing_email();
			$phone = $order->get_billing_phone();
		 
			$shipping_address_1 = $order->get_shipping_address_1();
			$shipping_address_1 = empty( $shipping_address_1 ) ? $address : $shipping_address_1;
			$shipping_address_2 = $order->get_shipping_address_2();
			$shipping_address_2 = empty( $shipping_address_2 ) ? $address_a : $shipping_address_2;
		
			$deliverCity = $order->get_shipping_city();
			$deliverCity = empty( $deliverCity ) ? $city : $deliverCity;
		
			$deliverPostalCode = $order->get_shipping_postcode();
			$deliverPostalCode = empty( $deliverPostalCode ) ? $postalCode : $deliverPostalCode;
		
			$remarks = $order->get_customer_note();
			$total = $order->get_total();
		
			$shippingTotal = $order->get_shipping_total();
			$total_discount = $order->get_total_discount();
			$billingAddress = $address . " " . $address_a;
			$shippingAddress = $shipping_address_1 . " " . $shipping_address_2;
		
			// Get and Loop Over Order Items
			foreach ( $order->get_items() as $item_id => $item ) {
				$product_id = $item->get_product_id();
		   
				$kdm_product_id = get_post_meta( $product_id, 'kundenmeister_product_id', true );
			
				if ( ! empty( $kdm_product_id ) ) {
					$quantity = $item->get_quantity();
					$product_name = $item->get_name();
					$total = $item->get_total();
					$unitPrice = $total / $quantity;
					$subtotal = $item->get_subtotal();
					$tax = $item->get_subtotal_tax();
					
					$kdm_order["items"][ $item_id ] = $item;
					$kdm_order["items_invoide"][ $item_id ] = array(
						"text" => $product_name,
						"quantity" => $quantity,
						"price_per_unit" => $unitPrice,
						"product_id" => $kdm_product_id,
						"tax" => $tax,
					);
			   }
			}
		
			// Create customer
			if ( $kdm_customer_id == 0 ) {
			
				// create customer
				$kdm_Customer = array(
					"Customer.mail" => $email,
					"Customer.name" => $firstName . " " . $lastName,
					"Customer.firstName" => $firstName,
					"Customer.street" => $address . " " . $address_a,
					"Customer.postalCode" => $postalCode,
					"Customer.city" => $city,
					"Customer.federalState" => $state,
					"Customer.phoneNumber" => $phone,
					"Customer.invoiceName" => $firstName . " " . $lastName,
					"Customer.invoiceFirstName" => $firstName,
					"Customer.invoiceStreet" => $address . " " . $address_a,
					"Customer.invoicePostalCode" => $postalCode,
					"Customer.invoiceCity" => $city,
				);
						
				$kdm_where = '?limit=1&where=[{"property":"Customer.mail","operator":"=","value":"' . $email . '"}]';
			
				$kdm_cus_get = $obj_kdm->km_get_request( 'model/Customer' . $kdm_where );
				if ( $obj_kdm->response_code == 200 && isset( $kdm_cus_get[0]["Customer.id"] ) ) {
					$kdm_customer_id = $kdm_cus_get[0]["Customer.id"];
					update_user_meta( $customer_id, "kundenmeister_customer", $kdm_cus_get[0] );
				} else {
					$kdm_cus = $obj_kdm->km_post_request( 'model/Customer', $kdm_Customer );
					if ( $obj_kdm->response_code == 200 && isset( $kdm_cus["Customer.id"] ) ) {
						$kdm_customer_id = $kdm_cus["Customer.id"];
						update_user_meta( $customer_id, "kundenmeister_customer", $kdm_cus );
					}	
				}
			}
		
			if ( isset( $kdm_order["items"] ) ) {
		
				// Create invice
				$kdm_invice_data = array();
				$kdm_invice_data["items"] = $kdm_order["items_invoide"];
				$kdm_invice_data["optionId"] = '1';
				$kdm_invice_data["invoiceData"] = array( "customer_email" => $email );
				$kdm_invice_data["isPaid"] = '1';
				$km_is_create_invoice = get_option( 'km_is_create_invoice' );
				if ( $km_is_create_invoice == "yes" ) {
					$res_kdm_invoice =  $obj_kdm->km_post_request( 'invoice/createInvoice', $kdm_invice_data);
				}
			
				$invoiceId = null;
				$invoiceCreated = 0;
				if ( isset( $res_kdm_invoice["invoiceIds"] ) ) {
					$invoiceId = $res_kdm_invoice["invoiceIds"][0];
					$invoiceCreated = 1;
				}
				
				$kdm_ShopOrder = array(
					"ShopOrder.id" => null,
					"ShopOrder.status" => 0,
					"ShopOrder.currencyId" => null,
					"ShopOrder.userId" => $kdm_customer_id,
					"ShopOrder.total" => $total,
					"ShopOrder.shippingTotal" => $shippingTotal,
					"ShopOrder.billingAddress" => null,
					"ShopOrder.shippingAddress" => null,
					"ShopOrder.userComment" => $customer_note,
					"ShopOrder.comment" => '',
					"ShopOrder.couponId" => 0,
					"ShopOrder.orderDiscount" => $total_discount,
					"ShopOrder.shippingStatus" => '4',
					"ShopOrder.paymentMethod" => null, 
					"ShopOrder.departmentId" => null,
					"ShopOrder.orderDate" => $orderDate,
					"ShopOrder.description" => "WooCommerce Order ID #" . $orderId,
					"ShopOrder.invoiceCreated" => $invoiceCreated,
					"ShopOrder.invoiceId" => $invoiceId,
					"ShopOrder.packageNumber" => null,
					"ShopOrder.orderCode" => null,
				);
				// Create order
				$res_kdm_order = $obj_kdm->km_post_request( 'model/ShopOrder', $kdm_ShopOrder );
				if ( isset( $res_kdm_order["ShopOrder.id"] ) ) {
					// Add item in order
					foreach( $kdm_order["items"] as $item_id => $item ) {
						$quantity = $item->get_quantity();
						$total = $item->get_total();
						$unitPrice = $total / $quantity;
						$subtotal = $item->get_subtotal();
						$tax = $item->get_subtotal_tax();
						$product_id = $item->get_product_id();
						$productId = get_post_meta( $product_id, 'kundenmeister_product_id', true );
						$kdm_items = array(
							"ShopOrderItem.orderId" => $res_kdm_order["ShopOrder.id"],
							"ShopOrderItem.productId" => $productId,
							"ShopOrderItem.quantity" => $quantity,
							"ShopOrderItem.price" => $unitPrice,
							"ShopOrderItem.subTotal" => $total,
							"ShopOrderItem.tax" => $tax,
							"ShopOrderItem.options" => "",
							"ShopOrderItem.storageId" => null,
						);
						$res_kdm_item =  $obj_kdm->km_post_request( 'model/ShopOrderItem', $kdm_items );
					}
					update_post_meta( $order_id, 'created_in_kundenmeister', $res_kdm_order );
				}
			}
		}
	}
endif;
/* Create order on kundenmeister  */
add_action( 'woocommerce_order_status_processing', 'firsttool_create_order' );
?>