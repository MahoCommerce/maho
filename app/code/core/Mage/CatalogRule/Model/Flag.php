<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_CatalogRule
 */

declare(strict_types=1);

class Mage_CatalogRule_Model_Flag extends Mage_Core_Model_Flag
{
    /**
     * Flag code
     *
     * @var string
     */
    #[\Override]
    protected $_flagCode = 'catalog_rules_dirty';

    #[\Override]
    protected function _beforeSave(): static
    {
        if ($this->getState()) {
            $this->setFlagData(bin2hex(random_bytes(16)));
        }
        return parent::_beforeSave();
    }

    /** @return array<int, string|null> */
    public function capturePendingChanges(): array
    {
        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $select = $write->select()
            ->from($this->_getResource()->getMainTable(), ['flag_id', 'flag_data'])
            ->where('flag_code = ?', $this->_flagCode)
            ->where('state <> ?', 0);

        return $write->fetchPairs($select);
    }

    /** @param array<int, string|null> $pending */
    public function clearAppliedChanges(array $pending): void
    {
        if ($pending === []) {
            return;
        }

        $write = Mage::getSingleton('core/resource')->getConnection('core_write');
        $table = $this->_getResource()->getMainTable();
        $values = ['state' => 0, 'last_update' => Mage::app()->getLocale()->formatDateForDb('now')];
        foreach ($pending as $id => $token) {
            $where = ['flag_id = ?' => $id, 'flag_code = ?' => $this->_flagCode, 'state <> ?' => 0];
            if ($token === null) {
                $where[] = 'flag_data IS NULL';
            } else {
                $where['flag_data = ?'] = $token;
            }
            $write->update($table, $values, $where);
        }
    }

    /**
     * Return true when the rules changed after the last apply. With no rule and no rule price,
     * an apply changes nothing, so the flag goes back to 0.
     */
    public function hasChangesToApply(): bool
    {
        $pending = $this->capturePendingChanges();
        if ($pending === []) {
            return false;
        }

        $resource = Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_write');
        foreach (['catalogrule/rule', 'catalogrule/rule_product_price'] as $table) {
            $select = $adapter->select()->from($resource->getTableName($table), [new Maho\Db\Expr('1')])->limit(1);
            if ($adapter->fetchOne($select)) {
                return true;
            }
        }

        $this->clearAppliedChanges($pending);
        return $this->capturePendingChanges() !== [];
    }
}
