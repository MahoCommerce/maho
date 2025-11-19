<?php

/**
 * Maho
 *
 * @package    Mage_Bundle
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Product View block (to modify getTierPrices method)
 *
 * @package    Mage_Bundle
 * @module     Catalog
 */
class Mage_Bundle_Block_Catalog_Product_View extends Mage_Catalog_Block_Product_View
{
    /**
     * Get tier prices (formatted)
     *
     * @param Mage_Catalog_Model_Product|null $product
     * @return array
     */
    #[\Override]
    public function getTierPrices($product = null)
    {
        if ($product === null) {
            $product = $this->getProduct();
        }

        $res = [];

        $prices = $product->getFormatedTierPrice();
        if (is_array($prices)) {
            $store = Mage::app()->getStore();
            $helper = Mage::helper('tax');
            $specialPrice = $product->getSpecialPrice();
            $defaultDiscount = max($product->getGroupPrice(), $specialPrice ? 100 - $specialPrice : 0);
            foreach ($prices as $price) {
                if ($defaultDiscount < $price['price']) {
                    $price['price_qty'] += 0;
                    $price['savePercent'] = ceil(100 - $price['price']);

                    $priceExclTax = $helper->getPrice($product, $price['website_price']);
                    $price['formated_price'] = $store->formatPrice($store->convertPrice($priceExclTax));

                    $priceInclTax = $helper->getPrice($product, $price['website_price'], true);
                    $price['formated_price_incl_tax'] = $store->formatPrice($store->convertPrice($priceInclTax));

                    $res[] = $price;
                }
            }
        }

        return $res;
    }
}
