<?php

namespace PrestaShop\Module\NuveiCheckout;

use PaymentModule;
use Symfony\Component\Routing\RouterInterface;

class NuveiCheckout extends PaymentModule
{
    public $name                        = 'nuvei_checkout';
    public $version                     = '3.0.0';
    public $author                      = 'Nuvei';
    public $tab                         = 'payments_gateways'; // Assign the module to the "Payment Gateways" section
    public $need_instance               = 1; // Set to 1 if the module depends on a shop context
    public $limited_countries           = []; // No limitations
    public $ps_versions_compliancy      = array(
        'min' => '8.1.0', 
        'max' => _PS_VERSION_ // for curent version - _PS_VERSION_
    );
    public $bootstrap                   = true;
    public $currencies                  = true;
    public $currencies_mode             = 'checkbox'; // Options: 'checkbox', 'radio'
    public $is_eu_compatible            = 1;
    
    private $router;
    
    public function __construct(RouterInterface $router)
    {
        parent::__construct();
        
        $this->displayName      = $this->trans('Nuvei Payments', [], 'Modules.NuveiCheckout.Admin');
        $this->description      = $this->trans('Accepts payments by Nuvei.', [], 'Modules.NuveiCheckout.Admin');
        $this->confirmUninstall = $this->trans(
            'Are you sure you want to uninstall the module and delete your details?',
            [],
            'Modules.NuveiCheckout.Admin'
        );
        
        $this->router = $router;
    }
    
    public function install()
    {
        if (!parent::install()
            || !Configuration::updateValue('SC_MERCHANT_SITE_ID', '')
            || !Configuration::updateValue('SC_MERCHANT_ID', '')
            || !Configuration::updateValue('SC_SECRET_KEY', '')
            || !$this->registerHook('paymentOptions')
            || !$this->registerHook('actionOrderSlipAdd')
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
//        $invisible_tab = new Tab();
//        
//        $invisible_tab->active      = 1;
//        $invisible_tab->class_name  = 'NuveiAjax';
//        $invisible_tab->name        = array();
//        
//        foreach (Language::getLanguages(true) as $lang) {
//            $invisible_tab->name[$lang['id_lang']] = 'NuveiAjax';
//        }
		
//		$this->createLog('Finish install');
        
        return true;
    }
    
    public function uninstall()
    {
//        if (!parent::uninstall()) {
//            return false;
//        }
//        
//        // Display custom message (PrestaShop core handles this message if you override)
//        $agree = $this->context->controller
//            ->confirmUninstall($this->l('Are you sure you want to uninstall the module and delete your details?'));
//        
//        // try to remove the merchant secret key on uninstall
//        if ($agree && Configuration::deleteByName('SC_SECRET_KEY')) {
//            return true;
//        }
//        
//        return false;
        
        return parent::uninstall() && Configuration::deleteByName('SC_SECRET_KEY');
    }
    
    public function getContent()
    {
//        $this->assignPaymentPlansJsonDownloadDate();
//        $this->assingAjaxUrl();
        
//        $this->html .= '<h2>'.$this->displayName.'</h2>';
        
        // in case the Nuvei settings form was submitted
        if (Tools::isSubmit('submitUpdate')) {
//            $this->postValidation();
            
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
            Configuration::updateValue('NUVEI_SDK_LOG_LEVEL',           Tools::getValue('NUVEI_SDK_LOG_LEVEL'));
            Configuration::updateValue('NUVEI_SDK_TRANSL',              Tools::getValue('NUVEI_SDK_TRANSL'));
            Configuration::updateValue('NUVEI_SDK_THEME',               Tools::getValue('NUVEI_SDK_THEME'));
            Configuration::updateValue('NUVEI_APM_WINDOW_TYPE',         Tools::getValue('NUVEI_APM_WINDOW_TYPE'));
            
            $nuvei_block_pms = Tools::getValue('NUVEI_BLOCK_PMS');
            
            if(is_array($nuvei_block_pms) && !empty($nuvei_block_pms)) {
                Configuration::updateValue('NUVEI_BLOCK_PMS', implode(',', $nuvei_block_pms));
            }
            else {
                Configuration::updateValue('NUVEI_BLOCK_PMS', '');
            }
        }

//        if (isset($this->_postErrors) && sizeof($this->_postErrors)) {
//            foreach ($this->_postErrors as $err){
//                $this->html .= '<div class="alert error">'. $err .'</div>';
//            }
//        }
        
        $this->context->smarty->assign('img_path',       '/modules/nuvei_checkout/views/img/');
//		$this->smarty->assign('defaultDmnUrl',  $this->getNotifyUrl());
		$this->context->smarty->assign('defaultDmnUrl',  $this->getNotifyUrl());
        
        // for the admin we need the Merchant Payment Methods
//        $this->getPaymentMethods();

        return $this->display(__FILE__, 'views/templates/admin/display_forma.tpl');
    }
    
    public function getNotifyUrl()
    {
        return $this->context->link
            ->getModuleLink('nuvei_checkout', 'payment', array(
                'prestaShopAction'  => 'processDmn',
            ));
	}
    
}
