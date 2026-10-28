<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Model_Resource_Attribute extends Mage_Eav_Model_Resource_Attribute
{
    /**
     * Get EAV website table
     *
     * Get table, where website-dependent attribute parameters are stored
     * If realization doesn't demand this functionality, let this function just return null
     *
     * @return string|null
     */
    #[\Override]
    protected function _getEavWebsiteTable()
    {
        return $this->getTable('customer/eav_attribute_website');
    }

    /**
     * Get Form attribute table
     *
     * Get table, where dependency between form name and attribute ids is stored
     *
     * @return string|null
     */
    #[\Override]
    protected function _getFormAttributeTable()
    {
        return $this->getTable('customer/form_attribute');
    }

    /**
     * Return the is_visible and is_required values of each website that has its own values.
     *
     * @return array<int, array{is_visible: ?int, is_required: ?int}>
     */
    public function getWebsiteValues(int $attributeId): array
    {
        $adapter = $this->_getReadAdapter();
        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($this->_getEavWebsiteTable(), ['website_id', 'is_visible', 'is_required'])
                ->where('attribute_id = ?', $attributeId),
        );
        $values = [];
        foreach ($rows as $row) {
            $values[(int) $row['website_id']] = [
                'is_visible' => $row['is_visible'] === null ? null : (int) $row['is_visible'],
                'is_required' => $row['is_required'] === null ? null : (int) $row['is_required'],
            ];
        }
        return $values;
    }

    /**
     * Save the mode of each website: '' uses the default values, and hidden, optional or required
     * sets is_visible and is_required.
     *
     * @param array<int|string, string> $modes
     */
    public function saveWebsiteValues(int $attributeId, array $modes): void
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->_getEavWebsiteTable();
        foreach ($modes as $websiteId => $mode) {
            [$isVisible, $isRequired] = match ($mode) {
                'hidden' => [0, null],
                'optional' => [1, 0],
                'required' => [1, 1],
                default => [null, null],
            };
            $adapter->insertOnDuplicate($table, [
                'attribute_id' => $attributeId,
                'website_id' => (int) $websiteId,
                'is_visible' => $isVisible,
                'is_required' => $isRequired,
            ], ['is_visible', 'is_required']);
        }
        $adapter->delete($table, [
            'attribute_id = ?' => $attributeId,
            'is_visible IS NULL',
            'is_required IS NULL',
            'default_value IS NULL',
            'multiline_count IS NULL',
        ]);
    }
}
