<?php
/**
 * SanalPosPRO BBVA Payment Gateway Uninstall
 *
 * Uninstalling the SanalPosPRO BBVA plugin deletes options, tables, and user metadata.
 *
 * @package SanalPosPRO
 */
if (!defined('ABSPATH')) exit;
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}


// Delete plugin options (legacy SPPRO_ prefix)
delete_option('SPPRO_PUBLIC_KEY');
delete_option('SPPRO_SECRET_KEY');
delete_option('SPPRO_TOKEN');
delete_option('SPPRO_ORDER_STATUS');
delete_option('SPPRO_CURRENCY_CONVERT');
delete_option('SPPRO_SHOWINSTALLMENTSTABS');
delete_option('SPPRO_PAYMENTPAGETHEME');
delete_option('SPPRO_INSTALLMENTS');
delete_option('SANALPOSPRO_ACCESS_TOKEN');

// Delete plugin options (current SANALPOSPRO_ prefix)
delete_option('SANALPOSPRO_PUBLIC_KEY');
delete_option('SANALPOSPRO_SECRET_KEY');
delete_option('SANALPOSPRO_TOKEN');
delete_option('SANALPOSPRO_ORDER_STATUS');
delete_option('SANALPOSPRO_CURRENCY_CONVERT');
delete_option('SANALPOSPRO_SHOWINSTALLMENTSTABS');
delete_option('SANALPOSPRO_PAYMENTPAGETHEME');
delete_option('SANALPOSPRO_INSTALLMENTS');
delete_option('SANALPOSPRO_VERSION');

// Delete WooCommerce specific options
delete_option('woocommerce_sanalpospro_settings');
delete_option('woocommerce_sppro_settings');

// Delete transients
delete_transient('sanalpospro_api_token');

// Clear scheduled hooks
wp_clear_scheduled_hook('sanalpospro_daily_cleanup');