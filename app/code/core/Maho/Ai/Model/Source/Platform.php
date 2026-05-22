<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_Source_Platform
{
    public function toOptionArray(): array
    {
        $options = [];
        foreach (Maho_Ai_Model_Platform::getAll() as $code => $label) {
            if (isset(Maho_Ai_Model_Platform::PACKAGES[$code])) {
                $label .= Mage::helper('core')->packageInstallWarning(Maho_Ai_Model_Platform::PACKAGES[$code]);
            }
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
    }
}
