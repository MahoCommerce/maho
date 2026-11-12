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
        $design = $this->newDesignChange()->load($id);
        if (!$design->getId() || ($storeIds !== null && !in_array((int) $design->getStoreId(), $storeIds, true))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__('This design change no longer exists.'));
        }
        return $design;
    }

    /**
     * Save $design. The transport puts its input into the change through the typed setters first.
     * The store view must exist, and a new or changed design must be an installed theme.
     * The resource model checks the dates and the overlap with the other changes of the store view.
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
        $theme = (string) $design->getDesign();
        if ($theme === '') {
            $errors->addError('design', $helper->__('The design is required.'));
        } elseif ((!$design->getId() || $design->dataHasChangedFor('design')) && !in_array($theme, $this->getInstalledDesigns(), true)) {
            $errors->addError('design', $helper->__('The design "%s" is not an installed theme. Use the form "package/theme".', $theme));
        }

        $design->getResource()->addDateErrors($design, $errors)->throwIfErrors();

        $design->save();
        return $design;
    }

    /**
     * @return list<string> each installed theme as "package/theme"
     */
    protected function getInstalledDesigns(): array
    {
        $designs = [];
        foreach (Mage::getModel('core/design_package')->getThemeList() as $package => $themes) {
            foreach ((array) $themes as $theme) {
                $designs[] = $package . '/' . $theme;
            }
        }
        return $designs;
    }

    public function delete(Mage_Core_Model_Design $design): void
    {
        $design->delete();
    }
}
