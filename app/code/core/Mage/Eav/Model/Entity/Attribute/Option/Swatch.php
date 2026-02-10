<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Option_Swatch _getResource()
 * @method Mage_Eav_Model_Resource_Entity_Attribute_Option_Swatch getResource()
 */
class Mage_Eav_Model_Entity_Attribute_Option_Swatch extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('eav/entity_attribute_option_swatch');
    }
}
