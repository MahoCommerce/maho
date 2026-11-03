<?php

/**
 * Load, check, save and delete the scheduled design changes of the store views, for the admin page and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Service_Design
{
    public function newDesignChange(): Mage_Core_Model_Design
    {
        /** @var Mage_Core_Model_Design $design */
        $design = Mage::getModel('core/design');
        return $design;
    }

    /**
     * Load the design change $id. With $storeIds, a change of another store view does not exist for the caller.
     *
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?array $storeIds = null): Mage_Core_Model_Design
    {
        $design = $this->newDesignChange();
        try {
            $design->load($id);
        } catch (\Doctrine\DBAL\Exception\DriverException) {
            // An ID that the column cannot hold matches no row
        }
        if (!$design->getId() || ($storeIds !== null && !in_array((int) $design->getStoreId(), $storeIds, true))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__('This design change no longer exists.'));
        }
        return $design;
    }

    /**
     * Check $design and save it. The transport puts its input into the change through the typed setters first.
     * An empty date removes that bound. A change with no dates applies at all times.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when the dates overlap another change of the same store view
     */
    public function save(Mage_Core_Model_Design $design): Mage_Core_Model_Design
    {
        $helper = Mage::helper('core');
        $errors = new Mage_Core_Exception_Input();

        $storeId = $design->getStoreId();
        if ($storeId === null) {
            $errors->addError('store_id', $helper->__('The store view is required.'));
        } elseif (!array_key_exists($storeId, Mage::app()->getStores())) {
            $errors->addError('store_id', $helper->__('The store view %s does not exist.', $storeId));
        }

        // Accept a stored design, because an admin can remove its theme after the change is saved
        $designName = (string) $design->getDesign();
        if ($designName === '') {
            $errors->addError('design', $helper->__('The design is required.'));
        } elseif ($design->dataHasChangedFor('design') && !in_array($designName, $this->getInstalledDesigns(), true)) {
            $errors->addError('design', $helper->__('The design "%s" is not an installed theme. Use the form "package/theme".', $designName));
        }

        $dateFrom = $this->readDate($design, 'date_from', $errors);
        $dateTo = $this->readDate($design, 'date_to', $errors);
        if ($dateFrom !== null && $dateTo !== null && $dateFrom > $dateTo) {
            $errors->addError('date_from', $helper->__('Start date cannot be greater than end date.'));
        }

        $errors->throwIfErrors();

        if ($design->getResource()->hasIntersection((int) $storeId, $dateFrom, $dateTo, (int) $design->getId())) {
            throw new Mage_Core_Exception_Conflict(
                $helper->__('Your design change for the specified store intersects with another one, please specify another date range.'),
            );
        }

        $design->save();
        return $design;
    }

    public function delete(Mage_Core_Model_Design $design): void
    {
        $design->delete();
    }

    /**
     * Write the date in $field of $design as Y-m-d, or null when it is empty, and return it.
     * Add an error to $errors and return null when it is not a date.
     */
    private function readDate(Mage_Core_Model_Design $design, string $field, Mage_Core_Exception_Input $errors): ?string
    {
        $helper = Mage::helper('core');
        $value = trim((string) $design->getData($field));
        if ($value === '') {
            $design->setData($field);
            return null;
        }
        if (!$helper->isValidDate($value) && !$helper->isValidDateTime($value)) {
            $errors->addError($field, $helper->__('The date "%s" is not valid. Use the format Y-m-d.', $value));
            return null;
        }
        $date = substr($value, 0, 10);
        $design->setData($field, $date);
        return $date;
    }

    /**
     * @return list<string> each installed theme as "package/theme"
     */
    private function getInstalledDesigns(): array
    {
        $designs = [];
        foreach (Mage::getModel('core/design_package')->getThemeList() as $package => $themes) {
            foreach ((array) $themes as $theme) {
                $designs[] = $package . '/' . $theme;
            }
        }
        return $designs;
    }
}
