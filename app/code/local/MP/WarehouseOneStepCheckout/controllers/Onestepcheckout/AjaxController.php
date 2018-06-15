<?php
/**
 * Mage Plugins, Inc
 * 
 * NOTICE OF LICENSE
 * 
 * This source file is subject to the InnoExts Commercial License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://mageplugins.net/commercial-license-agreement
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to mageplugins@gmail.com so we can send you a copy immediately.
 * 
 * @category    MP
 * @package     MP_WarehouseOneStepCheckout
 * @copyright   Copyright (c) 2012-2018 Mage Plugins, Inc. (http://mageplugins.net)
 * @license     https://mageplugins.net/commercial-license-agreement  InnoExts Commercial License
 */

require_once 'Idev/OneStepCheckout/controllers/AjaxController.php';

/**
 * Cart controller
 * 
 * @category   MP
 * @package    MP_WarehouseOneStepCheckout
 * @author     Mage Plugins <mageplugins@gmail.com>
 */
class MP_WarehouseOneStepCheckout_Onestepcheckout_AjaxController 
    extends Idev_OneStepCheckout_AjaxController 
{
    /**
     * Get warehouse helper
     * 
     * @return MP_Warehouse_Helper_Data
     */
    protected function getWarehouseHelper()
    {
        return Mage::helper('warehouse');
    }
    /**
     * Get template sub-directory
     * 
     * @return string
     */
    protected function getTemplateSubDirectory()
    {
        return 'warehouseonestepcheckout';
    }

    /**
     * Add coupon
     */
    public function add_couponAction()
    {
        $config     = $this->getWarehouseHelper()->getConfig();
        
        $quote = $this->_getOnepage()->getQuote();
        $couponCode = (string)$this->getRequest()->getParam('code');
        if ($this->getRequest()->getParam('remove') == 1) {
            $couponCode = '';
        }
        $response = array(
            'success' => false,
            'error'=> false,
            'message' => false,
        );
        try {
            
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                foreach ($quote->getAllShippingAddresses() as $address) {
                    $address->setCollectShippingRates(true);
                }
            } else {
                $address = $quote->getShippingAddress();
                $address->setCollectShippingRates(true);
            }
            
            $quote->setCouponCode(strlen($couponCode) ? $couponCode : '')
                ->collectTotals()
                ->save();
            if ($couponCode) {
                if ($couponCode == $quote->getCouponCode()) {
                    $response['success'] = true;
                    $response['message'] = $this->__('Coupon code "%s" was applied successfully.', Mage::helper('core')->escapeHtml($couponCode));
                }
                else {
                    $response['success'] = false;
                    $response['error'] = true;
                    $response['message'] = $this->__('Coupon code "%s" is not valid.', Mage::helper('core')->escapeHtml($couponCode));
                }
            } else {
                $response['success'] = true;
                $response['message'] = $this->__('Coupon code was canceled successfully.');
            }
        } catch (Mage_Core_Exception $e) {
            $response['success'] = false;
            $response['error'] = true;
            $response['message'] = $e->getMessage();
        } catch (Exception $e) {
            $response['success'] = false;
            $response['error'] = true;
            $response['message'] = $this->__('Can not apply coupon code.');
        }
        
        $html = $this->getLayout()
            ->createBlock('checkout/onepage_shipping_method_available')
            ->setTemplate($this->getTemplateSubDirectory().'/onestepcheckout/shipping_method.phtml')
            ->setSingleModeRenderer(
                'warehouse/checkout_onepage_shipping_method_available_singlemode', 
                'warehouseonestepcheckout/onestepcheckout/shipping_method/single_mode.phtml'
            )->setMultipleModeRenderer(
                'warehouse/checkout_onepage_shipping_method_available_multiplemode', 
                'warehouseonestepcheckout/onestepcheckout/shipping_method/multiple_mode.phtml'
            )->toHtml();
        
        $response['shipping_method'] = $html;
        $html = $this->getLayout()
            ->createBlock('checkout/onepage_payment_methods','choose-payment-method')
            ->setTemplate('onestepcheckout/payment_method.phtml');
        if(Mage::helper('onestepcheckout')->isEnterprise() && Mage::helper('customer')->isLoggedIn()){
            $customerBalanceBlock = $this->getLayout()->createBlock('enterprise_customerbalance/checkout_onepage_payment_additional', 'customerbalance', array('template'=>'onestepcheckout/customerbalance/payment/additional.phtml'));
            $customerBalanceBlockScripts = $this->getLayout()->createBlock('enterprise_customerbalance/checkout_onepage_payment_additional', 'customerbalance_scripts', array('template'=>'onestepcheckout/customerbalance/payment/scripts.phtml'));
            $rewardPointsBlock = $this->getLayout()->createBlock('enterprise_reward/checkout_payment_additional', 'reward.points', array('template'=>'onestepcheckout/reward/payment/additional.phtml', 'before' => '-'));
            $rewardPointsBlockScripts = $this->getLayout()->createBlock('enterprise_reward/checkout_payment_additional', 'reward.scripts', array('template'=>'onestepcheckout/reward/payment/scripts.phtml', 'after' => '-'));
            $this->getLayout()->getBlock('choose-payment-method')
                ->append($customerBalanceBlock)
                ->append($customerBalanceBlockScripts)
                ->append($rewardPointsBlock)
                ->append($rewardPointsBlockScripts);
        }
        if(Mage::helper('onestepcheckout')->isEnterprise()){
            $giftcardScripts = $this->getLayout()->createBlock('enterprise_giftcardaccount/checkout_onepage_payment_additional', 'giftcardaccount_scripts', array('template'=>'onestepcheckout/giftcardaccount/onepage/payment/scripts.phtml'));
            $html->append($giftcardScripts);
        }
        $response['payment_method'] = $html->toHtml();
          // Add updated totals HTML to the output
        
        $html = $this->getLayout()
            ->createBlock('onestepcheckout/summary')
            ->setTemplate($this->getTemplateSubDirectory().'/onestepcheckout/summary.phtml')
            ->toHtml();
        
        $response['summary'] = $html;
        $this->getResponse()->setBody(Zend_Json::encode($response));
    }
    /**
     * Add gift card 
     */
    public function add_giftcardAction(){
        $config     = $this->getWarehouseHelper()->getConfig();
        
        $response = array(
            'success' => false,
            'error'=> true,
            'message' => $this->__('Cannot apply Gift Card, please try again later.'),
        );
        $code = $this->getRequest()->getParam('code', false);
        $remove = $this->getRequest()->getParam('remove', false);
        if (!empty($code) && empty($remove)) {
            try {
                Mage::getModel('enterprise_giftcardaccount/giftcardaccount')
                    ->loadByCode($code)
                    ->addToCart();
                $response['success'] = true;
                $response['error'] = false;
                $response['message'] = $this->__('Gift Card "%s" was added successfully.', Mage::helper('core')->escapeHtml($code));

            } catch (Mage_Core_Exception $e) {
                Mage::dispatchEvent('enterprise_giftcardaccount_add', array('status' => 'fail', 'code' => $code));
                $response['success'] = false;
                $response['error'] = true;
                $response['message'] = $e->getMessage();
            } catch (Exception $e) {
                Mage::getSingleton('checkout/session')->addException(
                    $e,
                    $this->__('Cannot apply Gift Card, please try again later.')
                );
                $response['success'] = false;
                $response['error'] = true;
                $response['message'] = $this->__('Cannot apply Gift Card, please try again later.');

            }
        }
        if(!empty($remove)){
            $codes = $this->_getOnepage()->getQuote()->getGiftCards();
            if(!empty($codes)){
                $codes = unserialize($codes);
            } else {
                $codes = array();
            }
            $response['message'] = $this->__('Cannot remove Gift Card, please try again later.');
            $messageCodes = array();
            foreach($codes as $value){
                try {
                    Mage::getModel('enterprise_giftcardaccount/giftcardaccount')
                        ->loadByCode($value['c'])
                        ->removeFromCart();
                    $messageCodes[] = $value['c'];
                    $response['success'] = true;
                    $response['error'] = false;
                    $response['message'] = $this->__('Gift Card "%s" was removed successfully.', Mage::helper('core')->escapeHtml(implode(', ',$messageCodes)));
                } catch (Mage_Core_Exception $e) {
                    $response['success'] = false;
                    $response['error'] = true;
                    $response['message'] = $e->getMessage();
                } catch (Exception $e) {
                    Mage::getSingleton('checkout/session')->addException(
                        $e,
                        $this->__('Cannot remove Gift Card, please try again later.')
                    );
                    $response['success'] = false;
                    $response['error'] = true;
                    $response['message'] = $this->__('Cannot remove Gift Card, please try again later.');

                }
            }
        }
        
        // Add updated totals HTML to the output
        $html = $this->getLayout()
            ->createBlock('onestepcheckout/summary')
            ->setTemplate($this->getTemplateSubDirectory().'/onestepcheckout/summary.phtml')
            ->toHtml();
        
        $response['summary'] = $html;
        
        $html = $this->getLayout()
            ->createBlock('checkout/onepage_shipping_method_available')
            ->setTemplate($this->getTemplateSubDirectory().'/onestepcheckout/shipping_method.phtml')
            ->setSingleModeRenderer(
                'warehouse/checkout_onepage_shipping_method_available_singlemode', 
                'warehouseonestepcheckout/onestepcheckout/shipping_method/single_mode.phtml'
            )->setMultipleModeRenderer(
                'warehouse/checkout_onepage_shipping_method_available_multiplemode', 
                'warehouseonestepcheckout/onestepcheckout/shipping_method/multiple_mode.phtml'
            )
            ->toHtml();
        $response['shipping_method'] = $html;
        
        $html = $this->getLayout()
            ->createBlock('checkout/onepage_payment_methods')
            ->setTemplate('onestepcheckout/payment_method.phtml');
        if(Mage::helper('onestepcheckout')->isEnterprise()){
            $giftcardScripts = $this->getLayout()->createBlock('enterprise_giftcardaccount/checkout_onepage_payment_additional', 'giftcardaccount_scripts', array('template'=>'onestepcheckout/giftcardaccount/onepage/payment/scripts.phtml'));
            $html->append($giftcardScripts);
        }
        $response['payment_method'] = $html->toHtml();
        $this->getResponse()->setBody(Zend_Json::encode($response));
    }
    /**
     * Save billing
     */
    public function save_billingAction()
    {
        $config     = $this->getWarehouseHelper()->getConfig();

        $helper = Mage::helper('onestepcheckout/checkout');

        $billing_data = $this->getRequest()->getPost('billing', array());
        $shipping_data = $this->getRequest()->getPost('shipping', array());
        $customerAddressId = $this->getRequest()->getPost('billing_address_id', false);
        $shippingAddressId = $this->getRequest()->getPost('shipping_address_id', false);

        $billing_data = $helper->load_exclude_data($billing_data);
        $shipping_data = $helper->load_exclude_data($shipping_data);

        if(Mage::helper('customer')->isLoggedIn() && $helper->differentShippingAvailable()){
            if(!empty($customerAddressId)){
                $billingAddress = Mage::getModel('customer/address')->load($customerAddressId);
                if(is_object($billingAddress) && $billingAddress->getCustomerId() ==  Mage::helper('customer')->getCustomer()->getId()){
                    $billing_data = array_merge($billing_data, $billingAddress->getData());
                }
            }
            if(!empty($shippingAddressId)){
                $shippingAddress = Mage::getModel('customer/address')->load($shippingAddressId);
                if(is_object($shippingAddress) && $shippingAddress->getCustomerId() ==  Mage::helper('customer')->getCustomer()->getId()){
                    $shipping_data = array_merge($shipping_data, $shippingAddress->getData());
                }
            }
        }

        if(!empty($billing_data['use_for_shipping'])) {
           $shipping_data = $billing_data;
        }
        
        // set customer tax/vat number for further usage
        $taxid = '';
        if(!empty($billing_data['taxvat'])){
            $taxid = $billing_data['taxvat'];
        } else if(!empty($billing_data['vat_id'])){
            $taxid = $billing_data['vat_id'];
        }
        if (!empty($taxid)) {
            $this->_getOnepage()->getQuote()->setCustomerTaxvat($taxid);
            $this->_getOnepage()->getQuote()->setTaxvat($taxid);
            $this->_getOnepage()->getQuote()->getBillingAddress()->setTaxvat($taxid);
            $this->_getOnepage()->getQuote()->getBillingAddress()->setTaxId($taxid);
            $this->_getOnepage()->getQuote()->getBillingAddress()->setVatId($taxid);
        } else {
            $this->_getOnepage()->getQuote()->setCustomerTaxvat('');
            $this->_getOnepage()->getQuote()->setTaxvat('');
            $this->_getOnepage()->getQuote()->getBillingAddress()->setTaxvat('');
            $this->_getOnepage()->getQuote()->getBillingAddress()->setTaxId('');
            $this->_getOnepage()->getQuote()->getBillingAddress()->setVatId('');
        }

        $this->_getOnepage()->getQuote()->getBillingAddress()->addData($billing_data)->implodeStreetAddress()->setCollectShippingRates(true);
        
        if(!$this->_getOnepage()->getQuote()->isVirtual() && !Mage::helper('customer')->isLoggedIn()){
            
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                foreach ($this->_getOnepage()->getQuote()->getAllShippingAddresses() as $address) {
                    $address->addData($shipping_data)->implodeStreetAddress()->setCollectShippingRates(true);
                }
            } else {
                $address = $this->_getOnepage()->getQuote()->getShippingAddress();
                $address->addData($shipping_data)->implodeStreetAddress()->setCollectShippingRates(true);
            }
            
        }

        $paymentMethod = $this->getRequest()->getPost('payment_method', false);
        $selectedMethod = $this->_getOnepage()->getQuote()->getPayment()->getMethod();

        $store = $this->_getOnepage()->getQuote() ? $this->_getOnepage()->getQuote()->getStoreId() : null;
        $methods = $helper->getActiveStoreMethods($store, $this->_getOnepage()->getQuote());

        if($paymentMethod && !empty($methods) && !in_array($paymentMethod, $methods)){
            $paymentMethod = false;
        }

        if(!$paymentMethod && $selectedMethod && in_array($selectedMethod, $methods)){
             $paymentMethod = $selectedMethod;
        }

        if($this->_getOnepage()->getQuote()->isVirtual()) {
            $this->_getOnepage()->getQuote()->getBillingAddress()->setPaymentMethod(!empty($paymentMethod) ? $paymentMethod : null);
        } else {
            
            $quote = $this->_getOnepage()->getQuote();
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                foreach ($quote->getAllShippingAddresses() as $address) {
                    $address->setPaymentMethod(!empty($paymentMethod) ? $paymentMethod : null);
                }
            } else {
                $address = $quote->getShippingAddress();
                $address->setPaymentMethod(!empty($paymentMethod) ? $paymentMethod : null);
            }

        }

        try {
            if($paymentMethod){
                $this->_getOnepage()->getQuote()->getPayment()->getMethodInstance();
            }
        } catch (Exception $e) {
        }

        // $result = $this->_getOnepage()->saveBilling($billing_data, $customerAddressId);
        
        if(Mage::helper('customer')->isLoggedIn()){
            $this->_getOnepage()->getQuote()->getBillingAddress()->setSaveInAddressBook(empty($billing_data['save_in_address_book']) ? 0 : 1);
            
            $quote = $this->_getOnepage()->getQuote();
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                foreach ($quote->getAllShippingAddresses() as $address) {
                    $address->setSaveInAddressBook(empty($shipping_data['save_in_address_book']) ? 0 : 1);
                }
            } else {
                $address = $quote->getShippingAddress();
                $address->setSaveInAddressBook(empty($shipping_data['save_in_address_book']) ? 0 : 1);
            }
            
        }

        if($helper->differentShippingAvailable()) {
            if(empty($billing_data['use_for_shipping'])) {
                $shipping_result = $helper->saveShipping($shipping_data, $shippingAddressId);
            }else {
                $shipping_result = $helper->saveShipping($billing_data, $customerAddressId);
            }
        }

        $shipping_method = $this->getRequest()->getPost('shipping_method', false);

        if(!empty($shipping_method)) {
           $helper->saveShippingMethod($shipping_method);
        }
        
        if(!Mage::helper('customer')->isLoggedIn()){
            $this->_getOnepage()->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
        }

        $this->loadLayout(false);
        if(Mage::helper('onestepcheckout')->isEnterprise() && Mage::helper('customer')->isLoggedIn()){
            $customerBalanceBlock = $this->getLayout()->createBlock('enterprise_customerbalance/checkout_onepage_payment_additional', 'customerbalance', array('template'=>'onestepcheckout/customerbalance/payment/additional.phtml'));
            $customerBalanceBlockScripts = $this->getLayout()->createBlock('enterprise_customerbalance/checkout_onepage_payment_additional', 'customerbalance_scripts', array('template'=>'onestepcheckout/customerbalance/payment/scripts.phtml'));
            $rewardPointsBlock = $this->getLayout()->createBlock('enterprise_reward/checkout_payment_additional', 'reward.points', array('template'=>'onestepcheckout/reward/payment/additional.phtml', 'before' => '-'));
            $rewardPointsBlockScripts = $this->getLayout()->createBlock('enterprise_reward/checkout_payment_additional', 'reward.scripts', array('template'=>'onestepcheckout/reward/payment/scripts.phtml', 'after' => '-'));
            $this->getLayout()->getBlock('choose-payment-method')
            ->append($customerBalanceBlock)
            ->append($customerBalanceBlockScripts)
            ->append($rewardPointsBlock)
            ->append($rewardPointsBlockScripts)
            ;
        }
        if(Mage::helper('onestepcheckout')->isEnterprise()){
            $giftcardScripts = $this->getLayout()->createBlock('enterprise_giftcardaccount/checkout_onepage_payment_additional', 'giftcardaccount_scripts', array('template'=>'onestepcheckout/giftcardaccount/onepage/payment/scripts.phtml'));
            $this->getLayout()->getBlock('choose-payment-method')
            ->append($giftcardScripts);
        }
        $this->renderLayout();
    }
    /**
     * Set methods separate
     */
    public function set_methods_separateAction()
    {
        $config     = $this->getWarehouseHelper()->getConfig();
        
        $helper = Mage::helper('onestepcheckout/checkout');

        $shipping_method = $this->getRequest()->getPost('shipping_method');
        $old_shipping_method = $this->_getOnepage()->getQuote()->getShippingAddress()->getShippingMethod();

        // TODO
        
        if($shipping_method != '' && $shipping_method != $old_shipping_method)  {
            //$result = $this->_getOnepage()->saveShippingMethod($shipping_method);
            // Use our helper instead
            // $helper->saveShippingMethod($shipping_method);
        }
        
        $helper->saveShippingMethod($shipping_method);
        
        //$this->_getOnepage()->getQuote()->getShippingAddress()->collectTotals();

        $paymentMethod = $this->getRequest()->getPost('payment_method', false);
        $selectedMethod = $this->_getOnepage()->getQuote()->getPayment()->getMethod();

        $store = $this->_getOnepage()->getQuote() ? $this->_getOnepage()->getQuote()->getStoreId() : null;
        $methods = $helper->getActiveStoreMethods($store, $this->_getOnepage()->getQuote());

        if($paymentMethod && !empty($methods) && !in_array($paymentMethod, $methods)){
            $paymentMethod = false;
        }

        if(!$paymentMethod && $selectedMethod && in_array($selectedMethod, $methods)){
             $paymentMethod = $selectedMethod;
        }

        try {
            $payment = $this->getRequest()->getPost('payment', array());
            //$payment = array();
            if(!empty($paymentMethod)){
                $payment['method'] = $paymentMethod;
            }
            //$payment_result = $this->_getOnepage()->savePayment($payment);
            $helper->savePayment($payment);
        }
        catch(Exception $e) {
            //die('Error: ' . $e->getMessage());
            // Silently fail for now
        }
        
        # $this->_getOnepage()->getQuote()->reapplyStocks();
        
        $quote = $this->_getOnepage()->getQuote();
        $quote->setTotalsCollectedFlag(false)->collectTotals()->save();
        
        $this->loadLayout(false);

        if(Mage::helper('onestepcheckout')->isEnterprise() && Mage::helper('customer')->isLoggedIn()){

            $customerBalanceBlock = $this->getLayout()->createBlock('enterprise_customerbalance/checkout_onepage_payment_additional', 'customerbalance', array('template'=>'onestepcheckout/customerbalance/payment/additional.phtml'));
            $customerBalanceBlockScripts = $this->getLayout()->createBlock('enterprise_customerbalance/checkout_onepage_payment_additional', 'customerbalance_scripts', array('template'=>'onestepcheckout/customerbalance/payment/scripts.phtml'));

            $rewardPointsBlock = $this->getLayout()->createBlock('enterprise_reward/checkout_payment_additional', 'reward.points', array('template'=>'onestepcheckout/reward/payment/additional.phtml', 'before' => '-'));
            $rewardPointsBlockScripts = $this->getLayout()->createBlock('enterprise_reward/checkout_payment_additional', 'reward.scripts', array('template'=>'onestepcheckout/reward/payment/scripts.phtml', 'after' => '-'));

            $this->getLayout()->getBlock('choose-payment-method')
            ->append($customerBalanceBlock)
            ->append($customerBalanceBlockScripts)
            ->append($rewardPointsBlock)
            ->append($rewardPointsBlockScripts)
            ;
        }

        if(Mage::helper('onestepcheckout')->isEnterprise()){
            $giftcardScripts = $this->getLayout()->createBlock('enterprise_giftcardaccount/checkout_onepage_payment_additional', 'giftcardaccount_scripts', array('template'=>'onestepcheckout/giftcardaccount/onepage/payment/scripts.phtml'));
            $this->getLayout()->getBlock('choose-payment-method')
            ->append($giftcardScripts);
        }

        $this->renderLayout();
    }
    /**
     * Register
     */ 
    public function registerAction()
    {
        if ($this->_getSession()->isLoggedIn()) {
            $this->_redirect('*/*/');
            return;
        }
        if ($this->getRequest()->isPost()) {
            $errors = array();
            if (!$customer = Mage::registry('current_customer')) {
                $customer = Mage::getModel('customer/customer')->setId(null);
            }
            $lastOrderId = $this->_getOnepage()->getCheckout()->getLastOrderId();
            $order = Mage::getModel('sales/order')->load($lastOrderId);
            $billing = $order->getBillingAddress();
            $customer->setData('firstname', $billing->getFirstname());
            $customer->setData('lastname', $billing->getLastname());
            $customer->setData('email', $order->getCustomerEmail());
            foreach (Mage::getConfig()->getFieldset('customer_account') as $code=>$node) {
                if ($node->is('create') && ($value = $this->getRequest()->getParam($code)) !== null) {
                    $customer->setData($code, $value);
                }
            }
            // print_r($customer->toArray());
            if ($this->getRequest()->getParam('is_subscribed', false)) {
                $customer->setIsSubscribed(1);
            }
            /**
             * Initialize customer group id
             */
            $customer->getGroupId();
            if ($this->getRequest()->getPost('create_address')) {
                $address = Mage::getModel('customer/address')
                ->setData($this->getRequest()->getPost())
                ->setIsDefaultBilling($this->getRequest()->getParam('default_billing', false))
                ->setIsDefaultShipping($this->getRequest()->getParam('default_shipping', false))
                ->setId(null);
                $customer->addAddress($address);
                $errors = $address->validate();
                if (!is_array($errors)) {
                    $errors = array();
                }
            }
            $result = array(
                'success' => false,
                'message' => false,
                'error' => false,
            );
            try {
                $validationCustomer = $customer->validate();
                if (is_array($validationCustomer)) {
                    $errors = array_merge($validationCustomer, $errors);
                }
                $validationResult = count($errors) == 0;
                if (true === $validationResult) {
                    $customer->save();
                    $result['success'] = true;
                    if ($customer->isConfirmationRequired()) {
                        $customer->sendNewAccountEmail('confirmation', $this->_getSession()->getBeforeAuthUrl());
                        $this->_getSession()->addSuccess($this->__('Account confirmation is required. Please, check your e-mail for confirmation link. To resend confirmation email please <a href="%s">click here</a>.',
                        Mage::helper('customer')->getEmailConfirmationUrl($customer->getEmail())
                        ));
                        $result['message'] = 'email_confirmation';
                    }
                    else {
                        $this->_getSession()->setCustomerAsLoggedIn($customer);
                        $url = $this->_welcomeCustomer($customer);
                        $result['message'] = 'customer_logged_in';
                    }
                    
                    $config     = $this->getWarehouseHelper()->getConfig();
                    if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                        $orderIds = Mage::getSingleton('checkout/session')->getOrderIds();
                        foreach ($orderIds as $_orderId) {
                            $order = Mage::getModel('sales/order')->loadByIncrementId($_orderId);
                            $order->setCustomerId($customer->getId());
                            $order->setCustomerIsGuest(false);
                            $order->setCustomerGroupId($customer->getGroupId());
                            $order->save();
                        }
                    } else {
                        $order->setCustomerId($customer->getId());
                        $order->setCustomerIsGuest(false);
                        $order->setCustomerGroupId($customer->getGroupId());
                        $order->save();
                    }
                    
                } else {
                    $this->_getSession()->setCustomerFormData($this->getRequest()->getPost());
                    if (is_array($errors)) {
                        $result['error'] = 'validation_failed';
                        $result['errors'] = $errors;
                    }  else {
                        $result['error'] = 'invalid_customer_data';
                    }
                }
            } catch (Mage_Core_Exception $e) {
                $result['error'] = $e->getMessage();
            } catch (Exception $e) {
                $result['error'] = $e->getMessage();
            }
        }
        $this->getResponse()->setBody(Zend_Json::encode($result));
    }
}