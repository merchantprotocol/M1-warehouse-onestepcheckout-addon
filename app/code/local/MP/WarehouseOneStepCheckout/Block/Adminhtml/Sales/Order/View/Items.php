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
 * Order items
 *
 * @category   MP
 * @package    MP_WarehouseOneStepCheckout
 * @author     Mage Plugins <mageplugins@gmail.com>
 */
class MP_WarehouseOneStepCheckout_Block_Adminhtml_Sales_Order_View_Items 
    extends MP_Warehouse_Block_Adminhtml_Sales_Order_View_Items 
{
    /**
     * Get warehouse one step checkout helper
     * 
     * @return MP_Warehouse_Helper_Data
     */
    protected function getWarehouseOneStepCheckoutHelper()
    {
        return Mage::helper('warehouseonestepcheckout');
    }
    
    
    public function _toHtml(){
        $html = parent::_toHtml();
        $comment = $this->getCommentHtml();
        return $html.$comment;
    }

    /**
     * get comment from order and return as html formatted string
     *
     *@return string
     */
    public function getCommentHtml(){
        $comment = $this->getOrder()->getOnestepcheckoutCustomercomment();
        $feedback = $this->getOrder()->getOnestepcheckoutCustomerfeedback();
        $html = '';

        if ($this->isShowCustomerCommentEnabled() && $comment){
            $html .= '<div id="customer_comment" class="giftmessage-whole-order-container"><div class="entry-edit">';
            $html .= '<div class="entry-edit-head"><h4>'.$this->helper('onestepcheckout')->__('Customer Comment').'</h4></div>';
            $html .= '<fieldset>'.nl2br($this->helper('onestepcheckout')->htmlEscape($comment)).'</fieldset>';
            $html .= '</div></div>';
        }

        if($this->isShowCustomerFeedbackEnabled()){
            $html .= '<div id="customer_feedback" class="giftmessage-whole-order-container"><div class="entry-edit">';
            $html .= '<div class="entry-edit-head"><h4>'.$this->helper('onestepcheckout')->__('Customer Feedback').'</h4></div>';
            $html .= '<fieldset>'.nl2br($this->helper('onestepcheckout')->htmlEscape($feedback)).'</fieldset>';
            $html .= '</div></div>';
        }

        return $html;
    }

    public function isShowCustomerCommentEnabled(){
        return Mage::getStoreConfig('onestepcheckout/exclude_fields/enable_comments', $this->getOrder()->getStore());
    }

    public function isShowCustomerFeedbackEnabled(){
        return Mage::getStoreConfig('onestepcheckout/feedback/enable_feedback', $this->getOrder()->getStore());
    }
}