<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_ConfigurableSwatches
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_ConfigurableSwatches
 */
class Mage_ConfigurableSwatches_Block_Catalog_Media_Js_Product extends Mage_ConfigurableSwatches_Block_Catalog_Media_Js_Abstract
{
    /**
     * Return array of single product -- current product
     *
     * @return array
     */
    #[\Override]
    public function getProducts()
    {
        $product = Mage::registry('product');

        if (!$product) {
            return [];
        }

        return [$product];
    }

    /**
     * Default to base image type
     *
     * @return string
     */
    #[\Override]
    public function getImageType()
    {
        $type = parent::getImageType();

        if (empty($type)) {
            $type = Mage_ConfigurableSwatches_Helper_Productimg::MEDIA_IMAGE_TYPE_BASE;
        }

        return $type;
    }

    /**
     * instruct image image type to be loaded
     *
     * @return array
     */
    #[\Override]
    protected function _getImageSizes()
    {
        return ['image'];
    }
}
