<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Maho info API
 * @deprecated
 */
class Mage_Core_Model_Magento_Api extends Mage_Core_Model_Maho_Api
{
    /**
     * Retrieve information about the current Maho installation
     */
    #[\Override]
    public function info(): array
    {
        Mage::log('Deprecated API call to magentoInfo, use mahoInfo instead');

        $result = parent::info();
        $result['magento_version'] = '1.9.4.5';
        $result['magento_edition'] = 'Community';

        return $result;
    }
}
