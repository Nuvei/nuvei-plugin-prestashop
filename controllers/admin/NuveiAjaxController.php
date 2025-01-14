<?php

/**
 * @author Nuvei
 */

class NuveiAjaxController extends ModuleAdminControllerCore
{
    public function __construct()
    {
        parent::__construct();
        
        // security check
        if($this->module->getModuleSecurityKey() != Tools::getValue('security_key')) {
            $this->module->createLog(
                array(
                    'ModuleSecurityKey' => $this->module->getModuleSecurityKey(),
                    'security_key'      => Tools::getValue('security_key')
                ),
                'NuveiAjaxController Error - security key does not match'
            );
            exit;
        }
        
        $action = Tools::getValue('scAction');
        
        if(!$action) {
            header('Content-Type: application/json');
            echo json_encode(array(
                'status'    => 0, 
                'msg'       => 'There is no action.'
            ));
            exit;
        }
        
        if(is_numeric(Tools::getValue('scOrder'))
            && (int) Tools::getValue('scOrder') > 0
            && in_array($action, array('settle', 'void'))
        ) {
            $this->order_void_settle($action);
        }
        
        if(is_numeric(Tools::getValue('scOrder'))
            && (int) Tools::getValue('scOrder') > 0
            && 'cancelSubscription' == $action
        ) {
            $this->cancel_subscription();
        }
        
        if($action == 'downloadPaymentPlans') {
            $this->get_payment_plans();
        }
        
        if($action == 'getOrdersList' && Tools::getValue('orders')) {
            $this->getOrdersList();
        }
        
        if($action == 'getProductWithPaymentPlan' && Tools::getValue('combId')) {
            $this->getProductWithPaymentPlan();
        }
        
        if($action == 'getPaymentsData' && Tools::getValue('prodId')) {
            $this->getPaymentsData();
        }
        
        exit(json_encode(array(
            'status'    => 0, 
            'msg'       => 'The Action is not recognized.'
        )));
    }
    
    /**
     * Function order_void_settle
     * We use one function for both because the only
     * difference is the endpoint, all parameters are same
     * 
     * @param string $action the action
     */
    private function order_void_settle($action)
    {
        $this->module->createLog('Void/Settle');
        
        $order_id   = (int) Tools::getValue('scOrder');
        $order_info = new Order($order_id);
        $currency   = new Currency($order_info->id_currency);
        $sc_data    = Db::getInstance()->getRow('SELECT * FROM safecharge_order_data WHERE order_id = ' . $order_id);
        $time       = date('YmdHis', time());
        $status     = 1; // default status of the response
		$trans_id	= !empty($sc_data['transaction_id']) ? $sc_data['transaction_id'] : $sc_data['related_transaction_id'];
        
        $params = array(
            'clientRequestId'       => $time . '_' . $trans_id,
            'clientUniqueId'        => $order_id,
            'amount'                => $this->module->formatMoney($order_info->total_paid),
            'currency'              => $currency->iso_code,
            'relatedTransactionId'  => $trans_id,
            'authCode'              => $sc_data['auth_code'],
            'urlDetails'            => array('notificationUrl' => $this->module->getNotifyUrl()),
        );
        
        if($action == 'void') {
            $method = 'voidTransaction';
            
            if(0 == $params['amount']) {
                $this->cancel_subscription();
            }
        }
        
        if($action == 'settle') {
			$method = 'settleTransaction';
        }
        
        $resp = $this->module->callRestApi(
            $method,
            $params,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'authCode', 'url', 'timeStamp')
        );
        
        if(!$resp || !is_array($resp)
            || @$resp['status'] == 'ERROR'
            || @$resp['transactionStatus'] == 'ERROR'
            || @$resp['transactionStatus'] == 'DECLINED'
        ) {
            $status = 0;
        }
        elseif('voidTransaction' == $action) {
            $this->module->cancel_subscription($order_id);
        }
        
        header('Content-Type: application/json');
        exit(json_encode(array('status' => $status, 'data' => $resp)));
    }
    
    private function cancel_subscription()
    {
        $order_id   = (int) Tools::getValue('scOrder');
        $resp       = $this->module->cancel_subscription($order_id);
        $status     = 1; // default status of the response
        
        header('Content-Type: application/json');
                
        if($resp === false) {
            exit(json_encode(array('status' => 0)));
        }

        exit(json_encode(array(
            'status'    => $status,
            'data'      => $resp
        )));
    }
    
    /**
     * Get the merchant plans and save them as json.
     * 
     * @param int $call - number for the recursion
     */
    private function get_payment_plans($call = 0)
    {
        $time= date('YmdHis', time());
        
        $params = array(
            'planStatus'		=> 'ACTIVE',
			'currency'			=> '',
        );
        
        $checksum = hash(
            Configuration::get('SC_HASH_TYPE'),
            trim(Configuration::get('SC_MERCHANT_ID')) 
                . trim(Configuration::get('SC_MERCHANT_SITE_ID'))
                . $params['currency']
                . $params['planStatus']
                . $time
                . trim(Configuration::get('SC_SECRET_KEY'))
        );
        
        $params['checksum'] = $checksum;
        
        $resp = $this->module->callRestApi(
            'getPlansList',
            $params,
            array('merchantId', 'merchantSiteId', 'currency', 'planStatus', 'timeStamp')
        );
        
        if(!isset($resp['plans']) || !is_array($resp['plans'])) {
            header('Content-Type: application/json');
            echo json_encode(array('status' => 0));
            exit;
        }
        
        // generate default plan
        if(empty($resp['plans']) && $call == 0) {
            $this->create_default_payment_plan();
            
            $call += 1;
            $this->get_payment_plans($call);
            exit;
        }
        
        $file = _PS_ROOT_DIR_ . '/var/logs/' . $this->module->paymentPlanJson;
        
        header('Content-Type: application/json');
        
        if(!file_put_contents($file, json_encode($resp['plans']))) {
            echo json_encode(array('status' => 0));
            exit;
        }
        
        echo json_encode(array(
            'status'    => 1,
            'date'      => date('Y-m-d H:i:s', time()),
        ));
        exit;
    }
    
    private function create_default_payment_plan()
    {
        $params = array(
            'name'              => 'Default_plan_for_site_' . Configuration::get('SC_MERCHANT_SITE_ID'),
			'initialAmount'     => 0,
			'recurringAmount'   => 1,
			'currency'          => $this->context->currency->iso_code,
			'planStatus'        => 'ACTIVE',
			'startAfter'        => array(
				'day'   => 0,
				'month' => 1,
				'year'  => 0,
			),
			'recurringPeriod'   => array(
				'day'   => 0,
				'month' => 1,
				'year'  => 0,
			),
			'endAfter'          => array(
				'day'   => 0,
				'month' => 0,
				'year'  => 1,
			),
        );
        
        $this->module->callRestApi(
            'getPlansList', 
            $params,
            array('merchantId', 'merchantSiteId', 'currency', 'planStatus', 'timeStamp')
        );
    }
    
    private function getOrdersList()
    {
        $orders_params  = Tools::getValue('orders');
        $orders_str     = substr($orders_params, 1, -1);
        $orders_arr     = [];
        
        if(empty($orders_str)) {
            echo json_encode(array(
                'status'    => 1,
                'orders'    => [],
            ));
            exit;
        }
        
        $query = 
            "SELECT sod.order_id, sod.subscr_ids, nod.data "
            . "FROM safecharge_order_data AS sod "
            . "LEFT JOIN nuvei_orders_data AS nod "
                . "ON sod.order_id = nod.order_id "
            . "WHERE sod.order_id IN (" . DB::getInstance()->escape($orders_str, false, true) . ');';
        
        $res = DB::getInstance()->executeS($query);
        
//        $this->module->createLog($res, 'Orders list.');
        
        header('Content-Type: application/json');
        
        if(!$res || empty($res)) {
            exit(json_encode(array(
                'orders' => array(),
            )));
        }
        
        foreach ($res as $data) {
            $orders_arr[$data['order_id']]['subscr']    = 0;
            $orders_arr[$data['order_id']]['fraud']     = 0;
            
            $nuvei_data = json_decode($data['data'], true);
            
            if (!empty($data['subscr_ids'])
                || !empty($nuvei_data['subscriptions'])
            ) {
                $orders_arr[$data['order_id']]['subscr'] = 1;
            }
            
            $transactions = json_decode($data['data'], true);
            
            if (empty($transactions) || empty($transactions['transactions'])) {
                continue;
            }
            
            foreach ($transactions['transactions'] as $tr) {
                if (!in_array($tr['transactionType'], ['Auth', 'Sale'])) {
                    continue;
                }
                
                if (isset($tr['totalCurrAlert'])) {
                    $orders_arr[$data['order_id']]['fraud'] = 1;
                }
            }
        }
        
        exit(json_encode(array(
            'orders' => $orders_arr,
        )));
    }
    
    private function getProductWithPaymentPlan()
    {
        $sql = "SELECT plan_details "
            . "FROM nuvei_product_payment_plan_details "
            . "WHERE id_product_attribute = " . (int) Tools::getValue('combId');

        $res = Db::getInstance()->getRow($sql);

        $this->module->createLog([$sql, $res, gettype($res['plan_details'])], 'getProductWithPaymentPlan', 'DEBUG');
        
        // success
        if (!empty($res['plan_details'])) {
            exit((($res['plan_details'])));
        }
        
        // error
        exit([]);
    }
    
    /**
     * We call this method from the combination modal.
     */
    private function getPaymentsData()
    {
        $prodId = Tools::getValue('prodId');
        
        // error
        if (!is_numeric($prodId) || $prodId < 1) {
            exit([]);
        }
        
        $product        = new Product((int) $prodId);
        $id_lang        = Context::getContext()->language->id;
        $combinations   = $product->getAttributeCombinations((int) $id_lang, true);
        $comb_ids_arr   = array();
        // get Nuvei Payment Plan group IDs
        $group_ids_arr  = $this->module->getNuvePaymentPlanGroupIds();

        $this->module->createLog($group_ids_arr, 'getPaymentsData() $group_ids_arr', 'DEBUG');

        foreach($combinations as $data) {
            if(in_array($data['id_attribute_group'], $group_ids_arr)
                && !in_array($data['id_attribute_group'], $comb_ids_arr)
            ) {
                $comb_ids_arr[] = (string) $data['id_product_attribute'];
            }
        }

        // load Nuvei Payment Plans data
        $npp_data   = '';
        $file       = _PS_ROOT_DIR_ . '/var/logs/' . $this->module->paymentPlanJson;

        if(is_readable($file)) {
            $npp_data = file_get_contents($file);
        }

        // load the Payment details for the products
        $prod_plans  = array();

        if (!empty($comb_ids_arr)) {
            $sql = "SELECT id_product_attribute, plan_details "
                . "FROM nuvei_product_payment_plan_details "
                . "WHERE id_product_attribute IN (" . join(',', $comb_ids_arr) . ")";

            try {
                $res = Db::getInstance()->executeS($sql);

                if(is_array($res) && !empty($res)) {
                    foreach ($res as $details) {
                        if (empty($details['id_product_attribute'])) {
                            continue;
                        }

                        $prod_plans[$details['id_product_attribute']] 
                            = json_decode($details['plan_details'], true);
                    }
                }
            }
            catch(\Exception $e) {
                $this->module->createLog($e->getMessage(), 'hookDisplayBackOfficeHeader test', 'DEBUG');
            }
        }

        $this->module->createLog([$sql, $res, $prod_plans], 'hookDisplayBackOfficeHeader', 'DEBUG');

        exit(json_encode([
            'nuveiPaymentPlanCombinations'  => $comb_ids_arr,
            'nuveiPaymentPlansData'         => json_decode($npp_data, true),
            'nuveiProductsWithPaymentPlans' => (object) $prod_plans,
            'nuveiTexts'                    => [
                'NuveiPaymentPlanDetails'       => $this->module->nuveiTrans('Nuvei Payment Plan Details'),
                'PlanID'                        => $this->module->nuveiTrans('Plan ID'),
                'RecurringAmount'               => $this->module->nuveiTrans('Recurring Amount'),
                'RecurringEvery'                => $this->module->nuveiTrans('Recurring Every'),
                'RecurringEndAfter'             => $this->module->nuveiTrans('Recurring End After'),
                'TrialPeriod'                   => $this->module->nuveiTrans('Trial Period'),
                'Day'                           => $this->module->nuveiTrans('Day'),
                'Month'                         => $this->module->nuveiTrans('Month'),
                'Year'                          => $this->module->nuveiTrans('Year'),
            ],
        ]));
    }
    
}
