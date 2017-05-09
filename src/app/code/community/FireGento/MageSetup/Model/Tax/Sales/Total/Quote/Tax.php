<?php
/**
 * This file is part of the FIREGENTO project.
 *
 * FireGento_GermanSetup is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License version 3 as
 * published by the Free Software Foundation.
 *
 * This script is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * PHP version 5
 *
 * @category  FireGento
 * @package   FireGento_GermanSetup
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2013 FireGento Team (http://www.firegento.de). All rights served.
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 */

/**
 * Tax total model for mixed tax class calculation
 *
 * @category  FireGento
 * @package   FireGento_GermanSetup
 * @author    FireGento Team <team@firegento.com>
 * @copyright 2012 FireGento Team (http://www.firegento.de). All rights served.
 * @license   http://opensource.org/licenses/gpl-3.0 GNU General Public License, version 3 (GPLv3)
 * @version   $Id:$
 */
class FireGento_MageSetup_Model_Tax_Sales_Total_Quote_Tax extends Mage_Tax_Model_Sales_Total_Quote_Tax
{

    /**
     * Calculates the portions of a quote with a specifc tax class
     * The price incl tax is used for the calculation
     *
     * @param $quoteItems
     * @return array (taxClassId -> percentage in quote)
     */
    protected function _collectTaxClassPortions($quoteItems)
    {
        $taxClassIds = array();

        // Fetch the tax rates from the quote items
        $taxClassSums = array();
        $total = 0;
        foreach ($quoteItems as $item) {
            /** @var $item Mage_Sales_Model_Quote_Item */
            if ($item->getParentItem()) {
                continue;
            }
            // sum up all product values grouped by the tax class id
            if (!isset($taxClassSums[$item->getTaxClassId()])) {
                $taxClassSums[$item->getTaxClassId()] = 0;
            }
            $rowSum = $item->getPriceInclTax() * $item->getQty();
            $taxClassSums[$item->getTaxClassId()] += $rowSum;
            $total += $rowSum;
        }

        $portions = array();
        foreach($taxClassSums as $taxClassId=>$sum) {
            $portions[$taxClassId] = $sum/$total;
        }
        return $portions;
    }

    /**
     *
     * Calculate proportional mixed tax
     *
     * TODO Works only for "Shipping cost incl Tax"
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param Varien_Object                  $taxRateRequest
     *
     * @return $this|Mage_Tax_Model_Sales_Total_Quote
     */
    protected function _calculateShippingTax(Mage_Sales_Model_Quote_Address $address, $taxRateRequest)
    {
        if (Mage::getStoreConfigFlag(FireGento_GermanSetup_Model_Tax_Config::XML_PATH_SHIPPING_TAX_ON_PRODUCT_TAX)
            != FireGento_GermanSetup_Model_Tax_Config::USE_PROPORTIONALLY_MIXED_TAX) {
            return parent::_calculateShippingTax($address, $taxRateRequest);
        }

        if (!$address->getIsShippingInclTax()) {
            throw Exception('Not implemented');
        }
        $portions = $this->_collectTaxClassPortions($address->getQuote()->getAllItems());

        Mage::dispatchEvent('firegento_germansetup_calculate_shipping_tax_portions_after', array('quote_address' => $address, 'portions' => $portions));

        $totalTaxable = $address->getShippingTaxable();
        $totalBaseTaxable = $address->getBaseShippingTaxable();
        $totalShippingTaxAmount = 0;
        $totalBaseShippingTaxAmount = 0;
        foreach($portions as $taxClassId=>$portion) {
            $address->setShippingTaxable($totalTaxable * $portion);
            $address->setBaseShippingTaxable($totalBaseTaxable * $portion);
            $this->_config->setSimulateClass($taxClassId);
            parent::_calculateShippingTax($address, $taxRateRequest);
            $this->_config->setSimulateClass(null);
            $totalShippingTaxAmount += $address->getShippingTaxAmount();
            $totalBaseShippingTaxAmount += $address->getBaseShippingTaxAmount();
        }
        $address->setShippingTaxAmount($totalShippingTaxAmount);
        $address->setBaseShippingTaxAmount($totalBaseShippingTaxAmount);

        // now we have to adjust the actual shipping cost
        // we just set it to brutto minus tax
        $address->setTotalAmount('shipping', $address->getShippingInclTax() - $totalShippingTaxAmount);
        $address->setBaseTotalAmount('shipping', $address->getBaseShippingInclTax() - $totalBaseShippingTaxAmount);

        $address->setShippingTaxable($totalTaxable);
        $address->setBaseShippingTaxable($totalBaseTaxable);

        $address->setShippingAmount($address->getTotalAmount('shipping'));
        $address->setBaseShippingAmount($address->getBaseTotalAmount('shipping'));

        return $this;
    }


}