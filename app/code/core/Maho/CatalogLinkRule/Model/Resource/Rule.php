<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_CatalogLinkRule
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

/**
 * Catalog Link Rule Resource Model
 *
 * @category   Maho
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
