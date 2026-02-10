<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * Maho info API
 */
class Mage_Core_Model_Maho_Api extends Mage_Api_Model_Resource_Abstract
{
    /**
     * Retrieve information about the current Maho installation
     */
    public function info(): array
    {
        $result = [];
        $result['maho_version'] = Mage::getVersion();

        return $result;
    }
}
