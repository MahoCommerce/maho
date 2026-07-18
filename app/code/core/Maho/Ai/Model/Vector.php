<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

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
