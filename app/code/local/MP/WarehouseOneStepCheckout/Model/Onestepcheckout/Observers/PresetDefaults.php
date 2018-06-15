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
 * Preset defaults observer
 * 
 * @category   MP
 * @package    MP_WarehouseOneStepCheckout
 * @author     Mage Plugins <mageplugins@gmail.com>
 */
class MP_WarehouseOneStepCheckout_Model_Onestepcheckout_Observers_PresetDefaults 
    extends Idev_OneStepCheckout_Model_Observers_PresetDefaults 
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
}