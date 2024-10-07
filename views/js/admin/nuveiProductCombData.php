<script>
    console.log('nuveiProductCombData');
    
    var nuveiPaymentPlanGroupIds        = <?= json_encode($group_ids_arr); ?>;
    var nuveiPaymentPlanCombinations    = <?= json_encode($comb_ids_arr); ?>;
    var nuveiPaymentPlansData           = <?= $npp_data; ?>;
    var nuveiAjaxUrl                    = "<?= $nuvei_ajax_url; ?>";
    var nuveiIsIframePrestaVersion      = false;
//    var nuveiIsSaveBtnClicked           = false;
    
    if(typeof nuveiProductsWithPaymentPlans == "undefined") {
        var nuveiProductsWithPaymentPlans = {};
    }
    
    nuveiProductsWithPaymentPlans = <?= json_encode($prod_pans); ?>;
                
    // translations for the Plan Details fields
    var nuveiTexts = {
        NuveiPaymentPlanDetails : '<?= $this->l('Nuvei Payment Plan Details'); ?>',
        PlanID                  : '<?= $this->l('Plan ID'); ?>',
        RecurringAmount         : '<?= $this->l('Recurring Amount'); ?>',
        RecurringEvery          : '<?= $this->l('Recurring Every'); ?>',
        RecurringEndAfter       : '<?= $this->l('Recurring End After'); ?>',
        TrialPeriod             : '<?= $this->l('Trial Period'); ?>',
        Day                     : '<?= $this->l('Day'); ?>',
        Month                   : '<?= $this->l('Month'); ?>',
        Year                    : '<?= $this->l('Year'); ?>'
    };
</script>