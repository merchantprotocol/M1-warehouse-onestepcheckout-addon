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
 * @copyright   Copyright (c) 2013 Innoexts (http://www.innoexts.com)
 * @license     http://innoexts.com/commercial-license-agreement  InnoExts Commercial License
 */

/**
 * The warehouse one step checkout data helper
 * 
 * @category   Innoexts
 * @package    Innoexts_WarehouseOneStepCheckout
 * @author     Innoexts Team <developers@innoexts.com>
 */
class Innoexts_WarehouseOneStepCheckout_Helper_Data 
    extends Mage_Core_Helper_Abstract 
{
    /**
     * One step checkout version
     * 
     * @var string
     */
    protected $_version;
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
     * Get one step checkout version
     * 
     * @return string
     */
    public function getVersion()
    {
        if (is_null($this->_version)) {
            $this->_version = (string) Mage::getConfig()->getNode()->descend('modules/Idev_OneStepCheckout/version');
        }
        return $this->_version;
    }
    /**
     * Compare one step checkout version to the current
     * 
     * @param string $version
     * @param string $operator
     * 
     * @return int
     */
    public function compareVersion($version, $operator = null)
    {
        return version_compare($this->getVersion(), $version, $operator);
    }
    /**
     * Check if current one step checkout version is greater or equal
     * 
     * @return bool
     */
    public function isVersionGe($version)
    {
        return $this->compareVersion($version, '>=');
    }
    /**
     * Check if current one step checkout version is equal or greater then 4.0.7
     * 
     * @return bool
     */
    public function isVersionGe407()
    {
        return $this->isVersionGe('4.0.7');
    }
    /**
     * Check if current one step checkout version is equal or greater then 4.0.8
     * 
     * @return bool
     */
    public function isVersionGe408()
    {
        return $this->isVersionGe('4.0.8');
    }
    /**
     * Check if current one step checkout version is equal or greater then 4.0.9
     * 
     * @return bool
     */
    public function isVersionGe409()
    {
        return $this->isVersionGe('4.0.9');
    }
}