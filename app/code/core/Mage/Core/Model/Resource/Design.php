<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Model_Resource_Design extends Mage_Core_Model_Resource_Db_Abstract
{
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/design_change', 'design_change_id');
    }

    /**
     * Check the dates of $object, and the overlap with the other changes of its store view.
     * An empty date removes that bound, and a date is stored as Y-m-d.
     *
     * @param Mage_Core_Model_Design $object
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when the dates overlap another change of the same store view
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        $this->addDateErrors($object, new Mage_Core_Exception_Input())->throwIfErrors();

        if ($this->_checkIntersection((int) $object->getStoreId(), $object->getDateFrom(), $object->getDateTo(), (int) $object->getId())) {
            throw new Mage_Core_Exception_Conflict(
                Mage::helper('core')->__('Your design change for the specified store intersects with another one, please specify another date range.'),
            );
        }

        return parent::_beforeSave($object);
    }

    /**
     * Write each date of $object as Y-m-d, or null when it is empty. Add an error to $errors for a value
     * that is not a date, and for a start date after the end date. Return $errors.
     */
    public function addDateErrors(Mage_Core_Model_Design $object, Mage_Core_Exception_Input $errors): Mage_Core_Exception_Input
    {
        $dateFrom = $this->readDate($object, 'date_from', $errors);
        $dateTo = $this->readDate($object, 'date_to', $errors);
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $errors->addError('date_from', Mage::helper('core')->__('Start date cannot be greater than end date.'));
        }
        return $errors;
    }

    /**
     * Write the date in $field of $object as Y-m-d, or null when it is empty, and return it.
     * Add an error to $errors and return null when it is not a date.
     */
    private function readDate(Mage_Core_Model_Design $object, string $field, Mage_Core_Exception_Input $errors): ?string
    {
        $helper = Mage::helper('core');
        $value = trim((string) $object->getData($field));
        if ($value === '') {
            $object->setData($field);
            return null;
        }
        if (!$helper->isValidDate($value) && !$helper->isValidDateTime($value)) {
            $errors->addError($field, $helper->__('The date "%s" is not valid. Use the format Y-m-d.', $value));
            return null;
        }
        $date = substr($value, 0, 10);
        $object->setData($field, $date);
        return $date;
    }

    /**
     * Return whether another change of $storeId applies on a day between $dateFrom and $dateTo.
     * A null date is an open bound. The change $currentId does not count.
     *
     * @param int $storeId
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @param int $currentId
     * @return bool
     */
    protected function _checkIntersection($storeId, $dateFrom, $dateTo, $currentId)
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from(['main_table' => $this->getTable('design_change')])
            ->where('main_table.store_id = :store_id')
            ->where('main_table.design_change_id <> :current_id');

        $dateConditions = ['date_to IS NULL AND date_from IS NULL'];

        if (!empty($dateFrom)) {
            $dateConditions[] = ':date_from BETWEEN date_from AND date_to';
            $dateConditions[] = ':date_from >= date_from and date_to IS NULL';
            $dateConditions[] = ':date_from <= date_to and date_from IS NULL';
        } else {
            $dateConditions[] = 'date_from IS NULL';
        }

        if (!empty($dateTo)) {
            $dateConditions[] = ':date_to BETWEEN date_from AND date_to';
            $dateConditions[] = ':date_to >= date_from AND date_to IS NULL';
            $dateConditions[] = ':date_to <= date_to AND date_from IS NULL';
        } else {
            $dateConditions[] = 'date_to IS NULL';
        }

        if (empty($dateFrom) && !empty($dateTo)) {
            $dateConditions[] = 'date_to <= :date_to OR date_from <= :date_to';
        }

        if (!empty($dateFrom) && empty($dateTo)) {
            $dateConditions[] = 'date_to >= :date_from OR date_from >= :date_from';
        }

        if (!empty($dateFrom) && !empty($dateTo)) {
            $dateConditions[] = 'date_from BETWEEN :date_from AND :date_to';
            $dateConditions[] = 'date_to BETWEEN :date_from AND :date_to';
        } elseif (empty($dateFrom) && empty($dateTo)) {
            $dateConditions = [];
        }

        if (!empty($dateConditions)) {
            $condition = '(' . implode(') OR (', $dateConditions) . ')';
            $select->where($condition);
        }

        $bind = [
            'store_id'   => (int) $storeId,
            'current_id' => (int) $currentId,
        ];

        if (!empty($dateTo)) {
            $bind['date_to'] = $dateTo;
        }
        if (!empty($dateFrom)) {
            $bind['date_from'] = $dateFrom;
        }

        return (bool) $adapter->fetchOne($select, $bind);
    }

    /**
     * Load changes for specific store and date
     *
     * @param int $storeId
     * @param string $date
     * @return array
     */
    public function loadChange($storeId, $date = null)
    {
        $date ??= Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATETIME_FORMAT);

        $select = $this->_getReadAdapter()->select()
            ->from(['main_table' => $this->getTable('design_change')])
            ->where('store_id = :store_id')
            ->where('date_from <= :required_date or date_from IS NULL')
            ->where('date_to >= :required_date or date_to IS NULL');

        $bind = [
            'store_id'      => (int) $storeId,
            'required_date' => $date,
        ];

        return $this->_getReadAdapter()->fetchRow($select, $bind);
    }
}
