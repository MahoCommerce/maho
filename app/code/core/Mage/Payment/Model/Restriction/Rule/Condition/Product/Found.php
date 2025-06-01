<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction rule product found condition
 */
class Mage_Payment_Model_Restriction_Rule_Condition_Product_Found extends Mage_Payment_Model_Restriction_Rule_Condition_Product_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('payment/restriction_rule_condition_product_found');
    }

    /**
     * Load array
     *
     * @param array $arr
     * @param string $key
     * @return $this
     */
    public function loadArray($arr, $key = 'conditions')
    {
        $this->setAttribute($arr['attribute'] ?? null);
        $this->setOperator($arr['operator'] ?? null);
        parent::loadArray($arr, $key);
        return $this;
    }

    /**
     * Return as array
     *
     * @return array
     */
    public function asArray(array $arrAttributes = [])
    {
        $out = parent::asArray();
        $out['attribute'] = $this->getAttribute();
        $out['operator'] = $this->getOperator();
        return $out;
    }

    /**
     * Get XML string
     *
     * @return string
     */
    public function asXml($containerKey = 'conditions', $itemKey = 'condition')
    {
        $xml = '<attribute>' . $this->getAttribute() . '</attribute>'
            . '<operator>' . $this->getOperator() . '</operator>'
            . parent::asXml($containerKey, $itemKey);
        return $xml;
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    public function loadAttributeOptions()
    {
        $this->setAttributeOption([
            null => Mage::helper('payment')->__('Please choose a condition to add.'),
        ]);
        return $this;
    }

    /**
     * Load value options
     *
     * @return $this
     */
    public function loadValueOptions()
    {
        $this->setValueOption([
            1 => Mage::helper('payment')->__('FOUND'),
            0 => Mage::helper('payment')->__('NOT FOUND'),
        ]);
        return $this;
    }

    /**
     * Load operator options
     *
     * @return $this
     */
    public function loadOperatorOptions()
    {
        $this->setOperatorOption([
            '==' => Mage::helper('payment')->__('is'),
            '!=' => Mage::helper('payment')->__('is not'),
        ]);
        return $this;
    }

    /**
     * Get value element type
     *
     * @return string
     */
    public function getValueElementType()
    {
        return 'select';
    }

    /**
     * Return as html
     *
     * @return string
     */
    public function asHtml()
    {
        $html = $this->getTypeElement()->getHtml() .
            Mage::helper('payment')->__(
                'If an item is %s in the cart with %s of these conditions true:',
                $this->getOperatorElement()->getHtml(),
                $this->getValueElement()->getHtml(),
            );
        if ($this->getId() != '1') {
            $html .= $this->getRemoveLinkHtml();
        }
        return $html;
    }

    /**
     * Validate
     *
     * @return bool
     */
    public function validate(Varien_Object $object)
    {
        $all = $this->getAggregator() === 'all';
        $true = (bool) $this->getValue();
        $found = false;
        foreach ($object->getQuote()->getAllItems() as $item) {
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
        // not found and we're making sure it doesn't exist
        elseif (!$found && !$true) {
            return true;
        }
        return false;
    }
}
