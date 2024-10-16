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
    public $version                     = '2.0.0';
    public $ps_versions_compliancy      = array(
        'min' => '8.1.0', 
        'max' => '8.1.7' // _PS_VERSION_ // for curent version - _PS_VERSION_
    );
    public $controllers                 = array('payment', 'validation');
    public $bootstrap                   = true;
    public $currencies                  = true;
    public $currencies_mode             = 'checkbox'; // for the Payment > Preferences menu
    public $need_instance               = 1;
    public $is_eu_compatible            = 1;
    
    private $sdkLibProdUrl              = 'https://cdn.safecharge.com/safecharge_resources/v1/checkout/checkout.js';
    private $sdkLibTagUrl               = 'https://devmobile.sccdev-qa.com/checkoutNext/checkout.js';
    private $apmPopupAutoCloseUrl       = 'https://cdn.safecharge.com/safecharge_resources/v1/websdk/autoclose.html';
    private $restApiIntUrl              = 'https://ppp-test.nuvei.com/ppp/api/v1/';
    private $restApiProdUrl             = 'https://secure.safecharge.com/ppp/api/v1/';
    private $paymentPlanGroup           = 'Nuvei Payment Plan';
    private $pmAllowedVoidSettle        = ['cc_card', 'apmgw_expresscheckout'];
    private $nuvei_source_application   = 'PRESTASHOP_PLUGIN';
    private $html                       = '';
    private $is_rebilling_order         = false;
    private $plugin_git_changelog       = 'https://raw.githubusercontent.com/Nuvei/nuvei-plugin-prestashop/main/CHANGELOG.md';
    
    private $fieldsToMask = [
        'ips'       => ['ipAddress'],
        'names'     => ['firstName', 'lastName', 'first_name', 'last_name', 'shippingFirstName', 'shippingLastName'],
        'emails'    => [
            'userTokenId',
            'email',
            'shippingMail', // from the DMN
            'userid', // from the DMN
            'user_token_id', // from the DMN
        ],
        'address'   => ['address', 'phone', 'zip'],
        'others'    => ['userAccountDetails', 'userPaymentOption', 'paymentOption'],
    ];
    
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
            || !$this->registerHook('actionProductSave')
            || !$this->registerHook('displayBackOfficeHeader')
            || !$this->registerHook('actionAttributeCombinationDelete')
            || !$this->registerHook('actionCartUpdateQuantityBefore')
            || !$this->registerHook('displayProductButtons')
            || !$this->registerHook('displayDashboardTop')
            || !$this->registerHook('displayAdminOrderTop')
            || !$this->registerHook('actionGetAdminOrderButtons')
            || !$this->registerHook('displayAdminOrderMain')
            || !$this->registerHook('header') // for the strore front
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
                `subscr_state` varchar(10) NOT NULL,
                
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
                'On Install create safecharge_order_data table response',
                'WARN'
            );
		}
        
        // add subscr_ids field into safecharge_order_data table if not exists
        try {
            $res = $db->execute("ALTER TABLE safecharge_order_data "
                . "ADD `subscr_ids` varchar(255) NOT NULL;");

            if(!$res) {
                $this->createLog(
                    [$res, $db->getMsgError(), $db->getNumberError()],
                    'Error when try to add field `subscr_ids`',
                    'WARN'
                );
            }
        }
        catch (Exception $e) {
            $this->createLog(
                $e->getMessage(),
                'Error when try to add field `subscr_ids`'
            );
        }
        
        // add subscr_state field if not exists
        try {
            $res = $db->execute('ALTER TABLE `safecharge_order_data` ADD '
                . '`subscr_state` VARCHAR(10) NOT NULL;');

            if(!$res) {
                $this->createLog(
                    [$res, $db->getMsgError(), $db->getNumberError()],
                    'Error when try to add field `subscr_state`',
                    'WARN'
                );
            }
        }
        catch (Exception $e) {
            $this->createLog(
                $e->getMessage(),
                'Error when try to add field `subscr_state`',
            );
        }
		# safecharge_order_data table END
        
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
			$this->createLog(
                [$res, $db->getMsgError(), $db->getNumberError()],
                'On Install create nuvei_product_payment_plan_details table response',
                'WARN'
            );
		}
        # nuvei_product_payment_plan_details END
        
        # add new Nuvei table, in future it will be used to keep all data
        /**
         * data fiels json example:
         * 
         * {
         *      'transactions': {
         *          {transaction_id} : {
         *              'authCode'          => {AuthCode},
         *              'transactionId'     => {transaction
         * Id},
         *              'originalTotal'     => {customField2},
         *              'originalCurrency'  => {customField3},
         *              'clientUniqueId'    => {clientUniqueId},
         *              'upoId'             => {userPaymentOptionId},
         *              'userTokenId'       => {user_token_id},
         *              'totalCurrAlert'    => true|false, // optional, when PS Order total/currency is different than originalTotal/originalCurrency
         *          }
         *      },
         *      'subscriptions': {
         *          {subscription_id}: {
         *              'state': {state},
         *              'planId': {plan_id},
         *              'payments': {
         *                  {transaction_id}: {
         *                      'total'     => {total},
         *                      'currency'  => {total},
         *                  }
         *              }
         *          }
         *      },
         *      ...
         * }
         */
        $sql =
            "CREATE TABLE IF NOT EXISTS `nuvei_orders_data` (
                `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
                `order_id` int(11) unsigned NOT NULL,
                `transaction_id` varchar(25) NOT NULL,
				`data` text NOT NULL,
                
                PRIMARY KEY (`id`),
                KEY `order_id` (`order_id`),
                UNIQUE KEY `un_order_id` (`order_id`)
              ) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        
        $res = $db->execute($sql);
        # /add new Nuvei table, in future it will be used to keep all data
        
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
            Configuration::updateValue('NUVEI_ADD_CHECKOUT_STEP',   Tools::getValue('NUVEI_ADD_CHECKOUT_STEP'));
            Configuration::updateValue('NUVEI_PRESELECT_PAYMENT',   Tools::getValue('NUVEI_PRESELECT_PAYMENT'));
            
            Configuration::updateValue('NUVEI_USE_DCC',                 Tools::getValue('NUVEI_USE_DCC'));
            Configuration::updateValue('NUVEI_BLOCK_CARDS',             Tools::getValue('NUVEI_BLOCK_CARDS'));
            Configuration::updateValue('NUVEI_PAY_BTN_TEXT',            Tools::getValue('NUVEI_PAY_BTN_TEXT'));
            Configuration::updateValue('NUVEI_AUTO_EXPAND_PMS',         Tools::getValue('NUVEI_AUTO_EXPAND_PMS'));
            Configuration::updateValue('NUVEI_AUTO_CLOSE_APM_POPUP',    Tools::getValue('NUVEI_AUTO_CLOSE_APM_POPUP'));
            Configuration::updateValue('NUVEI_SDK_LOG_LEVEL',           Tools::getValue('NUVEI_SDK_LOG_LEVEL'));
            Configuration::updateValue('NUVEI_SDK_TRANSL',              Tools::getValue('NUVEI_SDK_TRANSL'));
            Configuration::updateValue('NUVEI_SDK_THEME',               Tools::getValue('NUVEI_SDK_THEME'));
            Configuration::updateValue('NUVEI_APM_WINDOW_TYPE',         Tools::getValue('NUVEI_APM_WINDOW_TYPE'));
            
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
	
    /**
     * Add custom JS to show error when the module decline to add a product in the Cart.
     * Looks like Prestashop 8 and up do not show by default.
     */
    public function hookHeader()
    {
        $this->context->controller->registerJavascript(
            'module-nuvei-frontend-js', // Unique ID for the script
            'modules/'.$this->name.'/views/js/front/storeMsg.js', // Path to your JS file
            [
                'position' => 'bottom', // 'top' for <head>, 'bottom' for before </body>
                'priority' => 20 // Priority, lower loads first
            ]
        );
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
            $option_text    = Configuration::get('SC_FRONTEND_NAME');

            if(!$option_text || empty($option_text)) {
                $option_text = $this->trans('Pay by Nuvei', [], 'Modules.nuvei');
            }

            $newOption
                ->setModuleName($this->name)
                ->setCallToActionText($option_text)
//                ->setLogo(_MODULE_DIR_ . 'nuvei_checkout/views/img/nuvei-v2.gif')
            ;
            
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
            $smarty->assign('nuvei_error', $this->l('Nuvei Error - Missing Order Transaction Type.'));
            
            return $this->display(__FILE__, 'views/templates/admin/order_top_msg.tpl');
        }
        // TODO do we need this check...?
        if(empty($sc_data['related_transaction_id'])) {
            $smarty->assign('nuvei_error', $this->l('Nuvei Error - Missing Order Transaction ID.'));
            
            return $this->display(__FILE__, 'views/templates/admin/order_top_msg.tpl');
        }
    }
	
    /**
     * Hook to display Nuvei specific order actions
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
        
        $this->createLog($order->id_cart, 'Order->id_cart');
        
        $data = $this->getNuveiOrderData($order_id);
        $this->createLog($data, 'getNuveiOrderData');
        
        // not Nuvei order
		if(strpos($payment, 'safecharge') === false
			&& strpos($payment, 'nuvei') === false
			&& strpos($payment, 'nuvei payments') === false
		) {
            $this->createLog($payment, 'This is not Nuvei Payment.');
			return false;
		}
        
        $sc_data = Db::getInstance()->getRow(
            'SELECT * FROM safecharge_order_data '
            . 'WHERE order_id = ' . $order_id
        );
        
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
		$enable_void    = false;
        $order_date_add = strtotime($order->date_add);
        $order_inv_date = strtotime($order->invoice_date);
        $order_time     = $order_inv_date > $order_date_add ? $order_inv_date : $order_date_add;
        
		if (isset($sc_data['payment_method']) 
            && in_array($sc_data['payment_method'], $this->pmAllowedVoidSettle)
            && time() < $order_time + 172800
        ) {
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

        # Cancel Subscription button
        $subs_state = Db::getInstance()->getRow(
            'SELECT * FROM safecharge_order_data '
            . 'WHERE order_id = ' . $order_id
        );
        
        if (!empty($subs_state['subscr_state']) && 'active' == $subs_state['subscr_state']) {
            $bar->add(
                new \PrestaShopBundle\Controller\Admin\Sell\Order\ActionsBarButton(
                    'btn btn-action',
                    [
                        'href'      => '#',
                        'type'      => "button",
                        'id'        => "nuvei_cancel_subscr_btn",
                        'onclick'   => "scOrderAction('cancelSubscription', {$order_id})",
                    ],
                    'Cancel Subscription'
                )
            );
        }
        # /Cancel Subscription button
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

        $request_amoutn = $this->formatMoney($request_amoutn);
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
        
        // the status of the request is ERROR
        $msg = $this->l('Request ERROR.');

        if(!empty($json_arr['reason'])) {
            $msg .= ' - ' . $json_arr['reason'] . '. ';
        }
        else {
            $msg .= '. ';
        }

        $this->context->controller->errors[] = $msg;

        $message->message = $msg;
        $message->add();

        return false;
    }

    /**
     * Admin hook in Product edit page.
     * Save Payment plan details for the product if any.
     * 
     * @param array $params
     * @return void
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
                    filter_var($data['rec_unit']) => (int) $data['rec_period']
                ),
                
                'startAfter'        => array(
                    filter_var($data['rec_trial_unit']) => (int) $data['trial_period']
                ),
                        
                'endAfter'          => array(
                    filter_var($data['rec_end_after_unit']) => (int) $data['rec_end_after_period']
                ),
            );
            
            $sql = "INSERT INTO nuvei_product_payment_plan_details "
                . "(id_product_attribute, id_product, plan_details ) "
                . "VALUES (". (int) $comb .", ". (int) $_POST['id_product'] .", '". json_encode($plan_details) ."') "
                . "ON DUPLICATE KEY UPDATE "
                    . "id_product = " . (int) $_POST['id_product'] . ", "
                    . "plan_details = '" . json_encode($plan_details) . "';";
            
            $res = Db::getInstance()->execute($sql);
            
            $this->createLog($plan_details, 'hookActionProductSave $plan_details');
            
            // on error
            if(!$res) {
                $this->createLog(
                    [
                        'error msg' => Db::getInstance()->getMsgError(),
                        'the post'  => $_POST
                    ],
                    'hookActionProductSave Error when try to insert the payment plan data for a product.'
                );
                
                return;
            }
        }
    }
    
    /**
     * An admin hook.
     * Use it to add a JS files or print scripts.
     */
    public function hookDisplayBackOfficeHeader()
    {
        $this->createLog(null, 'hookDisplayBackOfficeHeader', 'INFO');
        
        // insert this script only on Products page
        if(isset($_SERVER['PATH_INFO'])) {
            $path_info = stripslashes($_SERVER['PATH_INFO']);
            
            $this->createLog($path_info, 'hookDisplayBackOfficeHeader', 'DEBUG');
            
            if(strpos($path_info, 'sell/catalog/products') >= 0) {
                // try to get the product id
                $matches    = array();
                $prodId     = null;
                
                preg_match('/(\/sell\/catalog\/products(-v2)?\/)(\d+)(\/edit)?/', $path_info, $matches);
                
                foreach ($matches as $match) {
                    if (is_numeric($match)) {
                        $prodId = $match;
                        break;
                    }
                }
                
                $this->createLog([$matches, $prodId], 'hookDisplayBackOfficeHeader', 'DEBUG');
                
                // get Nuvei Payment Plan group IDs
                if (!is_null($prodId)) {
                    $this->context->controller->addJS('modules/nuvei_checkout/views/js/admin/nuveiProductScript.js');
                    
                    $product        = new Product((int) $prodId);
                    $id_lang        = Context::getContext()->language->id;
                    $combinations   = $product->getAttributeCombinations((int) $id_lang, true);
                    $comb_ids_arr   = array();
                    
                    ob_start();
        
                    $tplProdId      = $prodId;
                    // get Nuvei Payment Plan group IDs
                    $group_ids_arr  = $this->getNuvePaymentPlanGroupIds();
                    $nuvei_ajax_url = $this->context->link->getAdminLink("NuveiAjax") 
                        . '&security_key=' . $this->getModuleSecurityKey();
                    
                    $this->createLog($group_ids_arr, 'hookDisplayBackOfficeHeader $group_ids_arr', 'DEBUG');

                    foreach($combinations as $data) {
                        if(in_array($data['id_attribute_group'], $group_ids_arr)
                            && !in_array($data['id_attribute_group'], $comb_ids_arr)
                        ) {
                            $comb_ids_arr[] = (string) $data['id_product_attribute'];
                        }
                    }

                    // load Nuvei Payment Plans data
                    $npp_data   = '';
                    $file       = _PS_ROOT_DIR_ . '/var/logs/' . $this->paymentPlanJson;

                    if(is_readable($file)) {
                        $npp_data = stripslashes(file_get_contents($file));
                    }
                    
                    // load the Payment details for the products
                    $prod_pans  = array();
                    
                    if (!empty($comb_ids_arr)) {
                        $sql        = "SELECT id_product_attribute, plan_details "
                            . "FROM nuvei_product_payment_plan_details "
                            . "WHERE id_product_attribute IN (" . join(',', $comb_ids_arr) . ")";

                        try {
                            $res = Db::getInstance()->executeS($sql);
                            
                            if(is_array($res) && !empty($res)) {
                                foreach ($res as $details) {
                                    if (empty($details['id_product_attribute'])) {
                                        continue;
                                    }

                                    $prod_pans[$details['id_product_attribute']] 
                                        = json_decode($details['plan_details'], true);
                                }
                            }
                        }
                        catch(\Exception $e) {
                            $this->createLog($e->getMessage(), 'hookDisplayBackOfficeHeader test', 'DEBUG');
                        }
                    }
                    
                    $this->createLog([$sql, $res, $prod_pans], 'hookDisplayBackOfficeHeader', 'DEBUG');

                    require_once dirname(__FILE__) . '/views/js/admin/nuveiProductCombData.php';

                    return ob_get_flush();
                }
            }
        }
        
        // try to add this JS only on Orders List page
        if(Tools::getValue('controller') == 'AdminOrders' && !Tools::getValue('id_order')) {
            $code = '';
            
            ob_start();
            
            $nuvei_ajax_url = $this->context->link
                ->getAdminLink("NuveiAjax") . '&security_key=' . $this->getModuleSecurityKey();
        
            include dirname(__FILE__) . '/views/js/admin/nuveiOrdersList.php';

            $code .= ob_get_contents();
            ob_end_clean();
            
            return $code;
        }
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
     * Be sure the product with a Payment plan be alone in the Cart.
     * 
     * @param type $params
     * @return bool
     */
    public function hookActionCartUpdateQuantityBefore($params)
    {
        $this->createLog(
//            $params, 
            'hookActionCartUpdateQuantityBefore.'
        );
        
        try {
            $products           = $params['cart']->getProducts(); // array
            $is_user_logged     = (bool) $this->context->customer->isLogged();
            $productsAttributes = [];
            $attrIdsWithPlan    = [];
            
            # 1. Collect the attribute ID of all items and check for them in Nuvei Payment plan table
            // 1.1. The incoming product
            if ((int) $params['id_product_attribute'] > 0) {
                $productsAttributes[] = (int) $params['id_product_attribute'];
            }
            
            // 1.2. The products in the Cart
            foreach ($products as $product) {
                if (0 == (int) $product['id_product_attribute']) {
                    continue;
                }
                
                $productsAttributes[] = (int) $product['id_product_attribute'];
            }
            
            // 1.3. Prepare the query who search for the product in the Nuvei table
            $sql = "SELECT id_product_attribute "
                . "FROM nuvei_product_payment_plan_details "
                . "WHERE id_product_attribute ";
            
            if (count($productsAttributes) == 1) {
                $sql .= "= " . (int) $productsAttributes[0];
            }
            else {
                $sql .= "IN (" . implode(', ', $productsAttributes) . ")";
            }
            
            $ids = Db::getInstance()->executeS($sql);
            
            if (is_array($ids) && !empty($ids)) {
                foreach($ids as $col => $id) {
                    $attrIdsWithPlan[] = $id['id_product_attribute'];
                }
            }
            
//            $this->createLog(
//                [$ids, $attrIdsWithPlan], 
//                'hookActionCartUpdateQuantityBefore.'
//            );

            # 2. If the user is Guest, do not add the product if it is with Nuvei Payment plan!
            if (!$is_user_logged && in_array((int) $params['id_product_attribute'], $attrIdsWithPlan)) {
                $this->context->controller->errors[] = $this->l('You have to login to add this product.');

                $params['product']->available_for_order = false;
            }
            
            // From here we assume the user is logged.
            # 3. If the Cart is empty just add the product
            if (empty($products)) {
                return true;
            }
            
            # 4. If Cart is not empty, we have to check all of them for Rebilling
            // Check the products in the Cart for attributes
            foreach ($products as $product) {
                // If we find even on with Payment plan - do not add the product!
                if (in_array((int) $product['id_product_attribute'], $attrIdsWithPlan)) {
                    $this->context->controller->errors[] = $this->l('This product cannot be added to the cart.');

                    $params['product']->available_for_order = false;

                    return false;
                }
            }
            
            return true;
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
        // TODO - if you log the parameters - unset smarty, it is huge!
//        unset($params['smarty']);
        $this->createLog(
//            $params, 
            'hookDisplayProductButtons()', "DEBUG"
        );
        
        if(empty($params['product']['id']) 
            || empty($params['product']['attributes']) 
            || empty($params['product']['combination_specific_data'])
        ) {
            $this->createLog('hookDisplayProductButtons() - missing product combination data.');
            return;
        }
        
        $is_cart_empty  = true;
        $is_user_logged = (bool) $this->context->customer->isLogged();
        $attrId         = (int) $params['product']['combination_specific_data']['id_attribute'];
        $attrIdGroup    = (int) $params['product']['combination_specific_data']['id_attribute_group'];
        
        $sql = "SELECT npppd.*, pac.id_attribute "
            . "FROM nuvei_product_payment_plan_details AS npppd "
            . "LEFT JOIN " . _DB_PREFIX_ . "product_attribute_combination AS pac "
                . "ON npppd.id_product_attribute = pac.id_product_attribute "
            . "WHERE npppd.id_product = " . (int) $params['product']['id'] . " ";
        
        $res = Db::getInstance()->executeS($sql);
        
        if(!$res) {
            return;
        }
        
        $product_plans      = array();
        $gr_ids             = array();
        $currency           = new Currency($this->context->cart->id_currency);
        $conversion_rate    = $currency->getConversationRate();
        
        foreach($res as $data) {
            $data['plan_details'] = json_decode($data['plan_details'], true);
            
            // convert the recurringAmount according currency
            $data['plan_details']['recurringAmount']    *= $conversion_rate;
            $product_plans[$data['id_attribute']]       = $data;
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
            'Plan_duration'     => $this->l('Plan Duration'),
            'Charge_every'      => $this->l('Charge Every'),
            'Recurring_amount'  => $this->l('Recurring Amount'),
            'Trial_period'      => $this->l('Trial Period'),
            'product_plans'     => $product_plans,
            'gr_ids'            => $attrIdGroup,
            'is_cart_empty'     => $is_cart_empty,
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
        $allowed_controllers    = array('AdminDashboard', 'AdminOrders');
        $git_version            = 0;
        $plug_curr_ver          = str_replace('.', '', trim($this->version));
        
        if(!in_array(Tools::getValue('controller'), $allowed_controllers)) {
            return;
        }
        
        if (!empty($_SESSION['nuveiPluginGitVersion'])) {
            $git_version = $_SESSION['nuveiPluginGitVersion'];
        }
        
        $ver_str        = $this->getPluginVerFromGit();
        $git_version    = str_replace('.', '', trim($ver_str));
        $git_version    = $_SESSION['nuveiPluginGitVersion'] 
                        = str_replace('#', '', trim($git_version));
        
        $this->createLog($git_version);
        
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
                    A new version of Nuvei Plugin is available. <a href="'. $this->plugin_git_changelog .'" target="_blank">View version details.</a>
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
        $logs_path  = _PS_ROOT_DIR_ . '/var/logs/';
        $test_mode  = Configuration::get('SC_TEST_MODE');
		
		if(!is_dir($logs_path) || Configuration::get('SC_CREATE_LOGS') == 'no') {
			return;
		}
        
        // save debug logs only in test mode
        if('DEBUG' == $log_level && 'no' == $test_mode) {
            return;
        }
        
        $mask_details   = true; // true if the setting is not set
        $beauty_log     = ('yes' == $test_mode) ? true : false;
        $tab            = '    '; // 4 spaces
        
        if(Configuration::get('SC_CREATE_LOGS') == 'no') {
            $mask_details = false;
        }
        
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
            if ($mask_details) {
                // clean possible objects inside array
                $data = json_decode(json_encode($data), true);

                if (is_array($data)) {
                    array_walk_recursive($data, [$this, 'maskData'], $this->fieldsToMask);
                }
            }
            
            // paymentMethods can be very big array
            if(!empty($data['paymentMethods'])) {
                $exception = json_encode($data);
            }
            else {
                $exception = $beauty_log ? json_encode($data, JSON_PRETTY_PRINT) : json_encode($data);
            }
        }
        elseif(is_object($data)) {
            if ($mask_details && !empty($data)) {
                // clean possible objects inside array
                $data = json_decode(json_encode($data), true);

                if (is_array($data)) {
                    array_walk_recursive($data, [$this, 'maskData'], $this->fieldsToMask);
                }
            }
            
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
	}
	
	/**
	 * Create a Rest Api call and log input and output parameters
     * 
     * Explain 'merchantDetails'
     *      'merchantDetails'	=> array(
     *          'customField1' => string - cart secure_key,
     *          'customField2' => string - currency,
     *          'customField3' => string - order total,
     *          'customField4' => string - timestamp,
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
                'merchantId'        => trim(Configuration::get('SC_MERCHANT_ID')),
                'merchantSiteId'    => trim(Configuration::get('SC_MERCHANT_SITE_ID')),
                'clientRequestId'   => $time . '_' . uniqid(),
                
                'timeStamp'         => $time,
                'deviceDetails'     => NuveiRequest::get_device_details($this->version),
                'webMasterId'       => 'PrestaShop ' . _PS_VERSION_ . '; Plugin v' . $this->version,
                'sourceApplication' => $this->nuvei_source_application,
                'url'               => $notificationUrl, // a custom parameter for the checksum
            ),
            $params
        );
        
        $params['merchantDetails']['customField4'] = time();
        
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
        
        $concat .= trim(Configuration::get('SC_SECRET_KEY'));
        
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
	public function openOrder()
    {
		$this->createLog('openOrder()');
		
		# set some parameters
        $this->context->smarty->assign('preselectNuveiPayment', Configuration::get('NUVEI_PRESELECT_PAYMENT'));
        $this->context->smarty->assign('scAPMsErrorMsg',        '');
		
		$this->context->smarty->assign(
            'ajaxUrl',
            $this->context->link->getModuleLink(
                'nuvei_checkout',
                'payment',
                array('prestaShopAction'  => 'prePaymentCheck')
            )
        );
        
        $this->is_rebilling_order       = false;
        $nuvei_last_open_order_details  = [];

		try {
			$cart                           = $this->context->cart;
			$products                       = $cart->getProducts();
            $products_hash                  = md5(serialize($products));
			$currency                       = new Currency((int)($cart->id_currency));
			$customer                       = new Customer($cart->id_customer);
			$amount                         = $this->formatMoney($cart->getOrderTotal());
            $addresses                      = $this->getOrderAddresses();
            $prod_with_plan                 = $this->getProdsWithPlansFromCart();
            
            if(!empty($prod_with_plan) && is_array($prod_with_plan)) {
                $this->is_rebilling_order = true;
            }
        
            if(!empty($this->context->cookie->nuvei_last_open_order_details)) {
                $nuvei_last_open_order_details 
                    = unserialize($this->context->cookie->nuvei_last_open_order_details);
            }
            
			# try updateOrder
            $callUpdateOrder = $this->callUpdateOrder($nuvei_last_open_order_details, $amount);
            
            if ($callUpdateOrder) {
                $resp           = $this->updateOrder(); // this is merged array of response and the session
                $resp_status    = $this->getRequestStatus($resp);
                
                if (!empty($resp_status) && 'SUCCESS' == $resp_status) {
                    $this->context->smarty->assign('sessionToken', $resp['sessionToken']);

                    // pass billing country
                    $resp['billingAddress'] = $addresses['billingAddress'];

                    return $resp;
                }
            }
			# /try updateOrder
			
			$error_url = $this->context->link->getModuleLink(
				$this->name,
				'payment',
				array('prestaShopAction' => 'showError')
			);
            
			$success_url = $this->context->link->getModuleLink(
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
            
			# Open Order
			$oo_params = array(
				'clientUniqueId'	=> $this->setCuid((int) $cart->id),
				'amount'            => $amount,
				'currency'          => $currency->iso_code,

				'urlDetails'        => array(
					'notificationUrl'   => $this->getNotifyUrl(),
                    'backUrl'           => $this->context->link->getPageLink('order'),
                    'successUrl'        => $success_url,
                    'pendingUrl'        => $success_url,
                    'failureUrl'        => $error_url,
				),

				'billingAddress'    => $addresses['billingAddress'],
				'userDetails'       => $addresses['billingAddress'],
				'shippingAddress'   => $addresses['shippingAddress'],
				'transactionType'	=> Configuration::get('SC_PAYMENT_ACTION'),
				'userTokenId'       => $addresses['billingAddress']['email'],
				
                'merchantDetails'	=> array(
					'customField1' => $cart->secure_key,
                    'customField2' => $amount,
                    'customField3' => $currency->iso_code,
					'customField5' => $prod_with_plan['plan_details'] ?? '',
				),
			);
            
            if ('redirect' != Configuration::get('NUVEI_APM_WINDOW_TYPE')) {
                if (1 == Configuration::get('NUVEI_AUTO_CLOSE_APM_POPUP')
                    || 'no' == Configuration::get('SC_TEST_MODE')
                ) {
                    $oo_params['urlDetails']['successUrl']  = $oo_params['urlDetails']['failureUrl']
                                                            = $oo_params['urlDetails']['pendingUrl']
                                                            = $this->apmPopupAutoCloseUrl;
                }
            }
            
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

            // set some of the parameters into the session
            $nuvei_last_open_order_details = [
                'amount'			=> $oo_params['amount'],
                'sessionToken'		=> $resp['sessionToken'],
                'clientRequestId'	=> $resp['clientRequestId'],
                'orderId'			=> $resp['orderId'],
                'billingAddress'	=> array('country' => $oo_params['billingAddress']['country']),
                'isRebillingOrder'  => $this->is_rebilling_order,
                'transactionType'	=> $oo_params['transactionType'],
                'userTokenId'       => $oo_params['userTokenId'],
                'productsHash'      => $products_hash,
            ];
            
            $this->context->cookie->__set(
                'nuvei_last_open_order_details',
                serialize($nuvei_last_open_order_details)
            );
            // /set some of the parameters into the session
            
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
        
//        $this->createLog($oo_params, 'assignOrderData() $oo_params');
        
        if(empty($oo_params['sessionToken'])) {
            $this->createLog($oo_params, 'Missing session token!', 'CRITICAL');
            return false;
        }
        
        # for UPO
        $use_upos   = $save_pm 
                    = (bool) Configuration::get('SC_USE_UPOS');
        
        if(!(bool)$this->context->customer->isLogged()) {
            $use_upos = $save_pm = false;
        }
        
        if ($this->is_rebilling_order) {
            $save_pm = 'always';
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
        
        $useDCC = Configuration::get('NUVEI_USE_DCC');
        
        if (0 == $oo_params['request_params']['amount']) {
            $useDCC = 'false';
        }
        
        $locale = substr($this->context->language->locale, 0, 2);
        
        $checkout_params = [
            'sessionToken'              => $oo_params['sessionToken'],
			'env'                       => 'yes' == Configuration::get('SC_TEST_MODE') ? 'test' : 'prod',
			'merchantId'                => $oo_params['merchantId'],
			'merchantSiteId'            => $oo_params['merchantSiteId'],
			'country'                   => $oo_params['request_params']['billingAddress']['country'],
			'currency'                  => $oo_params['request_params']['currency'],
			'amount'                    => (string) $oo_params['request_params']['amount'],
			'renderTo'                  => '#nuvei_checkout',
			'useDCC'                    => $useDCC,
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
			'locale'                    => $locale,
			'autoOpenPM'                => (bool) Configuration::get('NUVEI_AUTO_EXPAND_PMS'),
			'logLevel'                  => Configuration::get('NUVEI_SDK_LOG_LEVEL'),
			'maskCvv'                   => true,
			'i18n'                      => json_decode(Configuration::get('NUVEI_SDK_TRANSL'), true),
			'theme'                     => Configuration::get('NUVEI_SDK_THEME'),
			'apmWindowType'             => Configuration::get('NUVEI_APM_WINDOW_TYPE'),
            'apmConfig'                 => [
                'googlePay' => [
                    'locale' => $locale
                ]
            ],
            'sourceApplication'         => $this->nuvei_source_application,
        ];
        
        if($this->is_rebilling_order) {
            unset($checkout_params['pmBlacklist']);
            $checkout_params['pmWhitelist'] = ['cc_card'];
        }
        
        $sdk_url = $this->getSdkLibUrl();
        
        $this->createLog($checkout_params, 'SDK params.');
        
        $this->context->smarty->assign('nuveiSdkUrl',       $sdk_url);
        $this->context->smarty->assign('showNuveoOnly',     $this->is_rebilling_order);
        $this->context->smarty->assign('nuveiSdkParams',    json_encode($checkout_params));
        
        return true;
    }
    
    /**
     * Cancel a Subscription Plan if any.
     * 
     * @param int $order_id
     * @return bool|array
     */
    public function cancel_subscription($order_id)
    {
        $subs_data = Db::getInstance()->getRow(
            'SELECT * FROM safecharge_order_data '
            . 'WHERE order_id = ' . $order_id
        );
        
        if (empty($subs_data['subscr_state'])
            || empty($subs_data['subscr_ids'])
            || 'active' != $subs_data['subscr_state']
            
        ) {
            $this->createLog($subs_data, 'Can not cancel the Subscription.');
            return false;
        }
        
        $resp = $this->callRestApi(
            'cancelSubscription',
            array('subscriptionId' => $subs_data['subscr_ids']),
            array('merchantId', 'merchantSiteId', 'subscriptionId', 'timeStamp',)
        );

        // On Error
        if (!$resp || !is_array($resp) || 'SUCCESS' != $resp['status']) {
            $message			= new MessageCore();
            $message->id_order	= $order_id;
            $msg                = $this->l('Error when try to cancel a Subscription #') 
                . (int) $subs_data['subscr_ids'];

            if (!empty($resp['reason'])) {
                $msg .= $this->l(' Reason: ') . $resp['reason'];
            }

            $message->private = true;
            $message->message = $msg;
            $message->add();

            return !empty($resp['status']) ? $resp : false;
        }
            
        return $resp;
    }
    
    /**
     * Get formatted money.
     * 
     * @param float|int $money
     * @param string    $currency If passed the currency will be added to the response.
     * 
     * @return string
     */
    public function formatMoney($money, $currency = '')
    {
        $formatted = number_format(round((float) $money, 2), 2, '.', '');
        
        if(!empty($currency)) {
            $formatted = $currency . ' ' . $formatted;
        }
        
        return $formatted;
    }
    
    /**
     * A place to get Nuvei data from the new table.
     * 
     * @param int $order_id
     * @return array
     */
    public function getNuveiOrderData($order_id)
    {
        $query = 
            "SELECT * "
            . "FROM nuvei_orders_data "
            . "WHERE order_id = " . (int) $order_id;

        $data = Db::getInstance()->getRow($query);
        
        $this->createLog($data, 'Nuvei Data in nuvei_orders_data table.');
        
        return $data;
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
    private function getSdkLibUrl()
    {
        if (!empty($_SERVER['SERVER_NAME'])
            && !empty($this->sdkLibTagUrl)
            && 'prestashopautomation.gw-4u.com' == $_SERVER['SERVER_NAME']
        ) {
            return $this->sdkLibTagUrl;
        }
        
        return $this->sdkLibProdUrl;
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
		
		$cart_amount    = (string) round($this->context->cart->getOrderTotal(), 2);
		$currency		= new Currency((int)($this->context->cart->id_currency));
        $addresses      = $this->getOrderAddresses();

		// create Order upgrade
		$params = array(
			'sessionToken'		=> $nuvei_last_open_order_details['sessionToken'],
			'orderId'			=> $nuvei_last_open_order_details['orderId'],
			'clientRequestId'	=> $nuvei_last_open_order_details['clientRequestId'],
			'currency'			=> $currency->iso_code,
			'amount'			=> $cart_amount,
			'items'				=> array(
				array(
					'name'          => 'prestashop_order',
					'price'         => $cart_amount,
					'quantity'      => 1
				)
			),
			'merchantDetails'   => array(
				'customField1' => $this->context->cart->secure_key,
                'customField2' => $cart_amount,
                'customField3' => $currency->iso_code,
			),
		);
        
        # check for a product with a Payment Plan
        $prod_with_plan = $this->getProdsWithPlansFromCart();
        
        // when will use UPOs
        if(!empty($prod_with_plan) && is_array($prod_with_plan)) {
            $params['merchantDetails']['customField5']  = $prod_with_plan['plan_details'];
            $this->is_rebilling_order                   = true;
        }
        
		$resp = $this->callRestApi(
            'updateOrder',
            $params,
            array('merchantId', 'merchantSiteId', 'clientRequestId', 'amount', 'currency', 'timeStamp')
        );
        
        $resp_status = $this->getRequestStatus($resp);
		
		# Success
		if (!empty($resp_status) && 'SUCCESS' == $resp_status) {
            $products                                       = $this->context->cart->getProducts();
            $nuvei_last_open_order_details['amount']        = $cart_amount;
            $nuvei_last_open_order_details['productsHash']  = md5(serialize($products));
            
            $this->context->cookie->__set(
                'nuvei_last_open_order_details',
                serialize($nuvei_last_open_order_details)
            );
			
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
    
    /**
     * Add a custom Order state.
     * 
     * @return boolean
     */
	private function addOrderState()
	{
        $db         = Db::getInstance();
        $source     = _PS_MODULE_DIR_ . 'nuvei_checkout/views/img/nuvei.png';
        $languages  = Language::getLanguages(false);
        
        $res = $db->getRow(
            'SELECT * '
			. 'FROM ' . _DB_PREFIX_ . "order_state "
			. "WHERE module_name = 'Nuvei' "
			. "ORDER BY id_order_state DESC;"
        );
		
		// create
		if(empty($res)) {
			// create new order state
			$order_state = new OrderState();

			$order_state->invoice		= false;
            $order_state->unremovable   = true;
			$order_state->send_email	= false;
			$order_state->module_name	= 'Nuvei';
			$order_state->color			= '#4169E1';
			$order_state->hidden		= false;
			$order_state->logable		= true;
			$order_state->delivery		= false;
			$order_state->name          = array();

			// set the name for all lanugaes
			foreach ($languages as $language) {
				$order_state->name[ $language['id_lang'] ] = 'Awaiting Nuvei payment';
			}

			if(!$order_state->add()) {
				return false;
			}
			
			// on success add icon
			$destination = _PS_ROOT_DIR_ . '/img/os/' . (int) $order_state->id . '.gif';
			copy($source, $destination);

			// set status in the config
            $this->createLog($order_state->id);
			Configuration::updateValue('SC_OS_AWAITING_PAIMENT', (int) $order_state->id);
		}
		// update if need to
		else {
			Configuration::updateValue('SC_OS_AWAITING_PAIMENT', (int) $res['id_order_state']);
            
            if (1 == $res['deleted']) {
                $db->execute(
                    "UPDATE " . _DB_PREFIX_ . "order_state "
                    . "SET deleted = 0, unremovable = 1 "
                    . "WHERE id_order_state = " . (int) $res['id_order_state']
                );
            }
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
                trim(Configuration::get('SC_SECRET_KEY')) . @$_REQUEST['totalAmount']
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
        $this->createLog([$params, $group_ids_arr], 'getProdsWithPlansFromCart', 'DEBUG');
        
        // must be only one
        $products = !empty($params['cart']) 
            ? $params['cart']->getProducts() : $this->context->cart->getProducts();
        
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

            $quantity           = (float) $data['quantity'];
            $res                = Db::getInstance()->executeS($sql);
            $currency           = new Currency($this->context->cart->id_currency);
            $conversion_rate    = (float) $currency->getConversationRate();
            
            $this->createLog([
                '$sql' => $sql, 
                '$res' => $res,
            ], 'getProdsWithPlansFromCart', 'DEBUG');
            
            // we have product with a Nuvei Payment Plan into the Cart
            if(!empty($res) && is_array($res)) {
                $details                            = current($res);
                $plan_details                       = json_decode($details['plan_details'], true);
                
                $plan_details['recurringAmount']    = number_format(
                    ($quantity * (float) $plan_details['recurringAmount'] * $conversion_rate),
                    2,
                    '.'
                );
                $details['plan_details']            = json_encode($plan_details);
                
                return $details;
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
     * Extract plugin version form Git main branch.
     * 
     * @return string
     */
    private function getPluginVerFromGit()
    {
        try{
            $matches = array();
            $ch      = curl_init();

            curl_setopt($ch, CURLOPT_URL, $this->plugin_git_changelog);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $file_text = curl_exec($ch);
            curl_close($ch);
            
            preg_match('/\# ([0-9]\.[0-9](\.[0-9])?)/', $file_text, $matches);

            if(empty($matches) || !is_array($matches)) {
                $this->createLog($matches, 'getPluginVerFromGit error - can not find the version.');
                return 0;
            }

            return current($matches);
        }
        catch(Exception $e) {
            $this->createLog($e->getMessage(), 'getPluginVerFromGit() Exception');
            return '0';
        }
    }
    
    /**
	 * Function setCuid
	 * 
	 * Set client unique id.
	 * We change it only for Sandbox (test) mode.
	 * 
	 * @param int $cart_id - cart or order id
	 * @return int|string
	 */
	private function setCuid($cart_id)
    {
		return $cart_id . '_' . time();
	}
    
    /**
     * Just check can we call an updateOrder request.
     * 
     * @params array $nuvei_last_open_order_details
     * @params float $amount The Order amount.
     * 
     * @return bool
     * 
     */
    private function callUpdateOrder($nuvei_last_open_order_details, $amount)
    {
        $callUpdateOrder = true;
            
        // when missing previous OpenOrder data
        if (empty($nuvei_last_open_order_details)) {
            $callUpdateOrder = false;
        }

        // when added new product with Rebilling
        if (empty($nuvei_last_open_order_details['isRebillingOrder'])
            && $this->is_rebilling_order
        ) {
            $this->createLog('A new product with rebilling product was added.');
            $callUpdateOrder = false;
        }

        // if by some reason missing transactionType
        if (empty($nuvei_last_open_order_details['transactionType'])) {
            $this->createLog('transactionType is empty.');
            $callUpdateOrder = false;
        }

        // when the total is 0 and saved transaction type is not Auth
        if ($amount == 0
            && ( empty($nuvei_last_open_order_details['transactionType'])
                || 'Auth' != $nuvei_last_open_order_details['transactionType']
            )
        ) {
            $this->createLog('Amont is 0, but transactionType is not Auth.');
            $callUpdateOrder = false;
        }
        
        if ($amount > 0
            && !empty($nuvei_last_open_order_details['transactionType'])
            && 'Auth' == $nuvei_last_open_order_details['transactionType']
            && $nuvei_last_open_order_details['transactionType'] != Configuration::get('SC_PAYMENT_ACTION')
        ) {
            $this->createLog('Amont is not 0, but transactionType is Auth.');
            $callUpdateOrder = false;
        }
        
        return $callUpdateOrder;
    }
    
    /**
     * A callback function for arraw_walk_recursive.
     * 
     * @param mixed $value
     * @param mixed $key
     * @param array $fields
     */
    private function maskData(&$value, $key, $fields)
    {
        if (!empty($value)) {
            if (in_array($key, $fields['ips'])) {
                $value = rtrim(long2ip(ip2long($value) & (~255)), "0")."x";
            } elseif (in_array($key, $fields['names'])) {
                $value = mb_substr($value, 0, 1) . '****';
            } elseif (in_array($key, $fields['emails'])) {
                $value = '****' . mb_substr($value, 4);
            } elseif (in_array($key, $fields['address'])
                || in_array($key, $fields['others'])
            ) {
                $value = '****';
            }
        }
    }
    
}
