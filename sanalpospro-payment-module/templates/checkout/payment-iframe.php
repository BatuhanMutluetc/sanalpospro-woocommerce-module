<?php
// Exit if accessed directly
if (!defined('ABSPATH')) exit;

/**
 * SanalPosPRO Payment iFrame Template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/sanalpospro/checkout/payment-iframe.php.
 *
 * @package SanalPosPRO
 * @version 10.0.4
 */
?>

<div id="payment-iframe-container" class="sppro-iframe-container">
    <div class="sppro-iframe-wrapper">
        <div class="sppro-iframe-header">
            <div class="sppro-header-spacer"></div>
            <button type="button" class="sppro-close-iframe">
                <?php esc_html_e('×', 'sanalpospro-payment-module'); ?>
            </button>
        </div>
        
        <div class="sppro-iframe-content">
            <div class="sppro-loading">
                <div class="sppro-spinner"></div>
                <p><?php esc_html_e('Loading payment page...', 'sanalpospro-payment-module'); ?></p>
            </div>
            <iframe 
                src="<?php echo esc_url($payment_link); ?>" 
                sandbox="allow-scripts allow-top-navigation allow-same-origin allow-forms"
                class="sppro-payment-iframe" 
                frameborder="0" 
                allow="payment"
            ></iframe>
        </div>
    </div>
</div>
<script>
(function(){
    var closeBtn = document.querySelector('#payment-iframe-container .sppro-close-iframe');
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            var container = document.getElementById('payment-iframe-container');
            if (container) { container.remove(); }
        });
    }
    var iframe = document.querySelector('#payment-iframe-container .sppro-payment-iframe');
    if (iframe) {
        iframe.addEventListener('load', function() {
            var loading = document.querySelector('.sppro-loading');
            if (loading) { loading.style.display = 'none'; }
        });
    }
})();
</script> 