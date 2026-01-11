<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction rule product subselect condition
 */
class Mage_Payment_Model_Restriction_Rule_Condition_Product_Subselect extends Mage_Payment_Model_Restriction_Rule_Condition_Product_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('payment/restriction_rule_condition_product_subselect')
            ->setValue(null);
    }

    /**
     * Load array
     *
     * @param array $arr
     * @param string $key
     * @return $this
     */
    #[\Override]
    public function loadArray($arr, $key = 'conditions')
    {
        $this->setAttribute($arr['attribute']);
        $this->setOperator($arr['operator']);
        $this->setValue($arr['value']);
        parent::loadArray($arr, $key);
        return $this;
    }

    /**
     * Return as array
     *
     * @return array
     */
    #[\Override]
    public function asArray(array $arrAttributes = [])
    {
        $out = parent::asArray();
        $out['attribute'] = $this->getAttribute();
        $out['operator'] = $this->getOperator();
        $out['value'] = $this->getValue();
        return $out;
    }

    /**
     * Get XML string
     *
     * @return string
     */
    #[\Override]
    public function asXml($containerKey = 'conditions', $itemKey = 'condition')
    {
        $xml = '<attribute>' . $this->getAttribute() . '</attribute>'
            . '<operator>' . $this->getOperator() . '</operator>'
            . '<value>' . $this->getValue() . '</value>'
            . parent::asXml($containerKey, $itemKey);
        return $xml;
    }

    /**
     * Load attribute options
     *
     * @return $this
     */
    #[\Override]
    public function loadAttributeOptions()
    {
        $this->setAttributeOption([
            'qty' => Mage::helper('payment')->__('total quantity'),
            'base_row_total' => Mage::helper('payment')->__('total amount'),
        ]);
        return $this;
    }

    /**
     * Load operator options
     *
     * @return $this
     */
    #[\Override]
    public function loadOperatorOptions()
    {
        $this->setOperatorOption([
            '==' => Mage::helper('payment')->__('is'),
            '!=' => Mage::helper('payment')->__('is not'),
            '>=' => Mage::helper('payment')->__('equals or greater than'),
            '<=' => Mage::helper('payment')->__('equals or less than'),
            '>' => Mage::helper('payment')->__('greater than'),
            '<' => Mage::helper('payment')->__('less than'),
        ]);
        return $this;
    }

    /**
     * Get value element type
     *
     * @return string
     */
    #[\Override]
    public function getValueElementType()
    {
        return 'text';
    }

    /**
     * Return as html
     *
     * @return string
     */
    #[\Override]
    public function asHtml()
    {
        $html = $this->getTypeElement()->getHtml() .
            Mage::helper('payment')->__(
                'If %s %s %s for a subselection of items in cart matching %s of these conditions:',
                $this->getAttributeElement()->getHtml(),
                $this->getOperatorElement()->getHtml(),
                $this->getValueElement()->getHtml(),
                $this->getAggregatorElement()->getHtml(),
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
    #[\Override]
    public function validate(\Maho\DataObject $object)
    {
        if (!$this->getConditions()) {
            return false;
        }

        $attr = $this->getAttribute();
        $total = 0;
        foreach ($object->getQuote()->getAllItems() as $item) {
            if (parent::validate($item)) {
                $total += $item->getData($attr);
            }
        }
        return $this->validateAttribute($total);
    }
}
