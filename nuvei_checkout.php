<?php

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
    exit;
}

class Nuvei_Checkout extends PaymentModule
{
    public $name                        = 'nuvei_checkout';
    public $author                      = 'Nuvei';
    public $displayName                 = 'Nuvei Payments'; // we see this in Prestashop Modules list
    public $paymentPlanJson             = 'nuvei_payment_plans.json';
    public $version                     = '1.0.0';
    public $ps_versions_compliancy      = array(
        'min' => '1.7.7.0', 
        'max' => _PS_VERSION_ // for curent version - _PS_VERSION_
    );
    public $controllers                 = array('payment', 'validation');
    public $bootstrap                   = true;
    public $currencies                  = true;
    public $currencies_mode             = 'checkbox'; // for the Payment > Preferences menu
    public $need_instance               = 1;
    public $is_eu_compatible            = 1;
    
    private $sdkLibDevUrl               = 'https://srv-bsf-devpppjs.gw-4u.com/checkoutNext/checkout.js';
    private $sdkLibProdUrl              = 'https://cdn.safecharge.com/safecharge_resources/v1/checkout/checkout.js';
    private $apmPopupAutoCloseUrl       = 'https://cdn.safecharge.com/safecharge_resources/v1/websdk/autoclose.html';
    private $restApiIntUrl              = 'https://ppp-test.safecharge.com/ppp/api/v1/';
    private $restApiProdUrl             = 'https://secure.safecharge.com/ppp/api/v1/';
    private $paymentPlanGroup           = 'Nuvei Payment Plan';
    private $nuvei_source_application   = ''; // Must be added some day
    private $html                       = '';
    private $trace_id;
    
    public function __construct()
    {
        require_once _PS_MODULE_DIR_ . $this->name . DIRECTORY_SEPARATOR 
            . 'classes' . DIRECTORY_SEPARATOR . 'NuveiRequest.php';
        
        $this->tab = 'payments_gateways';
        
        parent::__construct();

        $this->page				= basename(__FILE__, '.php'); // ?
        $this->description		= $this->l('Accepts payments by Nuvei.');
        $this->confirmUninstall	= $this->l('Are you sure you want to delete your details?');
        
        if (!isset($this->owner) || !isset($this->details) || !isset($this->address)) {
            $this->warning = $this->l('Merchant account details must be configured before using this module.');
        }
        
//        $this->createLog(version_compare(_PS_VERSION_, '1.7.8.3', '<='), 'compare');
    }
	
    public function install()
    {
        if (!parent::install()
            || !Configuration::updateValue('SC_MERCHANT_SITE_ID', '')
            || !Configuration::updateValue('SC_MERCHANT_ID', '')
            || !Configuration::updateValue('SC_SECRET_KEY', '')
            || !$this->registerHook('payment')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('actionOrderSlipAdd')
            || !$this->registerHook('actionModuleInstallBefore')
            || !$this->registerHook('displayAdminProductsCombinationBottom')
            || !$this->registerHook('actionProductSave')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('actionAttributeCombinationDelete')
            || !$this->registerHook('actionCartUpdateQuantityBefore')
            || !$this->registerHook('displayProductButtons')
            || !$this->registerHook('displayDashboardTop')
            || !$this->registerHook('displayAdminOrderTop')
            || !$this->registerHook('actionGetAdminOrderButtons')
            || !$this->registerHook('displayAdminOrderMain')
            || !$this->installTab('AdminCatalog', 'NuveiAjax', 'SafeChargeAjax')
            || !$this->addOrderState()
        ) {
            return false;
        }
        
        # safecharge_order_data table
		$db = Db::getInstance();
		
        $sql =
            "CREATE TABLE IF NOT EXISTS `safecharge_order_data` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `order_id` int(11) unsigned NOT NULL,
                `auth_code` varchar(20) NOT NULL,
                `related_transaction_id` varchar(20) NOT NULL,
                `resp_transaction_type` varchar(20) NOT NULL,
                `payment_method` varchar(50) NOT NULL,
				`error_msg` text,
                `subscr_ids` varchar(255) NOT NULL,
                
                PRIMARY KEY (`id`),
                KEY `order_id` (`order_id`),
                UNIQUE KEY `un_order_id` (`order_id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        
        $res = $db->execute($sql);
		
		if(!$res) {
			$this->createLog(
                [
                    'res' => $res,
                    'getMsgError' => $db->getMsgError(),
                    'getNumberError' => $db->getNumberError(),
                ],
                'On Install create safecharge_order_data table response'
            );
		}
		# safecharge_order_data table END
        
        # chec if subscr_ids field into safecharge_order_data table exists
        $sql = "SELECT column_name "
            . "FROM INFORMATION_SCHEMA.columns "
            . "WHERE table_name = 'safecharge_order_data' "
                . "AND column_name = 'subscr_ids';";
        
        $res = $db->executeS($sql);
        
        if(!$res || !is_array($res) || empty($res)) {
            $sql = "ALTER TABLE safecharge_order_data ADD subscr_ids varchar(255) NOT NULL;";
            $res = $db->execute($sql);
        }
        # for the versions before 2.4.0 add subscr_ids field into safecharge_order_data table END
        
        # nuvei_product_payment_plan_details
        $sql =
            "CREATE TABLE IF NOT EXISTS `nuvei_product_payment_plan_details` (
                `id_product_attribute` int(11) unsigned NOT NULL,
                `id_product` int(11) unsigned NOT NULL,
                `plan_details` text NOT NULL,
                
                PRIMARY KEY (`id_product_attribute`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        
        $res = $db->execute($sql);
		
		if(!$res) {
			$this->createLog($res, 'On Install create nuvei_product_payment_plan_details table response');
			$this->createLog($db->getMsgError(), 'getMsgError');
			$this->createLog($db->getNumberError(), 'getNumberError');
		}
        # nuvei_product_payment_plan_details END
        
        // create tab for the admin module
        $invisible_tab = new Tab();
        
        $invisible_tab->active      = 1;
        $invisible_tab->class_name  = 'NuveiAjax';
        $invisible_tab->name        = array();
        
        foreach (Language::getLanguages(true) as $lang) {
            $invisible_tab->name[$lang['id_lang']] = 'NuveiAjax';
        }
		
		$this->createLog('Finish install');
        
        return true;
    }
    
    /**
     * 
     * @param string $parent
     * @param string $class_name
     * @param string $name
     * 
     * @return
     */
    public function installTab($parent, $class_name, $name)
    {
        // Create new admin tab
        $tab = new Tab();
//        $tab->id_parent = (int)Tab::getIdFromClassName($parent); // will show link in the Catalog menu on left
        $tab->id_parent = -1;
        $tab->name = array();
        
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $name;
        }
        
        $tab->class_name	= $class_name;
        $tab->module		= $this->name;
        $tab->active		= 1;
		
        return $tab->add();
    }
    
    public function uninstall()
    {
        if (!Configuration::deleteByName('SC_MERCHANT_SITE_ID') || 
            !Configuration::deleteByName('SC_MERCHANT_ID') || 
            !Configuration::deleteByName('SC_SECRET_KEY') || 
			!Configuration::deleteByName('SC_OS_AWAITING_PAIMENT') ||
            !parent::uninstall()
        ) {
            return false;
        }
        
        return true;
    }
    
    public function getContent()
    {
        $this->assignPaymentPlansJsonDownloadDate();
        $this->assingAjaxUrl();
        
        $this->html .= '<h2>'.$this->displayName.'</h2>';
        
        if (Tools::isSubmit('submitUpdate')) {
            $this->postValidation();
            
            Configuration::updateValue('SC_FRONTEND_NAME',          Tools::getValue('SC_FRONTEND_NAME'));
            Configuration::updateValue('SC_MERCHANT_ID',            Tools::getValue('SC_MERCHANT_ID'));
            Configuration::updateValue('SC_MERCHANT_SITE_ID',       Tools::getValue('SC_MERCHANT_SITE_ID'));
            Configuration::updateValue('SC_SECRET_KEY',             Tools::getValue('SC_SECRET_KEY'));
            Configuration::updateValue('SC_HASH_TYPE',              Tools::getValue('SC_HASH_TYPE'));
            Configuration::updateValue('SC_PAYMENT_ACTION',         Tools::getValue('SC_PAYMENT_ACTION'));
            Configuration::updateValue('SC_USE_UPOS',               Tools::getValue('SC_USE_UPOS'));
            Configuration::updateValue('SC_TEST_MODE',              Tools::getValue('SC_TEST_MODE'));
            Configuration::updateValue('SC_CREATE_LOGS',            Tools::getValue('SC_CREATE_LOGS'));
            Configuration::updateValue('NUVEI_PRESELECT_CC',        Tools::getValue('NUVEI_PRESELECT_CC'));
//            Configuration::updateValue('NUVEI_APPLE_PAY_LABEL',         Tools::getValue('NUVEI_APPLE_PAY_LABEL'));
            Configuration::updateValue('NUVEI_ADD_CHECKOUT_STEP',   Tools::getValue('NUVEI_ADD_CHECKOUT_STEP'));
            Configuration::updateValue('NUVEI_CHECKOUT_MSG',        Tools::getValue('NUVEI_CHECKOUT_MSG'));
            Configuration::updateValue('NUVEI_PRESELECT_PAYMENT',   Tools::getValue('NUVEI_PRESELECT_PAYMENT'));
            
//			Configuration::updateValue(
//				'NUVEI_SAVE_ORDER_AFTER_APM_PAYMENT',
//				Tools::getValue('NUVEI_SAVE_ORDER_AFTER_APM_PAYMENT')
//			);
            
            Configuration::updateValue('NUVEI_SDK_VERSION',             Tools::getValue('NUVEI_SDK_VERSION'));
            Configuration::updateValue('NUVEI_USE_DCC',                 Tools::getValue('NUVEI_USE_DCC'));
            Configuration::updateValue('NUVEI_BLOCK_CARDS',             Tools::getValue('NUVEI_BLOCK_CARDS'));
            Configuration::updateValue('NUVEI_PAY_BTN_TEXT',            Tools::getValue('NUVEI_PAY_BTN_TEXT'));
            Configuration::updateValue('NUVEI_AUTO_EXPAND_PMS',         Tools::getValue('NUVEI_AUTO_EXPAND_PMS'));
            Configuration::updateValue('NUVEI_AUTO_CLOSE_APM_POPUP',    Tools::getValue('NUVEI_AUTO_CLOSE_APM_POPUP'));
            Configuration::updateValue('NUVEI_SDK_LOG_LEVEL',           Tools::getValue('NUVEI_SDK_LOG_LEVEL'));
            Configuration::updateValue('NUVEI_SDK_TRANSL',              Tools::getValue('NUVEI_SDK_TRANSL'));
            
            $nuvei_block_pms = Tools::getValue('NUVEI_BLOCK_PMS');
            
            if(is_array($nuvei_block_pms) && !empty($nuvei_block_pms)) {
                $res = Configuration::updateValue('NUVEI_BLOCK_PMS', implode(',', $nuvei_block_pms));
            }
            else {
                $res = Configuration::updateValue('NUVEI_BLOCK_PMS', '');
            }
        }

        if (isset($this->_postErrors) && sizeof($this->_postErrors)) {
            foreach ($this->_postErrors as $err){
                $this->html .= '<div class="alert error">'. $err .'</div>';
            }
        }
        
        $this->smarty->assign('img_path',       '/modules/nuvei_checkout/views/img/');
		$this->smarty->assign('defaultDmnUrl',  $this->getNotifyUrl());
        
        // for the admin we need the Merchant Payment Methods
        $this->getPaymentMethods();

        return $this->display(__FILE__, 'views/templates/admin/display_forma.tpl');
    }
	
    /**
     * Just assign the date to a smarty variable
     */
    public function assignPaymentPlansJsonDownloadDate() {
        $this->smarty->assign(
            'paymentPlanJsonDlDate',
            file_exists(_PS_ROOT_DIR_ . '/var/logs/' . $this->paymentPlanJson) ? 
                date ("Y-m-d H:i:s.", filemtime(_PS_ROOT_DIR_ . '/var/logs/' . $this->paymentPlanJson)) : ''
        );
    }
    
    /**
     * Just assign the module ajax URL to a smarty variable
     */
    public function assingAjaxUrl()
    {
        $this->smarty->assign(
            'ajaxUrl',
            $this->context->link
                ->getAdminLink("NuveiAjax") . '&security_key=' . $this->getModuleSecurityKey()
        );
    }
    
	public function getNotifyUrl()
    {
        return $this->context->link
            ->getModuleLink('nuvei_checkout', 'payment', array(
                'prestaShopAction'  => 'processDmn',
            ));
	}
	
    public function hookPaymentOptions($params)
    {
//        $this->createLog(
////            $params,
//            'hookPaymentOptions'
//        );
        
		if($this->isModuleActive() !== true){
            $this->createLog('hookPaymentOptions isPayment not true.');
            return false;
        }
		
		if(empty($params['cart']->delivery_option)) {
            $this->createLog(null, 'hookPaymentOptions - the Cart is empty.');
			return [];
		}
        
        try {
            $newOption      = new PaymentOption();
            $option_text    = Configuration::get('NUVEI_CHECKOUT_MSG');

            if(!$option_text || empty($option_text)) {
                $option_text = $this->trans('Pay by Nuvei', [], 'Modules.nuvei');
            }

            $newOption
                ->setModuleName($this->name)
                ->setCallToActionText($option_text)
                ->setLogo(_MODULE_DIR_ . 'nuvei_checkout/views/img/nuvei-v2.gif');
            
            $this->context->smarty->assign('nuveiModuleName', $this->name);
		
            // no second step
            if(Configuration::get('NUVEI_ADD_CHECKOUT_STEP') == 0) {
                if(!$this->assignOrderData()) {
                    return [];
                }
                
                $this->context->smarty->assign('formAction', $this->context->link
                    ->getModuleLink('nuvei_checkout', 'payment'));
                $this->context->smarty->assign('nuveiToken', $this->getModuleSecurityKey());
                
                $newOption->setAdditionalInformation($this->context->smarty
                    ->fetch('module:nuvei_checkout/views/templates/front/checkout.tpl'));
            }
            else { // add second step
                $newOption->setAction($this->context->link->getModuleLink(
                    $this->name,
                    'addStep', 
                    [
                        'cid' => hash(Configuration::get('SC_HASH_TYPE'), $params['cart']->id),
                        'csk' => hash(Configuration::get('SC_HASH_TYPE'), $params['cart']->secure_key),
                    ]
                ));
            }
        }
        catch(Exception $e) {
            $this->createLog(
                [
                    'message'   => $e->getMessage(),
                    'place'     => $e->getFile() . ' ' . $e->getLine()
                ],
                'hookPaymentOptions exception',
                'CRITICAL'
            );
        }
        
        return [$newOption];
    }
    
    /**
     * Use this hook to display error messages if Nuvei data is missing
     * for a Nuvei Order.
     * 
     * @param array $params Contain id_order.
     */
    public function hookDisplayAdminOrderTop($params)
    {
//        $this->createLog('hookDisplayAdminOrderTop');
        
        $smarty = $this->context->smarty;
        
        if($this->isModuleActive() !== true){
            $this->createLog('hookDisplayAdminOrderTop isModuleActive not true.');
            return false;
        }
        
        if(empty($this->context->cookie->nuvei_order_data)) {
            $smarty->assign('nuvei_error', $this->l('Missing specific Nuvei data for this Order.'));
            
            return $this->display(__FILE__, 'views/templates/admin/order_top_msg.tpl');
        }
        
        $sc_data = unserialize($this->context->cookie->nuvei_order_data);
        
        if(!empty($sc_data['error_msg'])) {
            $smarty->assign('nuvei_error', $this->l($sc_data['error_msg']));
            
            return $this->display(__FILE__, 'views/templates/admin/order_top_msg.tpl');
        }
        
        if(empty($sc_data['resp_transaction_type'])) {
            $smarty->assign('nuvei_error', $this->l('Missing Order Transaction Type.'));
            
            return $this->display(__FILE__, 'views/templates/admin/order_top_msg.tpl');
        }
        // TODO do we need this check...?
        if(empty($sc_data['related_transaction_id'])) {
            $smarty->assign('nuvei_error', $this->l('Missing Order Transaction ID.'));
            
            return $this->display(__FILE__, 'views/templates/admin/order_top_msg.tpl');
        }
    }
	
    /**
     * Hook to display SC specific order actions
     * 
     * @param array $params Contain id_order.
     * @return template
     */
    public function hookActionGetAdminOrderButtons($params)
    {
        $this->createLog('hookActionGetAdminOrderButtons');
        
        // reset the cookie
        if(!empty($this->context->cookie->nuvei_order_data)) {
            $this->context->cookie->__set('nuvei_order_data', '');
        }
        
        if($this->isModuleActive() !== true){
            $this->createLog('hookActionGetAdminOrderButtons isModuleActive not true.');
            return false;
        }
        
        $order_id   = (int) $params['id_order'];
        $order      = new Order($order_id);
        $payment    = strtolower($order->payment);
        
        // not Nuvei order
		if(strpos($payment, 'safecharge') === false
			&& strpos($payment, 'nuvei') === false
			&& strpos($payment, 'nuvei payments') === false
		) {
            $this->createLog($payment, 'This is not Nuvei Payment.');
			return false;
		}
        
        $sc_data = Db::getInstance()->getRow('SELECT * FROM safecharge_order_data '
            . 'WHERE order_id = ' . $order_id);
        
        if(empty($sc_data)) {
            $this->createLog('Missing safecharge_order_data for order ' . $order_id);
            return;
        }
        
        /** @var \PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButtonsCollection $bar */
        $bar                        = $params['actions_bar_buttons_collection'];
        $sc_data['order_state']     = $order->current_state;
        $sc_data['order_payment']   = $payment;
        
        // put the data into cookie to use it in next hooks
        $this->context->cookie->__set('nuvei_order_data', serialize($sc_data));
		
        # Settle button
        if(!empty($sc_data['resp_transaction_type']) 
            && "Auth" == $sc_data['resp_transaction_type']
            && !empty($sc_data['order_state'])
            && Configuration::get('SC_OS_AWAITING_PAIMENT') == $sc_data['order_state']
        ) {
            $bar->add(
                new \PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton(
                    'btn btn-action',
                    [
                        'href' => '#',
                        'type' => "button",
                        'id' => "sc_settle_btn",
                        'onclick' => "scOrderAction('settle', {$order_id})",
                    ],
                    'Settle'
                )
            );
        }
        # /Settle button
        
        # Void button
		$enable_void = false;
        
		if (!empty($sc_data['payment_method']) && 'cc_card' == $sc_data['payment_method']) {
			if(Configuration::get('PS_OS_PAYMENT') == $sc_data['order_state']
				&& in_array($sc_data['resp_transaction_type'], array('Sale', 'Settle'))
			) {
				$enable_void = true;
			}
			elseif(Configuration::get('SC_OS_AWAITING_PAIMENT') == $sc_data['order_state']
				&& 'Auth' == $sc_data['resp_transaction_type']
			) {
				$enable_void = true;
			}
			elseif(Configuration::get('PS_OS_ERROR') == $sc_data['order_state']
				&& in_array($sc_data['resp_transaction_type'], array('Sale', 'Settle'))
			) {
				$enable_void = true;
			}
		}
        
        // add Void button
        if($enable_void) {
            $bar->add(
                new \PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton(
                    'btn btn-action',
                    [
                        'href' => '#',
                        'type' => "button",
                        'id' => "sc_void_btn",
                        'onclick' => "scOrderAction('void', {$order_id})",
                    ],
                    'Void'
                )
            );
        }
        # /Void button
    }
    
    /**
     * Print Notes and other Nuvei information.
     * 
     * @return template
     */
    public function hookDisplayAdminOrderMain($params)
    {
        $this->createLog('hookDisplayAdminOrderMain');
        
        if($this->isModuleActive() !== true){
            $this->createLog('hookDisplayAdminOrderLeft isPayment not true.');
            return false;
        }
        
		$order_data	= new Order(intval($params['id_order']));
		$payment	= strtolower($order_data->payment);

		// not SC order
		if(!empty($order_data->payment)
			&& strpos($payment, 'safecharge') === false
			&& strpos($payment, 'nuvei') === false
			&& strpos($payment, 'nuvei payment') === false
		) {
			return false;
		}
        
        $nuvei_order_data   = [];
        $smarty             = $this->context->smarty;
        $messages           = MessageCore::getMessagesByOrderId(Tools::getValue('id_order'), true);
        
        
        if(!empty($this->context->cookie->nuvei_order_data)) {
            $nuvei_order_data = unserialize($this->context->cookie->nuvei_order_data);
        }
        
        $hide_refund_btn = 0;
        
        if(!in_array(
                $nuvei_order_data['order_state'],
                [Configuration::get('PS_OS_PAYMENT'), Configuration::get('PS_OS_REFUND')]
            )
            || (
                isset($nuvei_order_data['payment_method'])
                && !in_array($nuvei_order_data['payment_method'], ['cc_card', 'apmgw_expresscheckout'])
            )
        ) {
            $hide_refund_btn = 1;
        }
        
        $smarty->assign('nuvei_hide_refund_btn',    $hide_refund_btn);
        $smarty->assign('nuvei_messages',           $messages);
        $smarty->assign('ordersListURL',            Context::getContext()->link->getAdminLink('AdminOrders', true));
        $this->assingAjaxUrl();
        
        return $this->display(__FILE__, 'views/templates/admin/order_notes.tpl');
    }

    /**
     * This hook is executed after the Slip record is created.
     * We use it to request Refund to Nuvei
     * 
     * @param array $params Contains the Order the Cart and info about the Refund.
     */
    public function hookActionOrderSlipAdd($params)
    {
        $this->createLog($params, 'hookActionOrderSlipAdd');
        
        if(!$this->isModuleActive()) {
            $this->createLog('hookActionOrderSlipAdd - the module is not active.');
            return false;
        }
        
		if($params['order']->module != $this->name) {
			$this->createLog($params, 'hookActionOrderSlipAdd - the order does not belongs to Nuvei.');
			return false;
		}
        
        if(empty($params['productList'])) {
            $this->createLog($params, 'hookActionOrderSlipAdd - missing the product list.');
            return false;
        }
		
        $request_amoutn = 0;
        
        foreach ($params['productList'] as $prod_data) {
            $request_amoutn += (float) ($prod_data['amount']);
        }

        // add the Shipping money
        if(!empty($_REQUEST['cancel_product']['shipping_amount'])) {
            $request_amoutn += (float) $_REQUEST['cancel_product']['shipping_amount'];
        }

        $request_amoutn = number_format($request_amoutn, 2, '.', '');
        $order_id       = (int) $params['order']->id;

        // save order message
        $message            = new MessageCore();
        $message->id_order  = $order_id;
        $message->private   = true;

        $order_info = new Order($order_id);
        $currency   = new Currency($order_info->id_currency);

        $row = Db::getInstance()->getRow(
            "SELECT id_order_slip FROM " . _DB_PREFIX_ . "order_slip "
            . "WHERE id_order = {$order_id} AND amount = {$request_amoutn} "
            . "ORDER BY id_order_slip DESC");

        $last_slip_id = $row['id_order_slip'];
        
        if(empty($last_slip_id)) {
            if(!empty($_REQUEST['cancel_product']['_token'])) {
                $last_slip_id = $_REQUEST['cancel_product']['_token'];
            }
            else {
                $last_slip_id = date('YmdHis') . '_' . uniqid();
            }
        }

        $sc_order_info = Db::getInstance()->getRow(
            "SELECT * FROM safecharge_order_data WHERE order_id = {$order_id}");

        $notify_url     = $this->getNotifyUrl();
        $test_mode      = Configuration::get('SC_TEST_MODE');

        $ref_parameters = array(
            'clientRequestId'       => $last_slip_id,
            'clientUniqueId'        => $order_id,
            'amount'                => (string) $request_amoutn,
            'currency'              => $currency->iso_code,
            'relatedTransactionId'  => $sc_order_info['related_transaction_id'], // GW Transaction ID
            'authCode'              => $sc_order_info['auth_code'],
            'urlDetails'            => array('notificationUrl' => $notify_url),
        );

        $json_arr = $this->callRestApi(
            'refundTransaction', 
            $ref_parameters,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'clientUniqueId', 'amount', 'currency', 'relatedTransactionId', 'authCode', 'url', 'timeStamp')
        );
        
        if(!$json_arr) {
            $this->context->controller->errors[] = $this->l('Empty request response.');
            return false;
        }
        
        if(!is_array($json_arr)) {
            $this->context->controller->errors[] = $this->l('Invalid API response.');
            return false;
        }
        
        // APPROVED
        if(!empty($json_arr['transactionStatus']) && 'APPROVED' == $json_arr['transactionStatus']) {
            return true;
        }
        
        // in case we have message but without status
        if(!isset($json_arr['status']) && isset($json_arr['msg'])) {
            // save response message in the History
            $msg = $this->l('Request for Refund #') . $last_slip_id . $this->l(' problem: ') . $json_arr['msg'];
            $this->context->controller->errors[] = $msg;
            
            $message->message = $msg;
            $message->add();
            
            return false;
        }
        
        
//        $cpanel_url = ($test_mode == 'yes' ? 'sandbox' : 'cpanel') . '.safecharge.com';
//        
//        $msg = '';
//        $error_note = $this->l('Request for Refund #') . $last_slip_id 
//			. $this->l(' fail, if you want login into') . ' <i>' . $cpanel_url . '</i> '
//            . $this->l('and refund Transaction ID ') . $sc_order_info['related_transaction_id'];
        
//        if($json_arr === false) {
//            $msg = $this->l('The REST API retun false. ') . $error_note;
//            $this->context->controller->errors[] = $msg;
//
//            $message->message = $msg;
//            $message->add();
//            
//            return false;
//        }
        
        
        
        
        
        // the status of the request is ERROR
//        if(@$json_arr['status'] == 'ERROR') {
            $msg = $this->l('Request ERROR.');
            
            if(!empty($json_arr['reason'])) {
                $msg .= ' - ' . $json_arr['reason'] . '. ';
            }
            else {
                $msg .= '. ';
            }
                    
//            $msg .= $error_note;
            $this->context->controller->errors[] = $msg;

            $message->message = $msg;
            $message->add();
            
            return false;
//        }
        
        // if request is success, we will wait for DMN
//        $msg = $this->l('Request for Refund #') . $last_slip_id . $this->l(', was sent. Please, wait for DMN!');
//        $this->context->controller->success[] = $msg;
//        
//        $message->message = $msg;
//        $message->add();
        
        return true;
    }

    public function hookDisplayAdminProductsCombinationBottom($params)
    {
        $this->createLog('hookDisplayAdminProductsCombinationBottom');
        
        $product        = new Product((int) $params['id_product']);
        $id_lang        = Context::getContext()->language->id;
        $combinations   = $product->getAttributeCombinations((int) $id_lang, true);
        $group_ids_arr  = array();
        $comb_ids_arr   = array();
        
        ob_start();
        
        // get Nuvei Payment Plan group IDs
        $group_ids_arr = $this->getNuvePaymentPlanGroupIds();
        
        foreach($combinations as $data) {
            if(in_array($data['id_attribute_group'], $group_ids_arr)
                && !in_array($data['id_attribute_group'], $comb_ids_arr)
            ) {
                $comb_ids_arr[] = $data['id_product_attribute'];
            }
        }
        
        // load Nuvei Payment Plans data
        $npp_data   = '';
        $file       = _PS_ROOT_DIR_ . '/var/logs/' . $this->paymentPlanJson;
        
        
        if(is_readable($file)) {
            $npp_data = stripslashes(file_get_contents($file));
        }
        
        // load the Payment details for the products
        $prod_pans              = array();
        $id_product_attribute   = (int) $params['id_product_attribute'];
        
        if(isset($params['id_product_attribute'], $params['id_product'])) {
            $sql = "SELECT plan_details "
                . "FROM nuvei_product_payment_plan_details "
                . "WHERE id_product_attribute = " . $id_product_attribute;

            $res = Db::getInstance()->getRow($sql);
            
            if($res && !empty($res['plan_details'])) {
                $prod_pans = $res['plan_details'];
                
                $this->createLog($id_product_attribute);
                $this->createLog($prod_pans);
            }
        }
        
        require_once dirname(__FILE__) . '/views/js/admin/nuveiProductCombData.php';
        
        return ob_get_flush();
    }
    
    /**
     * Admin hook in Product edit page.
     * Save Payment plan details for the product if any.
     * 
     * @param array $params
     * @return
     */
    public function hookActionProductSave($params)
    {
        $this->createLog(
//            $params, 
            'hookActionProductSave post'
        ); 
        
        if(empty($_POST['nuvei_payment_plan_attr']) || !is_array($_POST['nuvei_payment_plan_attr'])) {
            return;
        }
        
        foreach($_POST['nuvei_payment_plan_attr'] as $comb => $data) {
            $plan_details = array(
                'planId'            => (int) $data['plan_id'],
                'recurringAmount'   => (string) round((float) $data['rec_amount'], 2),
                
                'recurringPeriod'   => array(
                    filter_var($data['rec_unit'], FILTER_SANITIZE_STRING) => (int) $data['rec_period']
                ),
                
                'startAfter'        => array(
                    filter_var($data['rec_trial_unit'], FILTER_SANITIZE_STRING) => (int) $data['trial_period']
                ),
                        
                'endAfter'          => array(
                    filter_var($data['rec_end_after_unit'], FILTER_SANITIZE_STRING) => (int) $data['rec_end_after_period']
                ),
            );
                    
            $this->createLog($plan_details, 'hookActionProductSave $plan_details');
            
            $sql = "INSERT INTO nuvei_product_payment_plan_details "
                . "(id_product_attribute, id_product, plan_details ) "
                . "VALUES (". (int) $comb .", ". (int) $_POST['id_product'] .", '". json_encode($plan_details) ."') "
                . "ON DUPLICATE KEY UPDATE "
                    . "id_product = " . (int) $_POST['id_product'] . ", "
                    . "plan_details = '" . json_encode($plan_details) . "';";
            
            $res = Db::getInstance()->execute($sql);
            
            if(!$res) {
                $this->createLog(
                    Db::getInstance()->getMsgError(),
                    'hookActionProductSave Error when try to insert the payment plan data for a product.'
                );
                
                $this->createLog($_POST, 'hookActionProductSave post');
            }
        }
    }
    
    /**
     * Admin hook.
     * Add a JS file or print the script in the Header.
     */
    public function hookDisplayBackOfficeHeader()
    {
        $this->createLog('hookDisplayBackOfficeHeader');
        
        $code = '';
        
        // insert this script only on Products page
        if(isset($_SERVER['PATH_INFO'])) {
            $path_info = filter_var($_SERVER['PATH_INFO']);
            
            if(strpos($path_info, 'sell/catalog/products') >= 0) {
                $this->context->controller->addJS(dirname(__FILE__) . '/views/js/admin/nuveiProductScript.js');
            }
        }
        
        // try to add this JS only on Orders List page
        if(Tools::getValue('controller') == 'AdminOrders' && !Tools::getValue('id_order')) {
            ob_start();
            
            $nuvei_ajax_url = $this->context->link
                ->getAdminLink("NuveiAjax") . '&security_key=' . $this->getModuleSecurityKey();
        
            include dirname(__FILE__) . '/views/js/admin/nuveiOrdersList.php';

            $code .= ob_get_contents();
            ob_end_clean();
        }
        
        return $code;
    }
    
    /**
     * Admin hook in edit product page.
     * Delete Payment plan record for a product combination.
     * 
     * @param array $params
     * @return
     */
    public function hookActionAttributeCombinationDelete($params)
    {
        $this->createLog('hookActionAttributeCombinationDelete');
        
        if(empty($params['id_product_attribute'])) {
            return;
        }
        
        $id_product_attribute = (int) $params['id_product_attribute'];
        
        $sql = "DELETE FROM `nuvei_product_payment_plan_details` "
            . "WHERE id_product_attribute = " . $id_product_attribute;
        
        $res = Db::getInstance()->execute($sql);
            
        if(!$res) {
            $this->createLog(
                Db::getInstance()->getMsgError(),
                'hookActionAttributeCombinationDelete Error when try to delete a record.'
            );

            $this->createLog($params, 'hookActionAttributeCombinationDelete $params');
        }
    }
    
    /**
     * Store hook in Product page.
     * Keep the product with a Payment plan be alone in the Cart.
     * 
     * @param type $params
     * @return type
     */
    public function hookActionCartUpdateQuantityBefore($params)
    {
//        $this->createLog(
////            $params['quantity'],
//            'hookActionCartUpdateQuantityBefore()'
//        );
        
        try {
            $products                   = $params['cart']->getProducts(); // array
            $group_ids_arr              = $this->getNuvePaymentPlanGroupIds(); // get Nuvei Payment Plan group IDs
//            $id_lang        = Context::getContext()->language->id;
            $id_lang                    = $this->context->language->id;
            $is_user_logged             = (bool)$this->context->customer->isLogged();
            $combinations               = $params['product']->getAttributeCombinations((int) $id_lang);
            
//            Configuration::get('NUVEI_ENABLE_GUEST_REBILLING')
            
            // if current combination is part of Nuvei Payment Plan group 
            // and the user is not logged in and Guests rebilling - do not add the product
            foreach($combinations as $data) {
                if($data['id_product_attribute'] == $params['id_product_attribute']
                    && in_array($data['id_attribute_group'], $group_ids_arr)
                    && !$is_user_logged
//                    && Configuration::get('NUVEI_ENABLE_GUEST_REBILLING') == 0
                ) {
                    $params['product']->available_for_order = false;
                    return false;
                }
            }
            
            # if the Cart is empty just add the product
            if(empty($products)) {
                $this->createLog('hookActionCartUpdateQuantityBefore() - The Cart is empty.');
                return;
            }
            
            # if the Cart is not empty
            // 2 if the incoming product does not have Nuvei Payment Plan - check the Cart
//            if(count($products) > 1) {
//                $this->createLog('hookActionCartUpdateQuantityBefore() - There are more tha one products in the Cart.');
//                return;
//            }
            
            $prod_with_plan = $this->getProdsWithPlansFromCart($params, $group_ids_arr);
            
            if(!empty($prod_with_plan)) {
                $params['product']->available_for_order = false;
                return false;
            }
            // 2 if the incoming product does not have Nuvei Payment Plan - check the Cart END
        }
        catch (Exception $e) {
            $this->createLog($e->getMessage(), 'hookActionCartUpdateQuantityBefore exception');
        }
    }
    
    /**
     * Store hook in Product page.
     * Add table with Nuvei Payment Plan details under "Add to Cart" button
     * The new name of the hook is "displayProductAdditionalInfo"
     * 
     * @param type $params
     * @return void|html
     */
    public function hookDisplayProductButtons ($params)
    {
//        $this->createLog('hookDisplayProductButtons()');
        
        if(empty($params['product']['id']) || empty($params['product']['attributes'])) {
            $this->createLog('hookDisplayProductButtons() - missing mandatory product data.');
            return;
        }
        
        $is_cart_empty      = true;
        $disable_add_btn    = false; // use it in case there is product with a Plan in the Cart
        $is_user_logged     = (bool)$this->context->customer->isLogged();
        
        if(isset($params['cart']) && is_object($params['cart'])) {
            try {
                $products = $params['cart']->getProducts(); // the cart products
                
                // cart is not empty
                if(is_array($products) && !empty($products)) {
                    $is_cart_empty  = false;
                    
                    foreach($products as $product) {
                        $sql = "SELECT plan_details "
                        . "FROM nuvei_product_payment_plan_details "
                        . "WHERE id_product = " . (int) $product['id_product'] . " "
                            . "AND id_product_attribute = '". (int) $product['id_product_attribute'] ."' ";

                        $res = Db::getInstance()->executeS($sql);
                        
                        if($res) {
                            $disable_add_btn = true;
                            break;
                        }
                    }
                }
            } catch (Exception $ex) {
                $this->createLog($ex->getMessage(), 'hookDisplayProductButtons Exception');
            }
        }
            
        // get nuvei payment plan details for the prduct
        $sql = "SELECT npppd.*, "
                . "pac.id_attribute, "
                . "agl.id_attribute_group "
            . "FROM nuvei_product_payment_plan_details AS npppd "
            
            . "LEFT JOIN " . _DB_PREFIX_ . "product_attribute_combination AS pac "
            . "ON npppd.id_product_attribute = pac.id_product_attribute "
            
            . "LEFT JOIN " . _DB_PREFIX_ . "attribute AS a "
            . "ON a.id_attribute = pac.id_attribute "
            
            . "LEFT JOIN " . _DB_PREFIX_ . "attribute_group_lang AS agl "
            . "ON agl.id_attribute_group = a.id_attribute_group "
            
            . "WHERE npppd.id_product = " . (int) $params['product']['id'] . " "
                . "AND agl.name = '". $this->paymentPlanGroup ."' ";
        
        $res = Db::getInstance()->executeS($sql);
        
        if(!$res) {
            // in this case hide "Add to Cart" button and show our message
            if($disable_add_btn) {
                ob_start();
                
                $data['error_msg'] = $this->l('You can not add this product, to a Product with a Payment Plan.');
                include dirname(__FILE__) . '/views/templates/front/hide_add_to_cart_btn.php';
                
                return ob_end_flush();
            }
            
            return;
        }
//        elseif(Configuration::get('NUVEI_ENABLE_GUEST_REBILLING') == 0 && !$is_user_logged) {
        elseif(!$is_user_logged) {
            ob_start();
                
            $data['error_msg'] = $this->l('Guests can not add Products with a Payment Plan.');
            include dirname(__FILE__) . '/views/templates/front/hide_add_to_cart_btn.php';

            return ob_end_flush();
        }
        
        $product_plans  = array();
        $gr_ids         = array();
        
        foreach($res as $data) {
            $data['plan_details'] = json_decode($data['plan_details'], true);
            $product_plans[$data['id_attribute']] = $data;
            
            if(!in_array($data['id_attribute_group'], $gr_ids)) {
                $gr_ids[] = $data['id_attribute_group'];
            }
        }
        
        if(!empty($gr_ids)) {
            $gr_ids = max($gr_ids);
        }
        
        ob_start();
        
        $data = array(
            'day'               => $this->l('day'),
            'days'              => $this->l('days'),
            'month'             => $this->l('month'),
            'months'            => $this->l('months'),
            'year'              => $this->l('year'),
            'years'             => $this->l('years'),
            'error_msg'         => $this->l('You can not add a Prduct with a Payment Plan to the Cart.'),
            'tab_title'         => $this->l('Nuvei Plan Details'),
            'Plan_duration'     => $this->l('Plan duration'),
            'Charge_every'      => $this->l('Charge every'),
            'Recurring_amount'  => $this->l('Recurring amount'),
            'Trial_period'      => $this->l('Trial period'),
            'product_plans'     => $product_plans,
            'gr_ids'            => $gr_ids,
            'is_cart_empty'     => $is_cart_empty,
            'disable_add_btn'   => $disable_add_btn,
        );
        include dirname(__FILE__) . '/views/templates/front/product_payment_plan_details.php';
        
        ob_get_flush();
    }
    
    /**
     * In the admin check for new version of the plugin
     * 
     * @param array $params
     */
    public function hookDisplayDashboardTop($params)
    {
//        $this->createLog(
//            $params, 
//            'displayDashboardToolbarTopMenu'
//        );
        
        // path is different fore each plugin
        $logs_path              = _PS_ROOT_DIR_ . '/var/logs/';
        $version_file           = $logs_path . 'nuvei-latest-version.json';
        $allowed_controllers    = array('AdminDashboard', 'AdminOrders');
        $git_version            = 0;
        $date_check             = 0;
        $plug_curr_ver          = str_replace('.', '', trim($this->version));
        
        if(!in_array(Tools::getValue('controller'), $allowed_controllers)) {
            return;
        }
        
        // if version file does not exists creat it
        if(!file_exists($version_file)) {
            $ver_str        = $this->getPluginVerFromGit();
            $git_version    = str_replace('.', '', trim($ver_str));
            $git_version    = str_replace('#', '', trim($git_version));
            $date_check     = gmdate('Y-m-d H:i:s', time());
            
            $array = array(
                'date'  => $date_check,
                'git_v' => (int) trim($git_version),
            );
            
            file_put_contents($version_file, json_encode($array));
        }
        
        // read file if need to
        if (0 == $date_check || 0 == $git_version) {
            $version_file_data = json_decode(file_get_contents($version_file), true);

            if (!empty($version_file_data['date'])) {
                $date_check = $version_file_data['date'];
            }
            if (!empty($version_file_data['git_v'])) {
                $git_version = $version_file_data['git_v'];
            }
        }

        // check file date and get new file if current one is more than a week old
        if (strtotime('-1 Week') > strtotime($date_check)) {
            $ver_str        = $this->getPluginVerFromGit();
            $git_version    = str_replace('.', '', trim($ver_str));
            $git_version    = str_replace('#', '', trim($git_version));
            $date_check     = gmdate('Y-m-d H:i:s', time());
        }
        
        if($git_version > $plug_curr_ver) {
            $this->createLog(
                [
                    '$git_version'      => $git_version,
                    '$plug_curr_ver'    => $plug_curr_ver,
                ],
                'Check plugin versions'
            );
            
            echo
                '<div class="alert alert-warning" style="margin: 16px;">
                    <button type="button" class="close" data-dismiss="alert">×</button>
                    There is a new version of Nuvei Plugin available. <a href="https://github.com/SafeChargeInternational/safecharge_prestashop/blob/master/CHANGELOG.md" target="_blank">View version details.</a>
                </div>';
        }
    }
    
    /**
     * Function isPayment
     * Actually here we check if the SC plugin is active and configured
     * 
     * @return boolean - the result
     */
    public function isModuleActive()
    {
        if (!$this->active) {
            return false;
        }
        
        if (!Configuration::get('SC_MERCHANT_SITE_ID')) {
            $this->createLog('Error: (invalid or undefined Merchant Site ID)');
            return $this->displayName . $this->l(' Error: (invalid or undefined Merchant Site ID)');
        }
          
        if (!Configuration::get('SC_MERCHANT_ID')) {
            $this->createLog('Error: (invalid or undefined Merchant ID)');
            return $this->displayName . $this->l(' Error: (invalid or undefined Merchant ID)');
        }
        
        if (!Configuration::get('SC_SECRET_KEY')) {
            $this->createLog('Error: (invalid or undefined secure key)');
            return $this->displayName . $this->l(' Error: (invalid or undefined Secure Key)');
        }
          
        return true;
    }
    
    /**
     * Function checkCurrency
     * Check if our payment method is available for order currency
     * 
     * @param Cart $cart - cart object
     * @return boolean
     */
    public function checkCurrency($cart)
	{
		$currency_order = new Currency($cart->id_currency);
		$currencies_module = $this->getCurrency($cart->id_currency);

		if (is_array($currencies_module)) {
			foreach ($currencies_module as $currency_module) {
				if ($currency_order->id == $currency_module['id_currency']) {
					return true;
                }
            }
        }
        
		return false;
	}
	
	/**
	 * @param mixed $data The data to save in the log.
     * @param string $message Record message.
     * @param string $log_level The Log level.
     * @param string $span_id Process unique ID.
	 */
	public function createLog($data, $message = '', $log_level = 'INFO', $span_id = '')
    {
        $logs_path = _PS_ROOT_DIR_ . '/var/logs/';
		
		if(!is_dir($logs_path) || Configuration::get('SC_CREATE_LOGS') == 'no') {
			return;
		}
        
        $beauty_log = ('yes' == Configuration::get('SC_TEST_MODE')) ? true : false;
        $tab        = '    '; // 4 spaces
        
        # prepare log parts
        $utimestamp     = microtime(true);
        $timestamp      = floor($utimestamp);
        $milliseconds   = round(($utimestamp - $timestamp) * 1000000);
        $record_time    = date('Y-m-d') . 'T' . date('H:i:s') . '.' . $milliseconds . date('P');
        
        if(null == $this->trace_id) {
            $this->trace_id = bin2hex(random_bytes(16));
        }
        
        if(!empty($span_id)) {
            $span_id .= $tab;
        }
        
        $machine_name       = '';
        $service_name       = 'Nuvei Prestashop Checkout ' . $this->version . '|';
        $source_file_name   = '';
        $member_name        = '';
        $source_line_number = '';
        $backtrace          = debug_backtrace();
        
        if(!empty($backtrace)) {
            if(!empty($backtrace[0]['file'])) {
                $file_path_arr  = explode(DIRECTORY_SEPARATOR, $backtrace[0]['file']);
                
                if(!empty($file_path_arr)) {
                    $source_file_name = end($file_path_arr) . '|';
                }
            }
            
            if(!empty($backtrace[0]['line'])) {
                $source_line_number = $backtrace[0]['line'] . $tab;
            }
        }
        
        if(!empty($message)) {
            $message .= $tab;
        }
        
        if(is_array($data)) {
            // paymentMethods can be very big array
            if(!empty($data['paymentMethods'])) {
                $exception = json_encode($data);
            }
            else {
                $exception = $beauty_log ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data);
            }
        }
        elseif(is_object($data)) {
            $data_tmp   = print_r($data, true);
            $exception  = $beauty_log 
                ? json_encode($data_tmp, JSON_PRETTY_PRINT) : json_encode($data_tmp);
        }
        elseif(is_bool($data)) {
            $exception = $data ? 'true' : 'false';
        }
        else {
            $exception = $data;
        }
        # prepare log parts END
        
        // Content of the log string:
        $string = $record_time      // timestamp
            . $tab                  // tab
            . $log_level            // level
            . $tab                  // tab
            . $this->trace_id       // TraceId
            . $tab                  // tab
            . $span_id              // SpanId, if not empty it will include $tab
//            . $parent_id            // ParentId, if not empty it will include $tab
            . $machine_name         // MachineName if not empty it will include a "|"
            . $service_name         // ServiceName if not empty it will include a "|"
            // TreadId
            . $source_file_name     // SourceFileName if not empty it will include a "|"
            . $member_name          // MemberName if not empty it will include a "|"
            . $source_line_number   // SourceLineName if not empty it will include $tab
            // RequestPath
            // RequestId
            . $message
            . $exception            // the exception, in our case - data to print
        ;
        
        $string     .= "\r\n\r\n";
        $file_name  = 'nuvei-' . date('Y-m-d', time());
        
		file_put_contents(
			$logs_path . $file_name . '.log',
			$string,
			FILE_APPEND
		);
        
//		$d		= $data;
//		$string	= '';
//
//		if(is_array($data)) {
//			// do not log accounts if on prod
//			if(Configuration::get('SC_TEST_MODE') == 'no') {
//				if(!empty($data['userAccountDetails']) && is_array($data['userAccountDetails'])) {
//					$data['userAccountDetails'] = 'userAccountDetails details';
//				}
//				if(!empty($data['userPaymentOption']) && is_array($data['userPaymentOption'])) {
//					$data['userPaymentOption'] = 'userPaymentOption details';
//				}
//				if(!empty($data['paymentOption']) && is_array($data['paymentOption'])) {
//					$data['paymentOption'] = 'paymentOption details';
//				}
//			}
//			// do not log accounts if on prod
//			
//			if(!empty($data['paymentMethods']) && is_array($data['paymentMethods'])) {
//				$data['paymentMethods'] = json_encode($data['paymentMethods']);
//			}
//
//			$d = Configuration::get('SC_TEST_MODE') == 'yes' ? print_r($data, true) : json_encode($data);
//		}
//		elseif(is_object($data)) {
//			$d = Configuration::get('SC_TEST_MODE') == 'yes' ? print_r($data, true) : json_encode($data);
//		}
//		elseif(is_bool($data)) {
//			$d = $data ? 'true' : 'false';
//		}
//
//		$string .= '[v.' . $this->version . '] | ';
//
//		if(!empty($message)) {
//			if(is_string($message)) {
//				$string .= $message;
//			}
//			else {
//				$string .= "\r\n" . (Configuration::get('SC_TEST_MODE') == 'yes'
//					? json_encode($message, JSON_PRETTY_PRINT) : json_encode($message));
//			}
//			
//			$string .= "\r\n";
//		}
//
//		$string .= $d . "\r\n\r\n";
//
//		try {
//			file_put_contents(
//				$logs_path . 'Nuvei-' . date('Y-m-d', time()) . '.log',
//				date('H:i:s', time()) . ': ' . $string, FILE_APPEND
//			);
//		}
//		catch (Exception $exc) {}
	}
	
	/**
	 * Create a Rest Api call and log input and output parameters
     * 
     * Explain 'merchantDetails'
     *      'merchantDetails'	=> array(
     *          'customField1' => string - cart secure_key,
     *          'customField2' => string - Prestashop plugin version,
     *          'customField3' => json string - items info,
     *          'customField4' => string - time,
     *          'customField5' => json string - subscription data
     *      ),
	 * 
	 * @param string $method
	 * @param array $params
	 * @param array $checsum_params - array with the keys for the checksum, the order is important
	 * 
	 * @return mixed $resp
	 */
	public function callRestApi($method, array $params, array $checsum_params)
    {
		$url = $this->getEndPointBase() . $method . '.do';
		
		if(empty($method)) {
			$this->createLog($url, 'callRestApi() Error - the passed method can not be empty.');
			return false;
		}
		
		if(!filter_var($url, FILTER_VALIDATE_URL)) {
			$this->createLog($url, 'callRestApi() Error - the passed url is not valid.');
			return false;
		}
		
		if(!is_array($params)) {
			$this->createLog($params, 'callRestApi() Error - the passed params parameter is not array ot object.');
			return false;
		}
        
        if(empty(Configuration::get('SC_HASH_TYPE'))) {
            return false;
        }
        
        $time               = date('YmdHis', time());
        $notificationUrl    = $this->getNotifyUrl();
        
        // set here some of the mandatory parameters
        $params = array_merge(
            array(
                'merchantId'        => Configuration::get('SC_MERCHANT_ID'),
                'merchantSiteId'    => Configuration::get('SC_MERCHANT_SITE_ID'),
                'clientRequestId'   => $time . '_' . uniqid(),
                
                'timeStamp'         => $time,
                'deviceDetails'     => NuveiRequest::get_device_details($this->version),
                'encoding'          => 'UTF-8',
                'webMasterId'       => 'PrestaShop ' . _PS_VERSION_,
                'sourceApplication' => $this->nuvei_source_application,
                'url'               => $notificationUrl, // a custom parameter for the checksum
                
//                'urlDetails'        => array(
//                    'notificationUrl'   => $notificationUrl,
//                ),
                
                'merchantDetails'	=> array(
					'customField2' => 'PrestaShop Plugin v' . $this->version,
					'customField4' => $time, // time when we create request
				),
            ),
            $params
        );
        
        // calculate the checksum
        $concat = '';
        
        foreach($checsum_params as $key) {
            if(!isset($params[$key])) {
                $this->createLog(
                    array(
                        'request url'   => $url,
                        'params'        => $params,
                        'missing key'   => $key,
                    ),
                    'Error - Missing a mandatory parameter for the Checksum:'
                );
                
                return array('status' => 'ERROR');
            }
            
            $concat .= $params[$key];
        }
        
        $concat .= Configuration::get('SC_SECRET_KEY');
        
        $params['checksum'] = hash(Configuration::get('SC_HASH_TYPE'), $concat);
        // calculate the checksum END
        
		$this->createLog(
			array(
				'REST API URL'	=> $url,
				'params'		=> $params
			),
			'REST API call (before validation)'
		);

		$resp = NuveiRequest::call_rest_api($url, $params);

		$this->createLog($resp, 'Rest API response');
		
		return $resp;
	}
	
	/**
	 * @global type $smarty
	 * @param bool $is_ajax
	 * 
	 * @return boolean
	 */
	public function openOrder($is_ajax = false)
    {
		$this->createLog('openOrder()');
		
		# set some parameters
        $this->context->smarty->assign('preselectNuveiPayment', Configuration::get('NUVEI_PRESELECT_PAYMENT'));
        $this->context->smarty->assign('scAPMsErrorMsg',        '');
		
		$this->context->smarty->assign(
            'ooAjaxUrl',
            $this->context->link->getModuleLink(
                'nuvei_checkout',
                'payment',
                array('prestaShopAction'  => 'createOpenOrder')
            )
        );

		try {
			$cart               = $this->context->cart;
			$products			= $cart->getProducts();
			$currency           = new Currency((int)($cart->id_currency));
			$customer           = new Customer($cart->id_customer);
			$amount				= (string) number_format($cart->getOrderTotal(), 2, '.', '');
            $addresses          = $this->getOrderAddresses();
			
			# try updateOrder
			$resp           = $this->updateOrder(); // this is merged array of response and the session
            $resp_status    = $this->getRequestStatus($resp);

			if (!empty($resp_status) && 'SUCCESS' == $resp_status) {
				if ($is_ajax) {
					exit(json_encode(array(
						'status'        => 1,
						'sessionToken'	=> $resp['sessionToken']
					)));
				}

				$this->context->smarty->assign('sessionToken', $resp['sessionToken']);

				// pass billing country
				$resp['billingAddress'] = $addresses['billingAddress'];
				
				return $resp;
			}
			# /try updateOrder
			
			$error_url		= $this->context->link->getModuleLink(
				$this->name,
				'payment',
				array('prestaShopAction' => 'showError')
			);
            
			$success_url	= $this->context->link->getModuleLink(
				$this->name,
				'payment',
				array(
					'prestaShopAction'	=> 'showCompleted',
					'id_cart'			=> (int) $cart->id,
					'id_module'			=> $this->id,
					'status'			=> Configuration::get('PS_OS_PREPARATION'),
					'amount'			=> $amount,
					'module'			=> $this->displayName,
					'key'				=> $customer->secure_key,
				)
			);
            
            // get products details
            $products_data = array();
			foreach ($products as $product) {
				$products_data[$product['id_product']] = array(
//						'name'		=> $product['name'],
					'quantity'	=> $product['quantity'],
					'total_wt'	=> (string)round(floatval($product['total_wt']), 2)
				);
			}
            
			# Open Order
			$oo_params = array(
				'clientUniqueId'	=> (int)$cart->id,
				'amount'            => $amount,
				'currency'          => $currency->iso_code,

				'urlDetails'        => array(
					'notificationUrl'   => $this->getNotifyUrl(),
//					'successUrl'		=> $success_url,
//					'failureUrl'		=> $error_url,
//					'pendingUrl'		=> $success_url,
                    'backUrl'           => $this->context->link->getPageLink('order'),
				),

				'billingAddress'    => $addresses['billingAddress'],
				'userDetails'       => $addresses['billingAddress'],
				'shippingAddress'   => $addresses['shippingAddress'],
				'paymentOption'		=> ['card' => ['threeD' => ['isDynamic3D' => 1]]],
				'transactionType'	=> Configuration::get('SC_PAYMENT_ACTION'),
				
                'merchantDetails'	=> array(
					'customField1' => $cart->secure_key,
					'customField3' => json_encode($products_data), // items info
				),
			);
            
            if(1 == Configuration::get('NUVEI_AUTO_CLOSE_APM_POPUP')
                || 'no' == Configuration::get('SC_TEST_MODE')
            ) {
                $oo_params['urlDetails']['successUrl']  = $oo_params['urlDetails']['failureUrl']
                                                        = $oo_params['urlDetails']['pendingUrl']
                                                        = $this->apmPopupAutoCloseUrl;
            }
            
            // rebiling parameters
            $rebilling_params = $this->preprareRebillingParams();
            
            # use or not UPOs
            // in case there is a Product with a Payment Plan
            if(isset($rebilling_params['isRebilling']) && 0 == $rebilling_params['isRebilling']) {
                $oo_params['userTokenId'] = $oo_params['billingAddress']['email'];
            }
            elseif(Configuration::get('SC_USE_UPOS') == 1 
                && (bool) $this->context->customer->isLogged()
            ) {
                $oo_params['userTokenId'] = $oo_params['billingAddress']['email'];
            }
            # /use or not UPOs
            
			$resp = $this->callRestApi(
                'openOrder', 
                $oo_params,
                array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp')
            );
            
			if(empty($resp['sessionToken'])
				|| empty($resp['status'])
				|| 'SUCCESS' != $resp['status']
			) {
				if(!empty($resp['message'])) {
					$this->context->smarty->assign('scAPMsErrorMsg',	$resp['message']);
				}

				return false;
			}

            $this->context->cookie->__set(
                'nuvei_last_open_order_details',
                serialize(array(
                    'amount'			=> $oo_params['amount'],
                    'items'				=> $oo_params['merchantDetails']['customField3'],
                    'sessionToken'		=> $resp['sessionToken'],
                    'clientRequestId'	=> $resp['clientRequestId'],
                    'orderId'			=> $resp['orderId'],
                    'billingAddress'	=> array('country' => $oo_params['billingAddress']['country']),
                ))
            );
            
			// when need session token only
			if($is_ajax) {
				$this->createLog($resp['sessionToken'], 'Session token for Ajax call');

				exit(json_encode(array(
					'status'        => 1,
					'sessionToken' => $resp['sessionToken']
				)));
			}
            
            // pass the rebilling fields as response only
            $oo_params = array_merge_recursive($oo_params, $rebilling_params);
            
            $return_arr                     = $resp;
            $return_arr['request_params']   = $oo_params;
            
            return $return_arr;
		}
		catch (Exception $ex) {
			$this->createLog($ex->getMessage(), 'hookPaymentOptions Exception');
			
            $this->context->smarty->assign('scAPMsErrorMsg', 'Exception ' . $ex->getMessage());
			
			return false;
		}
		# create openOrder END
	}
    
    /**
     * Generate semi dynamic security key for Ajax calls.
     * 
     * @global object $cookie
     * @return string
     */
    public function getModuleSecurityKey()
    {
        $merchant_hash_alg  = Configuration::get('SC_HASH_TYPE');
        $cookie             = $this->context->cookie;
        $user_id            = '';
        
        if(!empty($cookie->id_employee)) {
            $user_id = $cookie->id_employee;
        }
        elseif(!empty($this->context->cart->id_customer)) {
            $user_id = $this->context->cart->id_customer;
        }
        
        if(empty($merchant_hash_alg)) {
            $merchant_hash_alg = 'sha256';
        }
        
        return hash(
            $merchant_hash_alg,
            $this->name . '_' . $this->version . '_' . $user_id
        ); 
    }
    
    /**
     * Prepare the Order data and assign it to smarty.
     * 
     * @return bool
     */
    public function assignOrderData()
    {
        $oo_params = $this->openOrder();
        
        $this->createLog($oo_params, 'assignOrderData() $oo_params');
        
        if(empty($oo_params['sessionToken'])) {
            $this->createLog($oo_params, 'Missing session token!', 'CRITICAL');
            return false;
        }
        
        # for UPO
        $is_rebilling   = false;
        $use_upos       = $save_pm 
                        = (bool) Configuration::get('SC_USE_UPOS');
        
        if(!(bool)$this->context->customer->isLogged()) {
            $use_upos = $save_pm = false;
        }
        elseif(isset($oo_params['request_params']['isRebilling']) 
            && 0 == $oo_params['request_params']['isRebilling']
        ) {
            $is_rebilling   = true;
            $save_pm        = 'always';
        }
        # /for UPO
        
        # blocked PMs
        $blocked_pms = Configuration::get('NUVEI_BLOCK_PMS');
			
		if (!empty($blocked_pms)) {
			$blocked_pms = explode(',', $blocked_pms);
		}
        # /blocked PMs
        
        # blocked_cards
		$blocked_cards     = [];
		$blocked_cards_str = Configuration::get('NUVEI_BLOCK_CARDS');
		
        // clean the string from brakets and quotes
		$blocked_cards_str = str_replace('],[', ';', $blocked_cards_str);
		$blocked_cards_str = str_replace('[', '', $blocked_cards_str);
		$blocked_cards_str = str_replace(']', '', $blocked_cards_str);
		$blocked_cards_str = str_replace('"', '', $blocked_cards_str);
		$blocked_cards_str = str_replace("'", '', $blocked_cards_str);
		
		if (!empty($blocked_cards_str)) {
			$blockCards_sets = explode(';', $blocked_cards_str);

			if (count($blockCards_sets) == 1) {
				$blocked_cards = explode(',', current($blockCards_sets));
			} else {
				foreach ($blockCards_sets as $elements) {
					$blocked_cards[] = explode(',', $elements);
				}
			}
		}
		# /blocked_cards
        
        if(empty($oo_params['request_params']['billingAddress'])) {
            $addresses                                      = $this->getOrderAddresses();
            $oo_params['request_params']['billingAddress']  = $addresses['billingAddress'];
        }
        
        $checkout_params = [
            'sessionToken'              => $oo_params['sessionToken'],
			'env'                       => 'yes' == Configuration::get('SC_TEST_MODE') ? 'test' : 'prod',
			'merchantId'                => $oo_params['merchantId'],
			'merchantSiteId'            => $oo_params['merchantSiteId'],
			'country'                   => $oo_params['request_params']['billingAddress']['country'],
			'currency'                  => $oo_params['request_params']['currency'],
			'amount'                    => $oo_params['request_params']['amount'],
			'renderTo'                  => '#nuvei_checkout',
			'useDCC'                    => Configuration::get('NUVEI_USE_DCC'),
			'strict'                    => false,
			'savePM'                    => $save_pm,
			'showUserPaymentOptions'    => $use_upos,
			'pmWhitelist'               => null,
			'pmBlacklist'               => $blocked_pms,
            'blockCards'                => $blocked_cards,
			'alwaysCollectCvv'          => true,
			'fullName'                  => $oo_params['request_params']['billingAddress']['firstName'] . ' ' 
                . $oo_params['request_params']['billingAddress']['lastName'],
			'email'                     => $oo_params['request_params']['billingAddress']['email'],
			'payButton'                 => Configuration::get('NUVEI_PAY_BTN_TEXT'),
			'showResponseMessage'       => false, // shows/hide the response popups
			'locale'                    => substr($this->context->language->locale, 0, 2),
			'autoOpenPM'                => (bool) Configuration::get('NUVEI_AUTO_EXPAND_PMS'),
			'logLevel'                  => Configuration::get('NUVEI_SDK_LOG_LEVEL'),
			'maskCvv'                   => true,
			'i18n'                      => Configuration::get('NUVEI_SDK_TRANSL'),
            'billingAddress'            => $oo_params['request_params']['billingAddress'],
        ];
        
        if($is_rebilling) {
            unset($checkout_params['pmBlacklist']);
            $checkout_params['pmWhitelist'] = ['cc_card'];
        }
        
        $sdk_url = $this->getSdkLibUrl();
        
        // when use dev sdk, set this variable
        if('prod' != Configuration::get('NUVEI_SDK_VERSION')) {
            $checkout_params['webSdkEnv'] = 'dev';
        }

        $this->context->smarty->assign('nuveiSdkUrl',       $sdk_url);
        $this->context->smarty->assign('showNuveoOnly',     $is_rebilling);
        $this->context->smarty->assign('nuveiSdkParams',    json_encode($checkout_params));
        
        return true;
    }
    
    private function smartyToJsObject($object, $name = 'nuveiObj')
    {
        return '<script>var ' . $$name . ' = ' . json_encode($object) . ';</script>';
    }
    
    /**
	 * Here we only set template variables
	 */
	private function getPaymentMethods()
    {
        $cookie         = $this->context->cookie;
        $session_token  = $this->getSessionToken();
        
        // on missing session token
        if(empty($session_token)) {
            $this->createLog(null, 'Missing session token', 'WARN');
            
            $this->context->smarty->assign('paymentMethods', []);
            return;
        }
        
		$payment_methods            = [];
        $nuvei_block_pms            = Configuration::get('NUVEI_BLOCK_PMS');
        $nuvei_block_pms_arr        = !empty($nuvei_block_pms) ? explode(',', $nuvei_block_pms) : [];
        
        $this->createLog($nuvei_block_pms, '$nuvei_block_pms');
			
		$apms_params		= array(
			'sessionToken'  => $session_token,
			'languageCode'  => $this->context->language->iso_code,
//			'currencyCode'  => $this->context->currency->iso_code,
		);
        
		$res = $this->callRestApi(
            'getMerchantPaymentMethods', 
            $apms_params,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'timeStamp')
        );

		if(!empty($res['paymentMethods']) && is_array($res['paymentMethods'])) {
            foreach($res['paymentMethods'] as $pm) {
                if(empty($pm['paymentMethod'])) {
                    continue;
                }
                
                $pm_name = '';
                
                if(!empty($pm['paymentMethodDisplayName'][0]['message'])) {
                    $pm_name = $pm['paymentMethodDisplayName'][0]['message'];
                }
                else {
                    $pm_name = ucfirst(str_replace('_', ' ', $pm['paymentMethod']));
                }
                
                $payment_methods[$pm['paymentMethod']] = [
                    'name'      => $pm_name,
                    'selected'  => in_array($pm['paymentMethod'], $nuvei_block_pms_arr) ? 1 : 0
                ];
            }
		}
        
		$this->context->smarty->assign('paymentMethods', $payment_methods);
	}
    
    /**
     * Try to get a session token
     * 
     * @return string
     */
    private function getSessionToken()
    {
        $res = $this->callRestApi(
            'getSessionToken', 
            [],
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'timeStamp')
        );
        
        if(!empty($res['sessionToken'])) {
            return $res['sessionToken'];
        }
        
        return '';
    }
	
    /**
     * Get the IDs of the Nuvey Payment Plan group.
     * The query use cache by default.
     * 
     * @return array $ids
     */
    private function getNuvePaymentPlanGroupIds()
    {
        $query = "SELECT id_attribute_group AS id "
            . "FROM " . _DB_PREFIX_ . "attribute_group_lang "
            . "WHERE name = '". $this->paymentPlanGroup ."' "
            . "GROUP BY id";
        
        $ids        = array();
        $ids_res    = Db::getInstance()->executeS($query);
        
        foreach($ids_res as $data) {
            $ids[] = $data['id'];
        }
        
        return $ids;
    }
    
	/**
	 * Get the URL to the endpoint, without the method name, based on the site mode.
	 * 
	 * @return string
	 */
	private function getEndPointBase() {
		if (Configuration::get('SC_TEST_MODE') == 'yes') {
			return $this->restApiIntUrl;
		}
		
		return $this->restApiProdUrl;
	}
    
    /**
     * @return string
     */
    private function getSdkLibUrl() {
        if (Configuration::get('NUVEI_SDK_VERSION') == 'prod') {
			return $this->sdkLibProdUrl;
		}
		
		return $this->sdkLibDevUrl;
    }
	
	/**
	 * Function update_order
	 * 
	 * @return array
	 */
	private function updateOrder()
    {
        $nuvei_last_open_order_details = [];
        
        if(!empty($this->context->cookie->nuvei_last_open_order_details)) {
            $nuvei_last_open_order_details = unserialize($this->context->cookie->nuvei_last_open_order_details);
        }       
        
		$this->createLog(
			$nuvei_last_open_order_details,
			'updateOrder() - session[nuvei_last_open_order_details]'
		);
		
		if (empty($nuvei_last_open_order_details)
			|| empty($nuvei_last_open_order_details['sessionToken'])
			|| empty($nuvei_last_open_order_details['orderId'])
			|| empty($nuvei_last_open_order_details['clientRequestId'])
		) {
			$this->createLog('update_order() - exit updateOrder logic, continue with new openOrder.');
			
            return array('status' => 'ERROR');
		}
		
        $cart_items		= [];
		$cart_amount    = (string) round($this->context->cart->getOrderTotal(), 2);
		$currency		= new Currency((int)($this->context->cart->id_currency));
        $addresses      = $this->getOrderAddresses();

		// get items
		foreach ($this->context->cart->getProducts() as $product) {
			$cart_items[$product['id_product']] = array(
//				'name'		=> $product['name'],
				'quantity'	=> $product['quantity'],
				'total_wt'	=> (string) round(floatval($product['total_wt']), 2)
			);
		}
		
		// create Order upgrade
		$params = array(
			'sessionToken'		=> $nuvei_last_open_order_details['sessionToken'],
			'orderId'			=> $nuvei_last_open_order_details['orderId'],
			'clientRequestId'	=> $nuvei_last_open_order_details['clientRequestId'],
			'currency'			=> $currency->iso_code,
			'amount'			=> $cart_amount,
			'items'				=> array(
				array(
					'name'          => 'wc_order',
					'price'         => $cart_amount,
					'quantity'      => 1
				)
			),
			'merchantDetails'   => array(
				'customField1' => $this->context->cart->secure_key,
				'customField3' => json_encode($cart_items),
			),
		);
        
        // rebiling parameters
        $rebilling_params = $this->preprareRebillingParams();
        // when will use UPOs
        if(0 == $rebilling_params['isRebilling']) {
            $params['userTokenId'] = $addresses['billingAddress']['email'];
        }
        elseif(Configuration::get('SC_USE_UPOS') == 1) {
            $params['userTokenId'] = $addresses['billingAddress']['email'];
        }
        else {
            $params['userTokenId'] = null;
        }
        
        $params = array_merge_recursive($params, $rebilling_params);
        
		$resp = $this->callRestApi(
            'updateOrder',
            $params,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp')
        );
        
        $resp_status = $this->getRequestStatus($resp);
		
		# Success
		if (!empty($resp_status) && 'SUCCESS' == $resp_status) {
            $nuvei_last_open_order_details['amount'] = $cart_amount;
			$nuvei_last_open_order_details['items']  = $params['merchantDetails']['customField3'];
            
            $this->context->cookie->__set('nuvei_last_open_order_details', serialize($nuvei_last_open_order_details));
			
            $resp['request_params'] = $params;
            
			return $resp;
		}
		
		$this->createLog('update_order() - Order update was not successful.');

		return array('status' => 'ERROR');
	}
    
    /**
     * Get an array with billingAddress and shippingAddress.
     * 
     * @return array
     */
    private function getOrderAddresses()
    {
        $cart               = $this->context->cart;
        $customer           = new Customer($cart->id_customer);
        $address_invoice    = $address_delivery
                            = new Address((int)($cart->id_address_invoice));
        $country_inv        = $country_delivery
                            = new Country((int)($address_invoice->id_country), Configuration::get('PS_LANG_DEFAULT'));
            
        if(!empty($cart->id_address_delivery) && $cart->id_address_delivery != $cart->id_address_invoice) {
            $address_delivery	= new Address((int)($cart->id_address_delivery));
            $country_delivery   = new Country((int)($address_delivery->id_country), Configuration::get('PS_LANG_DEFAULT'));
        }
        
        return [
            'billingAddress' => [
                "firstName"	=> $address_invoice->firstname,
                "lastName"	=> $address_invoice->lastname,
                "address"   => $address_invoice->address1,
                "phone"     => $address_invoice->phone,
                "zip"       => $address_invoice->postcode,
                "city"      => $address_invoice->city,
                'country'	=> $country_inv->iso_code,
                'email'		=> $customer->email,
            ],
            
            'shippingAddress'    => [
                "firstName"	=> $address_delivery->firstname,
                "lastName"	=> $address_delivery->lastname,
                "address"   => $address_delivery->address1,
                "phone"     => $address_delivery->phone,
                "zip"       => $address_delivery->postcode,
                "city"      => $address_delivery->city,
                'country'	=> $country_delivery->iso_code,
                'email'		=> $customer->email,
            ],
        ];
    }
    
    private function preprareRebillingParams()
    {
        $params = [];
        
        // default rebiling parameters
        $params['isRebilling']                                        = 1;
        $params['paymentOption']['card']['threeD']['rebillFrequency'] = 0;
        $params['paymentOption']['card']['threeD']['rebillExpiry']    = gmdate('Ymd', time());
        
        # check for a product with a Payment Plan
        $prod_with_plan = $this->getProdsWithPlansFromCart();
        
        // in case there is a Product with a Payment Plan
        if(!empty($prod_with_plan) && is_array($prod_with_plan)) {
            $params['isRebilling']                                        = 0;
			$params['paymentOption']['card']['threeD']['rebillFrequency'] = 1;
			$params['paymentOption']['card']['threeD']['rebillExpiry']    = gmdate('Ymd', strtotime('+5 years'));
            $params['merchantDetails']['customField5']                    = $prod_with_plan['plan_details'];
        }
        
        return $params;
    }
	
	private function addOrderState()
	{
		$db = Db::getInstance();
		
		$res = $db->getRow('SELECT * '
			. 'FROM ' . _DB_PREFIX_ . "order_state "
			. "WHERE module_name = 'Nuvei' "
			. "ORDER BY id_order_state DESC;");
		
//		if (
//			!Configuration::get('SC_OS_AWAITING_PAIMENT')
//            || !Validate::isLoadedObject(new OrderState(Configuration::get('SC_OS_AWAITING_PAIMENT')))
//		) {
		// create
		if(empty($res)) {
			// create new order state
			$order_state = new OrderState();

			$order_state->invoice		= false;
			$order_state->send_email	= false;
			$order_state->module_name	= 'Nuvei';
			$order_state->color			= '#4169E1';
			$order_state->hidden		= false;
			$order_state->logable		= true;
			$order_state->delivery		= false;

			$order_state->name	= array();
			$languages			= Language::getLanguages(false);

			// set the name for all lanugaes
			foreach ($languages as $language) {
				$order_state->name[ $language['id_lang'] ] = 'Awaiting Nuvei payment';
			}

			if(!$order_state->add()) {
				return false;
			}
			
			// on success add icon
			$source = _PS_MODULE_DIR_ . 'nuvei_checkout/views/img/nuvei.png';
			$destination = _PS_ROOT_DIR_ . '/img/os/' . (int)$order_state->id . '.gif';
			copy($source, $destination);

			// set status in the config
			Configuration::updateValue('SC_OS_AWAITING_PAIMENT', (int) $order_state->id);
		}
		// update if need to
		else {
			Configuration::updateValue('SC_OS_AWAITING_PAIMENT', (int) $res['id_order_state']);
		}
		
		return true;
	}
	
    /**
     * Function getRequestStatus
     * We need this stupid function because as response request variable
     * we get 'Status' or 'status'...
     * 
     * @params array $params Optional array with data to search into.
     * @return string
     */
    private function getRequestStatus($params = [])
    {
        if(!empty($params)) {
            if(isset($params['Status'])) {
                return $params['Status'];
            }

            if(isset($params['status'])) {
                return $params['status'];
            }
        }
        
        if(isset($_REQUEST['Status'])) {
            return $_REQUEST['Status'];
        }

        if(isset($_REQUEST['status'])) {
            return $_REQUEST['status'];
        }
        
        return '';
    }
    
    private function checkAdvancedCheckSum()
    {
        try {
            $str = hash(
                Configuration::get('SC_HASH_TYPE'),
                Configuration::get('SC_SECRET_KEY') . @$_REQUEST['totalAmount']
                    . @$_REQUEST['currency'] . @$_REQUEST['responseTimeStamp']
                    . @$_REQUEST['PPP_TransactionID'] . $this->getRequestStatus()
                    . @$_REQUEST['productId']
            );
        }
        catch(Exception $e) {
            $this->createLog($e->getMessage(), 'checkAdvancedCheckSum Exception: ');
            return false;
        }

        if ($str == @$_REQUEST['advanceResponseChecksum']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check in the Cart for Products with Nuvei Payment Plan. If any return the details.
     * We do not expect to more than one type of products with a Plan.
     * The quantity is not limited.
     * 
     * @param array $params optional, pass it from the hook, must include Cart object
     * @param array $group_ids_arr optional, array with Nuvei Payment Plans groups IDs
     * 
     * @return array empty or Payment Plan Details for the product
     */
    private function getProdsWithPlansFromCart($params = array(), $group_ids_arr = null)
    {
        // must be only one
        $products = !empty($params['cart']) 
            ? $params['cart']->getProducts() : $this->context->cart->getProducts();
        
        if(count($products) > 1) {
            $this->createLog(count($products), 'getProdsWithPlansFromCart() - did not expect products to be more than 1.');
        }
        
        // get Nuvei Payment Plan group IDs
        if(!is_array($group_ids_arr) || empty($group_ids_arr)) {
            $group_ids_arr = $this->getNuvePaymentPlanGroupIds();
        }
        
        if(empty($group_ids_arr)) {
            return [];
        }
        
        foreach($products as $data) {
            $sql = "SELECT npppd.* "
                . "FROM ". _DB_PREFIX_ ."attribute "
                . "LEFT JOIN ". _DB_PREFIX_ ."product_attribute_combination AS pac "
                . "ON pac.id_attribute = ". _DB_PREFIX_ ."attribute.id_attribute "
                . "LEFT JOIN nuvei_product_payment_plan_details as npppd "
                . "ON npppd.id_product_attribute = pac.id_product_attribute "
                . "WHERE pac.id_product_attribute = ". (int) $data['id_product_attribute'] ." "
                    . "AND ". _DB_PREFIX_ ."attribute.id_attribute_group IN (". implode(',', $group_ids_arr) .");";

            $res = Db::getInstance()->executeS($sql);
            
            $this->createLog($res, 'getProdsWithPlansFromCart()');

            // we have product with a Nuvei Payment Plan into the Cart
            if(!empty($res) && is_array($res)) {
                return current($res);
            }
        }
        
        return [];
    }
    
    /**
     * Function postValidation()
     * Validate mandatory fields.
     */
    private function postValidation()
    {
        if (!Tools::getValue('SC_MERCHANT_SITE_ID')) {
            $this->_postErrors[] = $this->l('Nuvei "Merchant site ID" is required.');
        }

        if (!Tools::getValue('SC_MERCHANT_ID')) {
            $this->_postErrors[] = $this->l('Nuvei "Merchant ID" is required.');
        }

        if (!Tools::getValue('SC_SECRET_KEY')) {
            $this->_postErrors[] = $this->l('Nuvei "Secret key" is required.');
        }
        
        if (!Tools::getValue('SC_TEST_MODE')) {
            $this->_postErrors[] = $this->l('Nuvei "Test mode" is required.');
        }
        
        if (!Tools::getValue('SC_HASH_TYPE')) {
            $this->_postErrors[] = $this->l('Nuvei "Hash type" is required.');
        }
        
        if (!Tools::getValue('SC_PAYMENT_ACTION')) {
            $this->_postErrors[] = $this->l('Nuvei "Payment action" is required.');
        }
    }
    
    /**
     * Extract plugin version form Git master branch.
     * 
     * @return string|0
     */
    private function getPluginVerFromGit()
    {
        try{
            $matches = array();
            $ch      = curl_init();

            curl_setopt(
                $ch,
                CURLOPT_URL,
                'https://github.com/SafeChargeInternational/safecharge_prestashop/blob/master/CHANGELOG.md'
            );

            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $file_text = curl_exec($ch);
            curl_close($ch);

            preg_match('/\#([0-9]\.[0-9](\.[0-9])?)/', $file_text, $matches);

            if(empty($matches) || !is_array($matches)) {
                $this->createLog($matches, 'getPluginVerFromGit() error');
                return 0;
            }

            return current($matches);
        }
        catch(Exception $e) {
            $this->createLog($e->getMessage(), 'getPluginVerFromGit() Exception');
            return 0;
        }
    }
    
}
