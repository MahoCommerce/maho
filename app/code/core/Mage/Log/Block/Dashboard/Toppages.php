<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Top pages block for dashboard
 */
class Mage_Log_Block_Dashboard_Toppages extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/toppages.phtml');
    }

    /**
     * Get top visited pages
     */
    public function getTopPages(): array
    {
        return Mage::helper('log/dashboard')->getTopPages(7, 10);
    }

    /**
     * Format URL for display (shorten if needed)
     */
    public function formatUrl(string $url, int $maxLength = 50): string
    {
        if (strlen($url) <= $maxLength) {
            return $url;
        }
        return substr($url, 0, $maxLength - 3) . '...';
    }
}
