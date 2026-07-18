<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

declare(strict_types=1);

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
