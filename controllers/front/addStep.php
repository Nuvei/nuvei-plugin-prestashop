<?php

/**
 * @author Nuvei
 */

class Nuvei_CheckoutAddStepModuleFrontController extends ModuleFrontController
{
	public function initContent()
    {
        try {
            parent::initContent();

            $error_url	= $this->context->link->getPageLink('order');
            $cart		= $this->context->cart;
            $error_msg  = '';

            // 
            if($this->module->isModuleActive() !== true){
                $this->module->createLog(null, 'isPayment check fail.', 'WARN');
//                Tools::redirect($error_url);
                $error_msg = $this->l('The selected Payment Module is not active. '
                    . 'Please go back and select another one.');
            }

            if(empty($cart->delivery_option)) {
                $this->createLog(null, 'The Cart is empty.', 'WARN');
//                Tools::redirect($error_url);
                $error_msg = $this->l('The delivery option is not valid. '
                    . 'Please go back and try again.');
            }
            
            // check parameters
            if(hash(Configuration::get('SC_HASH_TYPE'), $cart->secure_key) 
                != Tools::getValue('csk')
            ) {
                $this->module->createLog(
                    [
                        '$cart->secure_key hash'    => hash(Configuration::get('SC_HASH_TYPE'), $cart->secure_key) ,
                        'incoming secure_key'       => Tools::getValue('csk'),
                        'merchant has'              => Configuration::get('SC_HASH_TYPE'),
                    ],
                    'Secure key hash not mutch!'
                );

//                Tools::redirect($error_url);
                $error_msg = $this->l('Cart check error. Please go back and try again.');
            }

            if($cart->id != hash(Configuration::get('SC_HASH_TYPE'), Tools::getValue('cid'))) {
                $this->module->createLog(
                    [
                        '$cart->id hash'    => $cart->id,
                        'incoming cart id'  => hash(Configuration::get('SC_HASH_TYPE'), Tools::getValue('cid')),
                        'merchant has'      => Configuration::get('SC_HASH_TYPE'),
                    ],
                    'Cart ID hash not mutch!'
                );

//                Tools::redirect($error_url);
                $error_msg = $this->l('Cart check error. Please go back and try again.');
            }
            // check parameters END

            if(empty($error_msg)) {
                if(!$this->module->assignOrderData()) {
                    $this->module->createLog(null, 'assignOrderData() call return false', 'WARN');
//                    Tools::redirect($error_url);
                    $error_msg = $this->l('Unexpected error. Please go back and try again.');
                }
            }

            $this->context->smarty->assign('formAction',        Tools::getValue('formAction'));
            $this->context->smarty->assign('nuveiModuleName',   $this->module->name);
            $this->context->smarty->assign('nuveiAddStep',      true);
            $this->context->smarty->assign('nuveiToken',        $this->module->getModuleSecurityKey());
            $this->context->smarty->assign('nuveiErrorMsg',     $error_msg);

            $this->setTemplate('module:nuvei_checkout/views/templates/front/second_step.tpl');
            return;
        }
        catch(Exception $e) {
            $this->module->createLog(
                [
                    'message'   => $e->getMessage(),
                    'place'     => $e->getFile() . ' ' . $e->getLine()
                ],
                'hookPaymentOptions exception',
                'CRITICAL'
            );
        }
    }
}