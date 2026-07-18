<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

declare(strict_types=1);

/**
 * Session metrics block for dashboard
 */
class Mage_Log_Block_Dashboard_Session extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/session.phtml');
    }

    /**
     * Get session metrics
     */
    public function getMetrics(): array
    {
        return Mage::helper('log/dashboard')->getSessionMetrics(7);
    }

    /**
     * Format duration for display
     */
    public function formatDuration(int $seconds): string
    {
        return Mage::helper('log/dashboard')->formatDuration($seconds);
    }
}
