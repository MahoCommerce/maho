<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ContentVersion
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ContentVersion_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function getMaxVersions(): int
    {
        return (int) Mage::getStoreConfig('general/contentversion/max_versions');
    }

    public function getMaxAgeDays(): int
    {
        return (int) Mage::getStoreConfig('general/contentversion/max_age_days');
    }
}
