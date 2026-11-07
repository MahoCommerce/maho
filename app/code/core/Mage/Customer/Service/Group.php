<?php

/**
 * Load, save and delete customer groups for the admin pages, the API and the CLI.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

class Mage_Customer_Service_Group
{
    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Mage_Customer_Model_Group
    {
        /** @var Mage_Customer_Model_Group $group */
        $group = Mage::getModel('customer/group');
        // A lookup in PHP: PostgreSQL refuses a query with an ID that is out of range for the column
        if (array_key_exists($id, Mage::getResourceModel('customer/group_collection')->toOptionHash())) {
            $group->load($id);
        }
        if ($group->getId() === null) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('customer')->__('Customer group not found with ID: %s', $id));
        }
        return $group;
    }

    /**
     * Save $group. The transport puts its input into the group through the setters first.
     * The model checks the code and the tax class, and the resource model refuses a code that another group uses.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem
     * @throws Mage_Core_Exception_Conflict when the code is in use, or the change renames NOT LOGGED IN
     */
    public function save(Mage_Customer_Model_Group $group): Mage_Customer_Model_Group
    {
        $group->save();
        return $group;
    }

    /**
     * Delete $group. The customers of the group move to the default group of their store.
     *
     * @throws Mage_Core_Exception_Conflict for NOT LOGGED IN and for a default group
     */
    public function delete(Mage_Customer_Model_Group $group): void
    {
        $group->delete();
    }

    /**
     * Refuse to delete NOT LOGGED IN, which guests use, and a group that a scope uses as the default group.
     *
     * @throws Mage_Core_Exception_Conflict
     */
    public function validateDelete(Mage_Customer_Model_Group $group): void
    {
        $group->getResource()->validateDelete($group);
    }

    /**
     * @return array<int, string> the name of each customer tax class, by ID
     */
    public function getTaxClasses(): array
    {
        return Mage::getResourceModel('tax/class_collection')
            ->setClassTypeFilter(Mage_Tax_Model_Class::TAX_CLASS_TYPE_CUSTOMER)
            ->toOptionHash();
    }
}
