<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_SpeculationRules
 */

declare(strict_types=1);

class Maho_SpeculationRules_Block_Script extends Mage_Core_Block_Template
{
    /**
     * Get speculation rules JSON
     */
    public function getSpeculationRulesJson(): string
    {
        /** @var Maho_SpeculationRules_Helper_Data $helper */
        $helper = $this->helper('speculationrules');
        return $helper->generateSpeculationRulesJson();
    }

    /**
     * Check if speculation rules are enabled
     */
    public function isEnabled(): bool
    {
        /** @var Maho_SpeculationRules_Helper_Data $helper */
        $helper = $this->helper('speculationrules');
        return $helper->isEnabled();
    }

    /**
     * Render block HTML
     */
    #[\Override]
    protected function _toHtml(): string
    {
        if (!$this->isEnabled()) {
            return '';
        }

        return parent::_toHtml();
    }
}
