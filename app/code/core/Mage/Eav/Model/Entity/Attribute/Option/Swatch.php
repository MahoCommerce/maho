<?php

/**
 * SPDX-FileCopyrightText: 2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

declare(strict_types=1);

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
