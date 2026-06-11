<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Dataflow
 */

declare(strict_types=1);

/**
 * Dataflow Batch export model
 *
 * @package    Mage_Dataflow
 *
 * @method Mage_Dataflow_Model_Resource_Batch_Export _getResource()
 * @method Mage_Dataflow_Model_Resource_Batch_Export getResource()
 * @method int getBatchId()
 * @method $this setBatchId(int $value)
 * @method int getStatus()
 * @method $this setStatus(int $value)
 */

class Mage_Dataflow_Model_Batch_Export extends Mage_Dataflow_Model_Batch_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/batch_export');
    }
}
