{extends file='page.tpl'}
{block name='page_content'}
    {if $nuveiErrorMsg eq ''}
        {include file="module:nuvei_checkout/views/templates/front/checkout.tpl"}
    {else}
        <div id="sc_pm_error" class="alert alert-warning">
			<span class="sc_error_msg">{$nuveiErrorMsg}</span>
			<span class="close" onclick="$('#sc_pm_error').hide();">&times;</span>
		</div>
    {/if}
    
    <br/>
    <div id="payment-confirmation">
        <div class="ps-shown-by-js">
            <button type="button" class="btn btn-primary center-block" onclick="javascript: history.back();">
                {l s='Go back to Checkout' mod='nuvei'}
            </button>
        </div>
    </div>
    </br>
{/block}