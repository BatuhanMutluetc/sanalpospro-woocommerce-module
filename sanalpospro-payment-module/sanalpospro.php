<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/* ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL); */

require_once __DIR__ . '/vendor/include.php';

use Eticsoft\Sanalpospro\EticConfig;

/*
 * Plugin Name: SanalPosPRO Payment Gateway
 * Plugin URI: https://sanalpospro.com
 * Description: SanalPosPRO payment gateway for WooCommerce
 * Version: 10.0.4
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Author: EticSoft R&D Lab.
 * Author URI: https://eticsoft.com
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: sanalpospro-payment-module
 * Domain Path: /languages
 */

// Define constants
if (!defined('SPPRO_VERSION')) {
    define('SPPRO_VERSION', '10.0.4');
}
if (!defined('SPPRO_PLUGIN_URL')) {
    define('SPPRO_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('SPPRO_PLUGIN_DIR')) {
    define('SPPRO_PLUGIN_DIR', plugin_dir_path(__FILE__));
}
define('SPPRO_PLUGIN_FILE', __FILE__);
if (!defined('SPPRO_PAYMENT_ORIGIN')) {
    define('SPPRO_PAYMENT_ORIGIN', 'https://pay.paythor.com');
}

// Hook registration
register_activation_hook(__FILE__, 'sppro_activate_plugin');
add_action('plugins_loaded', 'sppro_setup_gateway_class');
add_action('plugins_loaded', 'sppro_setup_admin_page');
add_action('woocommerce_blocks_loaded', 'sppro_register_block_support');
add_action('wp_footer', 'sppro_add_payment_iframe_script');
add_action('wp_ajax_sppro_internal_api_request', 'sppro_internal_api_request');
add_action('wp_ajax_nopriv_sppro_internal_api_request', 'sppro_internal_api_request');
add_action('wp_enqueue_scripts', 'sppro_enqueue_styles');
add_action('admin_enqueue_scripts', 'sppro_enqueue_admin_assets');
add_action('admin_init', 'sppro_check_theme_compatibility');


/**
 * Enqueue admin assets
 */
function sppro_enqueue_admin_assets() {
    wp_enqueue_style('sppro-admin-popup', SPPRO_PLUGIN_URL . 'assets/css/admin-popup.css', array(), SPPRO_VERSION);
    wp_enqueue_script('sppro-admin-popup', SPPRO_PLUGIN_URL . 'assets/js/admin-popup.js', array('jquery'), SPPRO_VERSION, true);
}

/**
 * Register WooCommerce Blocks checkout support
 */
function sppro_register_block_support()
{
    if (!class_exists('Automattic\\WooCommerce\\Blocks\\Payments\\Integrations\\AbstractPaymentMethodType')) {
        return;
    }

    require_once SPPRO_PLUGIN_DIR . 'includes/blocks/class-sppro-blocks-support.php';

    add_action(
        'woocommerce_blocks_payment_method_type_registration',
        function (\Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
            $payment_method_registry->register(new SPPRO_Blocks_Support());
        }
    );
}

/**
 * Check theme compatibility
 * Displays admin notice if WooCommerce block checkout is enabled
 */
function sppro_check_theme_compatibility()
{

    if (!is_admin()) {
        return;
    }


    $using_wc_blocks = false;


    if (class_exists('WC_Blocks_Utils') && method_exists('WC_Blocks_Utils', 'is_block_checkout_enabled')) {
        $using_wc_blocks = \WC_Blocks_Utils::is_block_checkout_enabled();
    }


    if (!$using_wc_blocks && function_exists('wc_get_page_id')) {
        $checkout_page_id = wc_get_page_id('checkout');
        if ($checkout_page_id > 0) {
            $checkout_page = get_post($checkout_page_id);
            if ($checkout_page && has_block('woocommerce/checkout', $checkout_page->post_content)) {
                $using_wc_blocks = true;
            }
        }
    }


    if ($using_wc_blocks) {
        add_action('admin_notices', 'sppro_block_checkout_admin_notice');
    }
}

/**
 * Display admin notice for WooCommerce block checkout incompatibility
 */
function sppro_block_checkout_admin_notice()
{
   // Ensure the popup HTML is added to the footer
   add_action('admin_footer', 'sppro_popup_html');
   ?>
       <div class="notice notice-error sppro-notice" style="display: flex; align-items: center; padding: 15px 20px;">
           <div style="margin-right: 15px;">
               <?php
               // Using proper WordPress way to display an image
               $image_attributes = [
                   'src' => esc_url(WC()->plugin_url() . '/assets/images/icons/info.svg'),
                   'alt' => esc_attr__('Warning', 'sanalpospro-payment-module'),
                   'width' => 48,
                   'height' => 48,
               ];
               
               echo '<img';
               foreach ($image_attributes as $name => $value) {
                   echo ' ' . esc_attr($name) . '="' . esc_attr($value) . '"';
               }
               echo '>';
               ?>
           </div>
           <div>
               <p style="font-size: 16px; margin: 0; line-height: 1.5;"><strong><?php esc_html_e('SanalPosPro Payment Gateway Warning', 'sanalpospro-payment-module'); ?></strong>: <?php esc_html_e('You need to switch to Classic Checkout view!', 'sanalpospro-payment-module'); ?> 
                   <button id="sppro-show-instructions" class="button button-primary" style="margin-left: 15px; font-size: 14px; padding: 5px 15px;"><?php esc_html_e('How to do it?', 'sanalpospro-payment-module'); ?></button>
               </p>
           </div>
       </div>
   <?php
}

/**
 * Add popup HTML to admin footer
 */
function sppro_popup_html() {
    ?>
    <div class="sppro-popup-overlay" id="sppro-popup">
        <div class="sppro-popup-content">
            <a href="#" class="sppro-popup-close">&times;</a>
            <div class="sppro-popup-title"><?php esc_html_e('SanalPosPro Payment Gateway Compatibility Notice', 'sanalpospro-payment-module'); ?></div>
            <p style="font-size: 15px; line-height: 1.6;"><?php esc_html_e('After the WooCommerce infrastructure update, the block-based payment option view is not currently available in our plugin. This issue is in our planning and will be completed by our team as soon as possible.', 'sanalpospro-payment-module'); ?></p>
            <p style="font-size: 15px; line-height: 1.6;"><strong><?php esc_html_e('The classic payment view can be activated with the following steps:', 'sanalpospro-payment-module'); ?></strong></p>
            <ol style="font-size: 15px; line-height: 1.8;">
                <li><?php esc_html_e('Back up your site just in case.', 'sanalpospro-payment-module'); ?></li>
                <li><?php esc_html_e('Go to WordPress admin panel > Pages > Checkout Page.', 'sanalpospro-payment-module'); ?></li>
                <li><?php esc_html_e('After clicking on Payment options, click on the Block button in the right menu.', 'sanalpospro-payment-module'); ?></li>
                <li><?php esc_html_e('Click on the Switch to classic checkout button in this area and save the changes with the Save button.', 'sanalpospro-payment-module'); ?></li>
            </ol>
            <p style="font-size: 15px; line-height: 1.6;"><?php esc_html_e('After these steps, the Classic payment view will be active and you can use our plugin.', 'sanalpospro-payment-module'); ?></p>
        </div>
    </div>
    <?php
    }
    
/**
 * Plugin activation function
 * Checks requirements and sets up default settings
 */
function sppro_activate_plugin()
{

    if (version_compare(PHP_VERSION, '7.4', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html(sprintf(
                /* translators: 1: Required PHP version, 2: Current PHP version */
                __('SanalPosPRO Payment Gateway requires PHP version %1$s or higher. You are running version %2$s. Please upgrade PHP and try again.', 'sanalpospro-payment-module'),
                '7.4',
                PHP_VERSION
            )),
            esc_html(__('Plugin Activation Error', 'sanalpospro-payment-module')),
            array('back_link' => true)
        );
    }


    if (version_compare($GLOBALS['wp_version'], '5.8', '<')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html(sprintf(
                /* translators: 1: Required WordPress version, 2: Current WordPress version */
                __('SanalPosPRO Payment Gateway requires WordPress version %1$s or higher. You are running version %2$s. Please upgrade WordPress and try again.', 'sanalpospro-payment-module'),
                '5.8',
                esc_html($GLOBALS['wp_version'])
            )),
            esc_html(__('Plugin Activation Error', 'sanalpospro-payment-module')),
            array('back_link' => true)
        );
    }


    if (!class_exists('WooCommerce')) {
        deactivate_plugins(plugin_basename(__FILE__));
        wp_die(
            esc_html(__('SanalPosPRO Payment Gateway requires WooCommerce to be installed and activated.', 'sanalpospro-payment-module')),
            esc_html(__('Plugin Activation Error', 'sanalpospro-payment-module')),
            array('back_link' => true)
        );
    }

    // Set default settings
    $default_settings = array(
        'SANALPOSPRO_ORDER_STATUS' => 'processing',
        'SANALPOSPRO_CURRENCY_CONVERT' => 'no',
        'SANALPOSPRO_SHOWINSTALLMENTSTABS' => 'no',
        'SANALPOSPRO_PAYMENTPAGETHEME' => 'classic',
        'SANALPOSPRO_INSTALLMENTS' => json_encode([]),
        'SANALPOSPRO_VERSION' => SPPRO_VERSION
    );

    foreach ($default_settings as $key => $value) {
        if (false === get_option($key)) {
            update_option($key, $value);
        }
    }

    // Flush rewrite rules
    flush_rewrite_rules();
}

/**
 * Setup admin page
 */
function sppro_setup_admin_page()
{
    if (is_admin()) {
        require_once SPPRO_PLUGIN_DIR . 'admin/class-admin.php';
        new SPPRO_Admin();
    }
}

/**
 * Setup payment gateway class
 */
function sppro_setup_gateway_class()
{
    if (!class_exists('WC_Payment_Gateway')) {
        return;
    }

    /**
     * SanalPosPRO Payment Gateway Class
     */
    class SPPRO_Payment_Gateway extends WC_Payment_Gateway
    {
        /**
         * Constructor for the gateway
         */
        public function __construct()
        {
            $this->id = 'sanalpospro';
            $this->icon = SPPRO_PLUGIN_URL . 'assets/images/sanalpospro-logo.png';
            $this->has_fields = false;
            $this->method_title = 'SanalPosPRO';
            $this->method_description = __('SanalPosPRO Payment Gateway for WooCommerce plugin', 'sanalpospro-payment-module');
            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = __('Pay via Card (SanalPosPRO)', 'sanalpospro-payment-module');
            $this->description = __('Payment by your credit/debit card.', 'sanalpospro-payment-module');

            // Actions and filters
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
            add_action('woocommerce_receipt_' . $this->id, array($this, 'receipt_page'));
            add_filter('wp_kses_allowed_html', array($this, 'allow_iframe_in_html'), 10, 2);
            add_filter('woocommerce_gateway_' . $this->id . '_settings_args', array($this, 'remove_wpautop'));

            if (is_admin()) {
                add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
                add_action('woocommerce_admin_order_data_after_billing_address', array($this, 'show_payment_warning'), 10, 1);
            }
        }


        /**
         * Initialize form fields for the gateway settings
         */
        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'sanalpospro-payment-module'),
                    'type' => 'checkbox',
                    'label' => __('Enable/Disable SanalPosPRO payment gateway', 'sanalpospro-payment-module'),
                    'default' => 'yes',
                    'description' => __('Enable/Disable SanalPosPRO payment gateway', 'sanalpospro-payment-module'),
                    'desc_tip' => true,
                ),
                'panel_button' => array(
                    'title' => '',
                    'type' => 'panel_button',
                    'description' => '',
                    'desc_tip' => false,
                ),
            );
        }

        /**
         * Check if gateway is available for use
         */
        public function is_available()
        {

            if ('no' === $this->get_option('enabled')) {
                return false;
            }


            if (!class_exists('WC_Payment_Gateway')) {
                return false;
            }

            return true;
        }

        /**
         * Generate panel button HTML
         */
        public function generate_panel_button_html($key, $data)
        {
            return '<tr><td colspan="2" style="padding-left: 0;">
                <a href="' . esc_url(admin_url('admin.php?page=sppro_admin')) . '" 
                   class="button button-primary">
                    ' . esc_html__('Click to Access SanalPosPRO Panel', 'sanalpospro-payment-module') . '
                </a>
            </td></tr>';
        }

        /**
         * Payment fields displayed on checkout
         */
        public function payment_fields()
        {
            $supported_cards = array('visa', 'mastercard', 'amex', 'troy');

            sppro_get_template('checkout/payment-form.php', array(
                'description' => $this->description,
                'supported_cards' => $supported_cards
            ));
        }

        /**
         * Process payment
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $pay_for_order = isset($_GET['pay_for_order']) && sanitize_text_field(wp_unslash($_GET['pay_for_order']));
            $xfvv = wp_create_nonce('sppro_internal_api_request');
            $receipt_nonce = wp_create_nonce('sppro_payment_confirmation');
            try {
                $api = new \Eticsoft\Sanalpospro\InternalApi();
                $data = [
                    'order_id' => $order_id,
                    'receipt_nonce' => $receipt_nonce
                ];
                $res = ($api->run('CreatePaymentLink', $data))->getResponse();

                if ($res['status'] !== 'success') {
                    throw new Exception(sprintf(
                        '<div>%s: %s</div> <div>%s</div>',
                        esc_html__('Error Code', 'sanalpospro-payment-module'),
                        esc_html($res['status']),
                        esc_html($res['message'])
                    ));
                }
                $order_confirmation_url = add_query_arg(
                    array(
                        'order_id' => $order_id,
                        'key' => $order->get_order_key(),
                        '_wpnonce' => $receipt_nonce
                    ),
                    $order->get_checkout_payment_url(true)
                );

                if ($pay_for_order) {
                    return array(
                        'result' => 'success',
                        'redirect' => $order->get_checkout_payment_url(true)
                    );
                }


                $payment_link = isset($res['data']['payment_link']) ? $res['data']['payment_link'] : '';

                if (empty($payment_link)) {
                    throw new Exception(__('Payment link could not be created. Please try again.', 'sanalpospro-payment-module'));
                }

                ob_start();
                sppro_get_template('checkout/payment-iframe.php', array(
                    'payment_link' => $payment_link
                ));
                $iframe_html = ob_get_clean();

                return array(
                    'result' => 'success',
                    'messages' => 'Payment link created successfully',
                    // Important: do NOT send WooCommerce `redirect` here.
                    // Both Classic and Blocks must open iframe modal first.
                    'iframe_html' => $iframe_html,
                    'redirect_url' => $order_confirmation_url
                ); 
            } catch (Exception $e) {
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('[SanalPosPRO] process_payment error: ' . $e->getMessage());
                }
                $user_message = __('An error occurred during payment processing. Please try again.', 'sanalpospro-payment-module');
                wc_add_notice($user_message, 'error');
                return array(
                    'result' => 'failure',
                    'messages' => $user_message
                );
            }
        }

        /**
         * Receipt page
         */

        public function receipt_page($order_id)
        {
         
          
            // Verify nonce to prevent CSRF attacks
            if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'sppro_payment_confirmation')) {
                wp_die(esc_html__('Security check failed. Please try again.', 'sanalpospro-payment-module'));
            }
         

            // Verify user has permission to view this order
            $order = wc_get_order(absint($order_id));
            if (!$order) {
                wp_die(esc_html__('Invalid order.', 'sanalpospro-payment-module'));
            }
           

            // Check if user has permission to view this order
            if (!current_user_can('manage_woocommerce') && $order->get_customer_id() !== get_current_user_id()) {
                wp_die(esc_html__('You do not have permission to view this order.', 'sanalpospro-payment-module'));
            }
          

            // Sanitize and validate input parameters
            $p_id = isset($_GET['p_id']) ? sanitize_text_field(wp_unslash($_GET['p_id'])) : null;
            $id = $order_id;

            
           
           

            if (!$id) {
                wp_die(esc_html__('Invalid payment ID.', 'sanalpospro-payment-module'));
            }

            // Idempotency guard: prevent duplicate processing of the same callback
            $processed_token = $order->get_meta('_sppro_processed_token');
            if (!empty($p_id) && $processed_token === $p_id) {
                $checkOutUrl = $order->get_checkout_order_received_url();
                wp_safe_redirect($checkOutUrl);
                exit;
            }

          

            try {
                $api = new \Eticsoft\Sanalpospro\InternalApi();

                $data = [
                    'process_token' => $p_id,
                    'order_id' => $id,
                ];

                $apiReq = $api->getInstance()->run('confirmOrder', $data);
                $response = $apiReq->getResponse();

                

                if ($response['status'] !== 'success') {
                    $order->update_status('failed', __('Payment failed', 'sanalpospro-payment-module'));
                    wc_add_notice($response['message'], 'error');
                    wp_safe_redirect(wc_get_checkout_url());
                    exit;
                }

                $order->update_status(EticConfig::get('SANALPOSPRO_ORDER_STATUS'), __('Processing SanalPosPRO payment', 'sanalpospro-payment-module'));
                $order->add_order_note(
                    sprintf(
                        /* translators: %s: Payment gateway name */
                        __('Payment completed successfully via %s.', 'sanalpospro-payment-module'),
                        $response['data']['gateway']  ?? 'sanalpospro'
                    )
                );

                // Save processed token to prevent duplicate processing
                if (!empty($p_id)) {
                    $order->update_meta_data('_sppro_processed_token', $p_id);
                }

                $amount = $response['data']['amount'] ?? 0;
                $original_total = $order->get_total();
                $transaction_amount = $amount;
                $tax_amount = $transaction_amount - $original_total;

                // Only add commission fee if not already added and amount is non-zero
                $has_commission_fee = false;
                foreach ($order->get_fees() as $existing_fee) {
                    if ($existing_fee->get_name() === __('Commission Fee', 'sanalpospro-payment-module')) {
                        $has_commission_fee = true;
                        break;
                    }
                }

                if (!$has_commission_fee && abs($tax_amount) > 0) {
                    $fee = new WC_Order_Item_Fee();
                    $fee->set_name(__('Commission Fee', 'sanalpospro-payment-module'));
                    $fee->set_amount($tax_amount);
                    $fee->set_total($tax_amount);
                    $fee->set_tax_class('');
                    $fee->set_tax_status('none');
                    $order->add_item($fee);
                }

                $order->calculate_totals();
                $order->save();
                WC()->cart->empty_cart();
                $checkOutUrl = $order->get_checkout_order_received_url();
                return wp_safe_redirect($checkOutUrl);
            } catch (Exception $e) {
                $order->update_status('failed', $e->getMessage());
                wc_add_notice($e->getMessage(), 'error');
                wp_safe_redirect(wc_get_checkout_url());
                exit;
            }
        }

        /**
         * Show payment warning in admin
         */
        public function show_payment_warning($order) {
            // Static flag to prevent duplicate warnings
            static $warning_shown = [];
            
            // Create a unique ID for this order
            $order_id = $order->get_id();
            
            // Check if warning already shown for this order
            if (isset($warning_shown[$order_id])) {
                return;
            }
            
            if ($order->get_payment_method() === 'sanalpospro') {
                // Only show warning for orders with completed payment status
                $completed_statuses = array(
                    EticConfig::get('SANALPOSPRO_ORDER_STATUS'),
                    'completed',
                    'processing'
                );
                
                $order_status = $order->get_status();
                
                if (in_array($order_status, $completed_statuses)) {
                    echo '<div class="notice notice-warning sppro-warning" style="padding: 10px; margin: 10px 0;">
                        <h4>' . esc_html__('SanalPosPRO Payment Warning', 'sanalpospro-payment-module') . '</h4>
                        <p>' . esc_html__('Payment was processed through SanalPosPRO', 'sanalpospro-payment-module') . '</p>
                        <p>' . esc_html__('Please check the payment status and verify with your bank/payment institution.', 'sanalpospro-payment-module') . '</p>
                    </div>';
                    
                    // Mark this order as having shown the warning
                    $warning_shown[$order_id] = true;
                }
            }
        }

       /*  public function show_payment_warning($order)
        {
            if ($order->get_payment_method() === 'sanalpospro') {
                echo '<div class="notice notice-warning sppro-warning" style="padding: 10px; margin: 10px 0;">
                    <h4>' . esc_html__('SanalPosPRO Payment Warning', 'sanalpospro-payment-module') . '</h4>
                    <p>' . esc_html__('Payment was processed through SanalPosPRO', 'sanalpospro-payment-module') . '</p>
                    <p>' . esc_html__('Please check the payment status and verify with your bank/payment institution.', 'sanalpospro-payment-module') . '</p>
                </div>';
            }
        } */

        /**
         * Allow iframe in HTML
         */
        public function allow_iframe_in_html($tags, $context)
        {
            if ('admin' === $context) {
                $tags['a'] = array(
                    'href' => true,
                    'target' => true,
                );
            } else {
                $tags['iframe'] = array(
                    'src' => true,
                    'width' => true,
                    'height' => true,
                    'frameborder' => true,
                    'allowfullscreen' => true,
                    'style' => true,
                    'class' => 'sppro-card-image',
                );
            }
            return $tags;
        }

        /**
         * Remove wpautop
         */
        public function remove_wpautop($settings)
        {
            remove_filter('woocommerce_payment_gateway_form_fields_' . $this->id, 'wpautop');
            return $settings;
        }

        /**
         * Enqueue scripts
         */
        public function enqueue_scripts()
        {


            $nonce = wp_create_nonce('sppro_internal_api_request');


            wp_localize_script('jquery', 'sppro_params', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'iapi_xfvv' => $nonce,
                'payment_origin' => SPPRO_PAYMENT_ORIGIN
            ));

            wp_enqueue_style('sppro-payment-style', plugins_url('assets/css/payment.css', __FILE__), array(), SPPRO_VERSION);
        }
    }
}


add_filter('woocommerce_product_tabs', 'sppro_add_installment_tab');

/**
 * Add installment tab to product page
 */
function sppro_add_installment_tab($tabs)
{
    $showInstallmentsTabs = EticConfig::get('SANALPOSPRO_SHOWINSTALLMENTSTABS');
    if ($showInstallmentsTabs === 'yes') {
        $tabs['installment_tab'] = array(
            'title'    => __('Installment Options', 'sanalpospro-payment-module'),
            'priority' => 50,
            'callback' => 'sppro_installment_tab_content'
        );
    }
    return $tabs;
}

/**
 * Display installment tab content
 */
function sppro_installment_tab_content()
{
    global $product;
    if (!$product) return;

    if (EticConfig::get('SANALPOSPRO_SHOWINSTALLMENTSTABS') === 'yes') {
        $theme = EticConfig::get('SANALPOSPRO_PAYMENTPAGETHEME');
        $theme = (!$theme || !in_array($theme, ['classic', 'modern'])) ? 'classic' : $theme;

        $settings = [
            'theme' => $theme,
            'price' => $product->get_price(),
            'installments' => EticConfig::get('SANALPOSPRO_INSTALLMENTS')
        ];


        sppro_get_template('installment_theme/' . $theme . '.php', $settings);
    }
}


/**
 * Add gateway class to WooCommerce
 */
function sppro_add_gateway_class($methods)
{
    $methods[] = 'SPPRO_Payment_Gateway';
    return $methods;
}
add_filter('woocommerce_payment_gateways', 'sppro_add_gateway_class');

/**
 * Add payment iframe script
 */
function sppro_add_payment_iframe_script()
{
    if (!is_checkout()) return;

    $script_path = SPPRO_PLUGIN_DIR . 'assets/js/checkout.js';
    $script_version = file_exists($script_path) ? filemtime($script_path) : SPPRO_VERSION;

    wp_enqueue_script('sppro-checkout-iframe', plugins_url('assets/js/checkout.js', __FILE__), array('jquery', 'wc-checkout'), $script_version, true);
}

/**
 * Get card image HTML
 */

function sppro_get_card_image($card_key, $args = array())
{
    if (!is_string($card_key)) {
        return '';
    }

    $card_file = sanitize_file_name($card_key) . '.png';
    $image_path = SPPRO_PLUGIN_DIR . 'assets/images/cards/' . $card_file;
    $image_url = SPPRO_PLUGIN_URL . 'assets/images/cards/' . $card_file;
    
    // If the specific card image doesn't exist, use default
    if (!file_exists($image_path)) {
        $image_url = SPPRO_PLUGIN_URL . 'assets/images/cards/default.png';
    }
    
    $default_args = array(
        'src' => esc_url($image_url),
        'alt' => esc_attr($card_key),
        'class' => 'sppro-card-image'
    );
    $args = wp_parse_args($args, $default_args);

    $html_attributes = array_map(
        function ($key, $value) {
            return sprintf(
                '%s="%s"',
                esc_attr($key),
                esc_attr($value)
            );
        },
        array_keys($args),
        $args
    );

    return sprintf('<img %s>', implode(' ', $html_attributes));
}
/**
 * Handle AJAX request for internal API
 */
function sppro_internal_api_request()
{
    require_once SPPRO_PLUGIN_DIR . 'vendor/include.php';

    if (!check_ajax_referer('sppro_internal_api_request', 'iapi_xfvv', false)) {
        wp_send_json('INSUFFICIENT PERMISSION');
    }

    try {
        $api = new \Eticsoft\Sanalpospro\InternalApi();
        $response = ($api->run())->getResponse();
        wp_send_json($response);
    } catch (Exception $e) {
        wp_send_json(array(
            'status' => 'error',
            'message' => $e->getMessage()
        ));
    }
}

/**
 * Enqueue plugin styles
 */
function sppro_enqueue_styles()
{
    if (is_checkout()) {
        wp_enqueue_style('sppro-payment-styles', SPPRO_PLUGIN_URL . 'assets/css/sanalpospro-payment.css', array(), SPPRO_VERSION);
    }
}

/**
 * Get template path
 */
function sppro_get_template_path()
{
    return SPPRO_PLUGIN_DIR . 'templates/';
}

/**
 * Get template
 */
function sppro_get_template($template_name, $args = array(), $template_path = '', $default_path = '')
{
    if (!$template_path) {
        $template_path = 'woocommerce/sanalpospro/';
    }

    if (!$default_path) {
        $default_path = sppro_get_template_path();
    }


    $template = locate_template(
        array(
            trailingslashit($template_path) . $template_name,
            $template_name
        )
    );


    if (!$template) {
        $template = $default_path . $template_name;
    }

    if ($args && is_array($args)) {
        extract($args);
    }

    include($template);
}
