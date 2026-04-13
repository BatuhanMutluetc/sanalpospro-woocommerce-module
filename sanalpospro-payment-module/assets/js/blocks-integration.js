(function () {
    const wcBlocksRegistry = window.wc && window.wc.wcBlocksRegistry;
    const wcSettings = window.wc && window.wc.wcSettings;
    const wpElement = window.wp && window.wp.element;
    const htmlEntities = window.wp && window.wp.htmlEntities;

    if (!wcBlocksRegistry || !wcSettings || !wpElement) {
        return;
    }

    const settings = wcSettings.getSetting('sanalpospro_data', {});

    const decode = (value) => {
        if (!value) return '';
        if (htmlEntities && typeof htmlEntities.decodeEntities === 'function') {
            return htmlEntities.decodeEntities(value);
        }
        return value;
    };

    const label = decode(settings.title) || 'SanalPosPRO';
    const description = decode(settings.description) || '';

    const getDetailValue = (paymentResult, key) => {
        if (!paymentResult || !Array.isArray(paymentResult.payment_details)) {
            return '';
        }

        const found = paymentResult.payment_details.find((item) => item && item.key === key);
        return found && typeof found.value !== 'undefined' ? found.value : '';
    };

    const resetWooBlocksCheckoutState = () => {
        const wcBlocksData = window.wc && window.wc.wcBlocksData;
        const wpData = window.wp && window.wp.data;

        if (!wcBlocksData || !wpData || typeof wpData.dispatch !== 'function') {
            return false;
        }

        let stateReset = false;

        try {
            const checkoutStore = wcBlocksData.checkoutStore || 'wc/store/checkout';
            const checkoutDispatch = wpData.dispatch(checkoutStore);

            if (checkoutDispatch) {
                if (typeof checkoutDispatch.__internalSetIdle === 'function') {
                    checkoutDispatch.__internalSetIdle();
                    stateReset = true;
                } else if (typeof checkoutDispatch.setCheckoutStatus === 'function') {
                    checkoutDispatch.setCheckoutStatus('idle');
                    stateReset = true;
                } else if (typeof checkoutDispatch.setIdle === 'function') {
                    checkoutDispatch.setIdle();
                    stateReset = true;
                }
            }
        } catch (e) {
            // no-op
        }

        try {
            const paymentStore = wcBlocksData.paymentStore || 'wc/store/payment';
            const paymentDispatch = wpData.dispatch(paymentStore);

            if (paymentDispatch) {
                if (typeof paymentDispatch.__internalSetIdle === 'function') {
                    paymentDispatch.__internalSetIdle();
                    stateReset = true;
                } else if (typeof paymentDispatch.__internalSetStatus === 'function') {
                    paymentDispatch.__internalSetStatus('idle');
                    stateReset = true;
                } else if (typeof paymentDispatch.setStatus === 'function') {
                    paymentDispatch.setStatus('idle');
                    stateReset = true;
                }
            }
        } catch (e) {
            // no-op
        }

        return stateReset;
    };

    const resetBlockCheckoutUiAfterModalClose = () => {
        const buttonSelector = '.wc-block-components-checkout-place-order-button, .wc-block-checkout__actions_row button[type="button"], .wc-block-checkout__actions_row button[type="submit"]';

        const normalizeButton = (button) => {
            if (!button) {
                return;
            }

            button.removeAttribute('disabled');
            button.setAttribute('aria-disabled', 'false');
            button.classList.remove(
                'is-busy',
                'loading',
                'wc-block-components-button--disabled',
                'wc-block-components-checkout-place-order-button--loading'
            );

            if (button.style && button.style.pointerEvents === 'none') {
                button.style.pointerEvents = '';
            }

            const successIcon = button.querySelector('.wc-block-components-checkout-place-order-button__icon');
            if (successIcon) {
                successIcon.remove();
            }
        };

        const checkoutRoot = document.querySelector('.wc-block-checkout');
        if (checkoutRoot) {
            checkoutRoot.removeAttribute('aria-busy');
        }

        // 1) Önce Woo Blocks state'ini resmi store dispatch ile idle'a çekmeyi dene.
        const stateReset = resetWooBlocksCheckoutState();

        // 2) Sonra UI'yı normalize et. React yeniden yazabildiği için retry yap.
        let attempts = 0;
        const maxAttempts = stateReset ? 12 : 20;

        const timer = setInterval(() => {
            attempts += 1;
            const buttons = document.querySelectorAll(buttonSelector);
            buttons.forEach(normalizeButton);

            const stillLocked = Array.from(buttons).some((button) => {
                const pointerBlocked = (button.style && button.style.pointerEvents === 'none');
                const loadingClass = button.classList.contains('wc-block-components-checkout-place-order-button--loading');
                const isDisabled = button.disabled || button.getAttribute('aria-disabled') === 'true';
                return pointerBlocked || loadingClass || isDisabled;
            });

            if (!stillLocked || attempts >= maxAttempts) {
                clearInterval(timer);

                // Eğer internal state resetlenmemişse son çare soft reload.
                if (stillLocked && !stateReset) {
                    window.location.reload();
                }
            }
        }, 120);
    };

    const appendIframeModal = (iframeHtml, redirectUrl) => {
        if (!iframeHtml || document.getElementById('payment-iframe-container')) {
            return;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = iframeHtml;

        while (wrapper.firstChild) {
            document.body.appendChild(wrapper.firstChild);
        }

        const loading = document.querySelector('.sppro-loading');
        if (loading) {
            loading.style.display = 'block';
        }

        const closeBtn = document.querySelector('.sppro-close-iframe');
        if (closeBtn && !closeBtn.dataset.spproBound) {
            closeBtn.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopImmediatePropagation();

                const container = document.getElementById('payment-iframe-container');
                if (container) {
                    container.remove();
                }

                resetBlockCheckoutUiAfterModalClose();
            }, true);

            closeBtn.dataset.spproBound = '1';
        }

        if (!window.__spproBlockMessageListenerAdded) {
            const allowedOrigin = (settings && settings.payment_origin)
                ? settings.payment_origin
                : 'https://pay.paythor.com';

            window.addEventListener('message', function (event) {
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
                    const closeBtn = document.querySelector('.sppro-close-iframe');
                    if (closeBtn) {
                        closeBtn.style.display = 'none';
                    }

                    if (window.__spproLastRedirectUrl) {
                        window.location.href = window.__spproLastRedirectUrl + '&p_id=' + encodeURIComponent(event.data.processID || '');
                    }
                } else if (event.data && event.data.error) {
                    const iframeContent = document.querySelector('.sppro-iframe-content');
                    if (iframeContent) {
                        const errorEl = document.createElement('div');
                        errorEl.className = 'sppro-error-message';
                        errorEl.textContent = event.data.error;
                        iframeContent.appendChild(errorEl);
                    }
                }
            }, false);

            window.__spproBlockMessageListenerAdded = true;
        }

        if (redirectUrl) {
            window.__spproLastRedirectUrl = redirectUrl;
        }
    };

    const installCheckoutResponseWatcher = () => {
        if (window.__spproCheckoutWatcherInstalled || typeof window.fetch !== 'function') {
            return;
        }

        const originalFetch = window.fetch.bind(window);

        window.fetch = async function (...args) {
            const response = await originalFetch(...args);

            try {
                const requestUrl = (args[0] && args[0].url) ? args[0].url : String(args[0] || '');
                if (requestUrl.indexOf('/wc/store/v1/checkout') === -1) {
                    return response;
                }

                const cloned = response.clone();
                const data = await cloned.json();

                const paymentMethod = data && data.payment_method;
                const paymentResult = data && data.payment_result;

                const iframeHtml = getDetailValue(paymentResult, 'iframe_html');
                const redirectUrl = getDetailValue(paymentResult, 'redirect_url') || (paymentResult && paymentResult.redirect_url) || '';

                if ((paymentMethod === 'sanalpospro' || iframeHtml) && iframeHtml) {
                    appendIframeModal(iframeHtml, redirectUrl);
                }
            } catch (e) {
                // no-op
            }

            return response;
        };

        window.__spproCheckoutWatcherInstalled = true;
    };

    installCheckoutResponseWatcher();

    const Content = (props) => {
        const useEffect = wpElement.useEffect;

        useEffect(() => {
            if (!props || !props.eventRegistration || !props.emitResponse) {
                return undefined;
            }

            const cleanups = [];

            if (typeof props.eventRegistration.onPaymentSetup === 'function') {
                const successType = props.emitResponse.responseTypes && props.emitResponse.responseTypes.SUCCESS;

                if (successType) {
                    const unsubSetup = props.eventRegistration.onPaymentSetup(() => ({
                        type: successType,
                        meta: {
                            paymentMethodData: {
                                payment_method: 'sanalpospro',
                            },
                        },
                    }));

                    if (typeof unsubSetup === 'function') {
                        cleanups.push(unsubSetup);
                    }
                }
            }

            if (typeof props.eventRegistration.onPaymentProcessing === 'function' && typeof props.emitResponse.responseSuccess === 'function') {
                const unsubProcessing = props.eventRegistration.onPaymentProcessing(() => {
                    return props.emitResponse.responseSuccess({
                        paymentMethodData: {
                            payment_method: 'sanalpospro',
                        },
                    });
                });

                if (typeof unsubProcessing === 'function') {
                    cleanups.push(unsubProcessing);
                }
            }

            return () => {
                cleanups.forEach((fn) => {
                    if (typeof fn === 'function') {
                        fn();
                    }
                });
            };
        }, []);

        return wpElement.createElement(
            'div',
            { className: 'sppro-blocks-payment-method' },
            description ? wpElement.createElement('p', null, description) : null
        );
    };

    wcBlocksRegistry.registerPaymentMethod({
        name: 'sanalpospro',
        paymentMethodId: 'sanalpospro',
        label: label,
        content: wpElement.createElement(Content, null),
        edit: wpElement.createElement(Content, null),
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: settings.supports || ['products'],
        },
    });
})();
