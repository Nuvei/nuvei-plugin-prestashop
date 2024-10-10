window.onload = function() {
    if (typeof prestashop !== 'undefined') {
        prestashop.on('handleError',(function (e) {
            console.log('handleError', e.eventType, e.resp.hasError, $('.alert.alert-danger.ajax-error').length)
            
            if (e.eventType == 'addProductToCart' && e.resp.hasError) {
                $('.alert.alert-danger.ajax-error').remove();
                
                var htt = '<div class="alert alert-danger ajax-error" role="alert">' + e.resp.errors[0] + '</div>';
                
//                $(htt).insertAfter($('.product-quantity.clearfix'));
                $(htt).insertAfter($('.product-add-to-cart .clearfix'));
            }
        }));

        prestashop.on('updateCart', (function (e) {
            $('.alert.alert-danger.ajax-error').remove();
        }));
    }
}  