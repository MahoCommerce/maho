<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Source_EmbedPlatform
{
    public function toOptionArray(): array
    {
        $options = [];
        foreach (Maho_Ai_Model_Platform::getProvidersWithCapability('embed') as $code => $label) {
            if (isset(Maho_Ai_Model_Platform::PACKAGES[$code])) {
                $label .= Mage::helper('core')->packageInstallWarning(Maho_Ai_Model_Platform::PACKAGES[$code]);
            }
            $options[] = ['value' => $code, 'label' => $label];
        }
        return $options;
    }
}
