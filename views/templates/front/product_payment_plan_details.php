<div id="nuvei_add_error" style="display: none; border: 1px solid red; background-color: rgba(255,0,0,0.1); color: red; padding: 5px; margin-bottom: 10px;">
    <span><?= $data['error_msg']; ?></span>
</div>

<div id="nuvei_payment_plan_details" style="">
    <h5><?= $data['tab_title']; ?></h5>
    
    <table border="1" cellpadding="5">
        <tr>
            <td><?= $data['Plan_duration']; ?></td>
            <td id="nuvei_plan_duration"></td>
        </tr>
        
        <tr>
            <td><?= $data['Charge_every']; ?></td>
            <td id="nuvei_charge_every"></td>
        </tr>
        
        <tr>
            <td><?= $data['Recurring_amount']; ?></td>
            <td id="nuvei_rec_amount"></td>
        </tr>
        
        <tr>
            <td><?= $data['Trial_period']; ?></td>
            <td id="nuvei_trial_period"></td>
        </tr>
    </table>
</div>

<script>
    var nuveiCurrProductPlans   = <?= json_encode($data['product_plans']); ?>;
    var nuveiAttrGroupId        = <?= $data['gr_ids']; ?>;
    
    function nuveiShowPlanDetails(_val) {
        console.log('nuveiShowPlanDetails()', _val);
        
        // the option has no plan
        if(!nuveiCurrProductPlans.hasOwnProperty(_val)) {
            console.log('nuveiCurrProductPlans han no property', _val);
            
            document.getElementById('nuvei_payment_plan_details').style.display = 'none';
            document.getElementsByClassName('product-add-to-cart')[0].style.display = 'block';
            document.getElementById('nuvei_add_error').style.display = 'none';
            
            return true;
        }
        
        // fill table and show it
        for(var i in nuveiCurrProductPlans[_val].plan_details.endAfter) {
            document.getElementById('nuvei_plan_duration').innerHTML 
                = nuveiGetPeriod(nuveiCurrProductPlans[_val].plan_details.endAfter[i], i);
        }
        
        for(var i in nuveiCurrProductPlans[_val].plan_details.recurringPeriod) {
            document.getElementById('nuvei_charge_every').innerHTML 
                = nuveiGetPeriod(nuveiCurrProductPlans[_val].plan_details.recurringPeriod[i], i);
        }
        
        for(var i in nuveiCurrProductPlans[_val].plan_details.startAfter) {
            document.getElementById('nuvei_trial_period').innerHTML 
                = nuveiGetPeriod(nuveiCurrProductPlans[_val].plan_details.startAfter[i], i);
        }
        
        document.getElementById('nuvei_rec_amount').innerHTML 
            = prestashop.currency.sign + Number.parseFloat(nuveiCurrProductPlans[_val].plan_details.recurringAmount).toFixed(2);
        
        document.getElementById('nuvei_payment_plan_details').style.display = 'block';
        
        // if the Cart is not empty - hide "Add to Cart" button
        <?php if(!$data['is_cart_empty']): ?>
            document.getElementsByClassName('product-add-to-cart')[0].style.display = 'none';
            document.getElementById('nuvei_add_error').style.display = 'block';
        <?php endif; ?>
    }
    
    /**
     * Generate period as text.
     * 
     * @param {int} _cnt
     * @param {string} _unit
     * 
     * @returns {String}
     */
    function nuveiGetPeriod(_cnt, _unit) {
        console.log('nuveiGetPeriod()');
        
        var period = _cnt + ' ';
        
        if(_cnt == 1) {
            if('day' == _unit) {
                return period += '<?= $data['day']; ?>';
            }
            if('month' == _unit) {
                return period += '<?= $data['month']; ?>';
            }
            if('year' == _unit) {
                return period += '<?= $data['year']; ?>';
            }
        }
        
        if('day' == _unit) {
            return period += '<?= $data['days']; ?>';
        }
        if('month' == _unit) {
            return period += '<?= $data['months']; ?>';
        }
        if('year' == _unit) {
            return period += '<?= $data['years']; ?>';
        }
    }
    
    if(document.getElementById('group_' + nuveiAttrGroupId) !== null) {
        nuveiShowPlanDetails(document.getElementById('group_' + nuveiAttrGroupId).value);
    }
    else {
        <?php if(isset($data['disable_add_btn']) && $data['disable_add_btn']): ?>
            document.getElementsByClassName('product-add-to-cart')[0].style.display = 'none';
            document.getElementById('nuvei_add_error').style.display = 'block';
        <?php endif; ?>
    }
</script>