<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

class Mage_ImportExport_Model_Source_Export_Entity
{
    public function toOptionArray(): array
    {
        return Mage_ImportExport_Model_Config::getModelsComboOptions(
            Mage_ImportExport_Model_Export::CONFIG_KEY_ENTITIES,
            true,
        );
    }
}
