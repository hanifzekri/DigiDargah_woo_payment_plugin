<?php

/*
* Plugin Name: افزونه دیجی درگاه برای ووکامرس
* Description: افزونه درگاه پرداخت رمز ارزی <a href="https://digidargah.com"> دیجی درگاه </a> برای ووکامرس.
* Version: 1.1
* developer: Hanif Zekri Astaneh
* Author: دیجی درگاه
* Author URI: https://digidargah.com
* Author Email: info@digidargah.com
* Text Domain: DigiDargah_woo_payment_plugin
* WC tested up to: 6.1
* copyright (C) 2020 DigiDargah
* license http://www.gnu.org/licenses/gpl-3.0.html GPLv3 or later
*/

if (!defined('ABSPATH')) exit;

function wc_gateway_digidargah_init(){

    if (class_exists('WC_Payment_Gateway')) {
        
		add_filter('woocommerce_payment_gateways', 'wc_add_digidargah_gateway');
		
		//Registers class WC_DigiDargah as a payment method
        function wc_add_digidargah_gateway($methods){
            $methods[] = 'WC_DigiDargah';
            return $methods;
        }
		
		//start main class
        class WC_DigiDargah extends WC_Payment_Gateway {

            protected $api_key;
            protected $pay_currency;
            protected $success_message;
            protected $failed_message;
            protected $payment_endpoint;
            protected $verify_endpoint;
            protected $order_status;

            public function __construct() {
                
				$this->id = 'WC_DigiDargah';
                $this->method_title = __('DigiDargah', 'woo-digidargah-gateway');
                $this->method_description = __('انتقال مشتریان به دیجی درگاه برای پرداخت هزینه سفارش از طریق رمز ارزها ', 'woo-digidargah-gateway');
                $this->has_fields = FALSE;
                $this->icon = apply_filters('WC_DigiDargah_logo', 'https://digidargah.com/file/image/gate/logo-icon.png');

                // Load the form fields.
                $this->init_form_fields();

                // Load the settings.
                $this->init_settings();

                // Get setting values.
                $this->title = $this->get_option('title');
                $this->description = $this->get_option('description');

                $this->api_key = $this->get_option('api_key');
                $this->pay_currency = $this->get_option('pay_currency');

                $this->order_status = $this->get_option('order_status');

                $this->payment_endpoint = 'https://digidargah.com/action/ws/request_create';
                $this->verify_endpoint = 'https://digidargah.com/action/ws/request_status';

                $this->success_message = $this->get_option('success_message');
                $this->failed_message = $this->get_option('failed_message');

                if (version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=')) {
                    add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                } else {
                    add_action('woocommerce_update_options_payment_gateways', array($this, 'process_admin_options'));
                }

                add_action('woocommerce_receipt_' . $this->id, array($this, 'digidargah_checkout_receipt_page'));
                add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'digidargah_checkout_return_handler'));
            }

            //Admin options for the gateway
            public function admin_options() {
                parent::admin_options();
            }

            //Processes and saves the gateway options in the admin page
            public function process_admin_options() {
                parent::process_admin_options();
            }

            //Initiate some form fields for the gateway settings
            public function init_form_fields() {
                // Populates the inherited property $form_fields.
                $this->form_fields = apply_filters('WC_DigiDargah_Config', array(
                    'enabled' => array(
                        'title' => __('فعال/غیرفعال', 'woo-digidargah-gateway'),
                        'type' => 'checkbox',
                        'label' => 'فعال سازی دیجی درگاه',
                        'description' => '',
                        'default' => 'yes',
                    ),
                    'title' => array(
                        'title' => __('عنوان', 'woo-digidargah-gateway'),
                        'type' => 'text',
                        'description' => __('این عنوان در صفحه پرداخت برای انتخاب توسط مشتری نمایش داده می شود.', 'woo-digidargah-gateway'),
                        'default' => __('درگاه پرداخت رمز ارزی دیجی درگاه', 'woo-digidargah-gateway'),
                    ),
                    'description' => array(
                        'title' => __('توضیح', 'woo-digidargah-gateway'),
                        'type' => 'textarea',
                        'description' => __('این توضیح در صفحه پرداخت و زیر عنوان بالا نمایش داده می شود.', 'woo-digidargah-gateway'),
                        'default' => __('پرداخت هزینه سفارش از طریق رمز ارزها مانند بیت کوین، اتریوم، دوج کوین و...', 'woo-digidargah-gateway'),
                    ),
                    'webservice_config' => array(
                        'title' => __('تنظیمات وب سرویس', 'woo-digidargah-gateway'),
                        'type' => 'title',
                        'description' => '',
                    ),
                    'api_key' => array(
                        'title' => __('کلید API', 'woo-digidargah-gateway'),
                        'type' => 'text',
                        'description' => __('برای ایجاد کلید API لطفا به آدرس رو به رو مراجعه نمایید. <a href="https://digidargah.com/cryptosite" target="_blank">https://digidargah.com/cryptosite</a>', 'woo-digidargah-gateway'),
                        'default' => '',
                    ),
                    'pay_currency' => array(
                        'title' => __('ارزهای قابل انتخاب', 'woo-digidargah-gateway'),
                        'type' => 'text',
                        'description' => __('به صورت پیش فرض کاربر امکان پرداخت از طریق تمامی <a href="https://digidargah.com/cryptosite" target="_blank"> ارزهای فعال </a> در درگاه را دارد اما در صورتی که تمایل دارید مشتری را محدود به پرداخت از طریق یک یا چند ارز خاص کنید، می توانید از طریق این متغییر نام ارز و یا ارزها را اعلام نمایید. در صورت تمایل به اعلام بیش از یک ارز، آنها را توسط خط تیره ( dash ) از هم جدا کنید.', 'woo-digidargah-gateway'),
                        'default' => '',
                    ),
                    'order_status' => array(
                        'title' => __('وضعیت سفارش', 'woo-digidargah-gateway'),
                        'label' => __('وضعیت سفارش پس از پرداخت توسط مشتری', 'woo-digidargah-gateway'),
                        'description' => __('پس از تایید پرداخت توسط مشتری، وضعیت سفارش روی کدام گزینه تنظیم شود ؟', 'woo-digidargah-gateway'),
                        'type' => 'select',
                        'options' => $this->valid_order_statuses(),
                        'default' => 'completed',
                    ),
                    'message_confing' => array(
                        'title' => __('تنظیم پیام ها', 'woo-digidargah-gateway'),
                        'type' => 'title',
                        'description' => __('پس از بازگشت کاربر از صفحه پرداخت، با توجه به وضعیت پرداخت، چه پیامی به وی نمایش داده شود.', 'woo-digidargah-gateway'),
                    ),
                    'success_message' => array(
                        'title' => __('پرداخت موفق', 'woo-digidargah-gateway'),
                        'type' => 'textarea',
                        'description' => __('اگر کاربر با موفقیت پرداخت را انجام داد، چه پیامی برای او نمایش داده شود. در متن پیام می توانید از عبارت های کلیدی {request_id} برای نمایش کد رهگیری و یا {order_id} برای نمایش شماره سفارش استفاده نمایید.', 'woo-digidargah-gateway'),
                        'default' => __('پرداخت شما با موفقیت انجام شد. <br><br> شماره سفارش : {order_id} <br> کد رهگیری پرداخت : {request_id}', 'woo-digidargah-gateway'),
                    ),
                    'failed_message' => array(
                        'title' => __('پرداخت ناموفق', 'woo-digidargah-gateway'),
                        'type' => 'textarea',
                        'description' => __('اگر کاربر پرداخت را به درستی انجام نداد، چه پیامی برای او نمایش داده شود. در متن پیام می توانید از عبارت های کلیدی {request_id} برای نمایش کد رهگیری و یا {order_id} برای نمایش شماره سفارش استفاده نمایید.', 'woo-digidargah-gateway'),
                        'default' => __('متاسفانه پرداخت شما با موفقیت انجام نشده است. <br><br> شماره سفارش : {order_id} <br> کد رهگیری پرداخت : {request_id}', 'woo-digidargah-gateway'),
                    ),
                ));
            }

            //Process payment
            //see process_order_payment() in the Woocommerce APIs
            //return array
            public function process_payment($order_id){
                $order = new WC_Order($order_id);
                return array('result' => 'success', 'redirect' => $order->get_checkout_payment_url(TRUE));
            }

            //Add DigiDargah Checkout items to receipt page
            public function digidargah_checkout_receipt_page($order_id) {
                
				global $woocommerce;

                $order = new WC_Order($order_id);
                $currency = $order->get_currency();

                $api_key = $this->api_key;
                $pay_currency = $this->pay_currency;

                $customer = $woocommerce->customer;
                $mail = $customer->get_billing_email();

                $amount = $order->get_total();
                $callback = add_query_arg('wc_order', $order_id, WC()->api_request_url('wc_digidargah'));

                $params = array(
					'api_key' => $api_key,
					'amount_value' => $amount,
					'amount_currency' => $currency,
                    'pay_currency' => $pay_currency,
                    'order_id' => $order_id,
                    'respond_type' => 'link',
                    'callback' => $callback,
                );

                $result = $this->call_gateway_endpoint($this->payment_endpoint, $params);
                
                if ($result->status != 'success') {
					$note = sprintf(__('پرداخت با خطا مواجه شد. <br> پاسخ درگاه پرداخت : %s', 'woo-digidargah-gateway'), $result->respond);
					$order->add_order_note($note);
                    wc_add_notice($note, 'error');
                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }

                //Save ID of this request
                update_post_meta($order_id, 'digidargah_request_id', $result->request_id);

                //Set remote status of the request to 1 as it's primary value.
                update_post_meta($order_id, 'digidargah_request_status', 1);

                $note = sprintf(__('کد رهگیری درگاه : %s', 'woo-digidargah-gateway'), $result->request_id);
                $order->add_order_note($note);
                wp_redirect($result->respond);
                exit;
            }

            //Handles the return from processing the payment
            public function digidargah_checkout_return_handler(){
                
				global $woocommerce;
				$order_id = sanitize_text_field($_GET['wc_order']);
                $order = wc_get_order($order_id);

                if (empty($order)) {
                    $this->digidargah_display_invalid_order_message();
                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
                }

                if ($order->get_status() == 'completed' || $order->get_status() == 'processing') {
                    $this->digidargah_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

                if (get_post_meta($order_id, 'digidargah_request_status', TRUE) >= 100) {
                    $this->digidargah_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }

                $api_key = $this->api_key;
                $pay_currency = $this->pay_currency;
                $request_id = get_post_meta($order_id, 'digidargah_request_id', TRUE);

                $params = array(
					'api_key' => $api_key,
                    'order_id' => $order_id,
                    'request_id' => $request_id,
                );

                $result = $this->call_gateway_endpoint($this->verify_endpoint, $params);
				
				if ($result->status != 'success') {
                    
					$note = '';
					$note .= sprintf(__('پرداخت با خطا مواجه شد. <br> پاسخ درگاه پرداخت : %s', 'woo-digidargah-gateway'), $result->respond);
                    $order->add_order_note($note);
                    $order->update_status('failed');
					wc_add_notice($note, 'error');
                    wp_redirect($woocommerce->cart->get_checkout_url());
                    exit;
					
                } else {
					
					$verify_status = !empty($this->valid_order_statuses()[$this->order_status]) ? $this->order_status : 'completed';
					
                    $verify_request_id = $result->request_id;
                    $verify_order_id = $result->order_id;
                    $verify_amount = $result->amount_value;
                    $verify_currency = $result->amount_currency;

                    // Completed
                    $note = sprintf(__('وضعیت پرداخت : %s', 'woo-digidargah-gateway'), $verify_status);
                    $note .= '<br/>';
                    $note .= sprintf(__('کد رهگیری پرداخت : %s', 'woo-digidargah-gateway'), $verify_request_id);
                    $order->add_order_note($note);

                    // Updates order's meta after verifying the payment.
                    update_post_meta($order_id, 'digidargah_request_status', $verify_status);
                    update_post_meta($order_id, 'digidargah_request_id', $verify_request_id);
                    update_post_meta($order_id, 'digidargah_order_id', $verify_order_id);
                    update_post_meta($order_id, 'digidargah_request_amount', $verify_amount);
                    update_post_meta($order_id, 'digidargah_request_currency', $verify_currency);
                    update_post_meta($order_id, 'digidargah_payment_date', $verify_date);

                    $currency = strtolower($order->get_currency());
                    $amount = $order->get_total();

                    if (empty($verify_status) || empty($verify_request_id) || number_format($verify_amount, 5) != number_format($amount) || $verify_currency != $currency) {
                        $note = __('متاسفانه در روند تایید تراکنش خطایی رخ داده است. لطفا مجددا تلاش نمایید و یا در صورت نیاز با پشتیبانی مکاتبه نمایید.', 'woo-digidargah-gateway');
						wc_add_notice($note, 'error');
                        $order->add_order_note($note);
                        $order->update_status('failed');
                        $this->digidargah_display_failed_message($order_id);
                        wp_redirect($woocommerce->cart->get_checkout_url());
                        exit;
					}

                    $order->payment_complete($verify_request_id);
                    $order->update_status($verify_status);
                    $woocommerce->cart->empty_cart();
                    $this->digidargah_display_success_message($order_id);
                    wp_redirect(add_query_arg('wc_status', 'success', $this->get_return_url($order)));
                    exit;
                }
            }

            //Shows an invalid order message
            private function digidargah_display_invalid_order_message($message=''){
                $notice = __('شماره سفارش صحیح نیست. لطفا مجددا تلاش نمایید و یا در صورت نیاز با پشتیبانی مکاتبه نمایید.', 'woo-digidargah-gateway');
                $notice = $notice . "<br>" . $message;
                wc_add_notice($notice, 'error');
            }
			
            //Shows a success message
			//This message is configured at the admin page of the gateway
            private function digidargah_display_success_message($order_id){
                $request_id = get_post_meta($order_id, 'digidargah_request_id', TRUE);
                $notice = wpautop(wptexturize($this->success_message));
                $notice = str_replace("{request_id}", $request_id, $notice);
                $notice = str_replace("{order_id}", $order_id, $notice);
                wc_add_notice($notice, 'success');
            }

            //Calls the gateway endpoints
            private function call_gateway_endpoint($url, $params){
				$ch = curl_init($url);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				$response = curl_exec($ch);
				curl_close($ch);
				$result = json_decode($response);
                return $result;
            }

            //Shows a failure message for the unsuccessful payments
			//This message is configured at the admin page of the gateway
            private function digidargah_display_failed_message($order_id, $message=''){
                $request_id = get_post_meta($order_id, 'digidargah_request_id', TRUE);
                $notice = wpautop(wptexturize($this->failed_message));
                $notice = str_replace("{request_id}", $request_id, $notice);
                $notice = str_replace("{order_id}", $order_id, $notice);
                $notice = $notice . "<br>" . $message;
                wc_add_notice($notice, 'error');
            }
			
			//
            private function valid_order_statuses(){
                return ['completed' => 'completed', 'processing' => 'processing'];
            }
        }

    }
}

//Add a function when hook 'plugins_loaded' is fired.
add_action('plugins_loaded', 'wc_gateway_digidargah_init');

?>
