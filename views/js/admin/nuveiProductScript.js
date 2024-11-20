/**
 * When change the Plan Id select fill the plan attributes.
 * 
 * @param int _planId
 * @param int combId
 */
function nuveiPopulatePlanFields(_planId, combId) {
    console.log('nuveiPopulatePlanFields()', combId, _planId);

    var parent = $('.combination-modal .combination-iframe').contents()
        .find('#nuveiPaymentFieldsRow_' + combId);
    
	if(parent.length < 1) {
		console.error('Nuvei Error - can not find container with ID ', _parentId);
		return;
	}

	for(var i in nuveiPaymentPlansData) {
		if(nuveiPaymentPlansData[i].planId != _planId) {
			continue;
		}
        
        console.log('fill parameters');
        
		parent.find('.nuvei_rec_amount').val(nuveiPaymentPlansData[i].recurringAmount);

		// recurring period
		if(nuveiPaymentPlansData[i].recurringPeriod.day != 0) {
			parent.find('.nuvei_rec_period').val(nuveiPaymentPlansData[i].recurringPeriod.day);
			parent.find('.nuvei_rec_unit').val('day');
		}
		if(nuveiPaymentPlansData[i].recurringPeriod.month != 0) {
			parent.find('.nuvei_rec_period').val(nuveiPaymentPlansData[i].recurringPeriod.month);
			parent.find('.nuvei_rec_unit').val('month');
		}
		if(nuveiPaymentPlansData[i].recurringPeriod.year != 0) {
			parent.find('.nuvei_rec_period').val(nuveiPaymentPlansData[i].recurringPeriod.year);
			parent.find('.nuvei_rec_unit').val('year');
		}
		// recurring period END

		// recurring end after period
		if(nuveiPaymentPlansData[i].endAfter.day != 0) {
			parent.find('.nuvei_rec_end_after_period').val(nuveiPaymentPlansData[i].endAfter.day);
			parent.find('.nuvei_rec_end_after_unit').val('day');
		}
		if(nuveiPaymentPlansData[i].endAfter.month != 0) {
			parent.find('.nuvei_rec_end_after_period').val(nuveiPaymentPlansData[i].endAfter.month);
			parent.find('.nuvei_rec_end_after_unit').val('month');
		}
		if(nuveiPaymentPlansData[i].endAfter.year != 0) {
			parent.find('.nuvei_rec_end_after_period').val(nuveiPaymentPlansData[i].endAfter.year);
			parent.find('.nuvei_rec_end_after_unit').val('year');
		}
		// recurring end after period END

		// recurring trial period
		if(nuveiPaymentPlansData[i].startAfter.day != 0) {
			parent.find('.nuvei_trial_period').val(nuveiPaymentPlansData[i].startAfter.day);
			parent.find('.nuvei_trial_unit').val('day');
		}
		if(nuveiPaymentPlansData[i].startAfter.month != 0) {
			parent.find('.nuvei_trial_period').val(nuveiPaymentPlansData[i].startAfter.month);
			parent.find('.nuvei_trial_unit').val('month');
		}
		if(nuveiPaymentPlansData[i].startAfter.year != 0) {
			parent.find('.nuvei_trial_period').val(nuveiPaymentPlansData[i].startAfter.year);
			parent.find('.nuvei_trial_unit').val('year');
		}
		// recurring trial period END
	}
    
    // update the object with the populated data.
    nuveiUpdateRecEndAfterUnitPeriod(combId);
    nuveiUpdateRecTrialrUnitPeriod(combId);
    nuveiUpdateRecUnitPeriod(combId);
    
    nuveiProductsWithPaymentPlans[combId]                   = nuveiProductsWithPaymentPlans[combId] || {};
    nuveiProductsWithPaymentPlans[combId].recurringAmount   = Number.parseInt(parent.find('.nuvei_rec_amount').val());
}

/**
 * @param int combId Product Combinantion ID
 * @returns string
 */
function nuveiFillIframeFields(combId) {
    console.log('nuveiFillIframeFields');
    
    // fill saved plan details
	var curr_prod_plan_id = null;
	if(nuveiProductsWithPaymentPlans.hasOwnProperty(combId)) {
		curr_prod_plan_id = nuveiProductsWithPaymentPlans[combId].planId;
	}

	var curr_rec_amount = null;
	if(nuveiProductsWithPaymentPlans.hasOwnProperty(combId)) {
		curr_rec_amount = nuveiProductsWithPaymentPlans[combId].recurringAmount;
	}

	var curr_rec_period	= null;
	var curr_rec_unit	= null;
	if(nuveiProductsWithPaymentPlans.hasOwnProperty(combId)) {
		for(var i in nuveiProductsWithPaymentPlans[combId].recurringPeriod) {
			curr_rec_period = nuveiProductsWithPaymentPlans[combId].recurringPeriod[i];
			curr_rec_unit	= i;
		}
	}

	var curr_rec_end_after_period	= null;
	var curr_rec_end_after_unit		= null;
	if(nuveiProductsWithPaymentPlans.hasOwnProperty(combId)) {
		for(var i in nuveiProductsWithPaymentPlans[combId].endAfter) {
			curr_rec_end_after_period	= nuveiProductsWithPaymentPlans[combId].endAfter[i];
			curr_rec_end_after_unit		= i;
		}
	}

	var curr_rec_trial_period	= null;
	var curr_rec_trial_unit		= null;
	if(nuveiProductsWithPaymentPlans.hasOwnProperty(combId)) {
		for(var i in nuveiProductsWithPaymentPlans[combId].startAfter) {
			curr_rec_trial_period	= nuveiProductsWithPaymentPlans[combId].startAfter[i];
			curr_rec_trial_unit		= i;
		}
	}
	// fill saved plan details END
    
    var iFrameCont  = $('.combination-modal .combination-iframe').contents();
    
    // generate plans list
    if(typeof nuveiPaymentPlansData == 'object') {
		try {
            var html = '';
            
			for(var i in nuveiPaymentPlansData) {
				html +=
                    '<option value="'+ nuveiPaymentPlansData[i].planId +'" '
                        + ( nuveiPaymentPlansData[i].planId == curr_prod_plan_id ? 'selected=""' : '' )  
                    +'>' + nuveiPaymentPlansData[i].name +'</option>';
			}
            
            iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' .nuvei_plans_list').html(html);
		} catch(_e) {}
	}
    
    // set recurring amount
    iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' .nuvei_rec_amount')
        .html( null !== curr_rec_amount ? curr_rec_amount : "1.00" );

    // set recurring period
    iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' .nuvei_rec_period')
        .html(  null !== curr_rec_period ? curr_rec_period : 1 );
    
    // set recurring unit
    iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' .nuvei_rec_unit option').each(function() {
        var _self = $(this);
        
        if (curr_rec_unit === _self.prop('value')) {
            _self.prop('selected', true);
            return;
        }
    });
    
    // set recurring end after period
    iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' .nuvei_rec_end_after_period')
        .html( null !== curr_rec_end_after_period ? curr_rec_end_after_period : 1 );
    
    // set recurring end after unit
    iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' .nuvei_rec_end_after_unit option').each(function() {
        var _self = $(this);
        
        if (curr_rec_end_after_unit === _self.prop('value')) {
            _self.prop('selected', true);
            return;
        }
    });
    
    // set trail period
    iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' .nuvei_trial_period')
        .html( null !== curr_rec_trial_period ? curr_rec_trial_period : 0 );

    // set trail period unit
    iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' .nuvei_trial_unit option').each(function() {
        var _self = $(this);
        
        if (curr_rec_trial_unit === _self.prop('value')) {
            _self.prop('selected', true);
            return;
        }
    });
    
    return;
}

/**
 * When change Nuvei recurring fields in the iframe, update the obecjt with the plans details.
 * 
 * @param int combId
 * @returns void
 */
function nuveiUpdateRecUnitPeriod(combId) {
    console.log('nuveiUpdateRecUnitPeriod');
    
    var iFrameCont  = $('.combination-modal .combination-iframe').contents();
    var container   = iFrameCont.find('#nuveiPaymentFieldsRow_' + combId);
    var newObj      = {};
    
    newObj[container.find('.nuvei_rec_unit').val()] 
        = Number.parseInt(container.find('.nuvei_rec_period').val());
    
    nuveiProductsWithPaymentPlans[combId]                   = nuveiProductsWithPaymentPlans[combId] || {};
    nuveiProductsWithPaymentPlans[combId].recurringPeriod   = newObj;
}

/**
 * When change Nuvei recurring fields in the iframe, update the obecjt with the plans details.
 * 
 * @param int combId
 * @returns void
 */
function nuveiUpdateRecEndAfterUnitPeriod(combId) {
    console.log('nuveiUpdateRecEndAfterUnitPeriod');
    
    var iFrameCont  = $('.combination-modal .combination-iframe').contents();
    var container   = iFrameCont.find('#nuveiPaymentFieldsRow_' + combId);
    var newObj      = {};
    
    newObj[container.find('.nuvei_rec_end_after_unit').val()] 
        = Number.parseInt(container.find('.nuvei_rec_end_after_period').val());
    
    nuveiProductsWithPaymentPlans[combId]           = nuveiProductsWithPaymentPlans[combId] || {};
    nuveiProductsWithPaymentPlans[combId].endAfter  = newObj;
}

/**
 * When change Nuvei recurring fields in the iframe, update the obecjt with the plans details.
 * 
 * @param int combId
 * @returns void
 */
function nuveiUpdateRecTrialrUnitPeriod(combId) {
    console.log('nuveiUpdateRecEndAfterUnitPeriod');
    
    var iFrameCont  = $('.combination-modal .combination-iframe').contents();
    var container   = iFrameCont.find('#nuveiPaymentFieldsRow_' + combId);
    var newObj      = {};
    
    newObj[container.find('.nuvei_trial_unit').val()] 
        = Number.parseInt(container.find('.nuvei_trial_period').val());
    
    nuveiProductsWithPaymentPlans[combId]               = nuveiProductsWithPaymentPlans[combId] || {};
    nuveiProductsWithPaymentPlans[combId].startAfter    = newObj;
}

function insertNuveiPlansFieldsIframe() {
    console.log('insertNuveiPlansFieldsIframe()');

    if ($('.combination-modal .combination-iframe').contents().find('#combination_form').length == 0) {
        console.log('abort insertNuveiPlansFieldsIframe');
        return;
    }
    
    var matches = $('.combination-modal .combination-iframe').attr('src').match(/\/combinations\/(.*)\/edit\?/);
    
    if (typeof matches != 'object' || matches.length < 2) {
        console.log('this is not the combinantion edit modal', typeof matches, matches.length);
        return;
    }
    
    var combId      = matches[1];
    var iFrameCont  = $('.combination-modal .combination-iframe').contents();
    
    // this combination does not belongs to Nuvei Plans
    if(nuveiPaymentPlanCombinations.indexOf(combId) < 0) {
        return;
    }
    
    // fill fields details
    if (iFrameCont.find('#nuveiPaymentFieldsRow_' + combId).length == 1
        && iFrameCont.find('#nuveiPaymentFieldsRow_' + combId + ' select.nuvei_plans_list option').length == 0
    ) {
        nuveiFillIframeFields(combId);
    }
    
}

$(document).on('DOMNodeInserted', '.combination-modal', function() {
    console.log('combination-modal loaded');
    insertNuveiPlansFieldsIframe();
    return;
});