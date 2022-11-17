<div class="card mt-2" style="margin-top: -20px !important;">
    <div class="card-header">
        <h3 class="card-header-title">{l s="Nuvei notes" mod='nuvei'} ({$nuvei_messages|count})</h3>
    </div>

    <div class="card-body">
        <div class="form-group row type-hidden ">
            <label for="order_payment__token" class="form-control-label "></label>
            <div class="col-sm"></div>
        </div>
        
        <table class="table">
            <thead>
                <tr>
                    <th class="table-head-date">{l s="Date" mod='nuvei'}</th>
                    <th class="table-head-date">{l s="Message" mod='nuvei'}</th>
                </tr>
            </thead>
            
            {if $nuvei_messages|count gt 0}
                <tbody>
                    {foreach $nuvei_messages as $msg}
                        <tr>
                            <td>{$msg.date_add}</td>
                            <td>{$msg.message}</td>
                        </tr>
                    {/foreach}
                </tbody>
            {/if}
        </table>
    </div>
</div>
        
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
            
            case 'cancelSubscription':
                question = '{l s='Are you sure you want to Cancel the Order Subscription?' mod='nuvei'}';
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
    
	{if $nuvei_hide_refund_btn eq 1}
        $('.partial-refund-display, .partial-refund').hide();
    {/if}
</script>