<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2018-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Eav
 */

class Mage_Eav_Model_Entity_Attribute_Source_Store extends Mage_Eav_Model_Entity_Attribute_Source_Table
{
    #[\Override]
    public function getAllOptions(bool $withEmpty = true, bool $defaultValues = false): array
    {
        $this->_options ??= Mage::getResourceModel('core/store_collection')->load()->toOptionArray();
        return $this->_options;
    }
}
