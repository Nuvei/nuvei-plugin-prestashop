{extends file='page.tpl'}
{block name='page_content'}
	<div class="alert alert-danger" style="font-size:20px;">
		<p>{l s='Your Payment failed. Your Order Status is FAILED, DECLINED or CANCELLED. Please try again.' mod='nuvei'}</p>
		
		{if count($cart['products']) > 0}
			<p>{l s='You can try again, by clicking on your Cart.' mod='nuvei'}</p>
		{else}
			<p><strong>{l s='If you are a registred user' mod='nuvei'}</strong> {l s='and want to post the order again - click to your user name at the top of the page, select ORDER HISTORY AND DETAILS, find your Order and click on Reorder link.' mod='nuvei'}</p>
		{/if}
	</div>
{/block}