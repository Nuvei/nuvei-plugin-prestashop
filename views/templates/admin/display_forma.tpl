<br/>
<div class="alert alert-info">
    <img src="/modules/nuvei_checkout/logo.png" style="float:left; margin-right:15px;" height="60" />
    <p><a target="_blank" href="http://www.nuvei.com/"><strong>Nuvei</strong></a></p>
    <p>{l s='Nuvei provides secure and reliable turnkey solutions for small to medium sized e-commerce businesses. Powered by Nuvei Technologies and backed by more than a decade of experience in the e-commerce industry, with expert international staff, Nuvei has the skills, tools, technology, and expertise to accept software vendors and digital service providers and help them succeed online with confidence in a secure and reliable environment. It also helps them promote their software and enjoy increased sales volumes.' mod='nuvei'}</p>
</div>

<form class="defaultForm form-horizontal" action="{$smarty.server['REQUEST_URI']}" method="post" enctype="multipart/form-data">
    <div class="panel">
        <div class="panel-heading">
            <i class="icon-cogs"></i> {l s='Settings' mod='sc'}
        </div>
								
        <div class="form-wrapper panel with-nav-tabs panel-defaul">
            <div class="panel-heading" style="padding-bottom: 0px;">
                <ul class="nav nav-tabs">
                    <li class="active">
                        <a href="#nuvei_general" data-toggle="tab">{l s='General' mod='nuvei'}</a>
                    </li>

                    <li><a href="#nuvei_advanced" data-toggle="tab">{l s='Advanced' mod='nuvei'}</a></li>
                    <li><a href="#nuvei_tools" data-toggle="tab">{l s='Help Tools' mod='nuvei'}</a></li>
                </ul>
            </div>
            
            <div class="panel-body">
                <div class="tab-content">
                    <!-- General group -->
                    <div class="tab-pane fade in active" id="nuvei_general">
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Default Title' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <input type="text" name="SC_FRONTEND_NAME" value="{if Configuration::get('SC_FRONTEND_NAME')}{Configuration::get('SC_FRONTEND_NAME')}{else}{l s='Secure Payment with Nuvei' mod='nuvei'}{/if}" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-3 required"> {l s='Test Mode' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="SC_TEST_MODE" required="">
                                    <option value="">{l s='Please, select an option...' mod='nuvei'}</option>
                                    <option value="yes" {if Configuration::get('SC_TEST_MODE') eq 'yes'}selected{/if}>{l s='Yes' mod='nuvei'}</option>
                                    <option value="no" {if Configuration::get('SC_TEST_MODE') eq 'no'}selected{/if}>{l s='No' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-3 required"> {l s='Merchant ID' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <input type="text" name="SC_MERCHANT_ID" value="{Configuration::get('SC_MERCHANT_ID')}" required="" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-3 required"> {l s='Merchant Site ID' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <input type="text" name="SC_MERCHANT_SITE_ID" value="{Configuration::get('SC_MERCHANT_SITE_ID')}" required="" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-3 required"> {l s='Merchant Secret Key' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <input type="password" name="SC_SECRET_KEY" value="{Configuration::get('SC_SECRET_KEY')}" required="" />
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-3 required"> {l s='Hash Type' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="SC_HASH_TYPE" required="">
                                    <option value="">{l s='Please, select an option...' mod='nuvei'}</option>
                                    <option value="sha256" {if Configuration::get('SC_HASH_TYPE') eq 'sha256'}selected{/if}>sha256</option>
                                    <option value="md5" {if Configuration::get('SC_HASH_TYPE') eq 'md5'}selected{/if}>md5</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="control-label col-lg-3 required"> {l s='Payment Action' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="SC_PAYMENT_ACTION" required="">
                                    <option value="">{l s='Please, select an option...' mod='nuvei'}</option>
                                    <option value="Sale" {if Configuration::get('SC_PAYMENT_ACTION') eq 'Sale'}selected{/if}>{l s='Authorize and Capture' mod='nuvei'}</option>
                                    <option value="Auth" {if Configuration::get('SC_PAYMENT_ACTION') eq 'Auth'}selected{/if}>{l s='Authorize' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Allow Auto Void' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_ALLOW_AUTO_VOID">
                                    <option value="yes" {if Configuration::get('NUVEI_ALLOW_AUTO_VOID') eq 'yes'}selected{/if}>{l s='Yes' mod='nuvei'}</option>
                                    <option value="no" {if Configuration::get('NUVEI_ALLOW_AUTO_VOID') neq 'yes'}selected{/if}>{l s='No' mod='nuvei'}</option>
                                </select>
                                
                                <span class="help-block">{l s='Allow plugin to initiate auto Void request in case there is Payment (transaction), but there is no Order for this transaction in the Store. This logic is based on incoming DMNs. Event the auto Void is disabled, a message will be saved in the Admin logs.' mod='nuvei'}</span>
                            </div>
                        </div>
                                
                        <div class="form-group">
                            <label class="control-label col-lg-3" for="SC_CREATE_LOGS">{l s='Save Logs' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="SC_CREATE_LOGS">
                                    <option value="yes" {if Configuration::get('SC_CREATE_LOGS') eq 'yes'}selected{/if}>{l s='Yes' mod='nuvei'}</option>
                                    <option value="no" {if Configuration::get('SC_CREATE_LOGS') eq 'no'}selected{/if}>{l s='No' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Advanced group -->
                    <div class="tab-pane fade" id="nuvei_advanced">
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Preselect Nuvei Payment' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_PRESELECT_PAYMENT">
                                    <option value="0" {if Configuration::get('NUVEI_PRESELECT_PAYMENT') eq 0}selected{/if}>{l s='No' mod='nuvei'}</option>
                                    <option value="1" {if Configuration::get('NUVEI_PRESELECT_PAYMENT') eq 1}selected{/if}>{l s='Yes' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                                
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='Enable UPOs' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="SC_USE_UPOS">
                                    <option value="">{l s='Please, select an option...' mod='nuvei'}</option>
                                    <option value="1" {if Configuration::get('SC_USE_UPOS') eq 1}selected{/if}>{l s='Use UPOs' mod='nuvei'}</option>
                                    <option value="0" {if Configuration::get('SC_USE_UPOS') eq 0}selected{/if}>{l s='Do NOT use UPOs' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                                
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='SDK Theme' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_SDK_THEME">
                                    <option value="accordion"> {if Configuration::get('SC_USE_UPOS') eq 'accordion'}selected{/if}{l s='Accordion' mod='nuvei'}</option>
                                    <option value="tiles" {if Configuration::get('NUVEI_SDK_THEME') eq 'tiles'}selected{/if}>{l s='Tiles' mod='nuvei'}</option>
                                    <option value="horizontal" {if Configuration::get('SC_USE_UPOS') eq 'horizontal'}selected{/if}>{l s='Horizontal' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Use Currency Conversion' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_USE_DCC">
                                    <option value="enable" {if Configuration::get('NUVEI_USE_DCC') eq 'enable'}selected{/if}>{l s='Enabled' mod='nuvei'}</option>
                                    <option value="force" {if Configuration::get('NUVEI_USE_DCC') eq 'force'}selected{/if}>{l s='Enabled and expanded' mod='nuvei'}</option>
                                    <option value="false" {if Configuration::get('NUVEI_USE_DCC') eq 'false'}selected{/if}>{l s='Disabled' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                            
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Block Cards' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <input name="NUVEI_BLOCK_CARDS" type="text" value="{Configuration::get('NUVEI_BLOCK_CARDS')}" />
                                <span class="help-block">{l s='For examples' mod='nuvei'} <a href="https://docs.nuvei.com/documentation/accept-payment/simply-connect/payment-customization/#Card_Processing" target="_blank">{l s='check the Documentation.' mod='nuvei'}</a></span>
                            </div>
                        </div>
                            
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Block Payment methods' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_BLOCK_PMS[]" multiple="">
                                    <option value="">{l s='None' mod='nuvei'}</option>
                                    {foreach from=$paymentMethods key=$method item=$pmData}
                                        <option value="{$method}" {if $pmData['selected'] eq '1'}selected{/if}>{$pmData['name']}</option>
                                    {/foreach}
                                </select>
                                
                                <span class="help-block">{l s='For examples' mod='nuvei'} <a href="https://docs.nuvei.com/documentation/accept-payment/simply-connect/payment-customization/#apm-whitelisting-blacklisting" target="_blank">{l s='check the Documentation.' mod='nuvei'}</a></span>
                            </div>
                        </div>
                            
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='Choose the Text on the Pay Button' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_PAY_BTN_TEXT">
                                    <option value="amountButton" {if Configuration::get('NUVEI_PAY_BTN_TEXT') eq 'amountButton'}selected{/if}>{l s='Shows the amount' mod='nuvei'}</option>
                                    <option value="textButton" {if Configuration::get('SC_USE_UPOS') eq 'textButton'}selected{/if}>{l s='Shows the payment method' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                                
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='Auto-expand PMs' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_AUTO_EXPAND_PMS">
                                    <option value="1" {if Configuration::get('NUVEI_AUTO_EXPAND_PMS') eq 1}selected{/if}>{l s='Yes' mod='nuvei'}</option>
                                    <option value="0" {if Configuration::get('NUVEI_AUTO_EXPAND_PMS') eq 0}selected{/if}>{l s='No' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='APMs Window Type' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_APM_WINDOW_TYPE">
                                    <option value="" {if Configuration::get('NUVEI_APM_WINDOW_TYPE') eq ''}selected{/if}>{l s='Popup' mod='nuvei'}</option>
                                    <option value="newTab" {if Configuration::get('NUVEI_APM_WINDOW_TYPE') eq 'newTab'}selected{/if}>{l s='New Tab' mod='nuvei'}</option>
                                    <option value="redirect" {if Configuration::get('NUVEI_APM_WINDOW_TYPE') eq 'redirect'}selected{/if}>{l s='Redirect' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                                
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='Mask User Details in the Log' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_MASK_LOG">
                                    <option value="yes" {if !Configuration::get('NUVEI_MASK_LOG') || Configuration::get('NUVEI_MASK_LOG') eq 'yes'}selected{/if}>{l s='Yes' mod='nuvei'}</option>
                                    <option value="no" {if Configuration::get('NUVEI_MASK_LOG') eq 'no'}selected{/if}>{l s='No' mod='nuvei'}</option>
                                </select>
                            </div>
                        </div>
                                
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='Checkout Log level' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_SDK_LOG_LEVEL">
                                    <option value="0" {if Configuration::get('NUVEI_SDK_LOG_LEVEL') eq 0}selected{/if}>0</option>
                                    <option value="1" {if Configuration::get('NUVEI_SDK_LOG_LEVEL') eq 1}selected{/if}>1</option>
                                    <option value="2" {if Configuration::get('NUVEI_SDK_LOG_LEVEL') eq 2}selected{/if}>2</option>
                                    <option value="3" {if Configuration::get('NUVEI_SDK_LOG_LEVEL') eq 3}selected{/if}>3</option>
                                    <option value="4" {if Configuration::get('NUVEI_SDK_LOG_LEVEL') eq 4}selected{/if}>4</option>
                                    <option value="5" {if Configuration::get('NUVEI_SDK_LOG_LEVEL') eq 5}selected{/if}>5</option>
                                    <option value="6" {if Configuration::get('NUVEI_SDK_LOG_LEVEL') eq 6}selected{/if}>6</option>
                                </select>
                                
                                <span class="help-block">{l s='0 for "No logging".' mod='nuvei'}</span>
                            </div>
                        </div>
                                
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='SDK Styling' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <textarea name="NUVEI_SDK_STYLE" rows="5" class="form-control textarea-autosize"placeholder='{
	"base": {
		"iconColor": "#c4f0ff"
	}
 }'>{Configuration::get('NUVEI_SDK_STYLE')}</textarea>
                                
                                <span class="help-block">{l s='This filed is for styling Simply Connect. The valid format is JSON. For examples' mod='nuvei'} <a href="https://docs.nuvei.com/documentation/accept-payment/web-sdk/nuvei-fields/nuvei-fields-styling/#example-javascript" target="_blank">{l s='check the Documentation.' mod='nuvei'}</a></span>
                            </div>
                        </div>
                            
                        <div class="form-group">
                            <label class="control-label col-lg-3"> {l s='SDK Translations' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <textarea name="NUVEI_SDK_TRANSL" rows="5" class="form-control textarea-autosize"placeholder='{
    "doNotHonor":"you dont have enough money",
    "DECLINE":"declined"
}'>{Configuration::get('NUVEI_SDK_TRANSL')}</textarea>
                                
                                <span class="help-block">{l s='This filed is the only way to translate Checkout SDK strings. Put the translations for all desired languages as shown in the placeholder. For examples' mod='nuvei'} <a href="https://docs.nuvei.com/documentation/accept-payment/simply-connect/ui-customization/#text-and-translation" target="_blank">{l s='check the Documentation.' mod='nuvei'}</a></span>
                            </div>
                        </div>
                            
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Use Additional Checkout Step' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <select name="NUVEI_ADD_CHECKOUT_STEP">
                                    <option value="0" {if Configuration::get('NUVEI_ADD_CHECKOUT_STEP') eq 0}selected{/if}>{l s='No' mod='nuvei'}</option>
                                    <option value="1" {if Configuration::get('NUVEI_ADD_CHECKOUT_STEP') eq 1}selected{/if}>{l s='Yes' mod='nuvei'}</option>
                                </select>

                                <span class="help-block">{l s='Please enable /Yes/ only when using a Once Step Checkout module!' mod='nuvei'}</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Help tools -->
                    <div class="tab-pane fade" id="nuvei_tools">
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Download Payment Plans' mod='nuvei'}</label>
                            <div class="col-lg-9">
                                <button type="button" onclick="downloadNuveiPlans()" class="btn btn-default" id="nuvei_dl_plans_btn">
                                    <i class="process-icon-download"></i>
                                </button>

                                <span class="help-block" id="nuvei_last_dl_data">{if $paymentPlanJsonDlDate != ''}
                                    {l s='Last download' mod='nuvei'} {$paymentPlanJsonDlDate}
                                {/if}</span>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="control-label col-lg-3">{l s='Notification (DMN) URL'}</label>
                            <div class="col-lg-9">
                                <span class="help-block">{$defaultDmnUrl}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div><!-- /.form-wrapper -->
        
        <div class="panel-footer">
            <button type="submit" value="1" name="submitUpdate" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save' mod='nuvei'}
            </button>
        </div>
    </div>
</form>
			
<script>
	function downloadNuveiPlans() {
		$('#nuvei_dl_plans_btn').attr('disabled', true);
		
		var ajax	= new XMLHttpRequest();
		var params	= 'scAction=downloadPaymentPlans';
		
		ajax.open("POST", "{$ajaxUrl}", true);
		ajax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

		ajax.onreadystatechange = function(){
			if (ajax.readyState == 4 && ajax.status == 200) {
				var resp = JSON.parse(this.responseText);

				if(resp.status == 1) {
					$('#nuvei_last_dl_data').html(
						'<i class="process-icon-ok" style="color: green; display: inline;"></i>&nbsp;&nbsp;' 
						+ "{l s='Last download' mod='nuvei'}: "
						+ resp.date
					);
			
					$('#nuvei_dl_plans_btn').attr('disabled', false);
					return;
				}
				
				$('#nuvei_last_dl_data').html(
					'<i class="process-icon-cancel" style="color: red; display: inline;"></i>&nbsp;&nbsp;'
					+ '{l s='Error during AJAX call.' mod='nuvei'}'
				);
		
				$('#nuvei_dl_plans_btn').attr('disabled', false);
				return;
			}
		}

		//If an error occur during the ajax call.
		if (ajax.readyState == 4 && ajax.status == 404) {
			$('#nuvei_last_dl_data').html(
				'<i class="process-icon-cancel" style="color: red; display: inline;"></i>&nbsp;&nbsp;'
				+ '{l s='Error during AJAX call.' mod='nuvei'}'
			);
	
			$('#nuvei_dl_plans_btn').attr('disabled', false);
		}

		ajax.send(params);
	}
    
    function disableNuveiPm(_id) {
        $(_id).hide();
        
        
    }
</script>
