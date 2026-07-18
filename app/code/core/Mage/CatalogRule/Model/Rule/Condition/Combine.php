<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

// Implementation lives in Mage_Catalog; this subclass re-skins it with the 'catalogrule/...'
// factory prefix and catalogrule translations, preserving back-compat for saved price rules.
class Mage_CatalogRule_Model_Rule_Condition_Combine extends Mage_Catalog_Model_Rule_Condition_Combine
{
    public function __construct()
    {
        parent::__construct();
        $this->setType('catalogrule/rule_condition_combine');
    }

    #[\Override]
    protected function _getConditionPrefix(): string
    {
        return 'catalogrule/rule_condition';
    }

    #[\Override]
    protected function _getConditionHelper(): string
    {
        return 'catalogrule';
    }
}
