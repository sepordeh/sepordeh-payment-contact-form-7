<?php



  global $wpdb;
  global $postid;
        
    $wpcf7 = WPCF7_ContactForm::get_current();
        $submission = WPCF7_Submission::get_instance();
        $your_email = '';
        $your_mobile = '';
        $your_message = '';
        $your_price = '';

        if ($submission) {
            $data = $submission->get_posted_data();
            $your_email = isset($data['your-email']) ? $data['your-email'] : "";
            $your_mobile = isset($data['your-mobile']) ? $data['your-mobile'] : "";
            $your_message = isset($data['your-message']) ? $data['your-message'] : "";
            $your_price = isset($data['your-price']) ? $data['your-price'] : "";
        }
        
        
        
        $price = get_post_meta($postid, "_cf7pp_price", true);
                if ($price == "") {
                    $price = $your_price;
                }
                $options = get_option('cf7pp_options');
                foreach ($options as $k => $v) {
                    $value[$k] = $v;
                }
                $active_gateway = 'Sepordeh';
                $MID = $value['gateway_merchantid'];
                $url_return = $value['return'];


//$your_email;
// Set Data -> Table Trans_ContantForm7
                $table_name = $wpdb->prefix . "cfZ7_sepordeh_transaction";
                $_x = array();
                $_x['idform'] = $postid;
                $_x['transid'] = time(); // create dynamic or id_get
                $_x['gateway'] = $active_gateway; // name gateway
                $_x['cost'] = $price;
                $_x['created_at'] = time();
                $_x['email'] = $your_email;
                $_x['mobile'] = $your_mobile;
                $_x['description'] = $your_message;
                $_x['status'] = 'none';
                $_y = array('%d', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s');

                if ($active_gateway == 'Sepordeh') {
                         
					$MerchantID = $MID; //Required
					$Amount = $price; //Amount will be based on Toman - Required
					$your_message = $your_message; // Required
					$Email = $your_email; // Optional
					$Mobile = $your_mobile; // Optional
					$CallbackURL = get_site_url().'/'.$url_return; // Required
                        
                        

					$url='https://sepordeh.com/merchant/invoices/add';
					$data=array(
							'merchant'          => $MerchantID,
							'amount'       => $Amount,
							'callback'     => $CallbackURL,
							'orderId' => $_x['transid'],
							'description'  => $your_message,
						);
					$args = array(
						'timeout' => 20,
						'body' => $data,
						'httpversion' => '1.1',
						'user-agent' => 'Official Sepordeh CF7 Plugin'
					);
					$number_of_connection_tries = 4;
					while ($number_of_connection_tries) {
						$response = wp_safe_remote_post($url, $args);
						if (is_wp_error($response)) {
							$number_of_connection_tries--;
							continue;
						} else {
							break;
						}
					}

					$result = json_decode($response["body"]);
                    if ($result->status == 200) {

//                        $_x['transid'] = $result->Authority;

                        $s = $wpdb->insert($table_name, (array) $_x,$_y);
						$go = "https://sepordeh.com/merchant/invoices/pay/automatic:true/id:".$result->information->invoice_id;
						header("Location: $go");
						
                    } else {
                        $tmp = 'خطایی رخ داده در اطلاعات پرداختی درگاه' . '<br>Error:' . $result->status . '<br> لطفا به مدیر اطلاع دهید <br><br>'.$result->message;
                        $tmp .= '<a href="' . get_option('siteurl') . '" class="mrbtn_red" > بازگشت به سایت </a>';
                        echo CreatePage_cf7('خطا در عملیات پرداخت', $tmp);
                    }
                }

?>


        
