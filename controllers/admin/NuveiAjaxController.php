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
            echo json_encode(array('status' => 0, 'msg' => 'There is no action.'));
            exit;
        }
        
        if(is_numeric(Tools::getValue('scOrder'))
            && intval(Tools::getValue('scOrder')) > 0
            && in_array($action, array('settle', 'void'))
        ) {
            $this->order_void_settle($action);
        }
        
        if($action == 'downloadPaymentPlans') {
            $this->get_payment_plans();
        }
        
        if($action == 'getOrdersWithPlans' && Tools::getValue('orders')) {
            $this->get_orders_with_plans();
        }
        
        exit;
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
            if(0 == $params['amount']) {
                $resp = $this->module->cancel_subscription($order_id);
                
                header('Content-Type: application/json');
                
                if($resp === false) {
                    exit(json_encode(array('status' => 0)));
                }
                
                exit(json_encode(array(
                    'status'    => $status,
                    'data'      => $resp
                )));
            }
            
            $method = 'voidTransaction';
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
            Configuration::get('SC_MERCHANT_ID') 
                . Configuration::get('SC_MERCHANT_SITE_ID')
                . $params['currency']
                . $params['planStatus']
                . $time
                . Configuration::get('SC_SECRET_KEY')
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
    
    private function get_orders_with_plans()
    {
        $orders_params  = Tools::getValue('orders');
        $orders_str     = substr($orders_params, 1, -1);
        $orders_arr     = array();
        
        if(empty($orders_str)) {
            echo json_encode(array(
                'status'    => 1,
                'orders'    => [],
            ));
            exit;
        }
        
        $query = "SELECT order_id FROM safecharge_order_data "
            . "WHERE subscr_ids <> '' "
                . "AND order_id IN (" . DB::getInstance()->escape($orders_str, false, true) . ');';
        
        $res = DB::getInstance()->executeS($query);
        
        header('Content-Type: application/json');
        
        if(!$res || empty($res)) {
            echo json_encode(array(
                'status'    => 1,
                'orders'    => array(),
            ));
            exit;
        }
        
        foreach ($res as $data) {
            $orders_arr[] = $data['order_id'];
        }
        
        echo json_encode(array(
            'status'    => 1,
            'orders'    => $orders_arr,
        ));
        exit;
    }
    
}
