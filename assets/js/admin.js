jQuery(document).ready(function () {
    jQuery(document).on('click', '#pbx-tabs a', function (e) {
        jQuery('#pbx-plugin-configuration .tab-active').removeClass('tab-active');
        jQuery(jQuery(this).attr('href')).addClass('tab-active');
        jQuery('#pbx-tabs a.nav-tab-active').removeClass('nav-tab-active');
        jQuery(this).addClass('nav-tab-active');
        jQuery('#mainform').attr('action', jQuery(this).attr('href'));
        e.preventDefault();
    });
    // Auto-select nav tab
    if (typeof (window.location.hash) == 'string') {
        jQuery('.nav-tab[href="' + window.location.hash + '"]').trigger('click');
        jQuery('#mainform').attr('action', window.location.hash);
    }

    // Ask for confirmation
    jQuery(document).on('change', '#woocommerce_' + pbxGatewayId + '_environment', function () {
        if (confirm(pbxConfigModeMessage)) {
            jQuery('.woocommerce-save-button').trigger('click');
        }
    });

    // Ask for confirmation
    jQuery(document).on('change', '[name="typeOfConfiguration"]', function () {
        window.onbeforeunload = null;
        window.location = pbxUrl + '&config_mode=' + this.value;
    });

    jQuery(document).on('change', '#woocommerce_' + pbxGatewayId + '_delay', function () {
        if (jQuery(this).val() == pbxOrderStateDelay) {
            jQuery('#woocommerce_' + pbxGatewayId + '_capture_order_status').parents('tr').removeClass('hidden');
        } else {
            jQuery('#woocommerce_' + pbxGatewayId + '_capture_order_status').parents('tr').addClass('hidden');
        }
    });

    jQuery(document).on('change', '#woocommerce_' + pbxGatewayId + '_subscription', function () {
        let currentSubscription = jQuery(this).val();
        jQuery('#woocommerce_' + pbxGatewayId + '_delay option[value="' + pbxOrderStateDelay + '"]').prop('disabled', (currentSubscription != pbxPremiumSubscriptionId));
        jQuery(pbxPremiumSubscriptionFields).each(function (i, pbxPremiumField) {
            if (currentSubscription == pbxPremiumSubscriptionId) {
                jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).parents('tr').removeClass('hidden');
            } else {
                jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).parents('tr').addClass('hidden');
            }
        });
    });

    jQuery('#woocommerce_' + pbxGatewayId + '_delay option[value="' + pbxOrderStateDelay + '"]').prop('disabled', (pbxCurrentSubscription != pbxPremiumSubscriptionId));
    jQuery(pbxPremiumSubscriptionFields).each(function (i, pbxPremiumField) {
        if (jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).hasClass('hidden')) {
            jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).parents('tr').addClass('hidden');
            jQuery('#woocommerce_' + pbxGatewayId + '_' + pbxPremiumField).removeClass('hidden');
        }
    });
    jQuery('#woocommerce_' + pbxGatewayId + '_delay').trigger('change');
});
