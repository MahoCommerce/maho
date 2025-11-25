<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Log
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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

    public function calculatePercentage(int $count, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }
        return round(($count / $total) * 100, 1);
    }

    public function getDeviceIcon(string $device): string
    {
        $icons = [
            'desktop' => 'device-desktop',
            'tablet' => 'device-tablet',
            'mobile' => 'device-mobile',
        ];
        $iconName = $icons[$device] ?? null;
        return $iconName ? $this->getIconSvg($iconName) : '';
    }

    public function getBrowserIcon(string $browser): string
    {
        $iconName = 'brand-' . strtolower(str_replace(' ', '', $browser));
        $icon = $this->getIconSvg($iconName);
        return $icon ?: $this->getIconSvg('browser');
    }
}
