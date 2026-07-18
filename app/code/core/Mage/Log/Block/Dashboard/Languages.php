<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Log
 */

declare(strict_types=1);

/**
 * Language breakdown block for dashboard
 */
class Mage_Log_Block_Dashboard_Languages extends Mage_Adminhtml_Block_Dashboard_Abstract
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('log/dashboard/languages.phtml');
    }

    /**
     * Get language breakdown
     */
    public function getLanguages(): array
    {
        return Mage::helper('log/dashboard')->getLanguageBreakdown(7, 10);
    }

    /**
     * Get language name from code
     */
    public function getLanguageName(string $code): string
    {
        return Mage::helper('log/dashboard')->getLanguageName($code);
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
     * Get flag emoji for language code
     */
    public function getLanguageFlag(string $code): string
    {
        $flags = [
            'en' => '🇬🇧',
            'es' => '🇪🇸',
            'fr' => '🇫🇷',
            'de' => '🇩🇪',
            'it' => '🇮🇹',
            'pt' => '🇵🇹',
            'nl' => '🇳🇱',
            'ru' => '🇷🇺',
            'zh' => '🇨🇳',
            'ja' => '🇯🇵',
            'ko' => '🇰🇷',
            'ar' => '🇸🇦',
            'hi' => '🇮🇳',
            'pl' => '🇵🇱',
            'tr' => '🇹🇷',
            'vi' => '🇻🇳',
            'th' => '🇹🇭',
            'sv' => '🇸🇪',
            'da' => '🇩🇰',
            'fi' => '🇫🇮',
            'no' => '🇳🇴',
            'cs' => '🇨🇿',
            'el' => '🇬🇷',
            'he' => '🇮🇱',
            'hu' => '🇭🇺',
            'id' => '🇮🇩',
            'ms' => '🇲🇾',
            'ro' => '🇷🇴',
            'sk' => '🇸🇰',
            'uk' => '🇺🇦',
        ];

        return $flags[$code] ?? '🌐';
    }
}
