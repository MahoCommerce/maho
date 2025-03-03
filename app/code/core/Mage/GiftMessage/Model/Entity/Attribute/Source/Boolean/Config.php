<?php

/**
 * Maho
 *
 * @package    Mage_GiftMessage
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Gift Message attribute source model
 *
 * @package    Mage_GiftMessage
 * @deprecated after 1.4.2.0
 */
class Mage_GiftMessage_Model_Entity_Attribute_Source_Boolean_Config extends Mage_Eav_Model_Entity_Attribute_Source_Boolean
{
    /**
     * Retrieve all attribute options
     *
     * @return array
     */
    #[\Override]
    public function getAllOptions()
    {
        if (!$this->_options) {
            $this->_options = [
                [
                    'label' => Mage::helper('giftmessage')->__('Yes'),
                    'value' =>  1,
                ],
                [
                    'label' => Mage::helper('giftmessage')->__('No'),
                    'value' =>  0,
                ],
                [
                    'label' => Mage::helper('giftmessage')->__('Use config'),
                    'value' =>  2,
                ],
            ];
        }
        return $this->_options;
    }
}
