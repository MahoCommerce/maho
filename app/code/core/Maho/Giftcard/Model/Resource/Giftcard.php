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

        $data = $adapter->fetchRow($select);

        if ($data) {
            $object->setData($data);
        }

        $this->_afterLoad($object);

        return $this;
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

        return parent::_beforeSave($object);
    }

    /**
     * Sync the giftcard_website junction whenever the model is saved.
     *
     * The admin form (and any other caller using setWebsiteIds) sets a
     * `website_ids` data key on the model. We delete the previous rows for
     * this card and insert the new set in one transaction, so a save either
     * lands cleanly or leaves the junction in its previous state if the
     * INSERT fails partway. Skipped when no `website_ids` was set, so a
     * background save that only touched, say, the balance doesn't trample
     * the website associations.
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $ids = $object->getData('website_ids');
        if (is_array($ids)) {
            // Fail closed on an explicit empty set. isValidForWebsite() rejects
            // a card with no associations on every website, so silently
            // accepting an empty set here would orphan the card with no admin
            // surfacing of the change. Callers that genuinely want to
            // de-associate everything should delete the card instead.
            if (empty($ids)) {
                throw new Mage_Core_Exception(
                    Mage::helper('giftcard')->__('A gift card must be associated with at least one website.'),
                );
            }

            $adapter = $this->_getWriteAdapter();
            $table = $this->getTable('giftcard/website');
            $giftcardId = (int) $object->getId();

            $adapter->beginTransaction();
            try {
                $adapter->delete($table, ['giftcard_id = ?' => $giftcardId]);
                $rows = [];
                foreach ($ids as $websiteId) {
                    $rows[] = [
                        'giftcard_id' => $giftcardId,
                        'website_id'  => (int) $websiteId,
                    ];
                }
                $adapter->insertMultiple($table, $rows);
                $adapter->commit();
            } catch (\Throwable $e) {
                $adapter->rollBack();
                throw $e;
            }
        }

        return parent::_afterSave($object);
    }

    /**
     * Load the cached website associations onto the model so getWebsiteIds()
     * reads them without an extra round trip.
     */
    #[\Override]
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        if ($object->getId()) {
            $object->setData('website_ids', $this->getWebsiteIds((int) $object->getId()));
        }
        return parent::_afterLoad($object);
    }

    /**
     * Fetch the website IDs this card is valid on, ordered.
     *
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
        return array_map('intval', $adapter->fetchCol($select));
    }
}
