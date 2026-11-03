<?php

/**
 * Load, check, save and delete customer groups for the admin pages, the API and the CLI.
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
     * Check $group and save it. The transport puts its input into the group through the setters first.
     * A code that another group uses is a Mage_Core_Exception_Conflict of the resource model.
     *
     * @throws Mage_Core_Exception_Input with one error for each problem
     * @throws Mage_Core_Exception_Conflict when the change renames NOT LOGGED IN
     */
    public function save(Mage_Customer_Model_Group $group): Mage_Customer_Model_Group
    {
        $helper = Mage::helper('customer');
        $errors = new Mage_Core_Exception_Input();

        $code = trim((string) $group->getCode());
        if ($code === '') {
            $errors->addError('code', $helper->__('Customer group code is required.'));
        } elseif (mb_strlen($code) > Mage_Customer_Model_Group::GROUP_CODE_MAX_LENGTH) {
            $errors->addError('code', $helper->__('Customer group code must not exceed %d characters.', Mage_Customer_Model_Group::GROUP_CODE_MAX_LENGTH));
        }
        $group->setCode($code);
        // The admin form does not let an admin rename the group of the guests, so no transport may
        if ($group->getOrigData('customer_group_id') !== null
            && (int) $group->getId() === Mage_Customer_Model_Group::NOT_LOGGED_IN_ID
            && $group->dataHasChangedFor('customer_group_code')
        ) {
            throw new Mage_Core_Exception_Conflict($helper->__('The NOT LOGGED IN group cannot be renamed.'));
        }

        // Accept a stored tax class, because an admin can delete it after the group is saved
        $taxClassId = (int) $group->getData('tax_class_id');
        if ($group->getId() === null || $group->dataHasChangedFor('tax_class_id')) {
            if ($taxClassId === 0) {
                $errors->addError('tax_class_id', $helper->__('Customer tax class is required.'));
            } elseif (!array_key_exists($taxClassId, $this->getTaxClasses())) {
                $errors->addError('tax_class_id', $helper->__('Customer tax class not found with ID: %s', $taxClassId));
            }
        }

        $errors->throwIfErrors();

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
