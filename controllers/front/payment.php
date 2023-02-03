<?php

/**
 * @author Nuvei
 */

class Nuvei_CheckoutPaymentModuleFrontController extends ModuleFrontController
{
//    public $ssl = true;
    
    public function initContent()
    {
        parent::initContent();
        
        if(!Configuration::get('SC_MERCHANT_ID')
            || !Configuration::get('SC_MERCHANT_SITE_ID')
            || !Configuration::get('SC_SECRET_KEY')
            || !Configuration::get('SC_CREATE_LOGS')
        ) {
            $this->module->createLog('Plugin is not active or missing Merchant mandatory data!');
            Tools::redirect($this->context->link->getPageLink('order'));
        }
        
        if(Tools::getValue('prestaShopAction', false) == 'showError') {
            $this->scOrderError();
            return;
        }
        
		if(Tools::getValue('prestaShopAction', false) == 'processDmn') {
            $this->processDmn();
            return;
        }
		
		if(Tools::getValue('prestaShopAction', false) == 'createOpenOrder') {
            // security check
            if($this->module->getModuleSecurityKey() != Tools::getValue('securityToken')) {
                $this->module->createLog(
                    array(
                        'ModuleSecurityKey' => $this->module->getModuleSecurityKey(),
                        'securityToken'      => Tools::getValue('securityToken'),
                        'request'           => @$_REQUEST,
                    ),
                    'NuveiPaymentModuleFrontController Error - security key does not much'
                );
                
                Tools::redirect($this->context->link->getPageLink('order'));
            }
            
            $this->module->openOrder(true);
            return;
        }
		
		if(Tools::getValue('prestaShopAction', false) == 'beforeSuccess') {
            $this->beforeSuccess();
            return;
        }
        
        $this->processOrder();
    }
    
    private function processOrder()
    {
		$this->module->createLog(@$_REQUEST, 'processOrder() params');
		
        try {
			$cart = $this->context->cart;
			
			// in case user go to confirm-order page too late
			if(empty($cart->id)) {
                $this->module->createLog('processOrder() $cart->id is empty.');
                
				// if there is Transaction ID we can check for existing Order
				if(is_numeric(Tools::getValue('sc_transaction_id'))) {
					$query = "SELECT id_order, id_cart, secure_key "
						. "FROM " . _DB_PREFIX_ . "order_payment "
						. "LEFT JOIN " . _DB_PREFIX_ . "orders "
						. "ON order_reference = reference "
						. "WHERE transaction_id = '" . Tools::getValue('sc_transaction_id') . "'";
					
					$order_data = Db::getInstance()->getRow($query);
					
					$this->module->createLog($query, 'processOrder() $query');
					$this->module->createLog($order_data, 'processOrder() $order_data');
					
					// redirect to success
					if(!empty($order_data)) {
						Tools::redirect($this->context->link->getPageLink(
							'order-confirmation',
							null,
							null,
							array(
								'id_cart'   => $order_data['id_cart'],
								'id_module' => (int)$this->module->id,
								'id_order'  => $order_data['id_order'],
								'key'       => $order_data['secure_key'],
							)
						));
						exit;
					}
				}
				
				$this->module->createLog('processOrder() Error - Cart ID is empty. Redirect to error page.');
				
				Tools::redirect($this->context->link->getModuleLink(
					$this->module->name,
					'payment',
					array('prestaShopAction' => 'showError')
				));
			}
			
			$customer = $this->validate($cart);

			$error_url = $this->context->link->getModuleLink(
                $this->module->name,
                'payment',
                array(
                    'prestaShopAction'	=> 'showError',
                    'id_cart'			=> (int)$cart->id,
                )
            );

			$success_url = $this->context->link->getPageLink(
                'order-confirmation',
                null,
                null,
                array(
                    'id_cart'   => (int)$cart->id,
                    'id_module' => (int)$this->module->id,
                    'id_order'  => $this->module->currentOrder,
                    'key'       => $customer->secure_key,
                )
            );

            # prepare Order data
			$total_amount = $this->module->formatMoney($cart->getOrderTotal());
			
			# additional check for existing Order by the Card ID
			$query = "SELECT id_order "
				. "FROM " . _DB_PREFIX_ . "orders "
				. "WHERE id_cart = " . intval($cart->id);

			$order_data = Db::getInstance()->getRow($query);
            
            $this->module->createLog($order_data, 'processOrder() check for Order data.');
			
			if(!empty($order_data)) {
				Tools::redirect($success_url);
			}
			# /additional check for existing Order by the Card ID
			
			if(!Tools::getValue('sc_transaction_id', false)) {
                Tools::redirect($error_url);
            }
            
            // save order
            $res = $this->module->validateOrder(
                (int)$cart->id
                ,Configuration::get('SC_OS_AWAITING_PAIMENT') // the status
                ,$total_amount
                ,$this->module->displayName . ' - ' . Tools::getValue('nuveiPaymentMethod') // payment_method
                ,'' // message
                ,array('transaction_id' => Tools::getValue('sc_transaction_id', false)) // extra_vars
                ,null // currency_special
                ,false // dont_touch_amount
                ,$this->context->cart->secure_key // secure_key
            );

            if(!$res) {
                $this->module->createLog('processOrder() Order was not validated');
                Tools::redirect(Tools::redirect($error_url));
            }

            if(!empty($this->context->cookie->nuvei_last_open_order_details)) {
                $this->context->cookie->__unset('nuvei_last_open_order_details');
            }

            $this->module->createLog(
                Tools::getValue('sc_transaction_id'),
                'processOreder() - the webSDK Order was saved.'
            );

            $this->updateCustomPaymentFields($this->module->currentOrder);

            Tools::redirect($success_url);
		}
		catch(Exception $e) {
			$this->module->createLog(
				array($e->getMessage(), $e->getTrace()),
				'processOrder() Exception:'
			);
			
			$this->module->createLog(
				$this->context->link->getModuleLink(
					$this->module->name, 
					'payment', 
					array('prestaShopAction' => 'showError')
				),
				'Exception URL:'
			);
			
			$this->module->createLog($this->context->cart, 'processOrder() Exception cart:');
			
			Tools::redirect(
				$this->context->link->getModuleLink(
					$this->module->name, 
					'payment', 
					array('prestaShopAction' => 'showError')
				)
			);
		}
    }
	
	/**
     * Function scOrderError
     * Shows a message when there is an error with the order
     */
    private function scOrderError()
    {
		$cart_id	= Tools::getValue('id_cart');
		$order_id	= Order::getOrderByCartId((int) $cart_id);
		$order_info = new Order($order_id);

		// in case the user owns the order for this cart id, and the order status
		// is canceled, redirect directly to Reorder Page
		if(
			(int) $this->context->customer->id == (int) $order_info->id_customer
			&& (int) $order_info->current_state == (int) Configuration::get('PS_OS_CANCELED')
		) {
			$url = $this->context->link->getPageLink(
				'order',
				null,
				null,
				array(
					'submitReorder'	=> '',
					'id_order'		=> (int) $order_id
				)
			);

			Tools::redirect($url);
		}
		
        $this->setTemplate('module:nuvei_checkout/views/templates/front/order_error.tpl');
    }
	
    /**
     * Process the DMNs.
     * IMPORTANT - with the DMN we get CartID, NOT OrderID
     */
    private function processDmn()
    {
        $this->module->createLog(@$_REQUEST, 'DMN request:');
		
        # manually stop DMN process
//        $this->module->createLog(
//            http_build_query(@$_REQUEST),
//            'DMN report: Manually stopped process.'
//        );
//        header('Content-Type: text/plain');
//        exit('DMN report: Manually stopped process.');
        # /manually stop DMN process
        
        $req_status = $this->getRequestStatus();
        $dmnType    = Tools::getValue('dmnType');
        
        if ('CARD_TOKENIZATION' == Tools::getValue('type')) {
			$this->module->createLog('DMN CARD_TOKENIZATION accepted.');
            
            header('Content-Type: text/plain');
			echo 'DMN CARD_TOKENIZATION accepted.';
			exit;
		}
        
        $this->validateChecksum();
        
        if (empty($req_status) && !$dmnType) {
			$this->module->createLog('DMN Error - the Status is empty!');
            
            header('Content-Type: text/plain');
			echo 'DMN Error - the Status is empty!';
			exit;
		}
        
        # Subscription State DMN
        if ('subscription' == $dmnType) {
            $subscriptionState  = strtolower(Tools::getValue('subscriptionState'));
			$subscriptionId     = Tools::getValue('subscriptionId');
			$cri_parts          = explode('_', Tools::getValue('clientRequestId'));
            $order_id           = 0;
            
            if (empty($cri_parts) 
                || empty($cri_parts[0]) 
                || !is_numeric($cri_parts[0])
            ) {
				$this->module->createLog($cri_parts, 'DMN Subscription Error with Client Request Id parts:');
                
                header('Content-Type: text/plain');
				exit('DMN Subscription Error with Client Request Id parts.');
			}
            
            $order_id   = (int) $cri_parts[0];
            $this->getOrder($order_id);
            
            if (empty($subscriptionState)) {
                $this->module->createLog($subscriptionState, 'DMN Subscription Error - subscriptionState is empty.');
                
                header('Content-Type: text/plain');
				exit('DMN Subscription Error - subscriptionState is empty');
            }
            
            if ('active' == $subscriptionState) {
                $msg = $this->l('Subscription is Active.') . ' '
                    . $this->l('Subscription ID: ') . $subscriptionId . ' '
                    . $this->l('Plan ID: ') . Tools::getValue('planId');
                
                // save the Subscription ID
                $ord_subscr_ids = '';
                $sql            = "SELECT subscr_ids FROM safecharge_order_data WHERE order_id = " . $order_id;
                $res            = Db::getInstance()->executeS($sql);

                $this->module->createLog($res, 'Order Rebilling data');
                
                if($res && is_array($res)) {
                    $first_res = current($res);
                    
                    if(is_array($first_res) && !empty($first_res['subscr_ids'])) {
                        $ord_subscr_ids = $first_res['subscr_ids'];
                    }
                }

                // just add the ID without the details, we need only the ID to cancel the Subscription
                if (!in_array($subscriptionId, $ord_subscr_ids)) {
                    $ord_subscr_ids = $subscriptionId;
                }

                $sql = "UPDATE `safecharge_order_data` "
                    . "SET subscr_ids = " . $ord_subscr_ids . " "
                    . "WHERE order_id = " . $order_id;
                $res = Db::getInstance()->execute($sql);

                if(!$res) {
                    $this->module->createLog(
                        array(
                            'subscriptionId'    => $subscriptionId,
                            'order_id'          => $order_id,
                        ),
                        'DMN Error - the subscription ID was not added to the Order data',
                        'WARN'
                    );
                }
                // save the Subscription ID END
            }
            elseif ('inactive' == $subscriptionState) {
                $msg = $this->l('Subscription is Inactive.') . ' '
                    . $this->l('Subscription ID: ') . $subscriptionId;
            }
            elseif ('canceled' == $subscriptionState) {
                $msg = $this->l('Subscription was canceled.') . ' '
                    .$this->l('Subscription ID: ') . $subscriptionId;
            }

            $message            = new MessageCore();
            $message->id_order  = $order_id;
            $message->private   = true;
            $message->message   = $msg;
            $message->add();
            
            // save the state
            $sql = "UPDATE `safecharge_order_data` "
                . "SET subscr_state = '" . $subscriptionState . "' "
                . "WHERE order_id = " . $order_id;
            $res = Db::getInstance()->execute($sql);

            if(!$res) {
                $this->module->createLog(
                    array(
                        'subscriptionId'    => $subscriptionId,
                        'order_id'          => $order_id,
                        'message'           => Db::getInstance()->getMsgError(),
                    ),
                    'DMN Error - the Subscription State was not added to the Order data',
                    'WARN'
                );
            }
            
            header('Content-Type: text/plain');
			exit('DMN received.');
        }
        # /Subscription State DMN
        
        $tr_id = Tools::getValue('TransactionID', '');
        
        if(empty($tr_id) || !is_numeric($tr_id)) {
			$this->module->createLog('DMN Error: TransactionID is empty or not numeric.');
			
            header('Content-Type: text/plain');
            exit('DMN Error: TransactionID is empty or not numeric.');
		}
        
        # Subscription Payment DMN
        if ('subscriptionPayment' == $dmnType && 0 != $tr_id) {
            $cri_parts  = explode('_', Tools::getValue('clientRequestId'));
            $order_id   = 0;
            
            if (empty($cri_parts) || empty($cri_parts[0]) || !is_numeric($cri_parts[0])) {
				$this->module->createLog($cri_parts, 'DMN Subscription Error with Client Request Id parts:');
                
                header('Content-Type: text/plain');
				exit('DMN Subscription Error with Client Request Id parts.');
			}
            
            $order_id   = (int) $cri_parts[0];
            $order_info = $this->getOrder($order_id);
            $currency   = new Currency((int)$order_info->id_currency);
            
            $msg = sprintf(
				/* translators: %s: the status of the Payment */
				$this->l('Subscription Payment with Status %s was made. '),
				$req_status
			)
				. $this->l('Plan ID: ') . Tools::getValue('planId') . '. '
				. $this->l('Subscription ID: ') . Tools::getValue('subscriptionId') . '. '
                . $this->l('Amount: ') . $this->module->formatMoney(Tools::getValue('totalAmount'), $currency->iso_code) . ' '
				. $this->l('TransactionId: ') . Tools::getValue('TransactionID') . '.';

			$this->module->createLog($msg, 'Subscription DMN Payment');
			
            $message            = new MessageCore();
            $message->id_order  = $order_id;
            $message->private   = true;
            $message->message   = $msg;
            $message->add();
            
            header('Content-Type: text/plain');
			exit('DMN received.');
        }
        # Subscription Payment DMN END
        
        $transactionType = Tools::getValue('transactionType', '');
        
		if(empty($transactionType)) {
			$this->module->createLog('DMN Error: transactionType is empty.');
			
            header('Content-Type: text/plain');
            exit('DMN Error: transactionType is empty.');
		}
		
		$merchant_unique_id = $this->getCuid();
        
        # Sale and Auth
        if(Tools::getValue('invoice_id') !== false
            && in_array($transactionType, array('Sale', 'Auth'))
        ) {
			$this->dmnSaleAuth($merchant_unique_id, $req_status);
        }
        
        # Refund
        if(in_array($transactionType, array('Credit', 'Refund'))
            && !empty($req_status)
            && !empty(Tools::getValue('relatedTransactionId'))
        ) {
            $this->dmnRefund($req_status);
        }
        
        # Void, Settle
        if(in_array($transactionType, array('Void', 'Settle'))) {
            $this->dmnVoidSettle($req_status, $transactionType);
        }
        
        header('Content-Type: text/plain');
        exit('DMN received, but not recognized.');
    }
	
	/**
	 * Function canOverrideOrderStatus
	 * 
	 * Not all statuses allow override. In this case stop the process.
	 * 
	 * @param int $order_status
	 */
	private function canOverrideOrderStatus($order_status) {
		// Void - can not be changed any more
		if(Configuration::get('PS_OS_CANCELED') == $order_status) {
			$this->module->createLog(
				[
					'TransactionID' => Tools::getValue('TransactionID'),
					'order state' => $order_status
				],
				'DMN Error - can not change status of Voided (Canceld) Order.'
			);

            header('Content-Type: text/plain');
			echo 'DMN Error - can not change status of Voided (Canceld) Order.';
			exit;
		}
		
		// Refund - can not be changed any more, but accept other Refund DMNs
		if(Configuration::get('PS_OS_REFUND') == $order_status
			&& !in_array(strtolower(Tools::getValue('transactionType')), array('credit', 'refund'))
		) {
			$this->module->createLog(
				[
					'TransactionID' => Tools::getValue('TransactionID'),
					'order state' => $order_status
				],
				'DMN Error - can not change status of Refunded Order.'
			);

            header('Content-Type: text/plain');
			echo 'DMN Error - can not change status of Refunded Order.';
			exit;
		}
		
		// Settle and Sale
		if((int) $order_status == (int) Configuration::get('PS_OS_PAYMENT')
			&& strtolower(Tools::getValue('transactionType')) == 'auth'
		) {
			$this->module->createLog(
				[
					'TransactionID' => Tools::getValue('TransactionID'),
					'order state' => $order_status
				],
				'DMN Error - can not change compleated Order to Auth.'
			);

            header('Content-Type: text/plain');
			echo 'DMN Error - can not change compleated Order to Auth.';
			exit;
		}
	}


	/**
	 * Function getOrder
	 * Get the Order by prestaShopOrderID parameter
	 * 
     * @param int the Order ID
	 * @return \Order
	 */
	private function getOrder($order_id = 0) {
		try {
            if(0 == $order_id || !is_numeric($order_id)) {
                if(is_numeric(Tools::getValue('prestaShopOrderID'))) {
                    $order_id = Tools::getValue('prestaShopOrderID');
                }
                elseif(Tools::getValue('relatedTransactionId')) {
                    $sc_data = Db::getInstance()->getRow(
                        'SELECT * FROM safecharge_order_data '
                        . 'WHERE related_transaction_id = "' . Tools::getValue('relatedTransactionId') .'"'
                    );

                    if(empty($sc_data) || empty($sc_data['order_id'])) {
                        header('Content-Type: text/plain');
                        echo 'DMN Error: we can not find Order connected with incoming relatedTransactionId.';
                        exit;
                    }

                    $order_id = $sc_data['order_id'];
                }
            }

			$order_info = new Order($order_id);
			
			if(empty($order_info)) {
				$this->module->createLog('DMN Refund Error - There is no Order for Order ID ' . $order_id);

                header('Content-Type: text/plain');
				echo 'DMN Refund Error - There is no Order for Order ID ' . $order_id;
				exit;
			}

			if($this->module->name != $order_info->module) {
				$this->module->createLog('DMN Error - the order do not belongs to the ' . $this->module->name);

                header('Content-Type: text/plain');
				echo 'DMN Error - the order do not belongs to the ' . $this->module->name;
				exit;
			}
			
			return $order_info;
		}
		catch (Excception $e) {
			$this->module->createLog($e->getMessage(), 'getOrder() exception:');
			
            header('Content-Type: text/plain');
			echo 'getOrder() Exception: ' . $e->getMessage();
			exit;
		}
	}
    
    /**
     * Function changeOrderStatus
     * Change the status of the order.
     * 
     * @param array $order_info
     * @param string $status
     */
    private function changeOrderStatus($order_info, $status)
    {
        $this->module->createLog(
            [
                'Order' => $order_info['id'],
                'Status' => $status,
            ],
            'changeOrderStatus()'
        );
		
        $msg				= '';
        $error_order_status	= '';
		$is_msg_private		= true;
        $message			= new MessageCore();
        $message->id_order	= $order_info['id'];
		$status_id			= $order_info['current_state'];
        $transactionType    = Tools::getValue('transactionType', '');
        $totalAmount        = Tools::getValue('totalAmount', 0);
        $default_msg_start  = $this->l('DMN '. $transactionType .' message.');
        
        $gw_data = $this->l('Status: ') . $this->l($status)
			. $this->l(', PPP Transaction ID: ') . Tools::getValue('PPP_TransactionID')
			. $this->l(', Transaction Type: ') . $transactionType
			. $this->l(', Transaction ID: ') . Tools::getValue('TransactionID')
			. $this->l(', Payment Method: ') . Tools::getValue('payment_method');
        
        $msg = $default_msg_start . ' ' . $gw_data;
        
        switch($status) {
            case 'CANCELED':
                $status_id  = (int)(Configuration::get('PS_OS_CANCELED'));
                break;

            case 'APPROVED':
				$status_id = (int)(Configuration::get('PS_OS_PAYMENT')); // set the Order status to Complete
				
                // Void
                if('Void' == $transactionType) {
                    $status_id  = (int)(Configuration::get('PS_OS_CANCELED'));
                    break;
                }
                
                // Refund
                if(in_array($transactionType, array('Credit', 'Refund'))) {
					$formated_refund    = $this->module->formatMoney($totalAmount, $order_info['currency']);
					$msg                .= $this->l(', Refund Amount: ') . $formated_refund;
					$status_id          = (int)(Configuration::get('PS_OS_REFUND'));
                    
                    break;
                }
                
                if('Auth' == $transactionType) {
                    $msg        = $this->l('The amount has been authorized and wait for Settle.') . ' ' . $gw_data;
					$status_id  = ''; // if we set the id we will have twice this status in the history
                }
                elseif('Settle' == $transactionType) {
                    $msg = $this->l('The amount has been authorized and captured by Nuvei.') . ' ' . $gw_data;
                }
				// compare DMN amount and Order amount
				elseif('Sale' == $transactionType) {
                    $msg            = $this->l('The amount has been authorized and captured by Nuvei.') . ' ' . $gw_data;
					$dmn_amount		= round((float) $totalAmount, 2);
					$order_amount	= round((float) $order_info['total_paid'], 2);
					
					if($dmn_amount !== $order_amount) {
						$error_order_status = (int)(Configuration::get('PS_OS_ERROR'));
					}
				}
                
                break;

            case 'ERROR':
            case 'DECLINED':
            case 'FAIL':
                $error          = $this->l(", Message = ") . Tools::getValue('message', '');
                $reason_holders = ['reason', 'Reason', 'paymentMethodErrorReason', 'gwErrorReason'];
                
                foreach($reason_holders as $key) {
                    if(!empty(Tools::getValue($key))) {
                        $error .= $this->l(', Reason: ') . Tools::getValue($key, '');
                        break;
                    }
                }
                
                $msg = $default_msg_start . ' ' . $gw_data . $error;
                
                // Refund, do not change status
                if(in_array($transactionType, array('Credit', 'Refund'))) {
                    if(0 == $totalAmount) {
                        break;
                    }
                    
                    $formated_refund    = $this->module->formatMoney($totalAmount, $order_info['currency']);
                    $msg                .= $this->l(', Refund Amount: ') . $formated_refund;
                    break;
                }
                
				// Sale or Auth
				if(in_array($transactionType, array('Sale', 'Auth'))) {
					$status_id = (int)(Configuration::get('PS_OS_CANCELED'));
				}
				
                break;

            case 'PENDING':
                $msg        = $default_msg_start . ' ' . $gw_data;
				$status_id  = ''; // set it empty to avoid adding duplicate status in the history
                break;
                
            default:
                $this->module->createLog($status, 'Unexisting status:');
        }
        
        // save order message
		if(!empty($msg)) {
			$message->private = $is_msg_private;
			$message->message = $msg;
			$message->add();
		}
        
        if(empty($status_id)) {
            $this->module->createLog('changeOrderStatus() END. $status_id is empty.');
            return;
        }
        
        // save order history
        $this->module->createLog('changeOrderStatus() - Order status will be set to ' . $status_id);

        $history = new OrderHistory();
        $history->id_order = (int)$order_info['id'];
        $history->changeIdOrderState($status_id, (int)($order_info['id']), !$order_info['has_invoice']);
        $history->add(true);

        // in case ot Payment error
        if(!empty($error_order_status)) {
            // add Error status
            $history->changeIdOrderState($error_order_status, (int)($order_info['id']));
            $history->add(true);

            // get and manipulate Order Payment
            $payment = new OrderPaymentCore();
            $order_payments	= $payment->getByOrderReference($order_info['reference']);

            if(is_array($order_payments) && !empty($order_payments)) {
                $order_payment	= end($order_payments);

                if(round($order_payment->amount, 2) != $dmn_amount) {
                    Db::getInstance()->update(
                        'order_payment',
                        array('amount' => $dmn_amount),
                        "order_reference = '" . $order_info['reference'] . "' AND amount = "
                            . $order_amount . ' AND  	id_order_payment = ' . $order_payment->id
                    );
                }
            }
        }
        
		$this->module->createLog(
			[
                'order id'      => $order_info['id'],
                'status id '    => $status_id
            ],
			'changeOrderStatus() END.'
		);
    }
    
    /**
     * Function getRequestStatus
     * We need this stupid function because as response request variable
     * we get 'Status' or 'status'...
     * 
     * @param array $params
     * @return string
     */
    private function getRequestStatus($params = array())
    {
        if(empty($params)) {
            $params = $_REQUEST;
        }
        
        if(isset($params['Status'])) {
            return $params['Status'];
        }

        if(isset($params['status'])) {
            return $params['status'];
        }
        
        return '';
    }
    
    /**
     * Validate the DMN.
     * On error print message and exit.
     * 
     * @return void
     */
    private function validateChecksum()
    {
        $advanceResponseChecksum = Tools::getValue('advanceResponseChecksum');
		$responsechecksum        = Tools::getValue('responsechecksum');
        
        if (empty($advanceResponseChecksum) && empty($responsechecksum)) {
			$this->module->createLog('Error - advanceResponseChecksum and responsechecksum parameters are empty.');
            
            header('Content-Type: text/plain');
            exit('DMN Error - advanceResponseChecksum and responsechecksum parameters are empty.');
		}
        
        # advanceResponseChecksum case
        if (!empty($advanceResponseChecksum)) {
            
            $str = Tools::getValue('totalAmount')
                . Tools::getValue('currency') 
                . Tools::getValue('responseTimeStamp')
                . Tools::getValue('PPP_TransactionID') 
                . $this->getRequestStatus()
                . Tools::getValue('productId');
            
            $full_str   = Configuration::get('SC_SECRET_KEY') . $str;
            $hash_str   = hash(Configuration::get('SC_HASH_TYPE'), $full_str);
            
            if ($hash_str != $advanceResponseChecksum) {
                $this->module->createLog($str, 'Error - advanceResponseChecksum validation fail.');
                
                header('Content-Type: text/plain');
                exit('DMN Error - advanceResponseChecksum validation fail.');
            }
            
            return;
        }
        
        # subscription DMN with responsechecksum case
        $concat                 = '';
        $request_params_keys    = array_keys($_REQUEST);
        $custom_params_keys     = array(
			'prestaShopAction',
			'test_mode',
			'sc_stop_dmn',
			'responsechecksum',
		);
        
        $dmn_params_keys = array_diff($request_params_keys, $custom_params_keys);
        
        foreach($dmn_params_keys as $key) {
            $concat .= Tools::getValue($key, '');
        }
        
        $concat_final   = $concat . Configuration::get('SC_SECRET_KEY');
        $checksum       = hash(Configuration::get('SC_HASH_TYPE'), $concat_final);
        
        if ($responsechecksum != $checksum) {
            $this->module->createLog(
                [
                    'urldecode($concat)' => urldecode($concat),
                    'utf8_encode($concat)' => utf8_encode($concat),
                ],
                'Error - responsechecksum validation fail.'
            );
            
            header('Content-Type: text/plain');
            exit('DMN Error - responsechecksum validation fail.');
		}
		
        return;
    }
    
    /**
     * Function updateCustomPaymentFields
     * Update Order Custom Payment Fields
     * 
     * @param int $order_id
	 * @return bool
     */
    private function updateCustomPaymentFields($order_id)
    {
		$trans_id = !empty(Tools::getValue('sc_transaction_id', ''))
			? Tools::getValue('sc_transaction_id', '') : Tools::getValue('TransactionID', '');
		
        $data = array('order_id' => $order_id);
		
		// do not update empty values
		if(!empty($auth = Tools::getValue('AuthCode', ''))) {
			$data['auth_code'] = intval(Tools::getValue('AuthCode', ''));
		}
		if(!empty($trans_id)) {
			$data['related_transaction_id'] = intval($trans_id);
		}
		if(!empty($tr_type = Tools::getValue('transactionType', ''))) {
			$data['resp_transaction_type'] = filter_var($tr_type, FILTER_SANITIZE_STRING);
		}
		if(!empty($pm = Tools::getValue('payment_method', ''))) {
			$data['payment_method'] = filter_var($pm, FILTER_SANITIZE_STRING);
		}
		// do not update empty values END
		
		$fields_strings = implode(", ", array_keys($data));
		$values_string	= "'" . implode("', '", $data) . "'";
		$update_array	= array();
		
		foreach($data as $field => $val) {
			$update_array[] = $field . "='" . $val . "'";
		}
		
		$query = "INSERT INTO safecharge_order_data ({$fields_strings}) VALUES ({$values_string}) "
			. "ON DUPLICATE KEY UPDATE " . implode(", ", $update_array) . ";";
		
		try {
			$res = Db::getInstance()->execute($query);
		}
		catch (Exception $e) {
			$this->module->createLog($e->getMessage(), 'updateCustomPaymentFields Exception');
			$this->module->createLog($query, '$query');
			return false;
		}
		
		if(!$res) {
			$this->module->createLog(Db::getInstance()->getMsgError(), 'updateCustomPaymentFields response error');
			$this->module->createLog($query, '$query');
		}
		
		return $res;
    }
	
	private function beforeSuccess()
	{
        $this->module->createLog(null, 'beforeSuccess()', 'DEBUG');
        
		$error_url = $this->context->link->getModuleLink(
			$this->module->name,
			'payment',
			array(
				'prestaShopAction'	=> 'showError',
				'id_cart'			=> (int) Tools::getValue('id_cart', 0),
			)
		);
		
		$payment_method = str_replace('apmgw_', '', Tools::getValue('payment_method', ''));
		
		if(empty($payment_method) || is_numeric($payment_method)) {
			$payment_method = str_replace('apmgw_', '', Tools::getValue('upo_name', ''));
		}
		
		// save order
		$res = $this->module->validateOrder(
			(int) Tools::getValue('id_cart', 0)
			,Configuration::get('SC_OS_AWAITING_PAIMENT') // the status
			,Tools::getValue('amount', 0)
			,$this->module->displayName . ' - ' . $payment_method // payment_method
			,'' // message
			,array() // extra_vars
			,null // currency_special
			,false // dont_touch_amount
			,Tools::getValue('key', $this->context->cart->secure_key) // secure_key
		);

		if(!$res) {
			Tools::redirect($error_url);
		}
		
        if(!empty($this->context->cookie->nuvei_last_open_order_details)) {
            $this->context->cookie->__unset('nuvei_last_open_order_details');
        }
		
		$this->module->createLog('beforeSuccess() - the Order was saved.');
		
		$success_url = $this->context->link->getPageLink(
			'order-confirmation',
			null,
			null,
			array(
				'id_cart'   => (int) Tools::getValue('id_cart', 0),
				'id_module' => (int)$this->module->id,
				'id_order'  => Tools::getValue('id_order', 0),
				'key'       => Tools::getValue('key', '')
			)
		);
		
		Tools::redirect($success_url);
	}
    
    /**
     * Function validate
     * Validate process
     * 
     * @param Cart $cart
     * @return Customer
     */
    private function validate($cart)
    {
        if ($cart->id_customer == 0
            || $cart->id_address_delivery == 0 
            || $cart->id_address_invoice == 0 
            || !$this->module->active
        ) {
            $this->module->createLog(
				array(
					'$cart->id_customer' => $cart->id_customer,
					'$cart->id_address_delivery' => $cart->id_address_delivery,
					'$cart->id_address_invoice' => $cart->id_address_invoice,
					'$this->module->active' => $this->module->active,
				),
				'Validate error',
				$this->module->version
			);
            
			Tools::redirect($this->context->link->getPageLink('order'));
        }

        $authorized = false;
        foreach (Module::getPaymentModules() as $module) {
            if ($this->module->name == $module['name'] && $this->module->isModuleActive()) {
                $authorized = true;
                break;
            }
        }
        
        if (!$authorized) {
            $this->module->createLog(Module::getPaymentModules(), 'This payment method is not available:');
            
			Tools::redirect($this->context->link->getModuleLink(
				$this->module->name,
				'payment',
				array('prestaShopAction' => 'showError')
			));
        }

        $customer = new Customer($cart->id_customer);
        
        if (!Validate::isLoadedObject($customer)) {
            $this->module->createLog($customer, '$customer:', $this->module->version);
            Tools::redirect($this->context->link->getPageLink('order'));
        }
        
        return $customer;
    }
	
	/**
	 * Function getCuid
	 * 
	 * Get client unique id.
	 * We change it only for Sandbox (test) mode.
	 * 
	 * @return int|string
	 */
	private function getCuid()
    {
        return current(explode('_', Tools::getValue('merchant_unique_id')));
	}
    
    /**
     * Help method for Auth and Sale DMNs logic.
     * 
     * @param int       $merchant_unique_id this is the Cart ID
     * @param string    $req_status the Status of the Request
     */
    private function dmnSaleAuth($merchant_unique_id, $req_status)
    {
        // REST and WebSDK
        $this->module->createLog(Tools::getValue('TransactionID'), 'DMN Report - REST sale.');

        if(!Tools::getValue('merchant_unique_id', false)) {
            $this->module->createLog(
                Tools::getValue('TransactionID'),
                'DMN Error - merchant_unique_id is empty!'
            );

            header('Content-Type: text/plain');
            echo 'DMN Error - merchant_unique_id is empty!';
            exit;
        }

        try {
            $order_info         = null;
            $tries				= 0;
            $order_id			= false;
            $max_tries			= 5;
            $order_request_time	= Tools::getValue('customField4'); // time of create/update order

            // do not search more than once for Auth and Sale, if the DMN response time is more than 24 hours before now
            if($order_request_time && (time() - $order_request_time > 3600 ) ) {
                $max_tries = 0;
            }

            do {
                $tries++;
                $order_id = Order::getIdByCartId($merchant_unique_id);

                $this->module->createLog($order_id, 'order_id');

                if(!$order_id) {
                    $this->module->createLog(
                        Tools::getValue('TransactionID'),
                        'DMN Report - the DMN wait for the order.'
                    );

                    sleep(3);
                }
                else {
                    $order_info	= new Order($order_id);

                    // check for slow Prestashop slow saving process
                    if(empty($order_info->current_state)) {
                        $this->module->createLog(
                            Tools::getValue('TransactionID'),
                            'DMN Error - current order state is 0. Wait to refresh the State.'
                        );

                        sleep(3);
                    }
                }
            }
            while( $tries <= $max_tries && (!$order_id || empty($order_info->current_state)) );

            $payment_method = str_replace('apmgw_', '', Tools::getValue('payment_method', ''));
            
            // try to create an Order by DMN data
            if(!$order_id) {
                // do not create order for Declined transaction
                if(strtolower($this->getRequestStatus()) != 'approved') {
                    $this->module->createLog(
                        Tools::getValue('TransactionID'),
                        'DMN Error - Not Approved DMN for not existing order - stop process.'
                    );
                    
                    header('Content-Type: text/plain');
                    echo 'DMN Error - Not Approved DMN for not existing order - stop process.';
                    exit;
                }

                // Approved Transaction - continue porocess
                $this->module->createLog(
                    Tools::getValue('TransactionID'),
                    'The DMN didn\'t wait for the Order creation. Try to save order by the DMN.'
                );

                $this->module->createLog(
                    array(
                        $merchant_unique_id
                        ,Configuration::get('SC_OS_AWAITING_PAIMENT') // the status
                        ,floatval(Tools::getValue('totalAmount', 0))
                        ,$this->module->displayName . ' - ' . $payment_method
                        ,'' // message
                        ,array(
                            'transaction_id' => Tools::getValue('TransactionID', false)
                        ) // extra_vars
                        ,null // currency_special
                        ,false // dont_touch_amount
                        ,Tools::getValue('customField1', '')
                    ),
                    'validateOrder params'
                );

                // try to create Order here
                $res = $this->module->validateOrder(
                    (int) $merchant_unique_id
                    ,Configuration::get('SC_OS_AWAITING_PAIMENT') // the status
                    ,floatval(Tools::getValue('totalAmount', 0))
                    ,$this->module->displayName . ' - ' . $payment_method // payment_method
                    ,'' // message
                    ,array(
                        'transaction_id' => Tools::getValue('TransactionID', false)
                    ) // extra_vars
                    ,null // currency_special
                    ,false // dont_touch_amount
                    ,Tools::getValue('customField1', '') // secure_key
                );

                if(!$res) {
                    $this->module->createLog(
                        Tools::getValue('TransactionID'),
                        'DMN Error - Order was not validated'
                    );

                    http_response_code(400);
                    header('Content-Type: text/plain');
                    echo 'DMN Error - Order was not validated';
                    exit;
                }

                if(!empty($this->context->cookie->nuvei_last_open_order_details)) {
					$this->context->cookie->__unset('nuvei_last_open_order_details');
				}

                $this->module->createLog(
                    Tools::getValue('TransactionID'),
                    'DMN Report - An Order was made.'
                );

                $order_id	= $this->module->currentOrder;
                $order_info	= new Order($order_id);
            } // if no Order ID

            // check if the Order belongs to this module
            if($this->module->name != $order_info->module) {
                $this->module->createLog(
                    [
                        'TransactionID' => Tools::getValue('TransactionID'),
                        'order module' => $order_info->module
                    ],
                    'DMN Error - the Order do not belongs to the ' . $this->module->name
                );

                header('Content-Type: text/plain');
                echo 'DMN Error - the Order do not belongs to the ' . $this->module->name;
                exit;
            }

            // is overriding status allowed
            $this->canOverrideOrderStatus($order_info->current_state);

            // check for transaction Id after sdk Order
            $payment		= new OrderPaymentCore();
            $order_payments	= $payment->getByOrderReference($order_info->reference);
            $insert_data	= true; // false for update

            if(!empty($order_payments) && is_array($order_payments)) {
                $last_payment = end($order_payments);

                if('' != $last_payment->transaction_id
                    && $last_payment->transaction_id != Tools::getValue('TransactionID', 'int')
                ) {
                    $this->module->createLog(
                        array(
                            'DMN TransactionID' => Tools::getValue('TransactionID', 'int'),
                            'Last Payment transaction_id' => $last_payment->transaction_id
                        ),
                        'DMN Error - DMN TransactionID does not mutch Last Payment transaction_id'
                    );

                    header('Content-Type: text/plain');
                    echo 'DMN Error - DMN TransactionID does not mutch Last Payment transaction_id';
                    exit;
                }

                $insert_data = false;
            }
            // check for transaction Id after sdk Order END

            // wrong amount check
            $order_amount	= round(floatval($order_info->total_paid), 2);
            $dmn_amount		= round(Tools::getValue('totalAmount', 0), 2);

            if($order_amount != $dmn_amount) {
                $this->module->createLog(
                    array(
                        'DMN totalAmount' => $dmn_amount,
                        'Order Amount' => $order_amount
                    ),
                    'DMN Error - DMN totalAmount does not mutch Order Amount'
                );
            }
            // wrong amount check

            # check for previous DMN data
            $sc_data = Db::getInstance()->getRow(
                'SELECT * FROM safecharge_order_data '
                . 'WHERE order_id = ' . $order_id
            );

            // there is prevous DMN data
            if(!empty($sc_data) && 'declined' == strtolower($req_status)) {
                $this->module->createLog('DMN Error - Declined DMN for already Approved Order. Stop process here.');

                header('Content-Type: text/plain');
                echo 'DMN Error - Declined DMN for already Approved Order. Process Stops here.';
                exit;
            }
            # check for previous DMN data END

            $this->updateCustomPaymentFields($order_id, $insert_data);

            if((int) $order_info->current_state != (int) Configuration::get('PS_OS_PAYMENT')
                && (int) $order_info->current_state != (int) Configuration::get('PS_OS_ERROR')
            ) {
                $this->changeOrderStatus(
                    array(
                        'id'            => $order_id,
                        'current_state' => $order_info->current_state,
                        'has_invoice'	=> $order_info->hasInvoice(),
                        'total_paid'	=> $order_info->total_paid,
                        'id_customer'	=> $order_info->id_customer,
                        'reference'		=> $order_info->reference,
                    )
                    ,$req_status
                );
            }
            
            $this->module->createLog(
                [$order_info->payment, strpos($order_info->payment, 'APM')],
                'DMN Order payment method'
            );
            
            // update the payment method
            if(empty($order_info->payment) || false !== strpos($order_info->payment, 'APM')) {
                $ord_payment_method = $order_info->payment;
                $new_payment_method = str_replace(
                    'APM',
                    DB::getInstance()->escape($payment_method, false, true),
                    $ord_payment_method
                );
                
                $query = "UPDATE " . _DB_PREFIX_ . "order_payment AS op "
                    . "LEFT JOIN " . _DB_PREFIX_ . "orders AS o "
                    . "ON op.order_reference = o.reference "
                    . "SET op.payment_method = '" . $new_payment_method . "', "
                    . "o.payment = '" . $new_payment_method . "' "
                    . "WHERE op.transaction_id = " . (int) Tools::getValue('TransactionID');
                
                $resp = Db::getInstance()->execute($query);
                
                $this->module->createLog(
                    [$new_payment_method, $query, $resp],
                    'DMN Order update payment method'
                );
            }
                
            // try to start a Subscription
            $currency = new Currency((int)$order_info->id_currency);
            
            $this->startSubscription($order_id, $currency->iso_code);
        }
        catch (Exception $ex) {
            $this->module->createLog(
                [
                    $ex->getMessage(),
                    $ex->getTrace()
                ],
                'Sale DMN Exception:', 'CRITICAL'
            );

            header('Content-Type: text/plain');
            echo 'DMN Exception: ' . $ex->getMessage();
            exit;
        }

        header('Content-Type: text/plain');
        echo 'DMN received.';
        exit;
    }
    
    /**
     * Help function for the Refund DMNs
     * 
     * @param string $req_status the Status of the Request
     */
    private function dmnRefund($req_status)
    {
        $this->module->createLog('PrestaShop Refund DMN.');
            
        $order_info	= $this->getOrder();

        // is overriding status allowed
        $this->canOverrideOrderStatus($order_info->current_state);

        try {
            $currency = new Currency((int)$order_info->id_currency);

            $this->changeOrderStatus(
                array(
                    'id'            => $order_info->id,
                    'current_state' => $order_info->current_state,
                    'has_invoice'	=> $order_info->hasInvoice(),
                    'currency'      => $currency->iso_code,
                )
                ,$req_status
            );

            header('Content-Type: text/plain');
            echo 'DMN received.';
            exit;
        }
        catch (Excception $e) {
            $this->module->createLog($e->getMessage(), 'Refund DMN exception:');
            
            header('Content-Type: text/plain');
            echo 'DMN Exception: ' . $e->getMessage();
            exit;
        }
    }
    
    /**
     * Help method for the Void and the Settle DMNs
     * 
     * @param string $req_status the Status of the Request
     * @param string $transactionType Void or Settle
     */
    private function dmnVoidSettle($req_status, $transactionType)
    {
        $this->module->createLog($transactionType, 'dmnVoidSettle() transactionType:');
            
        try {
            $order_info = $this->getOrder();

            // is overriding status allowed
            $this->canOverrideOrderStatus($order_info->current_state);
            
            if($transactionType == 'Settle') {
                $this->updateCustomPaymentFields($order_info->id, false);
            }

            $this->changeOrderStatus(
                array(
                    'id'            => $order_info->id,
                    'current_state' => $order_info->current_state,
                    'has_invoice'	=> $order_info->hasInvoice(),
                )
                ,$req_status
            );
            
            if('Void' == $transactionType && 'APPROVED' == $req_status) {
                $this->module->cancel_subscription($order_info->id);
            }
            else { // Settle, try to start a Subscription
                $currency = new Currency((int)$order_info->id_currency);
                $this->startSubscription($order_info->id, $currency->iso_code);
            }
        }
        catch (Exception $ex) {
            $this->module->createLog($ex->getMessage(), 'processDmn() Void/Settle Exception:');
        }

        header('Content-Type: text/plain');
        echo 'DMN received.';
        exit;
    }
    
    /**
     * Create subscription logic.
     * 
     * @param int $order_id
     * @param string $currency
     * 
     * @return void
     */
    private function startSubscription($order_id, $currency)
    {
        $this->module->createLog('Try to start Subscription.');
        
        if($this->getRequestStatus() != 'APPROVED') {
            $this->module->createLog('We can not start Subscription for not APPROVED transaction.');
            return;
        }
        
        if(!in_array(Tools::getValue('transactionType'), array('Sale', 'Settle'))
            || empty(Tools::getValue('customField5'))
        ) {
            $this->module->createLog('There is no Subscription data.');
            return;
        }
        
        $prod_plan = json_decode(Tools::getValue('customField5'), true);
        
        if (empty($prod_plan) || !is_array($prod_plan)) {
			$this->module->createLog(
                $prod_plan,
                'DMN Payment Plan data is empty or wrong format. We will not start a Payment plan.'
            );
            
			return;
		}
        
        $params = array_merge(
			array(
                'clientRequestId'       => $order_id . '_' . uniqid(),
				'userPaymentOptionId'   => Tools::getValue('userPaymentOptionId'),
				'userTokenId'           => Tools::getValue('user_token_id'),
				'currency'              => Tools::getValue('currency'),
				'initialAmount'         => 0,
			),
			$prod_plan
		);
        
        $msg                = '';
        $message            = new MessageCore();
        $message->id_order  = $order_id;
        
        $resp = $this->module->callRestApi(
            'createSubscription',
            $params,
            array('merchantId', 'merchantSiteId', 'userTokenId', 'planId', 'userPaymentOptionId', 'initialAmount', 'recurringAmount', 'currency', 'timeStamp')
        );

        // On Error
        if (!$resp || !is_array($resp) || 'SUCCESS' != $resp['status']) {
            $msg = $this->l('Error when try to start a Subscription by the Order.');

            if (!empty($resp['reason'])) {
                $msg .= ' ' . $this->l('Reason: ') . $resp['reason'];
            }

            // save message
            $message->private = true;
            $message->message = $msg;
            $message->add();
        }

        // On Success
        $msg = $this->l('Subscription was created. ')  . ' '
            . $this->l('Subscription ID: ') . $resp['subscriptionId'] . '. '
            . $this->l('Recurring amount: ') . $this->module->formatMoney($prod_plan['recurringAmount'], $currency) . '.';

        // save message
        $message->private = true;
        $message->message = $msg;
        $message->add();
    }
    
}
