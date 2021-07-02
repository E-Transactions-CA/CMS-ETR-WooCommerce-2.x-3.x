var pbxOrderPollingInterval = null;

function pbxRedirectCustomer(url) {
    clearInterval(pbxIframePollingInterval);
    clearInterval(pbxOrderPollingInterval);
    let iframe = document.getElementById('pbx-seamless-iframe');
    iframe.style.display = 'none';
    if (typeof (url) == 'string') {
        window.location = url;
    } else {
        window.location = iframe.contentWindow.location.toString();
    }
}

var pbxIframePollingInterval = setInterval(function () {
    iframe = document.getElementById('pbx-seamless-iframe');
    try {
        if (iframe.contentWindow.location.toString().startsWith(pbx_fo.homeUrl)) {
            pbxRedirectCustomer(iframe.contentWindow.location.toString());
            return;
        }
    } catch (error) {
    }
}, 100);

jQuery(document).on('pbx-order-poll', function () {
    jQuery.ajax({
        type: 'POST',
        url: pbx_fo.orderPollUrl + '&nonce=' + jQuery('#pbx-nonce').val(),
        data: {
            order_id: jQuery('#pbx-id-order').val(),
        },
        dataType: 'json',
        success: function (response) {
            if (typeof (response.data.redirect_url) == 'string') {
                pbxRedirectCustomer(response.data.redirect_url);
            }
        }
    });
});

jQuery('iframe#pbx-seamless-iframe').on('load', function () {
    try {
        if (this.contentWindow.location.toString().startsWith(pbx_fo.homeUrl)) {
            pbxRedirectCustomer(this.contentWindow.location.toString());
            return;
        }
    } catch (error) {
    }
    if (pbxOrderPollingInterval === null) {
        pbxOrderPollingInterval = setInterval(function () {
            jQuery(document).trigger('pbx-order-poll');
        }, 3000);
    }
});
