<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Giftcard
 */

declare(strict_types=1);

class Maho_Giftcard_Model_Resource_Giftcard extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('giftcard/giftcard', 'giftcard_id');
    }

    /**
     * Load gift card by code
     *
     * @return $this
     */
    public function loadByCode(Maho_Giftcard_Model_Giftcard $object, string $code): self
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable())
            ->where('code = ?', $code);
        $this->_addWebsiteIdsColumn($select);

        $data = $adapter->fetchRow($select);

        if ($data) {
            $object->setData($data);
        }

        $this->_afterLoad($object);

        return $this;
    }

    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        $this->_addWebsiteIdsColumn($select);
        return $select;
    }

    /**
     * Hydrate `website_ids` as an aggregated CSV on the load select, so
     * getWebsiteIds() parses it instead of firing a junction query per read
     * (the website check runs on every quote totals collect).
     */
    protected function _addWebsiteIdsColumn(Maho\Db\Select $select): void
    {
        $select->columns([
            'website_ids' => new Maho\Db\Expr(sprintf(
                '(SELECT %s FROM %s gw WHERE gw.giftcard_id = %s.giftcard_id)',
                $this->_getReadAdapter()->getGroupConcatExpr('gw.website_id'),
                $this->getTable('giftcard/website'),
                $this->getMainTable(),
            )),
        ]);
    }

    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        // Set timestamps in UTC
        $now = Mage::app()->getLocale()->formatDateForDb('now');
        if (!$object->getId()) {
            $object->setCreatedAt($now);
        }
        $object->setUpdatedAt($now);

        $ids = $object->getData('website_ids');
        if (is_array($ids)) {
            $this->_validateWebsiteIds($object, $ids);
        }

        return parent::_beforeSave($object);
    }

    /**
     * @param int[] $ids
     */
    protected function _validateWebsiteIds(Mage_Core_Model_Abstract $object, array $ids): void
    {
        // An empty set would orphan the card on every website; delete the card instead
        if (empty($ids)) {
            throw new Mage_Core_Exception(
                Mage::helper('giftcard')->__('A gift card must be associated with at least one website.'),
            );
        }

        // The balance is denominated in one currency, so all websites must share it
        $currencies = [];
        foreach ($ids as $websiteId) {
            $currencies[Mage::app()->getWebsite((int) $websiteId)->getBaseCurrencyCode()] = true;
        }
        if (count($currencies) > 1) {
            throw new Mage_Core_Exception(
                Mage::helper('giftcard')->__('A gift card can only be assigned to websites that share the same base currency.'),
            );
        }

        // Re-scoping an existing card must not re-denominate its balance
        if ($object->getId()) {
            $stored = $this->getWebsiteIds((int) $object->getId());
            if ($stored !== []) {
                $storedCurrency = Mage::app()->getWebsite($stored[0])->getBaseCurrencyCode();
                if (!isset($currencies[$storedCurrency])) {
                    throw new Mage_Core_Exception(
                        Mage::helper('giftcard')->__('A gift card cannot be moved to websites with a different base currency.'),
                    );
                }
            }
        }
    }

    /**
     * Sync the junction from the pending `website_ids` key; skipped when the
     * key was never set, so an unrelated save keeps the associations.
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $ids = $object->getData('website_ids');
        if (is_array($ids) && ($ids = Maho_Giftcard_Model_Giftcard::canonicalizeWebsiteIds($ids)) !== []) {
            $giftcardId = (int) $object->getId();

            // The admin form posts the set on every save; skip the
            // delete/insert churn when the selection did not change
            if ($ids !== $this->getWebsiteIds($giftcardId)) {
                $adapter = $this->_getWriteAdapter();
                $table = $this->getTable('giftcard/website');

                $adapter->delete($table, ['giftcard_id = ?' => $giftcardId]);
                $rows = [];
                foreach ($ids as $websiteId) {
                    $rows[] = [
                        'giftcard_id' => $giftcardId,
                        'website_id'  => $websiteId,
                    ];
                }
                $adapter->insertMultiple($table, $rows);
            }
        }

        return parent::_afterSave($object);
    }

    /**
     * @return int[]
     */
    public function getWebsiteIds(int $giftcardId): array
    {
        if ($giftcardId <= 0) {
            return [];
        }
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getTable('giftcard/website'), ['website_id'])
            ->where('giftcard_id = ?', $giftcardId)
            ->order('website_id ASC');
        return array_map(intval(...), $adapter->fetchCol($select));
    }
}
