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

class Maho_SpeculationRules_Model_Observer
{
    /**
     * Add speculation rules block to head
     */
    public function addSpeculationRulesBlock(Varien_Event_Observer $observer): void
    {
        /** @var Mage_Core_Model_Layout $layout */
        $layout = $observer->getEvent()->getLayout();

        if (!$layout) {
            return;
        }

        $head = $layout->getBlock('head');
        if (!$head || !$head instanceof Mage_Page_Block_Html_Head) {
            return;
        }

        // Check if speculation rules are enabled
        /** @var Maho_SpeculationRules_Helper_Data $helper */
        $helper = Mage::helper('speculationrules');
        if (!$helper->isEnabled()) {
            return;
        }

        // Create speculation rules block
        /** @var Maho_SpeculationRules_Block_Script|false $speculationRulesBlock */
        $speculationRulesBlock = $layout->createBlock(
            'speculationrules/script',
            'speculation_rules_script',
            ['template' => 'speculationrules/script.phtml'],
        );

        if ($speculationRulesBlock instanceof Maho_SpeculationRules_Block_Script) {
            $head->append($speculationRulesBlock);
        }
    }
}
