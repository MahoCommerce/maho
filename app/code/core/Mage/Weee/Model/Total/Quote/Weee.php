<?php

/**
 * Maho
 *
 * @package    Mage_Weee
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Weee_Model_Total_Quote_Weee extends Mage_Tax_Model_Sales_Total_Quote_Tax
{
    /**
     * Weee module helper object
     *
     * @var Mage_Weee_Helper_Data
     */
    protected $_helper;

    /**
     * Store model
     *
     * @var Mage_Core_Model_Store
     */
    protected $_store;

    /**
     * Tax configuration object
     *
     * @var Mage_Tax_Model_Config
     */
    protected $_config;

    /**
     * Initialize Weee totals collector
     */
    public function __construct()
    {
        $this->setCode('weee');
        $this->_helper = Mage::helper('weee');
        $this->_config = Mage::getSingleton('tax/config');
    }

    /**
     * Collect Weee taxes amount and prepare items prices for taxation and discount
     *
     * @return  $this
     */
    #[\Override]
    public function collect(Mage_Sales_Model_Quote_Address $address)
    {
        Mage_Sales_Model_Quote_Address_Total_Abstract::collect($address);
        $items = $this->_getAddressItems($address);
        if (!count($items)) {
            return $this;
        }

        $address->setAppliedTaxesReset(true);
        $address->setAppliedTaxes([]);

        $this->_store = $address->getQuote()->getStore();
        $this->_helper->setStore($this->_store);

        $isTaxAffected = false;
        foreach ($items as $item) {
            if ($item->getParentItemId()) {
                continue;
            }
            $this->_resetItemData($item);
            if ($item->getHasChildren() && $item->isChildrenCalculated()) {
                foreach ($item->getChildren() as $child) {
                    $this->_resetItemData($child);
                    $isTaxAffected = $this->_process($address, $child) || $isTaxAffected;
                }
                $this->_recalculateParent($item);
            } else {
                $isTaxAffected = $this->_process($address, $item) || $isTaxAffected;
            }
        }

        if ($isTaxAffected) {
            $address->unsSubtotalInclTax();
            $address->unsBaseSubtotalInclTax();
        }

        return $this;
    }

    /**
     * Calculate item fixed tax and prepare information for discount and regular taxation
     *
     * @param   Mage_Sales_Model_Quote_Item_Abstract $item
     * @return  bool Whether tax was affected
     */
    protected function _process(Mage_Sales_Model_Quote_Address $address, $item): bool
    {
        if (!$this->_helper->isEnabled($this->_store)) {
            return false;
        }

        $attributes = $this->_helper->getProductWeeeAttributes(
            $item->getProduct(),
            $address,
            $address->getQuote()->getBillingAddress(),
            $this->_store->getWebsiteId(),
        );

        $applied = [];
        $productTaxes = [];

        $totalValue = 0;
        $baseTotalValue = 0;
        $totalRowValue = 0;
        $baseTotalRowValue = 0;

        $totalExclTaxValue = 0;
        $baseTotalExclTaxValue = 0;
        $totalExclTaxRowValue = 0;
        $baseTotalExclTaxRowValue = 0;

        $customerRatePercentage = $this->_customerRatePercent($address, $item);

        foreach ($attributes as $k => $attribute) {
            $baseValue = $attribute->getAmount();
            $baseValueExclTax = $baseValue;

            if ($customerRatePercentage && $this->_helper->isTaxIncluded($this->_store)) {
                //Remove the customer tax. This in general applies to EU scenario
                $baseValueExclTax
                        = $this->_getCalculator()->round(($baseValue * 100) / (100 + $customerRatePercentage));
            }

            $value = $this->_store->convertPrice($baseValue);
            $rowValue = $value * $item->getTotalQty();
            $baseRowValue = $baseValue * $item->getTotalQty();

            //Get the values excluding tax
            $valueExclTax = $this->_store->convertPrice($baseValueExclTax);
            $rowValueExclTax = $valueExclTax * $item->getTotalQty();
            $baseRowValueExclTax = $baseValueExclTax * $item->getTotalQty();

            $title = $attribute->getName();

            //Calculate the Wee value
            $totalValue += $value;
            $baseTotalValue += $baseValue;
            $totalRowValue += $rowValue;
            $baseTotalRowValue += $baseRowValue;

            //Calculate the Wee without tax
            $totalExclTaxValue += $valueExclTax;
            $baseTotalExclTaxValue += $baseValueExclTax;
            $totalExclTaxRowValue += $rowValueExclTax;
            $baseTotalExclTaxRowValue += $baseRowValueExclTax;

            /*
             * Note: including Tax does not necessarily mean it includes all the tax
             * *_incl_tax only holds the tax associated with Tax included products
             */

            $productTaxes[] = [
                'title' => $title,
                'base_amount' => $baseValueExclTax,
                'amount' => $valueExclTax,
                'row_amount' => $rowValueExclTax,
                'base_row_amount' => $baseRowValueExclTax,
                /**
                 * Tax value can't be presented as include/exclude tax
                 */
                'base_amount_incl_tax' => $baseValue,
                'amount_incl_tax' => $value,
                'row_amount_incl_tax' => $rowValue,
                'base_row_amount_incl_tax' => $baseRowValue,
            ];

            $applied[] = [
                'id' => $attribute->getCode(),
                'percent' => null,
                'hidden' => $this->_helper->includeInSubtotal($this->_store),
                'rates' => [[
                    'base_real_amount' => $baseRowValue,
                    'base_amount' => $baseRowValue,
                    'amount' => $rowValue,
                    'code' => $attribute->getCode(),
                    'title' => $title,
                    'percent' => null,
                    'position' => 1,
                    'priority' => -1000 + $k,
                ]],
            ];
        }

        //We set the TAX exclusive value
        $item->setWeeeTaxAppliedAmount($totalExclTaxValue);
        $item->setBaseWeeeTaxAppliedAmount($baseTotalExclTaxValue);
        $item->setWeeeTaxAppliedRowAmount($totalExclTaxRowValue);
        $item->setBaseWeeeTaxAppliedRowAmount($baseTotalExclTaxRowValue);

        $hasRowValue = (bool) $totalExclTaxRowValue;
        $processTotalResult = $this->_processTotalAmount($address, $totalExclTaxRowValue, $baseTotalExclTaxRowValue);
        $isTaxAffected = $hasRowValue || $processTotalResult;

        if ($hasRowValue) {
            $item->unsRowTotalInclTax()
                ->unsBaseRowTotalInclTax()
                ->unsPriceInclTax()
                ->unsBasePriceInclTax();
        }

        if ($this->_helper->isTaxable($this->_store)
            && !$this->_helper->isTaxIncluded($this->_store)
            && $totalExclTaxRowValue
            && !$this->_helper->includeInSubtotal($this->_store)
        ) {
            $item->setExtraTaxableAmount($totalExclTaxValue)
                ->setBaseExtraTaxableAmount($baseTotalExclTaxValue)
                ->setExtraRowTaxableAmount($totalExclTaxRowValue)
                ->setBaseExtraRowTaxableAmount($baseTotalExclTaxRowValue);
        }

        $this->_helper->setApplied($item, array_merge($this->_helper->getApplied($item), $productTaxes));
        if ($applied) {
            $this->_saveAppliedTaxes(
                $address,
                $applied,
                $item->getWeeeTaxAppliedAmount(),
                $item->getBaseWeeeTaxAppliedAmount(),
                null,
            );
        }

        return $isTaxAffected;
    }

    /**
     * Get the default store rate
     *
     * @param Mage_Sales_Model_Quote_Address $address
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     * @return mixed
     */
    protected function _customerRatePercent($address, $item)
    {
        $taxCalculationModel = Mage::getSingleton('tax/calculation');

        $request = $taxCalculationModel->getRateRequest(
            $address,
            $address->getQuote()->getBillingAddress(),
            $address->getQuote()->getCustomerTaxClassId(),
            $this->_store,
        );

        return $taxCalculationModel->getRate(
            $request->setProductClassId($item->getProduct()->getTaxClassId()),
        );
    }

    /**
     * Process row amount based on FPT total amount configuration setting
     *
     * @return  bool Whether tax is affected
     */
    protected function _processTotalAmount(Mage_Sales_Model_Quote_Address $address, float $rowValue, float $baseRowValue): bool
    {
        if ($this->_helper->includeInSubtotal($this->_store)) {
            $address->addTotalAmount('subtotal', $rowValue);
            $address->addBaseTotalAmount('subtotal', $baseRowValue);
            return true;
        }

        $address->setExtraTaxAmount($address->getExtraTaxAmount() + $rowValue);
        $address->setBaseExtraTaxAmount($address->getBaseExtraTaxAmount() + $baseRowValue);
        return false;
    }

    /**
     * Recalculate parent item amounts based on children results
     *
     * @return $this
     */
    #[\Override]
    protected function _recalculateParent(Mage_Sales_Model_Quote_Item_Abstract $item)
    {
        return $this;
    }

    /**
     * Reset information about FPT for shopping cart item
     *
     * @param Mage_Sales_Model_Quote_Item_Abstract $item
     */
    protected function _resetItemData($item)
    {
        $this->_helper->setApplied($item, []);

        $item->setBaseWeeeTaxDisposition(0);
        $item->setWeeeTaxDisposition(0);

        $item->setBaseWeeeTaxRowDisposition(0);
        $item->setWeeeTaxRowDisposition(0);

        $item->setBaseWeeeTaxAppliedAmount(0);
        $item->setBaseWeeeTaxAppliedRowAmount(0);

        $item->setWeeeTaxAppliedAmount(0);
        $item->setWeeeTaxAppliedRowAmount(0);
    }

    /**
     * Fetch FPT data to address object for display in totals block
     *
     * @return  $this
     */
    #[\Override]
    public function fetch(Mage_Sales_Model_Quote_Address $address)
    {
        return $this;
    }

    /**
     * Process model configuration array.
     * This method can be used for changing totals collect sort order
     *
     * @param   array $config
     * @param   Mage_Core_Model_Store $store
     * @return  array
     */
    #[\Override]
    public function processConfigArray($config, $store)
    {
        return $config;
    }

    /**
     * Returns the model for calculation
     *
     * @return Mage_Tax_Model_Calculation
     */
    protected function _getCalculator()
    {
        return Mage::getSingleton('tax/calculation');
    }

    /**
     * Set the helper object.
     *
     * @param Mage_Weee_Helper_Data $helper
     */
    public function setHelper($helper)
    {
        $this->_helper = $helper;
    }

    /**
     * Set the store Object
     *
     * @param  Mage_Core_Model_Store $store
     */
    public function setStore($store)
    {
        $this->_store = $store;
    }

    /**
     * No aggregated label for fixed product tax
     *
     * TODO: fix
     */
    #[\Override]
    public function getLabel()
    {
        return '';
    }
}
