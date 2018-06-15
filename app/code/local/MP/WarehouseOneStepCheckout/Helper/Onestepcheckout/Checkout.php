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

/**
 * Checkout helper
 * 
 * @category   MP
 * @package    MP_WarehouseOneStepCheckout
 * @author     Mage Plugins <mageplugins@gmail.com>
 */
class MP_WarehouseOneStepCheckout_Helper_Onestepcheckout_Checkout 
    extends Idev_OneStepCheckout_Helper_Checkout 
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
     * Get warehouse one step checkout helper
     * 
     * @return MP_Warehouse_Helper_Data
     */
    protected function getWarehouseOneStepCheckoutHelper()
    {
        return Mage::helper('warehouseonestepcheckout');
    }
    /**
     * Save payment
     */
    public function savePayment($data)
    {
        $helper     = $this->getWarehouseHelper();
        $config     = $helper->getConfig();
        if (empty($data)) {
            return array('error' => -1, 'message' => Mage::helper('checkout')->__('Invalid data'));
        }
        if ($this->getOnepage()->getQuote()->isVirtual()) {
            $this->getOnepage()->getQuote()->getBillingAddress()->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
        } else {
            
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                foreach ($this->getOnepage()->getQuote()->getAllShippingAddresses() as $address) {
                    $address->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
                }
            } else {
                $address = $this->getOnepage()->getQuote()->getShippingAddress();
                $address->setPaymentMethod(isset($data['method']) ? $data['method'] : null);
            }
            
        }
        $payment = $this->getOnepage()->getQuote()->getPayment();
        $payment->importData($data);
        $this->getOnepage()->getQuote()->save();
        return array();
    }
    /**
     * Save shipping method
     */
    public function saveShippingMethod($shippingMethod)
    {
        $res        = array();
        $helper     = $this->getWarehouseHelper();
        $config     = $helper->getConfig();
        $quote      = $this->getOnepage()->getQuote();
        if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
            foreach ($quote->getAllShippingAddresses() as $address) {
                $stockId = (int) $address->getStockId();
                if (!isset($shippingMethod[$stockId])) {
                    $res = array(
                        'error' => -1,
                        'message' => Mage::helper('checkout')->__('Invalid shipping method.')
                    );
                    break;
                }
            }
            if (empty($res)) {
                foreach ($quote->getAllShippingAddresses() as $address) {
                    $stockId = (int) $address->getStockId();
                    $_shippingMethod = $shippingMethod[$stockId];
                    $rate = $address->getShippingRateByCode($_shippingMethod);
                    if (!$rate) {
                        $res = array(
                            'error' => -1,
                            'message' => Mage::helper('checkout')->__('Invalid shipping method.')
                        );
                        break;
                    }
                }
            }
            if (empty($res)) {
                foreach ($quote->getAllShippingAddresses() as $address) {
                    $stockId = (int) $address->getStockId();
                    if (isset($shippingMethod[$stockId])) {
                        $_shippingMethod = $shippingMethod[$stockId];
                        $address->setShippingMethod($_shippingMethod);
                    }
                }
                # $quote->collectTotals()->save();
            }
        } else {
            if (empty($shippingMethod)) {
                $res = array(
                    'error' => -1,
                    'message' => Mage::helper('checkout')->__('Invalid shipping method.')
                );
            }
            if (empty($res)) {
                $rate = $quote->getShippingAddress()->getShippingRateByCode($shippingMethod);
                if (!$rate) {
                    $res = array(
                        'error' => -1,
                        'message' => Mage::helper('checkout')->__('Invalid shipping method.')
                    );
                }
            }
            if (empty($res)) {
                $quote->getShippingAddress()->setShippingMethod($shippingMethod);
            }
        }
        return $res;
    }
    /**
     * Save shipping
     */
    public function saveShipping($data, $customerAddressId)
    {
        if (empty($data)) {
            $res = array(
                'error' => -1,
                'message' => Mage::helper('checkout')->__('Invalid data')
            );
            return $res;
        }
        
        $helper     = $this->getWarehouseHelper();
        $config     = $helper->getConfig();
        $quote      = $this->getOnepage()->getQuote();
        $address    = $quote->getShippingAddress();
        $addresses  = $quote->getAllShippingAddresses();
        $addresses  = array_reverse($addresses);
        
        if (!empty($customerAddressId)) {
            $customerAddress = Mage::getModel('customer/address')->load($customerAddressId);
            if ($customerAddress->getId()) {
                if ($customerAddress->getCustomerId() != $this->getOnepage()->getQuote()->getCustomerId()) {
                    return array('error' => 1,
                        'message' => Mage::helper('checkout')->__('Customer Address is not valid.')
                    );
                }
                if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                    foreach ($addresses as $_address) {
                        $_address->importCustomerAddress($customerAddress);
                    }
                } else {
                    $address->importCustomerAddress($customerAddress);
                }
            }
        } else {
            unset($data['address_id']);
            if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
                foreach ($addresses as $_address) {
                    $_address->addData($data);
                }
            } else {
                $address->addData($data);
            }
        }
        
        $quote->reapplyStocks();
        $quote->save();

        if ($config->isMultipleMode() && $config->isSplitOrderEnabled()) {
            foreach ($addresses as $_address) {
                $_address->implodeStreetAddress();
                $_address->setCollectShippingRates(true);
            }
        } else {
            $address->implodeStreetAddress();
            $address->setCollectShippingRates(true);
        }
        if (($validateRes = $address->validate())!==true) {
            $res = array(
                'error' => 1,
                'message' => $validateRes
            );
            return $res;
        }
        $quote->collectTotals()->save();
        return array();
    }
    
}