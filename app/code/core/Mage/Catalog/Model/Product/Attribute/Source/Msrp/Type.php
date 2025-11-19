<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Catalog_Model_Product_Attribute_Source_Msrp_Type extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    /**
     * Display Product Price on gesture
     */
    public const TYPE_ON_GESTURE = '1';

    /**
     * Display Product Price in cart
     */
    public const TYPE_IN_CART    = '2';

    /**
     * Display Product Price before order confirmation
     */
    public const TYPE_BEFORE_ORDER_CONFIRM = '3';

    /**
     * Get all options
     *
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = [
                [
                    'label' => Mage::helper('catalog')->__('In Cart'),
                    'value' => self::TYPE_IN_CART,
                ],
                [
                    'label' => Mage::helper('catalog')->__('Before Order Confirmation'),
                    'value' => self::TYPE_BEFORE_ORDER_CONFIRM,
                ],
                [
                    'label' => Mage::helper('catalog')->__('On Gesture'),
                    'value' => self::TYPE_ON_GESTURE,
                ],
            ];
        }
        return $this->_options;
    }

    public function toOptionArray(): array
    {
        return $this->getAllOptions();
    }
}
