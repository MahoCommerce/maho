<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

class Maho_Ai_Model_Resource_Vector_Collection extends Mage_Core_Model_Resource_Db_Collection_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        // Resource model auto-resolves via Maho's <ai><resourceModel>ai_resource</resourceModel></ai>
        // convention - no need to pass it explicitly.
        $this->_init('ai/vector');
    }
}
