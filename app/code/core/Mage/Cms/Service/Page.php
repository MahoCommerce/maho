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
        $page = Mage::getModel('cms/page')->load($id);
        if (!$page->getId() || ($storeId !== null && !$page->isVisibleInStore($storeId))) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('cms')->__('This page no longer exists.'));
        }
        return $page;
    }

    /**
     * Return a new page with the defaults of the admin form: enabled, in all store views, with the default layout.
     */
    public function newPage(): Mage_Cms_Model_Page
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page');
        return $page->setIsActive()
            ->setRootTemplate(Mage::getSingleton('page/source_layout')->getDefaultValue())
            ->setData('stores', [Mage_Core_Model_App::ADMIN_STORE_ID]);
    }

    /**
     * Save $page. The transport puts its input into the page first.
     * Check the layouts, the layout updates and the robots value against the lists of the admin form, only when
     * the save changes them, so that a stored value from before the check does not block a save.
     * The resource model sanitizes the content, checks the identifier, and keeps a page enabled that the
     * configuration uses, for example as the home page.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when a page of a store view of the page has the identifier
     */
    public function save(Mage_Cms_Model_Page $page): Mage_Cms_Model_Page
    {
        $errors = new Mage_Core_Exception_Input();
        $identifier = (string) $page->getData('identifier');
        $identifierError = $identifier === '' ? null : $page->getResource()->getIdentifierError($identifier);
        if ($identifierError !== null) {
            $errors->addError('identifier', $identifierError);
        }
        foreach (['layout_update_xml', 'custom_layout_update_xml'] as $field) {
            $xml = (string) $page->getData($field);
            if ($xml === '' || !$page->dataHasChangedFor($field)) {
                continue;
            }
            /** @var Mage_Adminhtml_Model_LayoutUpdate_Validator $validator */
            $validator = Mage::getModel('adminhtml/layoutUpdate_validator');
            if (!$validator->isValid($xml)) {
                foreach ($validator->getMessages() as $message) {
                    $errors->addError($field, (string) $message);
                }
            }
        }
        $layouts = array_keys(Mage::getSingleton('page/source_layout')->getOptions());
        $options = [
            'root_template' => $layouts,
            'custom_root_template' => $layouts,
            'meta_robots' => array_column(Mage::getSingleton('page/source_robots')->getAllOptions(), 'value'),
        ];
        foreach ($options as $field => $values) {
            $value = (string) $page->getData($field);
            $values = array_map(strval(...), $values);
            if ($value !== '' && $page->dataHasChangedFor($field) && !in_array($value, $values, true)) {
                $errors->addError($field, Mage::helper('cms')->__('The value must be one of: %s', implode(', ', array_filter($values))));
            }
        }
        $errors->throwIfErrors();

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
