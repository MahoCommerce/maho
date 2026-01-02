<?php

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Option _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Option getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Option_Collection getCollection()
 *
 * @method int getAttributeId()
 * @method $this setAttributeId(int $value)
 * @method int getSortOrder()
 * @method $this setSortOrder(int $value)
 */
class Mage_Eav_Model_Entity_Attribute_Option extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_attribute_option');
    }

    /**
     * Retrieve swatch hex value
     *
     * @return string|false
     */
    public function getSwatchValue()
    {
        $swatch = Mage::getModel('eav/entity_attribute_option_swatch')
            ->load($this->getId(), 'option_id');
        if (!$swatch->getId()) {
            return false;
        }
        return $swatch->getValue();
    }
}
