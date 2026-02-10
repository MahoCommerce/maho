<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Dataflow
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
class Mage_Dataflow_Model_Resource_Batch_Export extends Mage_Dataflow_Model_Resource_Batch_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('dataflow/batch_export', 'batch_export_id');
    }
}
