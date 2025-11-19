<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

class Maho_CatalogLinkRule_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getLinkTypes(): array
    {
        return [
            Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED   => $this->__('Related Products'),
            Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL    => $this->__('Up-sell Products'),
            Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL => $this->__('Cross-sell Products'),
        ];
    }

    /**
     * Get available sort orders
     */
    public function getSortOrders(): array
    {
        return [
            'random'     => $this->__('Random (Default)'),
            'price_asc'  => $this->__('Price: Low to High'),
            'price_desc' => $this->__('Price: High to Low'),
            'name_asc'   => $this->__('Name: A-Z'),
            'name_desc'  => $this->__('Name: Z-A'),
            'newest'     => $this->__('Newest First'),
            'oldest'     => $this->__('Oldest First'),
        ];
    }
}
