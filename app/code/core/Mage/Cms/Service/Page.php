<?php

/**
 * Load, save and delete the CMS pages, for the admin pages, the API and the CMS importer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

declare(strict_types=1);

class Mage_Cms_Service_Page
{
    /**
     * Load the page $id. With $storeId, only an enabled page that store view $storeId shows exists for the caller.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, ?int $storeId = null): Mage_Cms_Model_Page
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page');
        try {
            $page->load($id);
        } catch (\Doctrine\DBAL\Exception\DriverException) {
            // An ID that the column cannot hold matches no row
        }
        $storeIds = array_map(intval(...), (array) $page->getStoreId());
        if (!$page->getId()
            || ($storeId !== null && (!$page->getIsActive() || !array_intersect([Mage_Core_Model_App::ADMIN_STORE_ID, $storeId], $storeIds)))
        ) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('cms')->__('This page no longer exists.'));
        }
        return $page;
    }

    /**
     * Save $page. The transport puts its input into the page first.
     * The resource model sanitizes the content and checks the identifier, the layouts, the layout updates and the robots value.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when a page of a store view of the page has the identifier
     */
    public function save(Mage_Cms_Model_Page $page): Mage_Cms_Model_Page
    {
        $page->save();
        return $page;
    }

    /**
     * @throws Mage_Core_Exception_Conflict when the configuration uses the page, for example as the home page
     */
    public function delete(Mage_Cms_Model_Page $page): void
    {
        $page->delete();
    }
}
