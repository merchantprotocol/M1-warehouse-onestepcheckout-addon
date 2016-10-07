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
 * Quote
 * 
 * @category   Innoexts
 * @package    Innoexts_WarehouseOneStepCheckout
 * @author     Innoexts Team <developers@innoexts.com>
 */
class Innoexts_WarehouseOneStepCheckout_Model_Sales_Quote 
    extends Innoexts_Warehouse_Model_Sales_Quote 
{
    /**
     * Collect totals patched for magento issue #26145
     *
     * @return Mage_Sales_Model_Quote
     */
    public function collectTotals()
    {
        /**
         * patch for magento issue #26145
         */
        if (!$this->getTotalsCollectedFlag()) {
            $items = $this->getAllItems();
            foreach($items as $item){
                $item->setData('calculation_price', null);
                $item->setData('original_price', null);
            }
        }
        parent::collectTotals();
        return $this;

    }
}