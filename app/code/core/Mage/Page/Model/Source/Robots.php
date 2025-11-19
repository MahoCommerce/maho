<?php

/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Page
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
