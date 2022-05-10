    <!-- custom style -->
    <style type="text/css">
        body .sc_hide { display: none !important; }

        /* for the 3DS popup */
        .sfcModal-dialog {
            margin-top: 10%;
        }

        #scForm .cc_load_spinner {
            text-align: center;
            padding-top: 10px;
        }
        
        #nuvei_error {
            border: 1px solid red;
            background: lightpink;
            padding: 10px;
            display: none;
        }
        
        #nuvei_error_msg {
            max-width: 96%;
            display: inline-block;
        }
        
        #nuvei_close_error {
            float: right;
            font-weight: bold;
            cursor: pointer;
            font-size: 16px;
        }
    </style>
    
    <div id="nuvei_error">
        <span id="nuvei_error_msg">Some error here</span>
        <span id="nuvei_close_error" onclick="document.getElementById('nuvei_error').style.display='none'">×</span>
    </div>
    
    <form method="post" id="scForm" action="{$formAction}">
        <input type="hidden" name="lst" id="sc_lst" value="{if !empty($sessionToken)}{$sessionToken}{/if}" />
        <input type="hidden" name="sc_transaction_id" id="sc_transaction_id" value="" />
        <input type="hidden" name="nuveiToken" id="nuveiToken" value="{$nuveiToken}"  />
        <input type="hidden" name="nuveiPaymentMethod" id="nuveiPaymentMethod" value=""  />

        <div class="cc_load_spinner">
            <img class="sc_rotate_img" src="/modules/nuvei_checkout/views/img/loading.png" alt="loading..." /> {l s='Loading...' mod='nuvei'}
        </div>
        
        <div id="nuvei_checkout"></div>
    </form>

    <script type="text/javascript">
        var scAPMsErrorMsg                  = "{if !empty($scAPMsErrorMsg)}{l s=$scAPMsErrorMsg mod='nuvei'}{/if}";
        var nuveiCheckoutSdkParams          = JSON.parse('{$nuveiSdkParams nofilter}');
        nuveiCheckoutSdkParams.onResult     = afterSdkResponse;
        nuveiCheckoutSdkParams.prePayment   = scUpdateCart;
        
        // load the SDK
        var scWebSdkScript		= document.createElement('script');
        scWebSdkScript.type		= 'text/javascript';
        scWebSdkScript.onload	= function() { nuveiLoadCheckout(); };
        scWebSdkScript.src		= '{$nuveiSdkUrl}';
        document.getElementsByTagName("head")[0].appendChild(scWebSdkScript);

        function nuveiLoadCheckout() {
            console.log('showNuveiCheckout()');
            
            {if !isset($nuveiAddStep)}
                nuveiCheckoutSdkParams.payButton = 'noButton';
            {/if}
                
            console.log('nuveiLoadCheckout()', nuveiCheckoutSdkParams);

            checkout(nuveiCheckoutSdkParams);

            var checkoutContainer = document.getElementById("nuvei_checkout_container");
            
            if(checkoutContainer) {
                checkoutContainer.style.display = 'block';
            }
            
            window.scrollTo(0,0);
        }

        /**
         * Function scUpdateCart
         * The first step of the checkout validation
         */
        function scUpdateCart() {
            //console.log('scUpdateCart()', "{$ooAjaxUrl}");
            
            return new Promise((resolve, reject) => {
                var errorMsg = "{l s='Payment error, please try again later!' mod='nuvei'}";

                jQuery.ajax({
                    type: "POST",
                    url: "{$ooAjaxUrl}",
                    data: {
                        securityToken : jQuery('#nuveiToken').val()
                    },
                    dataType: 'json'
                })
                    .fail(function(){
                        reject(errorMsg);
                    })
                    .done(function(resp) {
                        console.log(resp);

                        if(resp.hasOwnProperty('sessionToken') && '' != resp.sessionToken) {
                            jQuery('#lst').val(resp.sessionToken);

                            if(resp.sessionToken == nuveiCheckoutSdkParams.sessionToken) {
                                resolve();
                                return;
                            }

                            // reload the Checkout
                            nuveiCheckoutSdkParams.sessionToken	= resp.sessionToken;
                            nuveiCheckoutSdkParams.amount		= resp.amount;

                            nuveiLoadCheckout()();
                        }

                        reject(errorMsg);
                    });
            });
        }

        // process after we get the response from the webSDK
        function afterSdkResponse(resp) {
            console.log('afterSdkResponse()', resp);

            if(typeof resp.result != 'undefined') {
                if(resp.result === 'APPROVED' && resp.transactionId != 'undefined') {
                    jQuery('#sc_transaction_id').val(resp.transactionId);
                    
                    if(resp.hasOwnProperty('ccCardNumber') && '' != resp.ccCardNumber) {
                        jQuery('#nuveiPaymentMethod').val('Credit Card');
                    }
                    else {
                        jQuery('#nuveiPaymentMethod').val('APM');
                    }
                    
                    jQuery('#scForm').submit();
                    return;
                }

                if(resp.result == 'DECLINED') {
                    //console.log('Nuvei Declined payment:');
                    
                    scFormFalse("{l s='Your Payment was DECLINED. Please try another payment method!' mod='nuvei'}");
                    
                    jQuery( document ).ajaxComplete(function() {
                        console.log( "Triggered ajaxComplete handler." );
                    });
                    return;
                }

                /*
                if(resp.hasOwnProperty('errorDescription') && resp.errorDescription != '') {
                    scFormFalse(
                        "{l s='Error with your Payment. Please try again later!' mod='nuvei'}"
                        //+ resp.errorDescription
                    );
                    return;
                }

                if(resp.hasOwnProperty('reason') && '' != resp.reason) {
                    scFormFalse(
                        "{l s='Error with your Payment. Please try again later!' mod='nuvei'}"
                        //+ resp.reason
                    );
                    return;
                }

                //scFormFalse("{l s='Error with your Payment. Please try again later!' mod='nuvei'}");
                //return;
                */
            }

            /*
            // errors
            if(resp.hasOwnProperty('errorDescription') && resp.errorDescription != '') {
                scFormFalse(
                    "{l s='Error with your Payment. Please try again later!' mod='nuvei'}<br/>"
                    + resp.errorDescription
                );
            }
            else if(resp.hasOwnProperty('reason') && '' != resp.reason) {
                scFormFalse(
                    "{l s='Error with your Payment. Please try again later!' mod='nuvei'}<br/>"
                    + resp.reason
                );
            }
            else {
                scFormFalse("{l s='Error with your Payment. Please try again later!' mod='nuvei'}");
            }

            console.log('upo/card payment recreate');
            */
            //reCreateSCFields();
            //createSCFields();
            
            scFormFalse("{l s='Error with your Payment. Please try again later!' mod='nuvei'}");
        }
        
        function scFormFalse(msg) {
            $('#nuvei_error #nuvei_error_msg').text(msg);
            $('#nuvei_error').show();
            $(document).scrollTop(0);
        }

        function submitNuveiCheckout() {
            checkout.submitPayment();
            return;
        }
        
        document.addEventListener('DOMContentLoaded', function(event) {
            {if $showNuveoOnly}
                // remove other payment providers
                $('input[name="payment-option"]').each(function() {
                    var _self = $(this);

                    if(_self.attr('data-module-name') != '{$nuveiModuleName}') {
                        _self.closest('.payment-option').remove();
                    }
                });
            {/if}
        });


        window.onload = function() {
            console.log('window loaded');

            {if $preselectNuveiPayment eq 1}
                // preselect Nuvei payment provider
                $('input[name=payment-option]').each(function() {
                    var nuveiElem = $(this);

                    if('{$nuveiModuleName}' == nuveiElem.attr('data-module-name')) {
                        nuveiElem.trigger('click')

                        var apmsHolder = '#' + nuveiElem.attr('id') + '-additional-information';

                        if($(apmsHolder).length == 1 && $(apmsHolder).css('display') != 'block') {
                            $(apmsHolder).css('display', 'block');
                        }
                    }
                });
            {/if}
            
            // submit checkout payment
            jQuery('#payment-confirmation button').on('click', function(e) {
                //console.log('#payment-confirmation button click');
                
                if ($('input[name=payment-option]:checked').attr('data-module-name') == '{$nuveiModuleName}') {
                    //console.log('send checkout')
                    e.preventDefault();
                    e.stopPropagation();

                    checkout.submitPayment();
                    return;
                }
            });
            
            jQuery('#scForm').on('submit', function(ev) {
                console.log('#scForm submit');
            }); 

            $('input[name=payment-option]').on('change', function() {
                console.log('payment-option', $('input[name=payment-option]:checked').attr('data-module-name'));
                
                if($('#payment-confirmation button[type="submit"]').length == 0) {
                    return;
                }
                
                if ($('input[name=payment-option]:checked').attr('data-module-name') == '{$nuveiModuleName}') {
                    $('#payment-confirmation button[type="submit"]')
                        .attr('type', 'button')
                        .attr('onclick', 'submitNuveiCheckout()');
                }
                else {
                    $('#payment-confirmation button[type="button"]')
                        .attr('type', 'submit')
                        .attr('onclick', '');
                }
            });
            
            jQuery('.cc_load_spinner').hide();
        };
    </script>