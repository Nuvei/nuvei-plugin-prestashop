window.addEventListener('load', function() {
    console.log('nuvei getOrdersWithPlans');
    var nuveiOrdersList = [];

    $('#order_grid_table tbody tr').each(function(){
        var _row = $(this);
        nuveiOrdersList.push(Number.parseInt(_row.find('td:nth-child(2)').text()));
    });

    var nuveiAjax       = new XMLHttpRequest();
    var nuveiParams     = 'scAction=getOrdersList&orders=' + JSON.stringify(nuveiOrdersList);

    nuveiAjax.open("POST", nuveiAjaxUrl, true);
    nuveiAjax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

    nuveiAjax.onreadystatechange = function() {
        if (nuveiAjax.readyState == XMLHttpRequest.DONE && nuveiAjax.status == 200) {
            var nuveiResp = JSON.parse(nuveiAjax.response);
            console.log('nuvei getOrdersList', nuveiResp);

            // on missing data
            if (!nuveiResp.hasOwnProperty('orders')
                || nuveiResp.orders.length == 0
            ) {
                return;
            }

            $('#order_grid_table tbody tr').each(function(){
                var _row		= $(this);
                var rowOrderId	= Number.parseInt(_row.find('td:nth-child(2)').text());

                // not a Nuvei order
                if (!nuveiResp.orders.hasOwnProperty(rowOrderId)) {
                    return;
                }

                // set Subscription marker
                if (1 == nuveiResp.orders[rowOrderId].subscr) {
                    _row.find('td:nth-child(9)').append('<span class="label color_field" style="background-color: #40c1ac; color:#383838; margin-top: 3px; display: inline-block; padding: 2px 5px; border-radius: 4px;">Nuvei Subscription</span>');
                }

                // set suspicious total/currency marker
                if (1 == nuveiResp.orders[rowOrderId].fraud) {
                    _row.find('td:nth-child(9)').append('<span class="label color_field" style="background-color: #fbc6c3; color:#383838; margin-top: 3px; display: inline-block; padding: 2px 5px; border-radius: 4px;" title="Nuvei\s original total/currency is different than the Order\'s total/currency.">Nuvei Alert!</span>');
                }
            });

            return;
        }
    };

    nuveiAjax.send(nuveiParams);
});