<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Customer_Model_Resource_Form_Attribute extends Mage_Eav_Model_Resource_Form_Attribute
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('customer/form_attribute', 'attribute_id');
    }
}
