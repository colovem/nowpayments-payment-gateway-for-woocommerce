<?php

/**
 * @wordpress-plugin
 * Plugin Name:             nowpayments.io Gateway for WooCommerce
 * Plugin URI:              https://www.nowpayments.io/
 * Description:             Cryptocurrency Payment Gateway.
 * Version:                 1.6.4
 * Author:                  nowpayments.io
 * Author URI:              https://www.nowpayments.io/
 * License:                 proprietary
 * License URI:             http://www..org/
 * Text Domain:             wc-nowpayments-gateway
 * Domain Path:             /i18n/languages/
 * Requires at least:       5.5
 * Tested up to:            5.9
 * WC requires at least:    4.9.4
 * WC tested up to:         6.1.1
 *
 */

/**
 * Exit if accessed directly.
 */
if (!defined('ABSPATH'))
{
    exit();
}

if (version_compare(phpversion(), '7.1', '>=')) {
    ini_set('precision', 10);
    ini_set('serialize_precision', 10);
}



if (!defined('NOWPAYMENTS_FOR_WOOCOMMERCE_PLUGIN_DIR')) {
    define('NOWPAYMENTS_FOR_WOOCOMMERCE_PLUGIN_DIR', dirname(__FILE__));
}
if (!defined('NOWPAYMENTS_FOR_WOOCOMMERCE_ASSET_URL')) {
    define('NOWPAYMENTS_FOR_WOOCOMMERCE_ASSET_URL', plugin_dir_url(__FILE__));
}
if (!defined('VERSION_PFW')) {
    define('VERSION_PFW', '1.6.4');
}


/**
 * Add the gateway to WC Available Gateways
 *
 * @since 1.0.0
 * @param array $gateways all available WC gateways
 * @return array $gateways all WC gateways + offline gateway
 */
function wc_nowpayments_add_to_gateways( $gateways ) {
    if (!in_array('WC_Gateway_nowpayments', $gateways)) {
        $gateways[] = 'WC_Gateway_nowpayments';
    }
	return $gateways;
}
add_filter( 'woocommerce_payment_gateways', 'wc_nowpayments_add_to_gateways' );


/**
 * Set HPOS feature compatible by plugin
 */
add_action(
    'before_woocommerce_init',
    function () {
        if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
            \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
        }
    }
);


/**
 * Adds plugin page links
 *
 * @since 1.0.0
 * @param array $links all plugin links
 * @return array $links all plugin links + our custom links (i.e., "Settings")
 */
function wc_nowpayments_gateway_plugin_links( $links ) {

	$plugin_links = [
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=nowpayments_gateway' ) . '">' . __( 'Configure', 'wc-nowpayments-gateway' ) . '</a>',
        '<a href="mailto:support@nowpayments.io?cc=akshay.victrans@gmail.com">' . __( 'Email Developer', 'wc-nowpayments-gateway' ) . '</a>'
	];

	return array_merge( $plugin_links, $links );
}
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'wc_nowpayments_gateway_plugin_links' );


/**
 * Nowpayments.io Payment Gateway
 *
 *
 * @class 		WC_Gateway_nowpayments
 * @extends		WC_Payment_Gateway
 * @version		1.0.0
 * @package		WooCommerce/Classes/Payment
 * @author 		Akshay Nikhare
 */
add_action('plugins_loaded', 'wc_nowpayments_gateway_init', 11);
function wc_nowpayments_gateway_init()
{

    if (!class_exists('WC_Payment_Gateway')) {
        // oops!
        return;
    }

    class WC_Gateway_nowpayments extends WC_Payment_Gateway
    {
        var $ipn_url;

        /**
         * Constructor for the gateway.
         *
         * @access public
         * @return void
         */
        public function __construct()
        {
            global $woocommerce;
            $this->id = 'nowpayments_gateway';
            $this->icon = apply_filters('woocommerce_nowpayments_icon', 'https://nowpayments.io/images/logo/nowpayments.png');
            $this->has_fields = false;
            $this->method_title = __('nowpayments.io', 'wc-gateway-nowpayments');
            $this->method_description = __( 'Allows Cryptocurrency payments via Nowpayent.io', 'wc-nowpayments-gateway' );
            $this->ipn_url = add_query_arg('wc-api', 'WC_Gateway_nowpayments', home_url('/'));

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option( 'instructions', $this->description );
            $this->ipn_secret = $this->get_option('ipn_secret');
            $this->api_key = $this->get_option('api_key');
            $this->debug_email = $this->get_option('debug_email');
            $this->debug_post_url = $this->get_option('debug_post_url');
            $this->allow_zero_confirm = $this->get_option('allow_zero_confirm') == 'yes' ? true : false;
            $this->form_submission_method = $this->get_option('form_submission_method') == 'yes' ? true : false;
            $this->invoice_prefix = $this->get_option('invoice_prefix', 'WC-');
            $this->simple_total = $this->get_option('simple_total') == 'yes' ? true : false;

            // Logs
            $this->log = new WC_Logger();

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
			add_action('woocommerce_thankyou_' . $this->id, [ $this, 'thankyou_page' ] );
            add_action('woocommerce_api_wc_gateway_nowpayments', [ $this, 'check_ipn_response']);

            // Customer Emails
			add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );

            if (!$this->is_valid_for_use()) {
                $this->enabled = false;
            }
        }


        /**
         * Initialise Gateway Settings Form Fields
         *
         * @access public
         * @return void
         */
        function init_form_fields()
        {
            require_once( 'includes/setting_form_fields.php' );
        }


        /**
		 * Output for the order received page.
		 */
		public function thankyou_page() {
			if ( $this->instructions ) {
				echo wpautop( wptexturize( $this->instructions ) );
			}


            $this->debug_post_out( 'Order at thankyou_page' , json_encode($_POST));


            // $url = "https://api.nowpayments.io/v1/payment/";
            // $content = json_encode("your data to be sent");

            // $curl = curl_init($url);
            // curl_setopt($curl, CURLOPT_HEADER, false);
            // curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            // curl_setopt($curl, CURLOPT_HTTPHEADER,
            //         ["Content-type: application/json"]);
            // curl_setopt($curl, CURLOPT_POST, true);
            // curl_setopt($curl, CURLOPT_POSTFIELDS, $content);

            // $json_response = curl_exec($curl);

            // $status = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            // if ( $status != 201 ) {
            //     die("Error: call to URL $url failed with status $status, response $json_response, curl_error " . curl_error($curl) . ", curl_errno " . curl_errno($curl));
            // }


            // curl_close($curl);

            // $response = json_decode($json_response, true);

        }

		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool $sent_to_admin
		 * @param bool $plain_text
		 */
		public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {

			if ( $this->instructions && ! $sent_to_admin && $this->id === $order->payment_method && $order->has_status( 'on-hold' ) ) {
				echo wpautop( wptexturize( $this->instructions ) ) . PHP_EOL;
			}
		}


        /**
         * Process the payment and return the result
         *
         * @access public
         * @param int $order_id
         * @return array
         */
        function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $redirect_url= $this->generate_nowpayments_url($order);
            $this->debug_post_out( 'Sending_order' , $redirect_url );
            return [
                'result' => 'success',
                'redirect' => $redirect_url
            ];

        }


         /**
         * Generate the nowpayments button link
         *
         * @access public
         * @param mixed $order_id
         * @return string
         */
        function generate_nowpayments_url($order)
        {
            global $woocommerce;

            if ($order->status != 'completed' && get_post_meta($order->id, 'nowpayments payment complete', true) != 'Yes') {
                //$order->update_status('on-hold', 'Customer is being redirected to nowpayments...');
                $order->add_order_note('Customer is being redirected to nowpayments...');
                $order->update_status('pending', 'Customer is being redirected to nowpayments...');
                $this->debug_post_out( 'update_status' ,'pending : Customer is being redirected to nowpayments...' );
            }

            $nowpayments_adr = "https://nowpayments.io/payment?data=";
            $nowpayments_args = $this->get_nowpayments_args($order);
            $nowpayments_adr .= urlencode(json_encode($nowpayments_args));
            return $nowpayments_adr;
        }


        /**
         * Get nowpayments.io Args
         *
         * @access public
         * @param mixed $order
         * @return array
         */
        function get_nowpayments_args($order)
        {
            global $woocommerce;

            $order_id = $order->id;

            if (in_array($order->billing_country, ['US', 'CA'])) {
                $order->billing_phone = str_replace(['( ', '-', ' ', ' )', '.'], '', $order->billing_phone);
            }

            // nowpayments.io Args
            $nowpayments_args = [
                // Get the currency from the order, not the active currency
                // NOTE: for backward compatibility with WC 2.6 and earlier,
                // $order->get_order_currency() should be used instead
                'dataSource' => "woocommerce",
                'ipnURL' => $this->ipn_url,
                'paymentCurrency' => $order->get_currency(),
                'successURL' => $this->get_return_url($order),
                'cancelURL' => esc_url_raw($order->get_cancel_order_url_raw()),

                // Order key + ID
                'orderID' => $this->invoice_prefix . $order->get_order_number(),
                'apiKey' => $this->api_key,

                // Billing Address info
                'customerName' => $order->billing_first_name,
                'customerEmail' => $order->billing_email,
            ];

            if ($this->simple_total) {
                $nowpayments_args['paymentAmount'] = number_format($order->get_total(), 8, '.', '');
                $nowpayments_args['tax'] = 0.00;
                $nowpayments_args['shipping'] = 0.00;
            } else if (wc_tax_enabled() && wc_prices_include_tax()) {
                $nowpayments_args['paymentAmount'] = number_format($order->get_total(), 8, '.', '');
                $nowpayments_args['shipping'] = number_format($order->get_total_shipping() + $order->get_shipping_tax(), 8, '.', '');
                $nowpayments_args['tax'] = 0.00;
            } else {
                $nowpayments_args['paymentAmount'] = number_format($order->get_total(), 8, '.', '');
                $nowpayments_args['shipping'] = number_format($order->get_total_shipping(), 8, '.', '');
                $nowpayments_args['tax'] = $order->get_total_tax();
            }
            $order_cur = wc_get_order($order_id);
            $items_cur = $order_cur->get_items();
            $items = [];
            foreach ($items_cur as $item_id => $item) {
                $items[] = $item->get_data();
            }
            $nowpayments_args["products"] = $items;
            $nowpayments_args = apply_filters('woocommerce_nowpayments_args', $nowpayments_args);

            return $nowpayments_args;
        }



        /**
         * Check if this gateway is enabled and available in the user's country
         *
         * @access public
         * @return bool
         */
        function is_valid_for_use()
        {
            //if ( ! in_array( get_woocommerce_currency(), apply_filters( 'woocommerce_nowpayments_supported_currencies', [ 'AUD', 'CAD', 'USD', 'EUR', 'JPY', 'GBP', 'CZK', 'BTC', 'LTC' ] ) ) ) return false;
            // ^- instead of trying to maintain this list just let it always work
            return true;
        }




        /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         * @since 1.0.0
         */
        public function admin_options()
        {
            ?>
            <h3><?php _e('nowpayments.io', 'woocommerce'); ?></h3>
            <p><?php _e('Completes checkout via nowpayments.io', 'woocommerce'); ?></p>

            <?php if ($this->is_valid_for_use()) : ?>

                <table class="form-table">
                    <?php
                    $this->generate_settings_html();
                    ?>
                </table>
                <!--/.form-table-->

            <?php else : ?>
                <div class="inline error">
                    <p><strong><?php _e('Gateway Disabled', 'woocommerce'); ?></strong>: <?php _e('nowpayments.io does not support your store currency.', 'woocommerce'); ?></p>
                </div>
            <?php endif;

        }


        /**
         * Check Nowpayments.io IPN validity
         **/
        function check_ipn_request_is_valid()
        {

            global $woocommerce;

            $order = false;
            $error_msg = "Unknown error";
            $auth_ok = false;
            $request_data = null;


            if (isset($_SERVER['HTTP_X_NOWPAYMENTS_SIG']) && !empty($_SERVER['HTTP_X_NOWPAYMENTS_SIG'])) {
                $recived_hmac = $_SERVER['HTTP_X_NOWPAYMENTS_SIG'];

                $request_json = file_get_contents('php://input');
                $request_data = json_decode($request_json, true);
                ksort($request_data);
                $sorted_request_json = json_encode($request_data);


                if ($request_json !== false && !empty($request_json)) {
                    $hmac = hash_hmac("sha512", $sorted_request_json, trim($this->ipn_secret));

                    if ($hmac == $recived_hmac) {
                        $auth_ok = true;
                    } else {
                        $error_msg = 'HMAC signature does not match';
                    }
                } else {
                    $error_msg = 'Error reading POST data';
                }
            } else {
                $error_msg = 'No HMAC signature sent.';
            }

            if ($auth_ok) {
                $valid_order_id = str_replace("WC-", "", $request_data["order_id"]);
                $order = new WC_Order($valid_order_id);

                if ($order !== false) {
                    // Get the currency from the order, not the active currency
                    // NOTE: for backward compsatibility with WC 2.6 and earlier,
                    $payment_currency = strtoupper($request_data["pay_currency"]);
                    if ($payment_currency == ($order->get_currency() || $payment_currency)) {
                        if ($request_data["price_amount"] >= $order->get_total()) {
                            print "IPN check OK\n";
                            return true;
                        } else {
                            $error_msg = "Amount received is less than the total!";
                        }
                    } else {
                        $error_msg = "Original currency doesn't match!";
                    }
                } else {
                    $error_msg = "Could not find order info for order ";
                }
            }

            $report = "Error Message: " . $error_msg . "\n\n";

            if ($order) {
                $order->update_status('on-hold', sprintf(__('NOWPayments.io IPN Error: %s', 'wc-nowpayments-gateway'), $error_msg));
                $this->debug_post_out( 'update_status' , sprintf(__('on-hold : NOWPayments.io IPN Error: %s', 'wc-nowpayments-gateway'), $error_msg));
            }


            $this->debug_post_out( 'Report' , $report );

            if (!empty($this->debug_email)) {
                mail($this->debug_email, "Report", $report);
            };
            die('Error: ' . $error_msg);
            return false;
        }


        function debug_post_out($key,$datain)
        {
            if (!empty($this->debug_post_url)) {

                $data = [
                    $key => $datain
                ];

                $handle = curl_init($this->debug_post_url);
                $encodedData = json_encode($data);

                curl_setopt($handle, CURLOPT_POST, 1);
                curl_setopt($handle, CURLOPT_POSTFIELDS, $encodedData);
                curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($handle, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

                $result = curl_exec($handle);
            };
        }


        /**
         * Successful Payment!
         *
         * @access public
         * @param array $posted
         * @return void
         */
        function successful_request()
        {
            global $woocommerce;

            $request_json = file_get_contents('php://input');
            $request_data = json_decode($request_json, true);
            $valid_order_id = str_replace("WC-", "", $request_data["order_id"]);
            $order = new WC_Order($valid_order_id);


            if ($request_data["payment_status"] == "finished") {
                $this->debug_post_out( 'update_status' , 'processing :Order has been paid.');
                $order->update_status('completed', 'Order has been paid.');
            } else if ($request_data["payment_status"] == "partially_paid") {
                $order->update_status('on-hold', 'Order is holded.');
                $this->debug_post_out( 'update_status' , 'on-hold: Order is holded.');
                $order->add_order_note('Your payment is partially paid. Please contact support@nowpayments.io Amount received: ' . $request_data["actually_paid"]);
            } else if ($request_data["payment_status"] == "confirming") {
                $order->update_status('processing', 'Order is processing.');
                $this->debug_post_out( 'update_status' , 'processing:Order is processing.');
            } else if ($request_data["payment_status"] == "confirmed") {
                $order->update_status('processing', 'Order is processing.');
                $this->debug_post_out( 'update_status' , 'processing:Order is processing.');
            } else if ($request_data["payment_status"] == "sending") {
                $order->update_status('processing', 'Order is processing.');
                $this->debug_post_out( 'update_status' , 'processing:Order is processing.');
            } else if ($request_data["payment_status"] == "failed") {
                $order->update_status('on-hold', 'Order is failed. Please contact support@nowpayments.io');
                $this->debug_post_out( 'update_status' , 'on-hold:Order is failed. Please contact support@nowpayments.io');
            }

            $order->add_order_note('nowpayments.io Payment Status: ' . $request_data["payment_status"]);
            $this->debug_post_out( 'add_order_note' , $valid_order_id.' = order ,  nowpayments.io Payment Status: ' . $request_data["payment_status"]);
        }

        /**
         * Check for NOWPayments IPN Response
         *
         * @access public
         * @return void
         */
        function check_ipn_response()
        {
            $this->debug_post_out( 'ipn_response_recived' , json_encode($_POST));

            @ob_clean();
            if ($this->check_ipn_request_is_valid()) {
                $this->successful_request($_POST);
            } else {
                wp_die("NOWPayments.io IPN Request Failure");
                $this->debug_post_out( 'response result wp_die' ,  'NOWPayments.io IPN Request Failure');
            }
        }
    }

}
