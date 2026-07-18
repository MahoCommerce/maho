<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CatalogLinkRule
 */

declare(strict_types=1);

/**
 * Catalog Link Rule Resource Model
 *
 * @package    Maho_CatalogLinkRule
 */
class Maho_CatalogLinkRule_Model_Resource_Rule extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('cataloglinkrule/rule', 'rule_id');
    }
}
