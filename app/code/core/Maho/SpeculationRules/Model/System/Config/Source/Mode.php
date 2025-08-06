<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_SpeculationRules
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_SpeculationRules_Model_System_Config_Source_Mode
{
    /**
     * @return array<int, array{value: string, label: string}>
     */
    public function toOptionArray(): array
    {
        return [
            ['value' => 'prefetch', 'label' => Mage::helper('speculationrules')->__('Prefetch')],
            ['value' => 'prerender', 'label' => Mage::helper('speculationrules')->__('Prerender')],
        ];
    }

    /**
     * Get options in "key-value" format
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'prefetch' => Mage::helper('speculationrules')->__('Prefetch'),
            'prerender' => Mage::helper('speculationrules')->__('Prerender'),
        ];
    }
}
