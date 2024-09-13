/**
 * When change the Plan Id select fill the plan attributes.
 * 
 * @param string _parentId
 * @param int _planId
 * 
 * @returns
 */
function nuveiPopulatePlanFields(_parentId, _planId) {
    // before Presta v8.1.*
    if (!nuveiIsIframePrestaVersion) {
        var parent = $('#form').find(_parentId);
    }
    // Presta v8.1.* and up
    else {
        var parent = $('.combination-modal .combination-iframe').contents()
            .find('#combination_form').find(_parentId);
    }
    
    console.log(_parentId, _planId, parent);

	if(parent.length < 1) {
		console.error('Nuvei Error - can not find container with ID ', _parentId, nuveiPrestaVersion);
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
}

function getNuveiFields(combId) {
	console.log('getNuveiFields()');

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

	var html = 
		'<div id="nuveiPaymentFieldsRow_'+ combId +'">'
			+ '<h2 class="title">'+ nuveiTexts.NuveiPaymentPlanDetails +'</h2>'
			+ '<div class="row">'
				+ '<div class="col-md-3">'
					+ '<fieldset class="form-group">'
						+ '<label class="form-control-label">'+ nuveiTexts.PlanID +'</label>'
						+ '<select name="nuvei_payment_plan_attr['+ combId +'][plan_id]" class="form-control nuvei_plan_id" onchange="nuveiPopulatePlanFields(\'#nuveiPaymentFieldsRow_'+ combId +'\', this.value)" required="">';

	if(typeof nuveiPaymentPlansData == 'object') {
		try {
			for(var i in nuveiPaymentPlansData) {
				html +=
							'<option value="'+ nuveiPaymentPlansData[i].planId +'" '
								+ ( nuveiPaymentPlansData[i].planId == curr_prod_plan_id ? 'selected=""' : '' )  +'>'
								+ nuveiPaymentPlansData[i].name +'</option>';
			}
		} catch(_e) {}
	}

	html +=
						'</select>'
					+ '</fieldset>'
				+ '</div>'

				+ '<div class="col-md-3">'
					+ '<fieldset class="form-group">'
						+ '<label class="form-control-label">'+ nuveiTexts.RecurringAmount +'</label>'
						+ '<div class="input-group money-type">'
							+ '<div class="input-group-prepend">'
								+ '<span class="input-group-text">'+ currency.iso_code +' </span>'
							+ '</div>'
							+ '<input type="number" name="nuvei_payment_plan_attr['+ combId +'][rec_amount]" class="attribute_wholesale_price form-control nuvei_rec_amount" value="'+ ( null !== curr_rec_amount ? curr_rec_amount : 1 ) +'" min="0.01" step="1">'
						+ '</div>'
					+ '</fieldset>'
				+ '</div>'
			+ '</div>'

			+ '<div class="row">'
				+ '<div class="col-md-3">'
					+ '<fieldset class="form-group">'
						+ '<label class="form-control-label">'+ nuveiTexts.RecurringEvery +'</label>'
						+ '<input type="number" name="nuvei_payment_plan_attr['+ combId +'][rec_period]" class="attribute_wholesale_price form-control nuvei_rec_period" value="'+ ( null !== curr_rec_period ? curr_rec_period : 1 ) +'" min="1" step="1">'
					+ '</fieldset>'
				+ '</div>'

				+ '<div class="col-md-3">'
					+ '<fieldset class="form-group">'
						+ '<label class="form-control-label">&nbsp;</label>'
						+ '<select name="nuvei_payment_plan_attr['+ combId +'][rec_unit]" class="form-control nuvei_rec_unit" required="">'
							+ '<option value="day" '+ ( curr_rec_unit == "day" ? 'selected=""' : "" ) +'>'
								+ nuveiTexts.Day +'</option>'
							+ '<option value="month" '+ ( curr_rec_unit == "month" ? 'selected=""' : "" ) +'>'
								+ nuveiTexts.Month +'</option>'
							+ '<option value="year" '+ ( curr_rec_unit == "year" ? 'selected=""' : "" ) +'>'
								+ nuveiTexts.Year +'</option>'
						+ '</select>'
					+ '</fieldset>'
				+ '</div>'
			+ '</div>'

			+ '<div class="row">'
				+ '<div class="col-md-3">'
					+ '<fieldset class="form-group">'
						+ '<label class="form-control-label">'+ nuveiTexts.RecurringEndAfter +'</label>'
						+ '<input type="number" name="nuvei_payment_plan_attr['+ combId +'][rec_end_after_period]" class="attribute_wholesale_price form-control nuvei_rec_end_after_period" value="'+ ( null !== curr_rec_end_after_period ? curr_rec_end_after_period : 1 ) +'" min="1" step="1">'
					+ '</fieldset>'
				+ '</div>'

				+ '<div class="col-md-3">'
					+ '<fieldset class="form-group">'
						+ '<label class="form-control-label">&nbsp;</label>'
						+ '<select name="nuvei_payment_plan_attr['+ combId +'][rec_end_after_unit]" class="form-control nuvei_rec_end_after_unit" required="">'
							+ '<option value="day" '+ ( curr_rec_end_after_unit == "day" ? 'selected=""' : "" ) +'>'
								+  nuveiTexts.Day +'</option>'
							+ '<option value="month" '+ ( curr_rec_end_after_unit == "month" ? 'selected=""' : "" ) +'>'
								+ nuveiTexts.Month +'</option>'
							+ '<option value="year" '+ ( curr_rec_end_after_unit == "year" ? 'selected=""' : "" ) +'>' 
								+ nuveiTexts.Year +'</option>'
						+ '</select>'
					+ '</fieldset>'
				+ '</div>'
			+ '</div>'

			+ '<div class="row">'
				+ '<div class="col-md-3">'
					+ '<fieldset class="form-group">'
						+ '<label class="form-control-label">'+ nuveiTexts.TrialPeriod +'</label>'
						+ '<input type="number" name="nuvei_payment_plan_attr['+ combId +'][trial_period]" class="attribute_wholesale_price form-control nuvei_trial_period" value="'+ ( null !== curr_rec_trial_period ? curr_rec_trial_period : 0 ) +'" min="0" step="1">'
					+ '</fieldset>'
				+ '</div>'

				+ '<div class="col-md-3">'
					+ '<fieldset class="form-group">'
						+ '<label class="form-control-label">&nbsp;</label>'
						+ '<select name="nuvei_payment_plan_attr['+ combId +'][rec_trial_unit]" class="form-control nuvei_trial_unit" required="">'
							+ '<option value="day" '+ ( curr_rec_trial_unit == "day" ? 'selected=""' : "" ) +'>'
								+ nuveiTexts.Day +'</option>'
							+ '<option value="month" '+ ( curr_rec_trial_unit == "month" ? 'selected=""' : "" ) +'>'
								+ nuveiTexts.Month +'</option>'
							+ '<option value="year"'+ ( curr_rec_trial_unit == "year" ? 'selected=""' : "" ) +'>'
								+ nuveiTexts.Year +'</option>'
						+ '</select>'
					+ '</fieldset>'
				+ '</div>'
			+ '</div>'
		+ '</div>';

		return html;
}

/**
 * This method is for Prestashop v8.1.*. The version with the TS iframe templates.
 * 
 * @param int combId
 * @returns void
 */
function getNuveiFieldsIframe(combId) {
    console.log('getNuveiFieldsIframe() combination id', combId);
    
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

	var html = 
        '<div class="form-group" id="nuveiPaymentFieldsRow_'+ combId +'">'
            + '<h3>'+ nuveiTexts.NuveiPaymentPlanDetails +'</h3>'
    
            + '<div class="form-columns-3">'
                + '<div class="form-group text-widget">'
                    + '<label>'+ nuveiTexts.PlanID +'</label>'
                    + '<select name="nuvei_payment_plan_attr['+ combId +'][plan_id]" class="custom-select form-control nuvei-rebilling-form" onchange="window.top.nuveiPopulatePlanFields(\'#nuveiPaymentFieldsRow_'+ combId +'\', this.value)" required="">';
    
    if(typeof nuveiPaymentPlansData == 'object') {
		try {
			for(var i in nuveiPaymentPlansData) {
				html +=
                        '<option value="'+ nuveiPaymentPlansData[i].planId +'" '
                            + ( nuveiPaymentPlansData[i].planId == curr_prod_plan_id ? 'selected=""' : '' )  +'>'
                            + nuveiPaymentPlansData[i].name +'</option>';
			}
		} catch(_e) {}
	}
                        
    html +=
                    '</select>'
                + '</div>'

                + '<div class="form-group money-widget">'
                    + '<label>'+ nuveiTexts.RecurringAmount +'</label>'
                    + '<div class="input-group money-type">'
                        + '<div class="input-group-prepend">'
                            + '<span class="input-group-text">'+ currency.iso_code +'</span>'
                        + '</div>'

                        + '<input type="text" name="nuvei_payment_plan_attr['+ combId +'][rec_amount]" data-display-price-precision="2" class="js-comma-transformer form-control nuvei-rebilling-form nuvei_rec_amount" value="'+ ( null !== curr_rec_amount ? curr_rec_amount : 1 ) +'">'
                    + '</div>'
                + '</div>'
        
                + '<div class="form-group form-column-breaker"></div>'
        
                + '<div class="form-group number-widget">'
                    + '<label>'+ nuveiTexts.RecurringEvery +'</label>'
                    + '<input type="number" name="nuvei_payment_plan_attr['+ combId +'][rec_period]" aria-label="input" class="small-input js-comma-transformer form-control nuvei-rebilling-form nuvei_rec_period" min="1" value="'+ ( null !== curr_rec_period ? curr_rec_period : 1 ) +'">'
                + '</div>'
        
                + '<div class="form-group text-widget">'
                    + '<label>&nbsp;</label>'
                    + '<select name="nuvei_payment_plan_attr['+ combId +'][rec_unit]" class="custom-select form-control nuvei-rebilling-form nuvei_rec_unit" required="">'
                        + '<option value="day" '+ ( curr_rec_unit == "day" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Day +'</option>'
                        + '<option value="month" '+ ( curr_rec_unit == "month" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Month +'</option>'
                        + '<option value="year" '+ ( curr_rec_unit == "year" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Year +'</option>'
                    + '</select>'
                + '</div>'
        
                + '<div class="form-group form-column-breaker"></div>'
        
                + '<div class="form-group number-widget">'
                    + '<label>'+ nuveiTexts.RecurringEndAfter +'</label>'
                    + '<input type="number" name="nuvei_payment_plan_attr['+ combId +'][rec_end_after_period]" aria-label="input" class="small-input js-comma-transformer form-control nuvei-rebilling-form nuvei_rec_end_after_period" min="1" value="'+ ( null !== curr_rec_end_after_period ? curr_rec_end_after_period : 1 ) +'">'
                + '</div>'
        
                + '<div class="form-group text-widget">'
                    + '<label>&nbsp;</label>'
                    + '<select name="nuvei_payment_plan_attr['+ combId +'][rec_end_after_unit]" class="custom-select form-control nuvei-rebilling-form nuvei_rec_end_after_unit" required="">'
                        + '<option value="day" '+ ( curr_rec_unit == "day" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Day +'</option>'
                        + '<option value="month" '+ ( curr_rec_unit == "month" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Month +'</option>'
                        + '<option value="year" '+ ( curr_rec_unit == "year" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Year +'</option>'
                    + '</select>'
                + '</div>'
        
                + '<div class="form-group form-column-breaker"></div>'
                
                + '<div class="form-group number-widget">'
                    + '<label>'+ nuveiTexts.TrialPeriod +'</label>'
                    + '<input type="number" name="nuvei_payment_plan_attr['+ combId +'][trial_period]" aria-label="input" class="small-input js-comma-transformer form-control nuvei-rebilling-form nuvei_trial_period" min="0" value="'+ ( null !== curr_rec_trial_period ? curr_rec_trial_period : 0 ) +'">'
                + '</div>'
                
                + '<div class="form-group text-widget">'
                    + '<label>&nbsp;</label>'
                    + '<select name="nuvei_payment_plan_attr['+ combId +'][rec_trial_unit]" class="custom-select form-control nuvei-rebilling-form nuvei_trial_unit" required="">'
                        + '<option value="day" '+ ( curr_rec_unit == "day" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Day +'</option>'
                        + '<option value="month" '+ ( curr_rec_unit == "month" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Month +'</option>'
                        + '<option value="year" '+ ( curr_rec_unit == "year" ? 'selected=""' : "" ) +'>'
                            + nuveiTexts.Year +'</option>'
                    + '</select>'
                + '</div>'
            + '</div>'
        + '</div>';
    
    return html;
}

function insertNuveiPlansFields() {
	console.log('insertNuveiPlansFields()');

//	$('.combination-form.row').each(function(){
	$('.combination-form').each(function(){
		var _self	= $(this);
		var combId	= _self.attr('data');

//		console.log('combId', nuveiPaymentPlanCombinations, combId, typeof combId, nuveiPaymentPlanCombinations.indexOf(combId));

		// if this combination is part of Nuvei Payment Plan group
		if(nuveiPaymentPlanCombinations.indexOf(combId) > -1) {
			var htmlContainer = _self.find('.panel');

			console.log(
				'Nuvei Payment Plan Fields #nuveiPaymentFieldsRow_'+ combId,
				$('#form').find('#nuveiPaymentFieldsRow_'+ combId).length
			);

			if($('#form').find('#nuveiPaymentFieldsRow_'+ combId).length == 0) {
				htmlContainer.append(getNuveiFields(combId));
			}
		}
	});
}

function insertNuveiPlansFieldsIframe() {
    console.log('insertNuveiPlansFieldsIframe()');
    
    if ($('.combination-modal .combination-iframe').contents().find('#combination_form').length == 0) {
        return;
    }
    
    var matches = $('.combination-modal .combination-iframe').attr('src').match(/\/combinations\/(.*)\/edit\?/);
    
    if (typeof matches != 'object' || matches.length < 2) {
        console.log(typeof matches, matches.length);
        return;
    }
    
    var combId      = matches[1];
    var iFrameCont  = $('.combination-modal .combination-iframe').contents();
    
    // this combination does not belongs to Nuvei Plans
    if(nuveiPaymentPlanCombinations.indexOf(combId) < 0) {
        return;
    }
    
    // add combination_form_rebilling_details_
    if (iFrameCont.find('#combination_form #combination_form_rebilling_details_' + combId).length == 0) {
        iFrameCont.find('#combination_form').append(getNuveiFieldsIframe(combId));
        
        // enable modal Save button when change some of Nuvei Rebilling paramters
        iFrameCont.find('#combination_form .nuvei-rebilling-form').on('change', function() {
            $('.modal-content .modal-footer button.btn-primary').prop('disabled', false);
        });
    }
    
}

function nuveiCheckConditions() {
	console.log('nuveiCheckConditions()');

	if(typeof $ != 'function') {
		console.log('Nuvei Error - $ is not a function');
		return;
	}

	// click on Combinations tab or on Edit Combination icon
	$(document).on('click', '.nav-link, .btn-open.tooltip-link', function() {
		if($('.combination-form').length > 0) {
			insertNuveiPlansFields();
            return;
		}
	});
    
    // for v8.1.*, it use Iframe
    $(document).on('DOMNodeInserted', '.combination-modal', function() {
		nuveiIsIframePrestaVersion = true;
		
        insertNuveiPlansFieldsIframe();
        return;
    });
}

window.addEventListener('load', function() {
	nuveiCheckConditions();
});