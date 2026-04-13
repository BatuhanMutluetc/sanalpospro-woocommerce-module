<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

/**
 * WooCommerce Blocks integration for SanalPosPRO gateway
 */
final class SPPRO_Blocks_Support extends AbstractPaymentMethodType
{
    /**
     * Gateway ID
     *
     * @var string
     */
    protected $name = 'sanalpospro';

    /**
     * Gateway instance
     *
     * @var WC_Payment_Gateway|null
     */
    private $gateway = null;

    /**
     * Initialize integration
     */
    public function initialize()
    {
        if (!function_exists('WC') || !WC() || !WC()->payment_gateways()) {
            return;
        }

        $payment_gateways = WC()->payment_gateways()->payment_gateways();
        $this->gateway = $payment_gateways[$this->name] ?? null;
    }

    /**
     * Is payment method active?
     *
     * @return bool
     */
    public function is_active()
    {
        return $this->gateway && $this->gateway->is_available();
    }

    /**
     * Register frontend script for Blocks checkout
     *
     * @return string[]
     */
    public function get_payment_method_script_handles()
    {
        // Never load Blocks integration assets when checkout is Classic.
        if (!$this->is_block_checkout_enabled()) {
            return array();
        }

        $script_handle = 'sppro-blocks-integration';

        $script_path = SPPRO_PLUGIN_DIR . 'assets/js/blocks-integration.js';
        $script_version = file_exists($script_path) ? filemtime($script_path) : SPPRO_VERSION;

        wp_register_script(
            $script_handle,
            SPPRO_PLUGIN_URL . 'assets/js/blocks-integration.js',
            array('wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'),
            $script_version,
            true
        );

        return array($script_handle);
    }

    /**
     * Detect if store checkout page uses WooCommerce Checkout Block.
     *
     * @return bool
     */
    private function is_block_checkout_enabled()
    {
        if (!function_exists('wc_get_page_id')) {
            return false;
        }

        $checkout_page_id = wc_get_page_id('checkout');
        if (!$checkout_page_id || $checkout_page_id < 1) {
            return false;
        }

        $checkout_page = get_post($checkout_page_id);
        if (!$checkout_page) {
            return false;
        }

        return has_block('woocommerce/checkout', $checkout_page->post_content);
    }

    /**
     * Data passed to frontend script
     *
     * @return array
     */
    public function get_payment_method_data()
    {
        if (!$this->gateway) {
            return array();
        }

        return array(
            'title' => $this->gateway->get_title(),
            'description' => $this->gateway->get_description(),
            'supports' => array_values($this->gateway->supports),
            'payment_origin' => defined('SPPRO_PAYMENT_ORIGIN') ? SPPRO_PAYMENT_ORIGIN : 'https://pay.paythor.com',
        );
    }
}
