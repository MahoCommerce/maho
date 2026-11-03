<?php

/**
 * Load, save and delete blog categories for the admin pages, the storefront and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

class Maho_Blog_Service_Category
{
    /**
     * Return an unsaved category that is active on all store views.
     */
    public function newCategory(): Maho_Blog_Model_Category
    {
        /** @var Maho_Blog_Model_Category $category */
        $category = Mage::getModel('blog/category');
        return $category->setData('is_active', 1)->setData('stores', [Mage_Core_Model_App::ADMIN_STORE_ID]);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Maho_Blog_Model_Category
    {
        /** @var Maho_Blog_Model_Category $category */
        $category = Mage::getModel('blog/category');
        if ($id > 0) {
            $category->load($id);
        }
        if (!$category->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('blog')->__('This category no longer exists.'));
        }
        return $category;
    }

    /**
     * Load the category $id that a visitor of the store view $storeId can read: active and in that store view.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getVisibleById(int $id, int $storeId): Maho_Blog_Model_Category
    {
        $category = $this->getById($id);
        if (!$category->getIsActive()
            || array_intersect([Mage_Core_Model_App::ADMIN_STORE_ID, $storeId], array_map(intval(...), $category->getStores())) === []
        ) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('blog')->__('This category no longer exists.'));
        }
        return $category;
    }

    /**
     * Save $category. The resource model checks the name, the parent and the URL key.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when a sibling has the URL key
     */
    public function save(Maho_Blog_Model_Category $category): Maho_Blog_Model_Category
    {
        $category->save();
        return $category;
    }

    /**
     * Delete $category. The resource model deletes its descendants too.
     */
    public function delete(Maho_Blog_Model_Category $category): void
    {
        $category->delete();
    }
}
