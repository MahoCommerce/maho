<?php

/**
 * Maho
 *
 * @package    Mage_SalesRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Class Mage_SalesRule_Model_Rule_Condition_Product_Found
 *
 * @package    Mage_SalesRule
 *
 * @method setValueOption(array $array)
 */
class Mage_SalesRule_Model_Rule_Condition_Product_Found extends Mage_SalesRule_Model_Rule_Condition_Product_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('salesrule/rule_condition_product_found');
    }

    /**
     * Load value options
     *
     * @return $this
     */
    #[\Override]
    public function loadValueOptions()
    {
        $this->setValueOption([
            1 => static::$translate ? Mage::helper('salesrule')->__('FOUND') : 'FOUND',
            0 => static::$translate ? Mage::helper('salesrule')->__('NOT FOUND') : 'NOT FOUND',
        ]);
        return $this;
    }

    /**
     * @return string
     */
    #[\Override]
    public function asHtml()
    {
        $html = $this->getTypeElement()->getHtml() . Mage::helper('salesrule')->__('If an item is %s in the cart with %s of these conditions true:', $this->getValueElement()->getHtml(), $this->getAggregatorElement()->getHtml());
        if ($this->getId() != '1') {
            $html .= $this->getRemoveLinkHtml();
        }
        return $html;
    }

    /**
     * validate
     *
     * @param \Maho\DataObject $object Quote
     * @return bool
     */
    #[\Override]
    public function validate(\Maho\DataObject $object)
    {
        $all = $this->getAggregator() === 'all';
        $true = (bool) $this->getValue();
        $found = false;
        foreach ($object->getAllItems() as $item) {
            $found = $all;
            foreach ($this->getConditions() as $cond) {
                $validated = $cond->validate($item);
                if (($all && !$validated) || (!$all && $validated)) {
                    $found = $validated;
                    break;
                }
            }
            if (($found && $true) || (!$true && $found)) {
                break;
            }
        }
        // found an item and we're looking for existing one
        if ($found && $true) {
            return true;
        }
        if (!$found && !$true) {
            // not found and we're making sure it doesn't exist
            return true;
        }
        return false;
    }
}
