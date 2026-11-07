<?php

/**
 * Load, save and delete the scheduled design changes of the store views, for the admin page and the API.
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
     * Save $design. The transport puts its input into the change through the typed setters first.
     * The resource model checks the fields and the overlap with the other changes of the store view.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when the dates overlap another change of the same store view
     */
    public function save(Mage_Core_Model_Design $design): Mage_Core_Model_Design
    {
        $design->save();
        return $design;
    }

    public function delete(Mage_Core_Model_Design $design): void
    {
        $design->delete();
    }
}
