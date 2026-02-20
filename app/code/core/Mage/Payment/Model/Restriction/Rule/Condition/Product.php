<?php

/**
 * Maho
 *
 * @package    Mage_Payment
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Payment restriction rule product condition
 */
class Mage_Payment_Model_Restriction_Rule_Condition_Product extends Mage_Rule_Model_Condition_Product_Abstract
{
    /**
     * Fallback form instance
     */
    protected ?\Maho\Data\Form $_form = null;

    /**
     * Associated rule instance
     *
     * @var Mage_Payment_Model_Restriction_Rule|null
     */
    protected $_rule;

    public function __construct()
    {
        parent::__construct();
        $this->setType('payment/restriction_rule_condition_product');
        $this->loadAttributeOptions();
        $this->loadOperatorOptions();
    }

    /**
     * Get rule
     *
     * @return Mage_Payment_Model_Restriction_Rule|null
     */
    public function getRule()
    {
        return $this->_rule ?? null;
    }

    /**
     * Validate Product Rule Condition
     *
     * @return bool
     */
    #[\Override]
    public function validate(\Maho\DataObject $object)
    {
        $product = false;
        if ($object->getProduct() instanceof Mage_Catalog_Model_Product) {
            $product = $object->getProduct();
        } else {
            $product = Mage::getModel('catalog/product')->load($object->getProductId());
        }

        if (!$product instanceof Mage_Catalog_Model_Product) {
            return false;
        }

        $product->setQuoteItemQty($object->getQty())
            ->setQuoteItemPrice($object->getPrice())
            ->setQuoteItemRowTotal($object->getBaseRowTotal());

        return $this->validateAttribute($product->getData($this->getAttribute()));
    }

    /**
     * Override getForm to provide fallback when rule is not available
     */
    #[\Override]
    public function getForm()
    {
        if ($this->getRule()) {
            return $this->getRule()->getForm();
        }

        // Fallback: create a basic form if rule is not available
        if (!$this->_form) {
            $this->_form = new \Maho\Data\Form();
        }
        return $this->_form;
    }
}
