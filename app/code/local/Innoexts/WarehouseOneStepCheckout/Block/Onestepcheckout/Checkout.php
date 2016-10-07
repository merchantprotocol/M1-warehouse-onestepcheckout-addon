<?php
/**
 * Innoexts
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the InnoExts Commercial License
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://innoexts.com/commercial-license-agreement
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@innoexts.com so we can send you a copy immediately.
 * 
 * @category    Innoexts
 * @package     Innoexts_WarehouseOneStepCheckout
 * @copyright   Copyright (c) 2012 Innoexts (http://www.innoexts.com)
 * @license     http://innoexts.com/commercial-license-agreement  InnoExts Commercial License
 */

/**
 * Order items
 *
 * @category   Innoexts
 * @package    Innoexts_WarehouseOneStepCheckout
 * @author     Innoexts Team <developers@innoexts.com>
 */
class Innoexts_WarehouseOneStepCheckout_Block_Onestepcheckout_Checkout 
    extends Idev_OneStepCheckout_Block_Checkout 
{
    /**
     * Get warehouse helper
     * 
     * @return Innoexts_Warehouse_Helper_Data
     */
    protected function getWarehouseHelper()
    {
        return Mage::helper('warehouse');
    }
    /**
     * Get warehouse one step checkout helper
     * 
     * @return Innoexts_Warehouse_Helper_Data
     */
    protected function getWarehouseOneStepCheckoutHelper()
    {
        return Mage::helper('warehouseonestepcheckout');
    }
    /**
     * Handle post data
     */
    public function _handlePostData()
    {
        $helper     = $this->getWarehouseHelper();
        $config     = $helper->getConfig();
        
        $this->formErrors = array(
            'billing_errors' => array(),
            'shipping_errors' => array(),
        );
        $post = $this->getRequest()->getPost();
        if(!$post) {
            return;
        }
        // Save billing information
        if( $this->_isLoggedInWithAddresses() && false )    {
            // User is logged in and has addresses
        }
        else    {
            $checkoutHelper = Mage::helper('onestepcheckout/checkout');
            $payment_data = $this->getRequest()->getPost('payment');
            $billing_data = $this->getRequest()->getPost('billing', array());
            $shipping_data = $this->getRequest()->getPost('shipping', array());
            $billing_data = $checkoutHelper->load_exclude_data($billing_data);
            $shipping_data = $checkoutHelper->load_exclude_data($shipping_data);
            if(!empty($billing_data)){
                $this->getQuote()->getBillingAddress()->addData($billing_data)->implodeStreetAddress();
            }
            if($this->differentShippingAvailable()) {
                
                if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                    foreach ($this->getQuote()->getAllShippingAddresses() as $address) {
                        $address->setCountryId($shipping_data['country_id'])->setCollectShippingRates(true);
                    }
                } else {
                    $this->getQuote()->getShippingAddress()->setCountryId($shipping_data['country_id'])->setCollectShippingRates(true);
                }
                
            }
            if(isset($billing_data['email']))   {
                $this->email = $billing_data['email'];
            }
            if(!$this->_isLoggedIn()){
                $registration_mode = $this->settings['registration_mode'];
                if($registration_mode == 'auto_generate_account')   {
                    // Modify billing data to contain password also
                    $password = Mage::helper('onestepcheckout/checkout')->generatePassword();
                    $billing_data['customer_password'] = $password;
                    $billing_data['confirm_password'] = $password;
                    $this->getQuote()->getCustomer()->setData('password', $password);
                    $this->getQuote()->setData('password_hash',Mage::getModel('customer/customer')->encryptPassword($password));

                }
                if($registration_mode == 'require_registration' || $registration_mode == 'allow_guest')   {
                    if(!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password']) && ($billing_data['customer_password'] == $billing_data['confirm_password'])){
                        $password = $billing_data['customer_password'];
                        $this->getQuote()->setCheckoutMethod('register');
                        $this->getQuote()->setCustomerId(0);
                        $this->getQuote()->getCustomer()->setData('password', $password);
                        $this->getQuote()->setData('password_hash',Mage::getModel('customer/customer')->encryptPassword($password));
                    }
                }
            }
            if($this->_isLoggedIn() || $registration_mode == 'require_registration' || $registration_mode == 'auto_generate_account' || (!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password']))){
                //handle this as Magento handles subscriptions for registered users (no confirmation ever)
                $subscribe_newsletter = $this->getRequest()->getPost('subscribe_newsletter');
                if(!empty($subscribe_newsletter)){
                    $this->getQuote()->getCustomer()->setIsSubscribed(1);
                }
            }
            $billingAddressId = $this->getRequest()->getPost('billing_address_id');
            $customerAddressId = (!empty($billingAddressId)) ? $billingAddressId : false ;
            
            if($this->_isLoggedIn()){
                $this->getQuote()->getBillingAddress()->setSaveInAddressBook(empty($billing_data['save_in_address_book']) ? 0 : 1);
                
                if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                    foreach ($this->getQuote()->getAllShippingAddresses() as $address) {
                        $address->setSaveInAddressBook(empty($shipping_data['save_in_address_book']) ? 0 : 1);
                    }
                } else {
                    $this->getQuote()->getShippingAddress()->setSaveInAddressBook(empty($shipping_data['save_in_address_book']) ? 0 : 1);
                }
                
            }
            
            $result = $this->getOnepage()->saveBilling($billing_data, $customerAddressId);
            if(!empty($billing_data['customer_password']) && !empty($billing_data['confirm_password']))   {
                // Trick to allow saving of
                $this->getOnepage()->saveCheckoutMethod('register');
                $this->getQuote()->setCustomerId(0);
                
                if ($this->getWarehouseOneStepCheckoutHelper()->isVersionGe408()) {
                    $customerData = '';
                    $tmpBilling = $billing_data;

                    if(!empty($tmpBilling['street']) && is_array($tmpBilling['street'])){
                        $tmpBilling ['street'] = '';
                    }
                    
                    if ($this->getWarehouseOneStepCheckoutHelper()->isVersionGe409()) {
                        
                        /**
                         * ZAGGORA HACK array_intersect throws a notice because of 3D arrays
                         */
                        $billing_data_tmp = $billing_data;
                        $billing_data_tmp['street'] = $billing_data_tmp['street'][0];
                        $getBillingAddressTmp = array();
                        foreach($this->getQuote()->getBillingAddress()->getData() as $key => $value) {
                            if (gettype($value) == 'array') continue;
                            $getBillingAddressTmp[$key] = $value;
                        }
                        $customerData= array_intersect($billing_data_tmp, $getBillingAddressTmp);
                    } else {
                        $customerData= array_intersect($tmpBilling, $this->getQuote()->getBillingAddress()->implodeStreetAddress()->getData());
                    }
                    
                    
                } else {
                    $customerData= array_intersect($billing_data, $this->getQuote()->getBillingAddress()->getData());
                }
                
                $this->getQuote()->getCustomer()->addData($customerData);
                foreach($customerData as $key => $value){
                    $this->getQuote()->setData('customer_'.$key, $value);
                }
            }
            $customerSession = Mage::getSingleton('customer/session');
            if (!empty($billing_data['dob']) && !$customerSession->isLoggedIn()) {
                $dob = Mage::app()->getLocale()->date($billing_data['dob'], null, null, false)->toString('yyyy-MM-dd');
                $this->getQuote()->setCustomerDob($dob);
                $this->getQuote()->setDob($dob);
                $this->getQuote()->getBillingAddress()->setDob($dob);
            }
            if($customerSession->isLoggedIn() && !empty($billing_data['dob'])){
                $dob = Mage::app()->getLocale()->date($billing_data['dob'], null, null, false)->toString('yyyy-MM-dd');
                $customer = Mage::getModel('customer/customer')
                ->setId($customerSession->getId())
                ->setWebsiteId($customerSession->getCustomer()->getWebsiteId())
                ->setEmail($customerSession->getCustomer()->getEmail())
                ->setDob($dob)
                ->save()
                ;
            }
            
            // set customer tax/vat number for further usage
            $taxid = '';
            if(!empty($billing_data['taxvat'])){
                $taxid = $billing_data['taxvat'];
            } else if(!empty($billing_data['vat_id'])){
                $taxid = $billing_data['vat_id'];
            }
            if (!empty($taxid)) {
                $this->getQuote()->setCustomerTaxvat($taxid);
                $this->getQuote()->setTaxvat($taxid);
                $this->getQuote()->getBillingAddress()->setTaxvat($taxid);
                $this->getQuote()->getBillingAddress()->setTaxId($taxid);
                $this->getQuote()->getBillingAddress()->setVatId($taxid);
            }

            if($customerSession->isLoggedIn() && !empty($billing_data['taxvat'])){
                $customerSession->getCustomer()
                ->setTaxId($billing_data['taxvat'])
                ->setTaxvat($billing_data['taxvat'])
                ->setVatId($billing_data['taxvat'])
                ->save()
                ;
            }

            if(isset($result['error'])) {
                $this->formErrors['billing_error'] = true;
                $this->formErrors['billing_errors'] = $checkoutHelper->_getAddressError($result, $billing_data);
                $this->log[] = 'Error saving billing details: ' . implode(', ', $this->formErrors['billing_errors']);
            }
            
            // Validate stuff that saveBilling doesn't handle
            if(!$this->_isLoggedIn())   {
                $validator = new Zend_Validate_EmailAddress();
                if(!$billing_data['email'] || $billing_data['email'] == '' || !$validator->isValid($billing_data['email'])) {
                    if (is_array($this->formErrors['billing_errors']))   {
                        $this->formErrors['billing_errors'][] = 'email';
                    } else {
                        $this->formErrors['billing_errors'] = array('email');
                    }
                    $this->formErrors['billing_error'] = true;
                } else {
                    $allow_guest_create_account_validation = false;
                    if($this->settings['registration_mode'] == 'allow_guest')   {
                        if(isset($_POST['create_account']) && $_POST['create_account'] == '1')  {
                            $allow_guest_create_account_validation = true;
                        }
                    }
                    if($this->settings['registration_mode'] == 'require_registration' || $this->settings['registration_mode'] == 'auto_generate_account' || $allow_guest_create_account_validation)  {
                        if($this->_customerEmailExists($billing_data['email'], Mage::app()->getWebsite()->getId()))   {
                            $allow_without_password = $this->settings['registration_order_without_password'];
                            if(!$allow_without_password)    {
                                if(is_array($this->formErrors['billing_errors']))   {
                                    $this->formErrors['billing_errors'][] = 'email';
                                    $this->formErrors['billing_errors'][] = 'email_registered';
                                } else {
                                    $this->formErrors['billing_errors'] = array('email','email_registered');
                                }
                            } else    {
                            }
                        } else    {
                            $password_errors = array();
                            if(!isset($billing_data['customer_password']) || $billing_data['customer_password'] == '')    {
                                $password_errors[] = 'password';
                            }
                            if(!isset($billing_data['confirm_password']) || $billing_data['confirm_password'] == '')    {
                                $password_errors[] = 'confirm_password';
                            } else    {
                                if($billing_data['confirm_password'] !== $billing_data['customer_password']) {
                                    $password_errors[] = 'password';
                                    $password_errors[] = 'confirm_password';
                                }
                            }
                            if(count($password_errors) > 0) {
                                if(is_array($this->formErrors['billing_errors']))   {
                                    foreach($password_errors as $error) {
                                        $this->formErrors['billing_errors'][] = $error;
                                    }
                                } else {
                                    $this->formErrors['billing_errors'] = $password_errors;
                                }
                            }
                        }
                    }
                }
            }
            if($this->settings['enable_terms']) {
                if(!isset($post['accept_terms']) || $post['accept_terms'] != '1')   {
                    $this->formErrors['terms_error'] = true;
                }
            }
            if ($this->settings['enable_default_terms'] && $requiredAgreements = Mage::helper('checkout')->getRequiredAgreementIds()) {
                $postedAgreements = array_keys($this->getRequest()->getPost('agreement', array()));
                if ($diff = array_diff($requiredAgreements, $postedAgreements)) {
                    //$this->formErrors['terms_error'] = $this->__('Please agree to all the terms and conditions before placing the order.');
                    $this->formErrors['agreements_error'] = true;
                }
            }
            $shippingAddressId = $this->getRequest()->getPost('shipping_address_id', false);
            if($this->differentShippingAvailable()) {
                if(!isset($billing_data['use_for_shipping']) || $billing_data['use_for_shipping'] != '1')   {
                    //$shipping_result = $this->getOnepage()->saveShipping($shipping_data, $shippingAddressId);
                    $shipping_result = Mage::helper('onestepcheckout/checkout')->saveShipping($shipping_data, $shippingAddressId);
                    if(isset($shipping_result['error']))    {
                        $this->formErrors['shipping_error'] = true;
                        $this->formErrors['shipping_errors'] = $checkoutHelper->_getAddressError($shipping_result, $shipping_data, 'shipping');
                    }
                } else {
                    //$shipping_result = $this->getOnepage()->saveShipping($billing_data, $shippingAddressId);
                    $shipping_result = Mage::helper('onestepcheckout/checkout')->saveShipping($billing_data, $customerAddressId);
                }
            }
        }
        // Save shipping method
        $shipping_method = $this->getRequest()->getPost('shipping_method', '');
        if(!$this->isVirtual()){
            //additional checks if the rate is indeed available for chosen shippin address
            
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                foreach ($this->getQuote()->getAllShippingAddresses() as $address) {
                    $stockId = (int) $address->getStockId();
                    $rates = $address->getGroupedAllShippingRates();
                    $availableRates = $this->getAvailableRates($rates);
                    if(
                        !isset($shipping_method[$stockId]) || 
                        empty($shipping_method[$stockId]) || 
                        !in_array($shipping_method[$stockId],$availableRates)
                    ) {
                        $this->formErrors['shipping_method'] = true;
                    }
                }
            } else {
                $address = $this->getOnepage()->getQuote()->getShippingAddress();
                $rates = $address->getGroupedAllShippingRates();
                $availableRates = $this->getAvailableRates($rates);
                if(empty($shipping_method) || !in_array($shipping_method,$availableRates)){
                    $this->formErrors['shipping_method'] = true;
                }
            }

        }
        if(!$this->isVirtual() )  {
            //$result = $this->getOnepage()->saveShippingMethod($shipping_method);
            $result = Mage::helper('onestepcheckout/checkout')->saveShippingMethod($shipping_method);
            if(isset($result['error']))    {
                $this->formErrors['shipping_method'] = true;
            } else {
                Mage::dispatchEvent('checkout_controller_onepage_save_shipping_method', array('request'=>$this->getRequest(), 'quote'=>$this->getOnepage()->getQuote()));
            }
        }
        // Save payment method
        $payment = $this->getRequest()->getPost('payment', array());
        $paymentRedirect = false;
        
        
        if ($this->getWarehouseOneStepCheckoutHelper()->isVersionGe408()) {
            $payment = $this->filterPaymentData($payment);
        } else {
            /**
             * A fix for common one big form problem
             * we rename the fields in template and iterate over subarrays
             * to see if there's any values and set them to main scope
             */
            foreach($payment as $value){
                if(is_array($value) && !empty($value)){
                    foreach($value as $key => $realValue){
                        if(!empty($realValue)){
                            $payment[$key]=$realValue;
                        }
                    }
                }
            }
            /**
             * unset unnecessary fields
             */
            foreach ($payment as $key => $value){
                if(is_array($value)){
                    unset($payment[$key]);
                }
            }
        }
        
        
        
        
        try {
            if(!empty($payment['method']) && $payment['method'] == 'free'){
                $instance = Mage::helper('payment')->getMethodInstance('free');
                if ($instance->isAvailable($this->getOnepage()->getQuote())) {
                    $instance->setInfoInstance($this->getOnepage()->getQuote()->getPayment());
                    $this->getOnepage()->getQuote()->getPayment()->setMethodInstance($instance);
                }
            }
            $result = Mage::helper('onestepcheckout/checkout')->savePayment($payment);
            $paymentRedirect = $this->getOnepage()->getQuote()->getPayment()->getCheckoutRedirectUrl();
        } catch (Mage_Payment_Exception $e) {
            if ($e->getFields()) {
                $result['fields'] = $e->getFields();
            }
            $result['error'] = $e->getMessage();
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }

        if(isset($result['error'])) {
            if($result['error'] == 'Can not retrieve payment method instance')  {
                $this->formErrors['payment_method'] = true;
            } else {
                $this->formErrors['payment_method_error']  = $result['error'];
            }
        }

        if(!$this->hasFormErrors()) {
            if($this->settings['enable_newsletter']) {
                // Handle newsletter
                $subscribe_newsletter = $this->getRequest()->getPost('subscribe_newsletter');
                $registration_mode = $this->settings['registration_mode'];
                if(!empty($subscribe_newsletter) && ($registration_mode != 'require_registration' && $registration_mode != 'auto_generate_account') && !$this->getRequest()->getPost('create_account'))  {
                    $model = Mage::getModel('newsletter/subscriber');
                    $model->loadByEmail($this->email);
                    if(!$model->isSubscribed()){
                        $model->subscribe($this->email);
                    }
                }
            }
            if($paymentRedirect && $paymentRedirect != '')  {
                
                if ($this->getWarehouseOneStepCheckoutHelper()->isVersionGe408()) {
                    $response = Mage::app()->getResponse();
                    return $response->setRedirect($paymentRedirect);
                } else {
                    Header('Location: ' . $paymentRedirect);
                    die();
                }
                
            }
            if( $this->_isLoggedIn() )  {
                $this->_saveOrder();
                $this->log[] = 'Saving order as a logged in customer';

            } else {
                if( $this->_isEmailRegistered() )   {
                    $registration_mode = $this->settings['registration_mode'];
                    $allow_without_password = $this->settings['registration_order_without_password'];
                    if($registration_mode == 'require_registration' || $registration_mode == 'auto_generate_account')   {
                        if($allow_without_password) {
                            // Place order on the emails account without the password
                            $this->setCustomerAfterPlace($this->_getCustomer());
                            $this->getOnepage()->saveCheckoutMethod('guest');
                            $this->_saveOrder();
                        } else    {
                            // This should not happen, because validation should handle it
                            die('Validation did not handle it');
                        }
                    } else    {
                        $this->getOnepage()->saveCheckoutMethod('guest');
                        $this->_saveOrder();
                    }
                    // Place order as customer with same e-mail address
                    $this->log[] = 'Save order on existing account with email address';
                } else {
                    if($this->settings['registration_mode'] == 'require_registration')  {
                        $this->log[] = 'Save order as REGISTER';
                        $this->getOnepage()->saveCheckoutMethod('register');
                        $this->getQuote()->setCustomerId(0);
                        $this->_saveOrder();
                    } elseif($this->settings['registration_mode'] == 'allow_guest')   {
                        if(isset($_POST['create_account']) && $_POST['create_account'] == '1')  {
                            $this->getOnepage()->saveCheckoutMethod('register');
                            $this->getQuote()->setCustomerId(0);
                            $this->_saveOrder();
                        } else {
                            $this->getOnepage()->saveCheckoutMethod('guest');
                            $this->_saveOrder();
                        }
                    } else {
                        $registration_mode = $this->settings['registration_mode'];
                        if($registration_mode == 'auto_generate_account')   {
                            $this->getOnepage()->saveCheckoutMethod('register');
                            $this->getQuote()->setCustomerId(0);
                            $this->_saveOrder();
                        } else {
                            $this->getOnepage()->saveCheckoutMethod('guest');
                            $this->_saveOrder();
                        }
                    }
                }
            }
        }
    }
    /**
     * After place order
     */
    protected function afterPlaceOrder()
    {
        $customer   = $this->customer_after_place_order;
        $helper     = $this->getWarehouseHelper();
        $config     = $helper->getConfig();
        $orderId    = $this->getOnepage()->getLastOrderId();
        
        if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
            $orderIds = Mage::getSingleton('checkout/session')->getOrderIds();
            if (!count($orderIds)) {
                $orderIds = array($orderId);
            }
            foreach ($orderIds as $_orderId) {
                $order = Mage::getModel('sales/order')->loadByIncrementId($_orderId);
                if($customer)   {
                    $order->setCustomerId($customer->getId());
                    $order->setCustomerIsGuest(false);
                    $order->setCustomerGroupId($customer->getGroupId());
                    $order->save();
                }
            }
        } else {
            $order = Mage::getModel('sales/order')->loadByIncrementId($orderId);
            if($customer)   {
                $order->setCustomerId($customer->getId());
                $order->setCustomerIsGuest(false);
                $order->setCustomerGroupId($customer->getGroupId());
                $order->save();
            }
        }
    }
    /**
     * Save order
     */
    protected function _saveOrder()
    {
        $helper     = $this->getWarehouseHelper();
        $config     = $helper->getConfig();
        $payment    = $this->getRequest()->getPost('payment', false);
        if($payment) {
            
            if ($this->getWarehouseOneStepCheckoutHelper()->isVersionGe408()) {
                $payment = $this->filterPaymentData($payment);
            } else {
                foreach($payment as $value){
                    if(is_array($value) && !empty($value)){
                        foreach($value as $key => $realValue){
                            if(!empty($realValue)){
                                $payment[$key]=$realValue;
                            }
                        }
                    }
                }
                foreach ($payment as $key => $value){
                    if(is_array($value)){
                        unset($payment[$key]);
                    }
                }
            }
            
            $this->getOnepage()->getQuote()->getPayment()->importData($payment);
            $ccSaveAllowedMethods = array('ccsave');
            $method = $this->getOnepage()->getQuote()->getPayment()->getMethodInstance();
            if(in_array($method->getCode(), $ccSaveAllowedMethods)){
                $info = $method->getInfoInstance();
                $info->setCcNumberEnc($info->encrypt($info->getCcNumber()));
            }
        }
        try {
            if(!Mage::helper('customer')->isLoggedIn()){
                $this->getOnepage()->getQuote()->setTotalsCollectedFlag(false)->collectTotals();
            }
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                $this->getOnepage()->saveOrders();
            } else {
                $this->getOnepage()->saveOrder();
            }
            
        } catch(Exception $e)   {
            $this->getOnepage()->getQuote()->setIsActive(true);
            
            $quote = $this->getOnepage()->getQuote();
            
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                foreach ($quote->getAllShippingAddresses() as $address) {
                    $address->setCollectShippingRates(true);
                }
            } else {
                $quote->getShippingAddress()->setCollectShippingRates(true);
            }
            
            $quote->collectTotals();
            
            $error = $e->getMessage();
            $this->formErrors['unknown_source_error'] = $error;
            Mage::logException($e);
            Mage::helper('checkout')->sendPaymentFailedEmail($this->getOnepage()->getQuote(), $error);
            return;
        }
        $this->afterPlaceOrder();
        $redirectUrl    = $this->getOnepage()->getCheckout()->getRedirectUrl();
        $redirectUrls   = $this->getOnepage()->getCheckout()->getRedirectUrls();
        
        
        
        if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
            if (isset($redirectUrls) && count($redirectUrls) == 1) {
                $redirect = current($redirectUrls);
            }
            if (!$redirect) {
                $redirect = $this->getUrl('checkout/onepage/success');
            }
        } else {
            if($redirectUrl)    {
                $redirect = $redirectUrl;
            } else {
                $this->getOnepage()->getQuote()->setIsActive(false);
                $this->getOnepage()->getQuote()->save();
                $redirect = $this->getUrl('checkout/onepage/success');
            }
        }
        
        if ($this->getWarehouseOneStepCheckoutHelper()->isVersionGe408()) {
            $response = Mage::app()->getResponse();
            return $response->setRedirect($redirect);
        } else {
            Header('Location: ' . $redirect);
            exit();
        }
        
    }
}