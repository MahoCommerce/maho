<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

class Mage_CatalogRule_Model_Resource_Rule_Collection extends Mage_Rule_Model_Resource_Rule_Collection_Abstract
{
    /**
     * Store associated with rule entities information map
     *
     * @var array
     */
    protected $_associatedEntitiesMap = [
        'website' => [
            'associations_table' => 'catalogrule/website',
            'rule_id_field'      => 'rule_id',
            'entity_id_field'    => 'website_id',
        ],
    ];

    /**
     * Set resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('catalogrule/rule');
    }

    /**
     * Find product attribute in conditions or actions
     *
     * @param string $attributeCode
     * @return $this
     */
    public function addAttributeInConditionFilter($attributeCode)
    {
        $adapter = $this->getConnection();
        $field = (string) $this->_getMappedField('conditions_serialized');
        $expr = $adapter->getJsonSearchExpr($field, $attributeCode, '$**.attribute');
        $this->getSelect()->where((string) $expr);

        return $this;
    }
}
