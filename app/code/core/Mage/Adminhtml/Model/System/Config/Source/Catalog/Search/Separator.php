<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_System_Config_Source_Catalog_Search_Separator
{
    public function toOptionArray(): array
    {
        return [
            [
                'value' => 'OR',
                'label' => 'OR',
            ], [
                'value' => 'AND',
                'label' => 'AND',
            ],
        ];
    }
}
