<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

class Mage_Checkout_Model_Resource_Agreement extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('checkout/agreement', 'agreement_id');
    }

    /**
     * Method to run before save
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        // format height
        $height = $object->getContentHeight();
        $height = Mage::helper('checkout')->stripTags($height);
        if (!$height) {
            $height = '';
        }
        if ($height && preg_match('/[0-9]$/', $height)) {
            $height .= 'px';
        }
        $object->setContentHeight($height);

        // The checkout escapes an agreement that is not HTML, so a filter would damage it
        if ($object->getIsHtml()) {
            // No renderer: the checkout prints the value, so no directive resolves.
            // The link filter opens links in a new tab. The customer must stay in the checkout.
            Mage::getSingleton('core/input_filter_maliciousCode')->sanitizeFields(
                $object,
                ['content', 'checkbox_text'],
                applyLinkFilter: true,
            );
        }

        return parent::_beforeSave($object);
    }

    /**
     * Method to run after save
     *
     * @return $this
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $this->saveVersion($object);

        $condition = ['agreement_id = ?' => $object->getId()];
        $this->_getWriteAdapter()->delete($this->getTable('checkout/agreement_store'), $condition);

        foreach ((array) $object->getData('stores') as $store) {
            $storeArray = [];
            $storeArray['agreement_id'] = $object->getId();
            $storeArray['store_id'] = $store;
            $this->_getWriteAdapter()->insert($this->getTable('checkout/agreement_store'), $storeArray);
        }

        return parent::_afterSave($object);
    }

    /**
     * Add a version row when $agreement is new or when its text differs from the text of its last version.
     * A caller that saves without a load (such as the admin form) sends no version, so the last version comes
     * from the database, not from the data of $agreement.
     */
    protected function saveVersion(Mage_Core_Model_Abstract $agreement): void
    {
        $adapter = $this->_getWriteAdapter();
        $versionTable = $this->getTable('checkout/agreement_version');
        $last = $adapter->fetchRow(
            $adapter->select()
                ->from($versionTable, ['version', 'name', 'content', 'checkbox_text', 'is_html'])
                ->where('agreement_id = ?', $agreement->getId())
                ->order('version DESC')
                ->limit(1),
        );
        $text = [
            'name' => $agreement->getData('name'),
            'content' => $agreement->getData('content'),
            'checkbox_text' => $agreement->getData('checkbox_text'),
            'is_html' => (int) (bool) $agreement->getData('is_html'),
        ];
        if ($last) {
            $changed = (string) $last['name'] !== (string) $text['name']
                || (string) $last['content'] !== (string) $text['content']
                || (string) $last['checkbox_text'] !== (string) $text['checkbox_text']
                || (int) $last['is_html'] !== $text['is_html'];
            if (!$changed) {
                $agreement->setData('version', (int) $last['version']);
                return;
            }
        }

        $version = $last ? (int) $last['version'] + 1 : 1;
        $adapter->insert($versionTable, $text + [
            'agreement_id' => (int) $agreement->getId(),
            'version' => $version,
            'created_at' => Mage::app()->getLocale()->formatDateForDb('now'),
        ]);
        $adapter->update($this->getMainTable(), ['version' => $version], ['agreement_id = ?' => $agreement->getId()]);
        $agreement->setData('version', $version);
    }

    /**
     * The ID of the current version of each agreement in $agreementIds.
     *
     * @param list<int> $agreementIds
     * @return array<int, int> agreement ID => version ID
     */
    public function getCurrentVersionIds(array $agreementIds): array
    {
        if ($agreementIds === []) {
            return [];
        }
        $adapter = $this->_getReadAdapter();
        $rows = $adapter->fetchPairs(
            $adapter->select()
                ->from(['a' => $this->getMainTable()], ['agreement_id'])
                ->join(
                    ['v' => $this->getTable('checkout/agreement_version')],
                    'v.agreement_id = a.agreement_id AND v.version = a.version',
                    ['version_id'],
                )
                ->where('a.agreement_id IN (?)', $agreementIds),
        );
        $ids = [];
        foreach ($rows as $agreementId => $versionId) {
            $ids[(int) $agreementId] = (int) $versionId;
        }
        return $ids;
    }

    /**
     * Method to run after load
     *
     * @return $this
     */
    #[\Override]
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        $select = $this->_getReadAdapter()->select()
            ->from($this->getTable('checkout/agreement_store'), ['store_id'])
            ->where('agreement_id = :agreement_id');

        if ($stores = $this->_getReadAdapter()->fetchCol($select, [':agreement_id' => $object->getId()])) {
            $object->setData('store_id', $stores);
        }

        return parent::_afterLoad($object);
    }

    /**
     * Get load select
     */
    #[\Override]
    protected function _getLoadSelect(string $field, int|string $value, Mage_Core_Model_Abstract $object): \Maho\Db\Select
    {
        $select = parent::_getLoadSelect($field, $value, $object);
        if ($object->getStoreId()) {
            $select->join(
                ['cps' => $this->getTable('checkout/agreement_store')],
                $this->getMainTable() . '.agreement_id = cps.agreement_id',
            )
            ->where('is_active=1')
            ->where('cps.store_id IN (0, ?)', $object->getStoreId())
            ->order('store_id DESC')
            ->limit(1);
        }
        return $select;
    }

    /**
     * The content and the checkbox text of every terms and conditions agreement, for the Tailwind build.
     *
     * @return iterable<string>
     */
    #[Maho\Config\TailwindSource('checkout_agreement', model: Mage_Checkout_Model_Agreement::class, fields: ['content', 'checkbox_text'])]
    public function getTailwindContent(): iterable
    {
        return Mage::getResourceSingleton('core/design_content')->readColumns($this->getMainTable(), $this->getIdFieldName(), ['content', 'checkbox_text']);
    }
}
