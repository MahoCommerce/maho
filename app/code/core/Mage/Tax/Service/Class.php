<?php

/**
 * Load, save and delete the tax classes of the products and the customer groups, for the admin pages and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

class Mage_Tax_Service_Class
{
    /**
     * Load the tax class $id. With $classType, a class of the other type does not exist for the caller.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?string $classType = null): Mage_Tax_Model_Class
    {
        /** @var Mage_Tax_Model_Class $class */
        $class = Mage::getModel('tax/class');
        $class->load($id);
        if (!$class->getId() || ($classType !== null && $class->getClassType() !== $classType)) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('tax')->__('This class no longer exists'));
        }
        return $class;
    }

    /**
     * Save $class. The transport puts its input into the class through the setters first.
     * The model checks the name and the type. The resource model refuses a name that another class of the type uses.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another class of the type has the name
     */
    public function save(Mage_Tax_Model_Class $class): Mage_Tax_Model_Class
    {
        $class->save();
        return $class;
    }

    /**
     * @throws Mage_Core_Exception_Conflict when a tax rule, a customer group or a product uses the class
     */
    public function delete(Mage_Tax_Model_Class $class): void
    {
        $class->delete();
    }
}
