jQuery(document).ready(function ($) {

    $('.wc_oyst_environnement').change(function () {
        if ( 'prod' === this.value ) {
            $('input.wc_oyst_preprod_key').closest('tr').hide();
            $('input.wc_oyst_custom_key').closest('tr').hide();
            $('input.wc_oyst_custom_url').closest('tr').hide();
            $('input.wc_oyst_prod_key').closest('tr').show();
        }
        if ('preprod' === this.value) {
            $('input.wc_oyst_preprod_key').closest('tr').show();
            $('input.wc_oyst_custom_key').closest('tr').hide();
            $('input.wc_oyst_custom_url').closest('tr').hide();
            $('input.wc_oyst_prod_key').closest('tr').hide();
        }
        if ('custom' === this.value) {
            $('input.wc_oyst_preprod_key').closest('tr').hide();
            $('input.wc_oyst_custom_key').closest('tr').show();
            $('input.wc_oyst_custom_url').closest('tr').show();
            $('input.wc_oyst_prod_key').closest('tr').hide();
        }
    }).change();

    var eventCode = $('#event_code').html();
    if ( 'cancel-received' === eventCode || 'authorisation' === eventCode ) {
        $('.wc-order-bulk-actions').hide();
    }

    var oystOrderId = $('#one-click-order-id').html();

    $('#accept-one-click-order, #denied-one-click-order').on('click', function(){
        if ('accept' === this.value){
            status = 'accepted';
        }
        if ('denied' === this.value){
            status = 'denied';
        }

        jQuery.ajax({
            type: "POST",
            url: ajaxurl,
            data: { action: 'one_click_status_update' , status:status , order:oystOrderId }
        }).done(function( msg ) {
           console.log(oystOrderId);
           console.log(status);
        });
    });


});