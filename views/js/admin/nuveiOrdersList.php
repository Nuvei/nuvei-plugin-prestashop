<script>
    window.addEventListener('load', function() {
        console.log('nuvei getOrdersWithPlans');
        var nuveiOrdersList = [];

        $('#order_grid_table tbody tr').each(function(){
            var _row = $(this);
            nuveiOrdersList.push(Number.parseInt(_row.find('td:nth-child(2)').text()));
        });

        var nuveiAjaxUrl    = "<?= $nuvei_ajax_url; ?>";
        var nuveiAjax       = new XMLHttpRequest();
        var nuveiParams     = 'scAction=getOrdersWithPlans&orders=' + JSON.stringify(nuveiOrdersList);

        nuveiAjax.open("POST", nuveiAjaxUrl, true);
        nuveiAjax.setRequestHeader("Content-type", "application/x-www-form-urlencoded");

        nuveiAjax.onreadystatechange = function(resp) {
            console.log('nuvei getOrdersWithPlans', resp);
            if (nuveiAjax.readyState == 4 && nuveiAjax.status == 200) {
                var nuveiResp = JSON.parse(this.response);

                if(1 == nuveiResp.status && nuveiResp.orders.length > 0) {
                    $('#order_grid_table tbody tr').each(function(){
                        var _row		= $(this);
                        var rowOrderId	= Number.parseInt(_row.find('td:nth-child(2)').text());

                        if(nuveiResp.orders.findIndex(item => item == rowOrderId) > -1) {
                            var rowStatus = _row.find('td:nth-child(9)').html()
                                + '<span class="label color_field" style="background-color:#40c1ac;color:#383838">Nuvei Subscription</span>';

                            _row.find('td:nth-child(9)').html(rowStatus);
                            // fix the style
                            _row.find('td:nth-child(9)').find('span.color_field').css({
                                marginTop: '3px',
                                display: 'inline-block',
                                padding: '2px 5px',
                                borderRadius: '4px'
                            });
                        }
                    });

                    return;
                }

                return;
            }
        }

        // If an error occur during the nuveiAjax call.
        if (nuveiAjax.readyState == 4 && ajax.status == 404) {
            console.error('Nuvei Ajax call error.');
        }

        nuveiAjax.send(nuveiParams);

    });
</script>