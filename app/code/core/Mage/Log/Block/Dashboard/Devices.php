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
 * Devices and browsers block for dashboard
 */
class Mage_Log_Block_Dashboard_Devices extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/devices.phtml');
    }

    /**
     * Get device and browser breakdown
     */
    public function getBreakdown(): array
    {
        return Mage::helper('log/dashboard')->getDeviceBreakdown(7);
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

    /**
     * Get device icon/emoji
     */
    public function getDeviceIcon(string $device): string
    {
        $icons = [
            'mobile' => '📱',
            'tablet' => '📲',
            'desktop' => '💻',
        ];
        return $icons[$device] ?? '';
    }
}
