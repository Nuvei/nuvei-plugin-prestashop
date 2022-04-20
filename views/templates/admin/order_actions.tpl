<style type="text/css">
    .fast-right-spinner {
        -webkit-animation: glyphicon-spin-r 1s infinite linear;
        animation: glyphicon-spin-r 1s infinite linear;
    }

    @-webkit-keyframes glyphicon-spin-r {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }

        100% {
            -webkit-transform: rotate(359deg);
            transform: rotate(359deg);
        }
    }

    @keyframes glyphicon-spin-r {
        0% {
            -webkit-transform: rotate(0deg);
            transform: rotate(0deg);
        }

        100% {
            -webkit-transform: rotate(359deg);
            transform: rotate(359deg);
        }
    }
</style>

{if !empty($scDataError)}
    <span class="span label label-danger">
        <i class="icon-warning-sign"></i>&nbsp;{$scDataError}
    </span>
{/if}

{if !empty($scData.error_msg)}
    <span class="span label label-danger">
        <i class="icon-warning-sign"></i>&nbsp;{$scData.error_msg}
    </span>&nbsp;
{/if}

{if empty($scData.resp_transaction_type)}
    <span class="span label label-danger">
        <i class="icon-warning-sign"></i>&nbsp;{l s='Missing Order Transaction Type.' mod='nuvei'}
    </span>&nbsp;
{/if}

{if empty($scData.related_transaction_id)}
    <span class="span label label-danger">
        <i class="icon-warning-sign"></i>&nbsp;{l s='Missing Order Transaction ID.' mod='nuvei'}
    </span>&nbsp;
{/if}

{if !empty($scData.resp_transaction_type) and $scData.resp_transaction_type eq "Auth" and !empty($scData.order_state) and $scData.order_state eq $state_sc_await_paiment}
    <button type="button" id="sc_settle_btn" class="btn btn-default" onclick="scOrderAction('settle', {$orderId})" title="{l s='You will be redirected to Orders list.' mod='nuvei'}">
        <i class="icon-thumbs-up"></i>
        <i class="icon-repeat fast-right-spinner hidden"></i>
        {l s='Settle' mod='nuvei'}
    </button>
{/if}

{if $enableVoid}
	<button type="button" id="sc_void_btn" class="btn btn-default" onclick="scOrderAction('void', {$orderId})">
		<i class="icon-retweet"></i>
		<i class="icon-repeat fast-right-spinner hidden"></i>
		{l s='Void' mod='nuvei'}
	</button>
{/if}
    
<script type="text/javascript">
    function scOrderAction(action, orderId) {
        var question = '';
        
        switch(action) {
            case 'settle':
                question = '{l s='Are you sure you want to Settle this order?' mod='nuvei'}';
                break;
                
            case 'void':
                question = '{l s='Are you sure you want to Cancel this order?' mod='nuvei'}';
                break;
            
            default:
                return;
        }
        
        if(confirm(question)) {
            disableScBtns(action);
            
            var ajax = new XMLHttpRequest();
            var params = 'scAction=' + action + '&scOrder=' + orderId;
            
			ajax.open("POST", "{$ajaxUrl}", true);
            ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");
            
            ajax.onreadystatechange = function(){
                if (ajax.readyState == 4 && ajax.status == 200) {
                    var resp = JSON.parse(this.responseText);
                    
                    if(resp.status == 1) {
                        window.location.href = "{$ordersListURL}";
                    }
                    else {
                        try {
                            if(typeof resp.msg != 'undefined') {
                                alert(resp.msg);
                                enableScBtns(action);
                            }
                            else if(typeof resp.data.gwErrorReason != 'undefined') {
                                alert(resp.data.gwErrorReason);
                                enableScBtns(action);
                            }
                            else if(typeof resp.data.reason != 'undefined') {
                                alert(resp.data.reason);
                                enableScBtns(action);
                            }
                        }
                        catch (exception) {
                            alert("Error during AJAX call");
                            enableScBtns(action);
                        }
                    }
                }
            }
            
            //If an error occur during the ajax call.
            if (ajax.readyState == 4 && ajax.status == 404) {
                alert('{l s='Error during AJAX call.' mod='nuvei'}');
                enableScBtns(action);
            }
            
            ajax.send(params);
        }
    }
    
    function disableScBtns(action) {
        $('#sc_'+ action +'_btn .icon-repeat').removeClass('hidden');
        $('#sc_'+ action +'_btn').addClass('disabled');
    }
    
    function enableScBtns(action) {
        $('#sc_'+ action +'_btn .icon-repeat').addClass('hidden');
        $('#sc_'+ action +'_btn').removeClass('disabled');
    }
    
    // remove PS refund buttons
    $('#desc-order-standard_refund').hide();
    
	{if
		!in_array($scData.order_state, array($state_completed, $state_refunded))
        or (isset($scData.payment_method) and !in_array($scData.payment_method, array('cc_card', 'dc_card', 'apmgw_expresscheckout')))
    }
    
        $('#desc-order-partial_refund').hide();
    {/if}
</script>