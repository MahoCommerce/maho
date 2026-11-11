<?php

/**
 * Load, save and delete the CMS blocks, for the admin pages, the API and the CMS importer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Service_Block
{
    /**
     * Load the block $id. With $storeId, only an enabled block that store view $storeId shows exists for the caller.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?int $storeId = null): Mage_Cms_Model_Block
    {
        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block')->load($id);
        if (!$block->getId() || ($storeId !== null && !$block->isVisibleInStore($storeId))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('cms')->__('This block no longer exists.'));
        }
        return $block;
    }

    /**
     * Return a new block with the defaults of the admin form: enabled, in all store views.
     */
    public function newBlock(): Mage_Cms_Model_Block
    {
        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block');
        return $block->setIsActive()
            ->setData('stores', [Mage_Core_Model_App::ADMIN_STORE_ID]);
    }

    /**
     * Save $block. The transport puts its input into the block first.
     * The model refuses a block that shows itself. The resource model sanitizes the content and checks the identifier.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when a block of a store view of the block has the identifier
     */
    public function save(Mage_Cms_Model_Block $block): Mage_Cms_Model_Block
    {
        $block->save();
        return $block;
    }

    public function delete(Mage_Cms_Model_Block $block): void
    {
        $block->delete();
    }
}
