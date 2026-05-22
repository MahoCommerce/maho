<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Ai_Model_Vector extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('ai/vector');
    }

    /**
     * Return the stored vector as a float array.
     *
     * @return float[]
     */
    public function getVectorArray(): array
    {
        $json = $this->getData('vector');
        if (!$json) {
            return [];
        }
        return Mage::helper('core')->jsonDecode($json) ?? [];
    }
}
