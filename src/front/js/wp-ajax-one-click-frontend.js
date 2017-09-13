jQuery(function ($) {

    /**
     * Process request to get oyst url
     */

    window.__OYST__ = window.__OYST__ || {}
    window.__OYST__.getOneClickURL = function (cb) {
        $(function () {
            var quantity = $('input[name=quantity]').val() ? $('input[name=quantity]').val() : 1;
            if ($('form.cart').hasClass('variations_form')) {
                referenceId = $('input[name=product_id]').val();
                variationId = $('input[name=variation_id]').val();
            } else {
                referenceId = $('.single_add_to_cart_button ').val();
                variationId = '';

            }
            var ajaxurl = wp_ajax_one_click_frontend.ajaxurl;
            var form = new FormData();
            form.append("action", "one_click_authorize");
            form.append("wp_ajax_one_click_frontend_nonce", wp_ajax_one_click_frontend.wp_ajax_one_click_frontend_nonce);
            form.append("product_reference", referenceId);
            form.append("variation_reference", variationId);
            form.append("quantity", quantity);
            var settings = {
                "async": true,
                "crossDomain": true,
                "url": ajaxurl,
                "method": "POST",
                "headers": {
                    "cache-control": "no-cache"
                },
                "processData": false,
                "contentType": false,
                "mimeType": "multipart/form-data",
                "data": form
            };
            $.ajax(settings).done(function (data) {
                data = JSON.parse(data);
                cb(null, data.url);
                console.log(data.url);
            });

        })
    };
});
