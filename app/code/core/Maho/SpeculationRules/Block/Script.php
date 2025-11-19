<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_SpeculationRules
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
