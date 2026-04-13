/**
 * SanalPosPRO Payment Gateway checkout script
 * Handles the payment iframe and communication with the payment provider
 */
(function($) {
    const getDetailValue = function(paymentResult, key) {
        if (!paymentResult || !Array.isArray(paymentResult.payment_details)) {
            return '';
        }

        const found = paymentResult.payment_details.find(function(item) {
            return item && item.key === key;
        });

        return found && typeof found.value !== 'undefined' ? found.value : '';
    };

    const ensureMessageListener = function(getRedirectUrl) {
        if (window.__spproClassicMessageListenerAdded) {
            return;
        }

        const allowedOrigin = (typeof sppro_params !== 'undefined' && sppro_params.payment_origin)
            ? sppro_params.payment_origin
            : 'https://pay.paythor.com';

        window.addEventListener('message', function(event) {
            if (event.origin !== allowedOrigin) {
                return;
            }

            if (event.data && event.data.type === 'opensource') {
                if (event.data.tdsForm && event.data.form_selector_id === 'three_d_form') {
                    const temp = document.createElement('div');
                    temp.innerHTML = event.data.tdsForm;
                    const form = temp.querySelector('form');
                    if (form) {
                        document.body.appendChild(form);
                        form.submit();
                    }
                }
            }

            if (event.data && event.data.isSuccess) {
                $('.sppro-close-iframe').hide();

                const redirectUrl = getRedirectUrl();
                if (redirectUrl) {
                    window.location.href = redirectUrl + '&p_id=' + encodeURIComponent(event.data.processID || '');
                }
            } else if (event.data && event.data.error) {
                $('.sppro-iframe-content').append('<div class="sppro-error-message">' + event.data.error + '</div>');
            }
        }, false);

        window.__spproClassicMessageListenerAdded = true;
    };

    $(document).ready(function() {
        if (typeof $ === 'undefined') {
            return;
        }

        let currentRedirectUrl = '';

        const handleCheckoutSuccess = function(response) {
            if (!response || typeof response !== 'object') {
                return true;
            }

            const paymentResult = response.payment_result || null;
            const iframeHtml = response.iframe_html || getDetailValue(paymentResult, 'iframe_html');
            const redirectUrl = response.redirect_url || getDetailValue(paymentResult, 'redirect_url') || '';

            if (iframeHtml) {
                currentRedirectUrl = redirectUrl;

                if (!document.getElementById('payment-iframe-container')) {
                    $('body').append(iframeHtml);
                }

                $('.sppro-loading').show();

                ensureMessageListener(function() {
                    return currentRedirectUrl;
                });

                return false;
            }

            return true;
        };

        // WooCommerce sürümüne göre event body veya form üzerinde tetiklenebiliyor.
        $(document.body).on('checkout_place_order_success', function(event, response) {
            return handleCheckoutSuccess(response);
        });

        $(document).on('checkout_place_order_success', 'form.checkout', function(event, response) {
            return handleCheckoutSuccess(response);
        });

        // Safety net: Event kaçarsa checkout AJAX cevabından modalı aç.
        $(document).ajaxSuccess(function(event, xhr, settings) {
            try {
                const url = (settings && settings.url) ? settings.url : '';
                if (url.indexOf('wc-ajax=checkout') === -1) {
                    return;
                }

                const postData = (settings && settings.data) ? String(settings.data) : '';
                if (postData.indexOf('payment_method=sanalpospro') === -1) {
                    return;
                }

                let response = xhr && xhr.responseJSON ? xhr.responseJSON : null;

                if (!response && xhr && xhr.responseText) {
                    response = JSON.parse(xhr.responseText);
                }

                handleCheckoutSuccess(response);
            } catch (e) {
                // no-op
            }
        });
    });
})(jQuery);
