<?php

/**
 * Load, save and delete the tax rules, for the admin pages and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Tax
 */

declare(strict_types=1);

class Mage_Tax_Service_Rule
{
    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Mage_Tax_Model_Calculation_Rule
    {
        /** @var Mage_Tax_Model_Calculation_Rule $rule */
        $rule = Mage::getModel('tax/calculation_rule');
        try {
            $rule->load($id);
        } catch (\Doctrine\DBAL\Exception\DriverException) {
            // An ID that the column cannot hold matches no row
        }
        if (!$rule->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('tax')->__('This rule no longer exists.'));
        }
        return $rule;
    }

    /**
     * Save $rule. The transport puts its input into the rule through the setters first.
     * The model checks the code, the priority, the position, the classes and the rates.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another rule has the code, or a combination of the rates and the classes
     */
    public function save(Mage_Tax_Model_Calculation_Rule $rule): Mage_Tax_Model_Calculation_Rule
    {
        $rule->save();
        return $rule;
    }

    public function delete(Mage_Tax_Model_Calculation_Rule $rule): void
    {
        $rule->delete();
    }
}
