<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Page
 */

class Mage_Page_Model_Source_Robots extends Mage_Eav_Model_Entity_Attribute_Source_Abstract
{
    #[\Override]
    public function getAllOptions(): array
    {
        if (!$this->_options) {
            $this->_options = [
                ['value' => '', 'label' => Mage::helper('page')->__('-- Use Default --')],
                ['value' => 'INDEX,FOLLOW', 'label' => 'INDEX, FOLLOW'],
                ['value' => 'NOINDEX,FOLLOW', 'label' => 'NOINDEX, FOLLOW'],
                ['value' => 'INDEX,NOFOLLOW', 'label' => 'INDEX, NOFOLLOW'],
                ['value' => 'NOINDEX,NOFOLLOW', 'label' => 'NOINDEX, NOFOLLOW'],
            ];
        }
        return $this->_options;
    }

    public function toOptionArray(): array
    {
        return $this->getAllOptions();
    }
}
