<?php

class WC_Paytronicks extends WC_Payment_Gateway
{
	protected $max_amount_per_order = 50000;

	protected $amount_per_transaction = 10000;

	public function __construct()
	{
		/**
		 * Payment Gateway ID
		 */
		$this->id = "paytronicks";

		/**
		 * Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
		 */
		$this->method_title = __("Paytronicks", 'paytronicks');

		/**
		 * Description for Payment Gateway, shown on the actual Payment options page on the backend
		 */
		$this->method_description = __( "Paytronicks Payment Gateway Plug-in for WooCommerce", 'paytronicks');

		/**
		 * Title in vertical tabs
		 */
		$this->title = __('Paytronicks', 'paytronicks');

		/**
		 * Icon (image to be displayed next to the gateway's name in frontend)
		 */
		$this->icon = null;

		/**
		 * Display payment fields on checkout
		 */
		$this->has_fields = true;

		/**
		 * Supports the default credit card form
		 */
		$this->supports = array('default_credit_card_form');

		/**
		 * Loan form fields
		 */
		$this->init_form_fields();

		/**
		 * Load settings
		 */
		$this->init_settings();

		/**
		 * Populate setting values
		 */
		foreach ($this->settings as $setting_key => $value ) {
			$this->$setting_key = $value;
		}

		/**
		 * Check SSL
		 */
		add_action('admin_notices', array($this, 'do_ssl_check'));

		/**
		 * Save settings
		 *
		 * Save administration options. Since we are not going to be doing anything special
		 * we have not defined 'process_admin_options' in this class so the method in the parent
		 * class will be used instead
		 */
		if (is_admin()) {
			add_action('woocommerce_update_options_payment_gateways_'.$this->id, array($this, 'process_admin_options'));
		}
		add_filter('woocommerce_checkout_fields', array($this, 'custom_checkout_fields'));
	}

	public function get_title()
	{
		return $this->title;
	}

	public function init_form_fields()
	{
		$this->form_fields = array(
			'enabled' => array(
				'title'		=> __('Enable', 'paytronicks'),
				'label'		=> __( 'Enable this payment gateway.', 'spyr-authorizenet-aim' ),
				'type'		=> 'checkbox',
				'default'	=> 'no',
			),
			'api_key' => array(
				'title'		=> __('API Key', 'paytronicks'),
				'type'		=> 'text',
				'desc_tip'	=> __('This is the API Key provided by Paytronicks when you signed up for an account.', 'paytronicks'),
			),
			'merchant_wallet_id' => array(
				'title'		=> __('Merchant Wallet ID', 'paytronicks'),
				'type'		=> 'text',
				'desc_tip'	=> __('10 Digit Merchant Wallet ID, can be found in your account.', 'paytronicks'),
			),
			'max_amount_per_order' => array(
				'title'		=> __('Maxium Amount Per Order', 'paytronicks'),
				'type'		=> 'text',
				'desc_tip'	=> __('Limit order to maximum amount as defined here. (Integer value only)', 'paytronicks'),
			),
			'amount_per_transaction' => array(
				'title'		=> __('Amount Per Transaction', 'paytronicks'),
				'type'		=> 'text',
				'desc_tip'	=> __('Amount to be withdrawn per transaction. (Integer value only)', 'paytronicks'),
			)
		);
		return $this;
	}

	public function do_ssl_check()
	{
		if( $this->enabled == "yes" ) {
			if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
				echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";	
			}
		}
	}

	public function custom_checkout_fields($fields)
	{
		$fields['billing']['billing_phone'] = array(
		    'label'     => __('Phone', 'woocommerce'),
		    'placeholder'   => _x('Phone', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('form-row-last'),
		    'clear'     => true
		);
		$fields['billing']['billing_date_of_birth'] = array(
		    'label'     => __('Date Of Birth (YYYY-MM-DD)', 'woocommerce'),
		    'placeholder'   => _x('YYYY-MM-DD', 'placeholder', 'woocommerce'),
		    'required'  => true,
		    'class'     => array('form-row-wide'),
		    'clear'     => true
		);
		return $fields;
	}

	/**
	 * Process the payment and return the result
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function process_payment($orderId)
	{
		require_once 'vendor/autoload.php';
		global $woocommerce;
		$order = new WC_Order($orderId);
		$ccExpiry = explode('/', str_replace(' ', '', $_POST['paytronicks-card-expiry']));
		$ccExpiryMonth = $ccExpiry[0];
		$ccExpiryYear = $ccExpiry[1];
		if (0 === strpos($ccExpiryMonth, '0')) {
			$ccExpiryMonth = ltrim($ccExpiryMonth, '0');
		}
		if ($ccExpiryMonth < 1 || $ccExpiryMonth > 12) {
			throw new Exception(__('Invalid credit card expiry', 'paytronicks'));
		}
		$cardPayment = new Oml\PaymentGateway\Paytronicks\CreditCard();
		$orderAmount = $order->order_total;
		if ($orderAmount > $this->max_amount_per_order) {
			throw new Exception(__('Order amount exceeded defined limit of $50,000. Please update your cart with the order amount with max $50,000', 'paytronicks'));
		}
		if (!$this->verify_date($_POST['billing_date_of_birth'])) {
			throw new Exception(__('Invalid date-of-birth in billing address, required format YYYY-MM-DD', 'paytronicks'));
		}
		$params = array(
			'key' => $this->api_key,
			'payment' => 'single',
			'currency' => 'USD',
			'number' =>  str_replace(array(' ', '-'), '', $_POST['paytronicks-card-number']),
			'month' => $ccExpiryMonth,
			'year' => $ccExpiryYear,
			'cvv' => $_POST['paytronicks-card-cvc'],
			'firstname' => $_POST['billing_first_name'],
			'lastname' => $_POST['billing_last_name'],
			'email' => $_POST['billing_email'],
			'phone' => $_POST['billing_phone'],
			'country' => $_POST['billing_country'],
			'address' => $_POST['billing_address_1'],
			'city' => $_POST['billing_city'],
			'state' => $_POST['billing_state'],
			'zip' => $_POST['billing_postcode'],
			'birth' => $_POST['billing_date_of_birth'],
			'ip' => $_SERVER['REMOTE_ADDR'],
		);
		$amounts = $this->split_order_total($orderAmount, $this->amount_per_transaction);
		$amountWithdrawn = 0;
		foreach ($amounts as $index => $amount) {
			$params['order'] = $orderId.'-'.$index.'-'.rand(100, 999);
			$params['amount'] = $amount;
			$cardPayment->fromArray($params);
			$payment = new Oml\PaymentGateway\Processor\Payment($cardPayment);
			$payment->setHttpClient(new \GuzzleHttp\Client);
			try {
				$payment->process();
				$httpContent = $payment->getHttpContent();
				$xml = simplexml_load_string($httpContent, "SimpleXMLElement", LIBXML_NOCDATA);
				$content = json_decode(json_encode($xml), true);
				$amountWithdrawn = $amountWithdrawn + $amount;
				if (is_array($content) && array_key_exists('response', $content)) {
					$response = $content['response'];
					$errorFound = false;
					if('failed' == $response['result']) {
						$errorFound = true;
						$errorMessage  = 'Error ('.$response['code'].') '.$response['error'].' - ';
						$errorMessage .= 'Total amount withdrew ('.$amountWithdrawn.')';
						throw new Exception(__($errorMessage, 'paytronicks'));
					}
					// All payment withdrew successfully
					if (count($amounts) == $index && 'success' == $response['result']) {
						$order->add_order_note(__('Processed payment of ($'.$amountWithdrawn.') successfully', 'paytronicks'));
						// Mark order as Paid
						$order->payment_complete();
						// Empty the cart (Very important step)
						$woocommerce->cart->empty_cart();
						// Redirect to thank you page
						return array(
							'result'   => 'success',
							'redirect' => $this->get_return_url($order),
						);
					}
				}
			} catch (Exception $e) {
				throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience - 1.', 'paytronicks'));
			}
		}
		throw new Exception(__('We are currently experiencing problems trying to connect to this payment gateway. Sorry for the inconvenience - 2.', 'paytronicks'));
	}

	/**
	 * Split total order amount
	 */
	protected function split_order_total($orderAmount, $amountPerTransaction)
	{
		$orderAmount = $orderAmount;
		$amountPerTransaction = $amountPerTransaction;
		$numberOfTransactions = $orderAmount / $amountPerTransaction;
		$numberOfTransactions = ceil($numberOfTransactions);
		$payableAmount = $orderAmount;
		$amount = array();
		for ($i = 1; $i < $numberOfTransactions + 1; $i++) {
			$amounts[$i] = $payableAmount > $amountPerTransaction ? $amountPerTransaction : $payableAmount;
			$payableAmount = $payableAmount - $amountPerTransaction;
		}
		return $amounts;
	}

	/**
	 * Verify date format
	 */
	public static function verify_date($date, $format = 'Y-m-d', $strict = true)
    {
        $dateTime = DateTime::createFromFormat($format, $date);
        if ($strict) {
            $errors = DateTime::getLastErrors();
            if (!empty($errors['warning_count'])) {
                return false;
            }
        }
        return $dateTime !== false;
    }
}
