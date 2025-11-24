<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Log_Block_Dashboard_Toppages extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/toppages.phtml');
    }

    public function getTopPages(): array
    {
        return Mage::helper('log/dashboard')->getTopPages(7, 10);
    }

    /**
     * Format URL for display (shorten if needed)
     */
    public function formatUrl(string $url, int $maxLength = 50): string
    {
        if (mb_strlen($url) <= $maxLength) {
            return $url;
        }
        return mb_substr($url, 0, $maxLength - 3) . '...';
    }
}
