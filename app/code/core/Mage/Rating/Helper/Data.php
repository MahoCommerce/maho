<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Rating
 */

declare(strict_types=1);

class Mage_Rating_Helper_Data extends Mage_Core_Helper_Abstract
{
    protected $_moduleName = 'Mage_Rating';

    /**
     * Render a read-only star rating from a 0-100 summary percentage,
     * rounded to half stars (DaisyUI rating markup).
     */
    public function getStarsHtml(float|int|string|null $percent, string $classes = 'rating-sm'): string
    {
        $halves = max(0, min(10, (int) round(((float) $percent) / 10)));
        $label = $this->escapeHtml($this->__('Rated %s out of 5', $halves / 2));
        $html = '<div class="rating rating-half ' . $this->escapeHtml($classes) . '" role="img" aria-label="' . $label . '">';
        for ($i = 1; $i <= 10; $i++) {
            $html .= '<div class="mask mask-star-2 ' . ($i % 2 ? 'mask-half-1' : 'mask-half-2') . '"'
                . ($i === $halves ? ' aria-current="true"' : '')
                . '></div>';
        }
        return $html . '</div>';
    }
}
