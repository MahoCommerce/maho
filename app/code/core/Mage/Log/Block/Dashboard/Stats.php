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
 * Visitor statistics block for dashboard
 */
class Mage_Log_Block_Dashboard_Stats extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/stats.phtml');
    }

    /**
     * Get number of currently online visitors
     */
    public function getOnlineCount(): int
    {
        return Mage::helper('log/dashboard')->getOnlineCount();
    }

    /**
     * Get number of visitors today
     */
    public function getTodayCount(): int
    {
        return Mage::helper('log/dashboard')->getTodayCount();
    }

    /**
     * Get number of visitors this week
     */
    public function getWeekCount(): int
    {
        return Mage::helper('log/dashboard')->getWeekCount();
    }
}
