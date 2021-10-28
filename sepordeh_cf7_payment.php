<?php

/*
Plugin Name: Contact Form 7 - Gateway Sepordeh v1.4
Plugin URI: https://wordpress.org/plugins/sepordeh-payment-contact-form-7
description: Sepordeh gateway payment for contact form 7
Author: Sepordeh
Author URI: https://sepordeh.com
Version: 1.4
*/


function sepordeh_cf7_fields_informations() { return "<br><b> داخل فرم برای اتصال به درگاه پرداخت میتوانید از نام فیلدهای زیر استفاده نمایید </b></br>
<ul>
<li>
[email your-email] نام فیلد دریافت ایمیل کاربر بایستی your-email انتخاب شود.
</li><li>
 [textarea your-message] نام فیلد  توضیحات پرداخت بایستی your-message انتخاب شود.
</li><li>
 [tel your-mobile] نام فیلد  موبایل بایستی your-mobile انتخاب شود.
</li><li>
 [text your-price] اگر کادر مبلغ در بالا خالی باشد می توانید به کاربر اجازه دهید مبلغ را خودش انتخاب نماید . کادر متنی با نام your-price ایجاد نمایید.
</li>
</ul>";
}
function SEPORDEH_CF7_relative_time($ptime)
{
    date_default_timezone_set("Asia/Tehran");
    $etime = time() - $ptime;
    if ($etime < 1) {
        return '0 ثانیه';
    }
    $a = array(12 * 30 * 24 * 60 * 60 => 'سال',
        30 * 24 * 60 * 60 => 'ماه',
        24 * 60 * 60 => 'روز',
        60 * 60 => 'ساعت',
        60 => 'دقیقه',
        1 => 'ثانیه'
    );
    foreach ($a as $secs => $str) {
        $d = $etime / $secs;
        if ($d >= 1) {
            $r = round($d);
            return $r . ' ' . $str . ($r > 1 ? ' ' : '');
        }
    }
}


function result_payment_func($atts)
{
    global $wpdb;
    $Return_MessageEmail = '';
    $Return_Trans_id = $_GET['authority'];
    $Return_orderId = $_GET['orderId'];
    $Status ='OK';

    $Theme_Message = get_option('cf7pp_theme_message', '');

    $theme_error_message = get_option('cf7pp_theme_error_message', '');

    $options = get_option('cf7pp_options');
    foreach ($options as $k => $v) {
        $value[$k] = $v;
    }

    $MID = $value['gateway_merchantid'];

    if ($Status == 'OK') {
        
        $table_name = $wpdb->prefix . 'cfZ7_sepordeh_transaction';
        $cf_Form = $wpdb->get_row("SELECT * FROM $table_name WHERE transid=" . $Return_orderId);
        if (null !== $cf_Form) {
            $Amount = $cf_Form->cost;
        }

       
        $url='https://sepordeh.com/merchant/invoices/verify';
		$data= [
			'merchant' 	=> $MID,
			'authority' => $Return_Trans_id,
		];
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
            $Return_MessageEmail = 'success';
        }else{
			$Return_MessageEmail = 'error';
		}
    } else {
        $Return_MessageEmail = 'error';
    }


    if ($Return_MessageEmail == 'success') {
        $wpdb->update($wpdb->prefix . 'cfZ7_transaction', array('status' => 'success', 'transid' => $result->information->invoice_id), array('transid' => $Return_orderId), array('%s', '%s'), array('%d'));

        //Dispaly
        $body = '<b>'.stripslashes(str_replace('[transaction_id]', $result->information->invoice_id, $Theme_Message)).'<b/>';
        return CreateMessage_cf7("", "", $body);
    } else if ($Return_MessageEmail == 'error') {
        $wpdb->update($wpdb->prefix . 'cfZ7_transaction', array('status' => 'error'), array('transid' => $Return_orderId), array('%s'), array('%d'));
        //Dispaly
        $body = '<b>'.$theme_error_message.'<b/>';
        return CreateMessage_cf7("", "", $body);
    }


}

add_shortcode('result_payment', 'result_payment_func');

function CreateMessage_cf7($title, $body, $endstr = "")
{
    if ($endstr != "") {
        return $endstr;
    }
    $tmp = '<div style="border:#CCC 1px solid; width:90%;"> 
    ' . $title . '<br />' . $body . '</div>';
    return $tmp;
}


function CreatePage_cf7($title, $body)
{
    $tmp = '
	<html>
	<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<title>' . $title . '</title>
	</head>
	<link rel="stylesheet"  media="all" type="text/css" href="' . plugins_url('style.css', __FILE__) . '">
	<body class="vipbody">	
	<div class="mrbox2" > 
	<h3><span>' . $title . '</span></h3>
	' . $body . '	
	</div>
	</body>
	</html>';
    return $tmp;
}




function SEPORDEH_Contant_Form_7_Gateway_install()
{
    
}


$dir = plugin_dir_path(__FILE__);
//register_activation_hook($dir . 'gateway_func.php', 'SEPORDEH_Contant_Form_7_Gateway_install');

//  plugin functions
register_activation_hook(__FILE__, "cf7pp_activate");
register_deactivation_hook(__FILE__, "cf7pp_deactivate");
register_uninstall_hook(__FILE__, "cf7pp_uninstall");


function cf7pp_activate()
{
	global $wpdb;
    $table_name = $wpdb->prefix . "cfZ7_sepordeh_transaction";
    if ($wpdb->get_var("show tables like '$table_name'") != $table_name) {
        $sql = "CREATE TABLE " . $table_name . " (
			id mediumint(11) NOT NULL AUTO_INCREMENT,
			idform bigint(11) DEFAULT '0' NOT NULL,
			transid VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			gateway VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			cost bigint(11) DEFAULT '0' NOT NULL,
			created_at bigint(11) DEFAULT '0' NOT NULL,
			email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			mobile VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci  NULL,
			description text CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			status VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_persian_ci NOT NULL,
			PRIMARY KEY id (id)
		);";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
	
	

    // remove ajax from contact form 7 to allow for php redirects
    function wp_config_put($slash = '')
    {
        $config = file_get_contents(ABSPATH . "wp-config.php");
        $config = preg_replace("/^([\r\n\t ]*)(\<\?)(php)?/i", "<?php define('WPCF7_LOAD_JS', false);", $config);
        file_put_contents(ABSPATH . $slash . "wp-config.php", $config);
    }

    if (file_exists(ABSPATH . "wp-config.php") && is_writable(ABSPATH . "wp-config.php")) {
        wp_config_put();
    } else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && is_writable(dirname(ABSPATH) . "/wp-config.php")) {
        wp_config_put('/');
    } else {
        ?>
        <div class="error">
            <p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been activated.', 'cf7pp'); ?></p>
        </div>
        <?php
        exit;
    }

    // write initical options
    $cf7pp_options = array(
        'MerchantID' => '',
        'return' => ''
    );

    add_option("cf7pp_options", $cf7pp_options);


}


function cf7pp_deactivate()
{

    function wp_config_delete($slash = '')
    {
        $config = file_get_contents(ABSPATH . "wp-config.php");
        $config = preg_replace("/( ?)(define)( ?)(\()( ?)(['\"])WPCF7_LOAD_JS(['\"])( ?)(,)( ?)(0|1|true|false)( ?)(\))( ?);/i", "", $config);
        file_put_contents(ABSPATH . $slash . "wp-config.php", $config);
    }

    if (file_exists(ABSPATH . "wp-config.php") && is_writable(ABSPATH . "wp-config.php")) {
        wp_config_delete();
    } else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && is_writable(dirname(ABSPATH) . "/wp-config.php")) {
        wp_config_delete('/');
    } else if (file_exists(ABSPATH . "wp-config.php") && !is_writable(ABSPATH . "wp-config.php")) {
        ?>
        <div class="error">
            <p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'cf7pp'); ?></p>
        </div>
        <button onclick="goBack()">Go Back and try again</button>
        <script>
            function goBack() {
                window.history.back();
            }
        </script>
        <?php
        exit;
    } else if (file_exists(dirname(ABSPATH) . "/wp-config.php") && !is_writable(dirname(ABSPATH) . "/wp-config.php")) {
        ?>
        <div class="error">
            <p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'cf7pp'); ?></p>
        </div>
        <button onclick="goBack()">Go Back and try again</button>
        <script>
            function goBack() {
                window.history.back();
            }
        </script>
        <?php
        exit;
    } else {
        ?>
        <div class="error">
            <p><?php _e('wp-config.php is not writable, please make wp-config.php writable - set it to 0777 temporarily, then set back to its original setting after this plugin has been deactivated.', 'cf7pp'); ?></p>
        </div>
        <button onclick="goBack()">Go Back and try again</button>
        <script>
            function goBack() {
                window.history.back();
            }
        </script>
        <?php
        exit;
    }
    
  
    delete_option("cf7pp_options");
    delete_option("cf7pp_my_plugin_notice_shown");

}


function cf7pp_uninstall()
{
}

// display activation notice
add_action('admin_notices', 'cf7pp_my_plugin_admin_notices');

function cf7pp_my_plugin_admin_notices() {
    if (!get_option('cf7pp_my_plugin_notice_shown')) {
        echo "<div class='updated'><p><a href='admin.php?page=cf7pp_admin_table'>برای تنظیم اطلاعات درگاه  کلیک کنید</a>.</p></div>";
        update_option("cf7pp_my_plugin_notice_shown", "true");
    }
}



// check to make sure contact form 7 is installed and active
include_once(ABSPATH . 'wp-admin/includes/plugin.php');
if (is_plugin_active('contact-form-7/wp-contact-form-7.php')) {

    // add paypal menu under contact form 7 menu
    add_action('admin_menu', 'cf7pp_admin_menu', 20);
    function cf7pp_admin_menu()
    {
        $addnew = add_submenu_page('wpcf7',
            __('تنظیمات سپرده', 'contact-form-7'),
            __('تنظیمات سپرده', 'contact-form-7'),
            'wpcf7_edit_contact_forms', 'cf7pp_admin_table',
            'cf7pp_admin_table');

        $addnew = add_submenu_page('wpcf7',
            __('لیست تراکنش ها', 'contact-form-7'),
            __('لیست تراکنش ها', 'contact-form-7'),
            'wpcf7_edit_contact_forms', 'cf7pp_admin_list_trans',
            'cf7pp_admin_list_trans');

    }


    // hook into contact form 7 - before send
    add_action('wpcf7_before_send_mail', 'cf7pp_before_send_mail');
    function cf7pp_before_send_mail($cf7)
    {
    }


    // hook into contact form 7 - after send
    add_action('wpcf7_mail_sent', 'cf7pp_after_send_mail');
    function cf7pp_after_send_mail($cf7)
    {
        global $wpdb;
        global $postid;
        $postid = $cf7->id();
        

        $enable = get_post_meta($postid, "_cf7pp_enable", true);
        $email = get_post_meta($postid, "_cf7pp_email", true);

        if ($enable == "1") {
            if ($email == "2") {

					include_once ('redirect.php');
			
                
                exit;

            }
        }

    } // End Function


    // hook into contact form 7 form
    add_action('wpcf7_admin_after_additional_settings', 'cf7pp_admin_after_additional_settings');
    function cf7pp_editor_panels($panels)
    {

        $new_page = array(
            'PricePay' => array(
                'title' => __('اطلاعات پرداخت', 'contact-form-7'),
                'callback' => 'cf7pp_admin_after_additional_settings'
            )
        );

        $panels = array_merge($panels, $new_page);

        return $panels;

    }

    add_filter('wpcf7_editor_panels', 'cf7pp_editor_panels');


    function cf7pp_admin_after_additional_settings($cf7)
    {
        $post_id = sanitize_text_field($_GET['post']);
        $enable = get_post_meta($post_id, "_cf7pp_enable", true);
        $price = get_post_meta($post_id, "_cf7pp_price", true);
        $email = get_post_meta($post_id, "_cf7pp_email", true);
        $your_mobile = get_post_meta($post_id, "_cf7pp_mobile", true);
        $your_message = get_post_meta($post_id, "_cf7pp_your_message", true);

        if ($enable == "1") {
            $checked = "CHECKED";
        } else {
            $checked = "";
        }

        if ($email == "1") {
            $before = "SELECTED";
            $after = "";
        } elseif ($email == "2") {
            $after = "SELECTED";
            $before = "";
        } else {
            $before = "";
            $after = "";
        }

        $admin_table_output = "";
        $admin_table_output .= "<form>";
        $admin_table_output .= "<div id='additional_settings-sortables' class='meta-box-sortables ui-sortable'><div id='additionalsettingsdiv' class='postbox'>";
        $admin_table_output .= "<div class='handlediv' title='Click to toggle'><br></div><h3 class='hndle ui-sortable-handle'> <span>اطلاعات پرداخت برای فرم</span></h3>";
        $admin_table_output .= "<div class='inside'>";

        $admin_table_output .= "<div class='mail-field'>";
        $admin_table_output .= "<input name='enable' id='cf71' value='1' type='checkbox' $checked>";
        $admin_table_output .= "<label for='cf71'>فعال سازی امکان پرداخت آنلاین</label>";
        $admin_table_output .= "</div><br />";

        //input -name
        $admin_table_output .= "<table>";
        $admin_table_output .= "<tr><td>مبلغ: </td><td><input type='text' name='price' style='text-align:left;direction:ltr;' value='$price'></td><td>تومان</td></tr>";

        $admin_table_output .= "</table>";


        //input -id
        $admin_table_output .= sepordeh_cf7_fields_informations();
        $admin_table_output .= "<input type='hidden' name='email' value='2'>";

        $admin_table_output .= "<input type='hidden' name='post' value='$post_id'>";

        $admin_table_output .= "</form>";
        $admin_table_output .= "</div>";
        $admin_table_output .= "</div>";
        $admin_table_output .= "</div>";
        echo $admin_table_output;

    }


    // hook into contact form 7 admin form save
    add_action('wpcf7_save_contact_form', 'cf7pp_save_contact_form');
    function cf7pp_save_contact_form($cf7)
    {

        $post_id = sanitize_text_field($_POST['post']);

        if (!empty($_POST['enable'])) {
            $enable = sanitize_text_field($_POST['enable']);
            update_post_meta($post_id, "_cf7pp_enable", $enable);
        } else {
            update_post_meta($post_id, "_cf7pp_enable", 0);
        }

        /*$name = sanitize_text_field($_POST['name']);
        update_post_meta($post_id, "_cf7pp_name", $name);
        */
        $price = sanitize_text_field($_POST['price']);
        update_post_meta($post_id, "_cf7pp_price", $price);

        /*$id = sanitize_text_field($_POST['id']);
        update_post_meta($post_id, "_cf7pp_id", $id);
        */
        $email = sanitize_text_field($_POST['email']);
        update_post_meta($post_id, "_cf7pp_email", $email);


    }


    function cf7pp_admin_list_trans()
    {
        if (!current_user_can("manage_options")) {
            wp_die(__("You do not have sufficient permissions to access this page."));
        }

        global $wpdb;
		
        $pagenum = isset($_GET['pagenum']) ? absint($_GET['pagenum']) : 1;
        $limit = 6;
        $offset = ($pagenum - 1) * $limit;
        $table_name = $wpdb->prefix . "cfZ7_transaction";

        $transactions = $wpdb->get_results("SELECT * FROM $table_name where (status NOT like 'none') ORDER BY $table_name.id DESC LIMIT $offset, $limit", ARRAY_A);
        $total = $wpdb->get_var("SELECT COUNT($table_name.id) FROM $table_name where (status NOT like 'none') ");
        $num_of_pages = ceil($total / $limit);
        $cntx = 0;
		echo '<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js" integrity="sha512-894YE6QWD5I59HgZOGReFYm4dnWc1Qt5NtvYSaNcOP+u1T9qYdvdihz0PPSiiqn/+/3e7Jo4EaG7TubfWGUrMQ==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><script src="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.js" integrity="sha512-uURl+ZXMBrF4AwGaWmEetzrd+J5/8NRkWAvJx5sbPSSuOb0bZLqf+tOzniObO00BjHa/dD7gub9oCGMLPQHtQA==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/fancybox/3.5.7/jquery.fancybox.min.css" integrity="sha512-H9jrZiiopUdsLpg94A333EfumgUBpO9MdbxStdeITo+KEIMaNfHNvwyjjDJb+ERPaRS6DpyRlKbvPUasNItRyw==" crossorigin="anonymous" referrerpolicy="no-referrer" />';
        echo '<div class="wrap">
		<h2>تراکنش فرم ها</h2>
		<table class="widefat post fixed" cellspacing="0">
			<thead>
				<tr>
					<th scope="col" id="name" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" class="manage-column" style="">موبایل</th>
                    <th scope="col" id="name" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" class="manage-column" style="">کد تراکنش</th>
					<th scope="col" id="name" class="manage-column" style="">وضعیت</th>
					<th scope="col" id="name" class="manage-column" style="">توضیحات</th>
				</tr>
			</thead>
			<tfoot>
				<tr>
					<th scope="col" id="name" class="manage-column" style="">نام فرم</th>
					<th scope="col" id="name" class="manage-column" style="">تاريخ</th>
                    <th scope="col" id="name" class="manage-column" style="">موبایل</th>
                    <th scope="col" id="name" class="manage-column" style="">ایمیل</th>
                    <th scope="col" id="name" class="manage-column" style="">مبلغ</th>
					<th scope="col" id="name" class="manage-column" style="">کد تراکنش</th>
					<th scope="col" id="name" class="manage-column" style="">وضعیت</th>
					<th scope="col" id="name" class="manage-column" style="">توضیحات</th>
				</tr>
			</tfoot>
			<tbody>';


        if (count($transactions) == 0) {

            echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="" colspan="6">هيج تراکنش وجود ندارد.</td>
				</tr>';

        } else {
            foreach ($transactions as $transaction) {

                echo '<tr class="alternate author-self status-publish iedit" valign="top">
					<td class="">' . get_the_title($transaction['idform']) . '</td>';
                echo '<td class="">' . date("Y-m-d", $transaction['created_at']).'<br />'.date("h:i", $transaction['created_at']).'</td>';

                echo '<td class="">' . $transaction['mobile'] . '</td>';
                echo '<td class="">' . $transaction['email'] . '</td>';
                echo '<td class="">' . $transaction['cost'] . ' تومان</td>';
                echo '<td class="">' . $transaction['transid'] . '</td>';
                echo '<td class="">';

                if ($transaction['status'] == "success") {
                    echo '<b style="color:#0C9F55">موفقیت آمیز</b>';
                } else {
                    echo '<b style="color:#f00">انجام نشده</b>';
                }
                echo '</td>';
				echo '<td class=""><a class="fancybox" href="#description_'.$transaction['id'].'">جزئیات</a><div style="display:none" id="description_'.$transaction['id'].'">' . $transaction['description'] . '</div></td>';
				echo '</tr>';
			}
        }
        echo '</tbody>
		</table>
		<script>
		$(document).ready(function() {
			$(".fancybox").fancybox();
		});
		</script>
        <br>';


        $page_links = paginate_links(array(
            'base' => add_query_arg('pagenum', '%#%'),
            'format' => '',
            'prev_text' => __('&laquo;', 'aag'),
            'next_text' => __('&raquo;', 'aag'),
            'total' => $num_of_pages,
            'current' => $pagenum
        ));

        if ($page_links) {
            echo '<center><div class="tablenav"><div class="tablenav-pages"  style="float:none; margin: 1em 0">' . $page_links . '</div></div>
		</center>';
        }

        echo '<br>
		<hr>
	</div>';
    }


    function cf7pp_admin_table()
    {
        global $wpdb;
        if (!current_user_can("manage_options")) {
            wp_die(__("You do not have sufficient permissions to access this page."));
        }
        // save and update options
        if (isset($_POST['update'])) {
            $options['gateway_merchantid'] = sanitize_text_field($_POST['gateway_merchantid']);
            $options['return'] = sanitize_text_field($_POST['return']);

			
            update_option("cf7pp_options", $options);

            update_option('cf7pp_theme_message', wp_filter_post_kses($_POST['theme_message']));
            update_option('cf7pp_theme_error_message', wp_filter_post_kses($_POST['theme_error_message']));
            
            echo "<br /><div class='updated'><p><strong>";
            _e("Settings Updated.");
            echo "</strong></p></div>";

        }

        $options = get_option('cf7pp_options');
        foreach ($options as $k => $v) {
            $value[$k] = $v;
        }
        

        $theme_message = get_option('cf7pp_theme_message', '');
        $theme_error_message = get_option('cf7pp_theme_error_message', '');
		
        
        echo "<div class='wrap'><h2>Contact Form 7 - Sepordeh Gateway Settings</h2></div><br />";
		?>
		<div class="notice notice-success is-dismissible">
			<p style="color:red;font-weight:bold;">
			در صورتی که کد زیر داخل فایل wp-config.php نبود به آن اضافه کنید.
			<pre style="direction: ltr;">define("WPCF7_LOAD_JS",false);</pre>
			</p>
			<div style="width:100%;height:1px;background-color:#000000"></div>
			<p><?php echo sepordeh_cf7_fields_informations(); ?></p>
		</div>
		<form method="post" action="" novalidate="novalidate">
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="gateway_merchantid">مرچنت</label></th>
					<td><input name="gateway_merchantid" type="text" id="gateway_merchantid" value="<?php echo $value['gateway_merchantid']?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="return">برگه بازگشت</label></th>
					<td><input name="return" type="text" id="return" value="<?php echo $value['return']?>" class="regular-text">
					<p class="description" id="tagline-description">یک برگه ایجاد کنید و [result_payment] را درون آن قرار دهید و سپس آدرس برگه را در این قسمت قرار دهید. </br> به طور مثال : http://your-site.com/mypage </br> شما وارد میکنید : mypage</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="theme_message">پیام تراکنش موفق</label></th>
					<td><input name="theme_message" type="text" id="theme_message" value="<?php echo !empty($theme_message)?$theme_message:"پرداخت با موفقیت انجام شد ، کد تراکنش : [transaction_id]"?>" class="regular-text"></td>
				</tr>
				<tr>
					<th scope="row"><label for="theme_error_message">پیام تراکنش ناموفق</label></th>
					<td><input name="theme_error_message" type="text" id="theme_error_message" value="<?php echo !empty($theme_error_message)?$theme_error_message:"پرداخت ناموفق بود"?>" class="regular-text"></td>
				</tr>
			</tbody>
		</table>
		<p class="submit"><input type="submit" name="update" id="submit" class="button button-primary" value="ذخیرهٔ تغییرات"></p>
		</form>
		<?php

    }
} else {
    // give warning if contact form 7 is not active
    function cf7pp_my_admin_notice()
    {
        echo '<div class="error">
			<p>' . _e('<b> افزونه درگاه بانکی برای افزونه Contact Form 7 :</b> Contact Form 7 باید فعال باشد ', 'my-text-domain') . '</p>
		</div>
		';
    }

    add_action('admin_notices', 'cf7pp_my_admin_notice');
}
?>
