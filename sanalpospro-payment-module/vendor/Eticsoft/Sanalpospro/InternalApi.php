<?php
/**
 * Class InternalApi 
 * @package Eticsoft\Sanalpospro
 * @description InternalApi class is used to handle the internal api requests 
 * from the module admin UI. 
 * @version 1.0
 * @since 1.0
 * @author EticSoft R&D Lab.
 * @license MIT
 */

namespace Eticsoft\Sanalpospro;

// Exit if accessed directly
if (!defined('ABSPATH')) exit;

use Eticsoft\Sanalpospro\EticContext;
use Eticsoft\Sanalpospro\EticConfig;
use Eticsoft\Sanalpospro\EticTools;
use Eticsoft\Sanalpospro\Payment;
use Eticsoft\Sanalpospro\ApiClient;

use Eticsoft\Common\Models\Cart;
use Eticsoft\Common\Models\Payer;
use Eticsoft\Common\Models\Order;
use Eticsoft\Common\Models\Invoice;
use Eticsoft\Common\Models\Address;
use Eticsoft\Common\Models\Shipping;
use Eticsoft\Common\Models\PaymentRequest;
use Eticsoft\Common\Models\PaymentModel;
use Eticsoft\Common\Models\CartItem;



class InternalApi
{

    public ?string $action = null;
    public ?string $payload = null;
    public ?array $params = [];
    public ?array $response = [
        'status' => 'error',
        'message' => 'Internal error',
        'data' => [],
        'details' => [],
        'meta' => [
            'xfvv' => null,
            'nonce' => null
        ]
    ];

    public $module;

    public function run(?string $action = null,  ?array $params = null): self
    {
        if(isset($this->response['meta']['xfvv']) || !$this->response['meta']['nonce']){
            $this->response['meta']['xfvv'] = wp_create_nonce('sppro_internal_api_request');
        }
        if($action){
            $this->action = $action;
        } else {
            $this->setAction();
        }
        if($params){
            $this->params = $params;
        } else {
            $this->setParams();
        }
        $this->call();
        return $this;
    }

    public static function getInstance(): self
    {
        return new self();
    }

    public function setAction(): self
    {
        
        $this->action = EticTools::postVal('iapi_action', false);
        return $this;
    }

    public function setParams(): self
    {
        $params = EticTools::postVal('iapi_params', '');
        $this->params = json_decode($params, true);
        return $this;
    }

    public function setModule($module): self
    {
        $this->module = $module;
        return $this;
    }

    public function call(): self
    {
       
        if (!$this->action) {
            return $this->setResponse('error', 'Action not found. #' . $this->action);
        }
       
        
        $this->action = ucfirst($this->action);
        if (!method_exists($this, 'action' . $this->action)) {
            return $this->setResponse('error', 'Action func not found. #' . 'action' . $this->action);
        }
        
        $f_name = 'action' . $this->action;
        return $this->$f_name();
    }

    public function setResponse(string $status = 'success', string $message = '', array $data = [], array $details = []): self
    {
        $this->response = [
            'status' => $status,
            'message' => $message,
            'data' => $data,
            'details' => $details,
            'meta' => $this->response['meta']
        ];

        if ($status != 'success') {
            unset($this->response['data']);
        }

        return $this;
    }

    private function actionSaveApiKeys(): self
    {
       
        $params_json = EticTools::postVal('iapi_params', '');
        if (empty($params_json)) {
            return $this->setResponse('error', 'No parameters provided');
        }

        $params_json = stripslashes($params_json);
        $params = json_decode($params_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->setResponse('error', 'Invalid JSON data: ' . json_last_error_msg());
        }

       
        $publicKey = isset($params['iapi_publicKey']) ? $params['iapi_publicKey'] : '';
        $secretKey = isset($params['iapi_secretKey']) ? $params['iapi_secretKey'] : '';

        if (empty($publicKey) || empty($secretKey)) {
            return $this->setResponse('error', 'Public key and secret key are required');
        }

        
        EticConfig::set('SANALPOSPRO_PUBLIC_KEY', $publicKey);
        EticConfig::set('SANALPOSPRO_SECRET_KEY', $secretKey);

        return $this->setResponse('success', 'API keys saved successfully');
    }

    private function actionCheckApiKeys(): self
    {
       
        $params_json = EticTools::postVal('iapi_params');
        $params_json = stripslashes($params_json);
        $params = json_decode($params_json, true);

      
        $accessToken = isset($params['iapi_accessToken']) ? $params['iapi_accessToken'] : null;

        if (!$accessToken) {
            return $this->setResponse(
                'error',
                'Access token is required',
                [],
                [
                    'params' => $params,
                    'received_data' => $_POST
                ]
            );
        }

        
        
        $apiClient = ApiClient::getInstanse();
        $response = $apiClient->post('/check/accesstoken', [
            'accesstoken' => $accessToken
        ]);

        // Ensure $this->response is always an array
        if (is_array($response)) {
            $this->response = $response;
            
            // API'den dönen token_string'i kaydet
            if (isset($this->response['data']['token_string'])) {
                EticConfig::set('SANALPOSPRO_ACCESS_TOKEN', $this->response['data']['token_string']);
            }
        } else {
            $this->setResponse('error', 'Invalid response from API', [], ['raw_response' => $response]);
        }
        return $this;
    }

    private function actionGetMerchantInfo(): self
    {
        try {

            $merchant_data = [
                'store' => [
                    'name' => EticConfig::getWooValue('store_name'),
                    'url' => EticConfig::getWooValue('store_url'),
                    'admin_email' => EticConfig::getWooValue('store_email'),
                    'phone' => EticConfig::getWooValue('store_phone'),
                    'language' => EticConfig::getWooValue('store_language'),
                    'address' => [
                        'street' => EticConfig::getWooValue('store_address'),
                        'street2' => EticConfig::getWooValue('store_address_2'),
                        'city' => EticConfig::getWooValue('store_city'),
                        'postcode' => EticConfig::getWooValue('store_postcode'),
                        'country' => EticConfig::getWooValue('store_country')
                    ],
                ],
                'payment' => [
                    'currency' => EticConfig::getWooValue('currency'),
                    'currency_symbol' => EticConfig::getWooValue('currency_symbol'),
                ]
            ];

            $this->setResponse('success', 'Merchant information retrieved successfully', $merchant_data);
            return $this;
            
        } catch (\Exception $e) {
            return $this->setResponse(
                'error',
                'Failed to retrieve merchant information: ' . $e->getMessage(),
                [],
                ['exception' => $e->getMessage()]
            );
        }
    }

    private function actionSetInstallmentOptions(): self
    {
        
        $params_json = EticTools::postVal('iapi_params', '');
        if (empty($params_json)) {
            return $this->setResponse('error', 'No parameters provided');
        }

        $params_json = stripslashes($params_json);
        $params = json_decode($params_json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->setResponse('error', 'Invalid JSON data: ' . json_last_error_msg());
        }

     
        $installmentOptions = isset($params['iapi_installmentOptions']) ? $params['iapi_installmentOptions'] : null;

        if (empty($installmentOptions)) {
            return $this->setResponse('error', 'Installment options are required');
        }

    
        EticConfig::set('SANALPOSPRO_INSTALLMENTS', json_encode($installmentOptions));
        
        return $this->setResponse('success', 'Installment options updated successfully');
    }

   private function actionCreatePaymentLink(): self
    {
        if (!isset($this->params['order_id']) || empty($this->params['order_id'])) {
            return $this->setResponse('error', 'Order ID is required');
        }
        if (!isset($this->params['receipt_nonce']) || empty($this->params['receipt_nonce'])) {
            return $this->setResponse('error', 'Receipt nonce is required');
        }

        $order_id = sanitize_text_field($this->params['order_id']);
    
       
        $order = wc_get_order($order_id);
        if (!$order) {
            return $this->setResponse('error', 'Invalid order ID');
        }

      
        $wc_cart = WC()->cart;
        $customer = WC()->customer;

        if (!$wc_cart || !$customer) {
            return $this->setResponse('error', 'Cart or customer not found');
        }

      
   
        $cart_total = floatval($wc_cart->get_total('edit'));
        $cart_total = \wc_format_decimal($cart_total, 2);
        
        $cart_total_discount = floatval($wc_cart->get_subtotal('edit'));
        $cart_total_discount = \wc_format_decimal($cart_total_discount, 2);
    
        if ($cart_total < 1) {
            return $this->setResponse('error', 'Cart total must be at least 1');
        }

     
        $cartModel = new Cart();

    
        foreach ($wc_cart->get_cart() as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];
            $price = floatval(\wc_get_price_excluding_tax($product));
            $price = \wc_format_decimal($price, 2);
            if ($price > 0) {
                $cartItem = new CartItem(
                    'PRD-' . $product->get_id(),
                    $product->get_name(),
                    'product',
                    $price,
                    $cart_item['quantity']
                );
                $cartModel->addItem($cartItem);
            }
        }
       
        $discounts = $wc_cart->get_coupons();
        if (!empty($discounts)) {
          
            $coupon_discount_totals = $wc_cart->get_coupon_discount_totals();
            
            foreach ($discounts as $coupon_code => $coupon) {
              
                $discount_amount = isset($coupon_discount_totals[$coupon_code]) 
                    ? floatval($coupon_discount_totals[$coupon_code]) 
                    : 0;
                
               
                if ($discount_amount <= 0) {
                    $discount_amount = (float) $coupon->get_amount();
                    $discount_type = $coupon->get_discount_type();
                    
                    if ($discount_type === 'percent') {
                        $discount_amount = ($cart_total_discount * $discount_amount) / 100;
                    }
                }
                
             
                $discount_amount = \wc_format_decimal($discount_amount, 2);
                
              
                if ($discount_amount > 0) {
                    $discountItem = new CartItem(
                        'DSC-' . $coupon->get_code(),
                        __('Discount', 'sanalpospro-payment-module'),
                        'discount',
                        $discount_amount,
                        1
                    );
                    
                    $cartModel->addItem($discountItem);
                }
            }
        }   
        $shipping_total = floatval($wc_cart->get_shipping_total());
        $shipping_total = \wc_format_decimal($shipping_total, 2);
        
        if ($shipping_total > 0) {
            $shippingItem = new CartItem(
                'SHIP-' . random_int(1000, 9999),
                __('Shipping', 'sanalpospro-payment-module'),
                'shipping',
                $shipping_total,
                1
            );
            $cartModel->addItem($shippingItem);
        }

      
        $total_tax = floatval($wc_cart->get_total_tax());
        $total_tax = \wc_format_decimal($total_tax, 2);
        
        
        if ($total_tax > 0) {
           
            $tax_totals = $wc_cart->get_tax_totals();
            $tax_labels = [];
            
         
            foreach ($tax_totals as $code => $tax) {
              
                $tax_amount = \wc_format_decimal($tax->amount, 2);
                $tax_labels[] = $tax->label . ': ' . wc_price($tax_amount);
            }
            
           
            $tax_label = __('Tax', 'sanalpospro-payment-module');
            
            
            $taxItem = new CartItem(
                'TAX-TOTAL-' . random_int(1000, 9999),
                __('Tax', 'sanalpospro-payment-module'),
                'tax',
                $total_tax,
                1
            );
            $cartModel->addItem($taxItem);
        }
        $receipt_nonce = $this->params['receipt_nonce'];
     
        $order_confirmation_url = add_query_arg(
            array(
                'order_id' => $order_id,
                'key' => $order->get_order_key(),
                '_wpnonce' => $receipt_nonce
            ),
            $order->get_checkout_payment_url(true)
        );
   
        
        $payment = new PaymentModel();
        $payment->setAmount($cart_total);
        $payment->setCurrency(get_woocommerce_currency());
        $payment->setBuyerFee('0');
        $payment->setMethod('creditcard');
        $payment->setMerchantReference($order_id);
        $payment->setReturnUrl($order_confirmation_url);
        
        $payerAddress = new Address();
        //$payerAddress->setLine1($customer->get_billing_address_1());
        //$payerAddress->setCity($customer->get_billing_city());
        //$payerAddress->setState($customer->get_billing_state());
        //$payerAddress->setPostalCode(empty($customer->get_billing_postcode()) ? '07050' : $customer->get_billing_postcode());
        //$payerAddress->setCountry($customer->get_billing_country());
        $payerAddress->setLine1(
            !empty($customer->get_shipping_address_1()) 
                ? $customer->get_shipping_address_1()
                : (!empty($customer->get_billing_address_1()) 
                    ? $customer->get_billing_address_1() 
                    : 'testadress')
        );
        $payerAddress->setCity(
            !empty($customer->get_shipping_city())
                ? $customer->get_shipping_city()
                : (!empty($customer->get_billing_city())
                    ? $customer->get_billing_city()
                    : 'testcity')
        );
        $payerAddress->setState(
            !empty($customer->get_shipping_state())
                ? $customer->get_shipping_state() 
                : (!empty($customer->get_billing_state())
                    ? $customer->get_billing_state()
                    : 'teststate')
        );
        $payerAddress->setPostalCode(
            !empty($customer->get_shipping_postcode())
                ? $customer->get_shipping_postcode()
                : (!empty($customer->get_billing_postcode())
                    ? $customer->get_billing_postcode()
                    : '07050')
        );
        $payerAddress->setCountry(
            !empty($customer->get_shipping_country())
                ? $customer->get_shipping_country()
                : (!empty($customer->get_billing_country())
                    ? $customer->get_billing_country()
                    : 'TR')
        );

        $shippingPhone = $customer->get_shipping_phone() ?: $customer->get_billing_phone() ?: '5000000000';
        $phone = $customer->get_billing_phone() ?: '5000000000';
   
        $payer = new Payer();
        $payer->setFirstName($customer->get_billing_first_name());
        $payer->setLastName($customer->get_billing_last_name());
        $payer->setEmail($customer->get_billing_email());
        $payer->setPhone($phone);
        $payer->setAddress($payerAddress);
        $payer->setIp(function_exists('\WC_Geolocation::get_ip_address') ? \WC_Geolocation::get_ip_address() : (isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '127.0.0.1'));

        $invoice = new Invoice();
        $invoice->setId($wc_cart->get_cart_hash());
        $invoice->setFirstName($customer->get_billing_first_name());
        $invoice->setLastName($customer->get_billing_last_name());
        $invoice->setPrice($cart_total);
        $invoice->setQuantity(1);
        
        
        
        $shipping = new Shipping();
        $shipping->setFirstName($customer->get_shipping_first_name());
        $shipping->setLastName($customer->get_shipping_last_name());
        $shipping->setPhone($phone);
        $shipping->setEmail($customer->get_billing_email());
        $shipping->setAddress($payerAddress);
        

        $order = new Order();
        $order->setCart($cartModel->toArray()['items']);
        $order->setShipping($shipping);
        $order->setInvoice($invoice);

        $paymentRequest = new PaymentRequest();
        $paymentRequest->setPayment($payment);
        $paymentRequest->setPayer($payer);
        $paymentRequest->setOrder($order);

        $result = Payment::createPayment($paymentRequest->toArray());
                
        $this->response = $result;
        return $this;
    }
    private function actionConfirmOrder(): self
    {
        $order_id = $this->params['order_id'];
        $order = \wc_get_order($order_id);

        if (!$order) {
            return $this->setResponse('error', 'Order not found');
        }

        try {
            $process_token = $this->params['process_token'];
            $res = Payment::validatePayment($process_token);

            if ($res['status'] != 'success') {
                $order->update_status('failed', __('Payment validation failed', 'sanalpospro-payment-module'));
                return $this->setResponse('error', 'Payment validation failed');
            }
            

            $transaction = $res['data']['transaction'] ?? [];
            $process = $res['data']['process'] ?? [];
            $result = $res['data']['result'] ?? [];
 
           

            if (
                ($transaction['status'] === 'completed') && 
                ($process['process_status'] === 'completed') && 
                ($result['status'] === 'completed')
            ) 
            {
                return $this->setResponse('success', 'Payment validated', [
                    'amount' => $process['amount'] ?? 0,
                    'gateway' => $process['gateway'] ?? ''
                ]);
            } else {
                $error_message = $result['message'] ?? $process['result_message'] ?? 'Payment failed';
                $order->update_status('failed', __($error_message, 'sanalpospro-payment-module'));
                return $this->setResponse('error', $error_message);
            }
        } catch (\Exception $e) {
            $order->update_status('failed', $e->getMessage());
            return $this->setResponse('error', 'Payment validation failed: ' . $e->getMessage());
        }
    }

    private function actionSetModuleSettings(): self
    {
        
        $params_json = EticTools::postVal('iapi_params');
        $params_json = stripslashes($params_json); 
        
      
        $params = json_decode($params_json, true);

       
        $settings = isset($params['iapi_moduleSettings']) ? $params['iapi_moduleSettings'] : null;

        try {
            if ($settings && is_array($settings)) {
                foreach ($settings as $key => $value) {
                    $option_name = 'SANALPOSPRO_' . strtoupper($key);
                    update_option($option_name, $value);
                }
                $this->setResponse('success', 'Module settings updated', [
                    'updated_settings' => $settings
                ]);
            } else {
                return $this->setResponse(
                    'error', 
                    'Invalid module settings', 
                    [], 
                    [   
                        'params_json_raw' => EticTools::postVal('iapi_params'),
                        'params_json_cleaned' => $params_json,
                        'decoded_params' => $params,
                        'settings' => $settings,
                        'json_last_error' => json_last_error_msg()
                    ]
                );
            }
        } catch (\Exception $e) {
            return $this->setResponse(
                'error', 
                'Failed to update module settings: ' . $e->getMessage(),
                [],
                ['exception' => $e->getMessage()]
            );
        }
        return $this;
    }
  
    public function getResponse()
    {
        return $this->response;
    }
}
