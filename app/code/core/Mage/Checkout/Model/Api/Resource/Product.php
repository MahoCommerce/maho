<?php

/**
 * Maho
 *
 * @package    Mage_Checkout
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Checkout_Model_Api_Resource_Product extends Mage_Checkout_Model_Api_Resource
{
    /**
     * Default ignored attribute codes
     *
     * @var array
     */
    protected $_ignoredAttributeCodes = ['entity_id', 'attribute_set_id', 'entity_type_id'];

    /**
     * Return loaded product instance
     *
     * @param  int|string $productId (SKU or ID)
     * @param  int|string $store
     * @param  string $identifierType
     * @return Mage_Catalog_Model_Product
     */
    protected function _getProduct($productId, $store = null, $identifierType = null)
    {
        return Mage::helper('catalog/product')->getProduct(
            $productId,
            $this->_getStoreId($store),
            $identifierType,
        );
    }

    /**
     * Get request for product add to cart procedure
     *
     * @param   mixed $requestInfo
     * @return \Maho\DataObject
     */
    protected function _getProductRequest($requestInfo)
    {
        if ($requestInfo instanceof \Maho\DataObject) {
            $request = $requestInfo;
        } elseif (is_numeric($requestInfo)) {
            $request = new \Maho\DataObject();
            $request->setQty($requestInfo);
        } else {
            $request = new \Maho\DataObject($requestInfo);
        }

        if (!$request->hasQty()) {
            $request->setQty(1);
        }
        return $request;
    }

    /**
     * Get QuoteItem by Product and request info
     *
     * @return Mage_Sales_Model_Quote_Item
     * @throw Mage_Core_Exception
     */
    protected function _getQuoteItemByProduct(
        Mage_Sales_Model_Quote $quote,
        Mage_Catalog_Model_Product $product,
        \Maho\DataObject $requestInfo,
    ) {
        $cartCandidates = $product->getTypeInstance(true)
                        ->prepareForCartAdvanced(
                            $requestInfo,
                            $product,
                            Mage_Catalog_Model_Product_Type_Abstract::PROCESS_MODE_FULL,
                        );

        /**
         * Error message
         */
        if (is_string($cartCandidates)) {
            throw Mage::throwException($cartCandidates);
        }

        /**
         * If prepare process return one object
         */
        if (!is_array($cartCandidates)) {
            $cartCandidates = [$cartCandidates];
        }

        /** @var Mage_Sales_Model_Quote_Item $item */
        $item = null;
        foreach ($cartCandidates as $candidate) {
            if ($candidate->getParentProductId()) {
                continue;
            }

            $item = $quote->getItemByProduct($candidate);
        }

        if (is_null($item)) {
            $item = Mage::getModel('sales/quote_item');
        }

        return $item;
    }
}
