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
 * Engagement metrics block for dashboard (conversion, new vs returning)
 */
class Mage_Log_Block_Dashboard_Engagement extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/engagement.phtml');
    }

    /**
     * Get customer conversion metrics
     */
    public function getConversion(): array
    {
        return Mage::helper('log/dashboard')->getCustomerConversion(7);
    }

    /**
     * Get new vs returning visitors
     */
    public function getNewVsReturning(): array
    {
        return Mage::helper('log/dashboard')->getNewVsReturning(7);
    }

    /**
     * Calculate percentage
     */
    public function calculatePercentage(int $count, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }
        return round(($count / $total) * 100, 1);
    }
}
