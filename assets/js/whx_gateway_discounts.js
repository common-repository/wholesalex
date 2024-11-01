(function ($) {
  'use strict';
  /**
   * All of the code for your public-facing JavaScript source
   * should reside in this file.
   */

  const handlePaymentMethodChange = () => {
    $('.wp-block-woocommerce-checkout-payment-block').addClass(['wc-block-components-checkout-step--disabled', 'wc-block-components-loading-mask']);
    $('.wc-block-checkout__actions .wc-block-components-checkout-place-order-button').prop('disabled', true);
    const { extensionCartUpdate } = wc.blocksCheckout;
    const { select } = window.wp.data;
    const { PAYMENT_STORE_KEY } = window.wc.wcBlocksData;
    const store = select(PAYMENT_STORE_KEY);

    extensionCartUpdate({
      namespace: 'wholesalex-payment-discount',
      data: {
        selected_gateway: store.getActivePaymentMethod(),
      },
    }).then(() => {
      $('.wp-block-woocommerce-checkout-payment-block').removeClass(['wc-block-components-checkout-step--disabled', 'wc-block-components-loading-mask']);
      $('.wc-block-checkout__actions .wc-block-components-checkout-place-order-button').prop('disabled', false);
    });

  }

  handlePaymentMethodChange();

  $(document).on('change', 'input[name="radio-control-wc-payment-method-options"]', handlePaymentMethodChange);


})(jQuery);