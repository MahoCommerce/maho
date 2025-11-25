<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Traffic sources block for dashboard
 */
class Mage_Log_Block_Dashboard_Sources extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/sources.phtml');
    }

    /**
     * Get traffic sources breakdown
     */
    public function getSources(): array
    {
        return Mage::helper('log/dashboard')->getTrafficSources(7, 10);
    }

    /**
     * Calculate percentage for a source
     */
    public function calculatePercentage(int $count, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }
        return round(($count / $total) * 100, 1);
    }

    /**
     * Format domain for display
     */
    public function formatDomain(string $domain): string
    {
        if ($domain === 'direct') {
            return $this->__('Direct / No referrer');
        }
        return $domain;
    }
}
