<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

// Back-compat alias: the implementation now lives in Mage_Catalog so dynamic categories can
// use product conditions without requiring this module. The 'catalogrule/rule_condition_product'
// factory alias is preserved for existing saved rules and Maho_CatalogLinkRule subclasses.
class Mage_CatalogRule_Model_Rule_Condition_Product extends Mage_Catalog_Model_Rule_Condition_Product {}
