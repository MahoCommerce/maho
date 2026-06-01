<?php

/**
 * Maho
 *
 * @package    Mage_CatalogRule
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
