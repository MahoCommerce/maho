<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

// Back-compat alias: the implementation now lives in Mage_Catalog so dynamic categories can
// use product conditions without requiring this module. The 'catalogrule/rule_condition_product'
// factory alias is preserved for existing saved rules and Maho_CatalogLinkRule subclasses.
class Mage_CatalogRule_Model_Rule_Condition_Product extends Mage_Catalog_Model_Rule_Condition_Product {}
