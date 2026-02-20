<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Giftcard
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Giftcard_Block_Adminhtml_Catalog_Product_Edit_Tab_Giftcard extends Mage_Adminhtml_Block_Widget implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('maho/giftcard/catalog/product/edit/giftcard.phtml');
    }

    /**
     * Get product
     */
    public function getProduct(): ?Mage_Catalog_Model_Product
    {
        $product = Mage::registry('current_product');

        // Manually load gift card attributes since they're not in attribute sets
        if ($product && $product->getId()) {
            $attributes = [
                'giftcard_type',
                'giftcard_amounts',
                'giftcard_min_amount',
                'giftcard_max_amount',
                'giftcard_allow_message',
                'giftcard_lifetime',
            ];

            foreach ($attributes as $code) {
                if (!$product->hasData($code)) {
                    $value = $product->getResource()->getAttributeRawValue(
                        $product->getId(),
                        $code,
                        $product->getStoreId(),
                    );
                    $product->setData($code, $value);
                }
            }
        }

        return $product;
    }

    /**
     * Get tab label
     */
    #[\Override]
    public function getTabLabel()
    {
        return $this->__('Gift Card');
    }

    /**
     * Get tab title
     */
    #[\Override]
    public function getTabTitle()
    {
        return $this->__('Gift Card Options');
    }

    /**
     * Can show tab
     */
    #[\Override]
    public function canShowTab()
    {
        return $this->getProduct()->getTypeId() === 'giftcard';
    }

    /**
     * Is tab hidden
     */
    #[\Override]
    public function isHidden()
    {
        return false;
    }
}
