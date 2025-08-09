<?php

/**
 * Maho
 *
 * @package    Mage_Paypal
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Paypal_Model_System_Config_Source_UrlMethod
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'GET', 'label' => 'GET'],
            ['value' => 'POST', 'label' => 'POST'],
        ];
    }
}
