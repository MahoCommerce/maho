<?php

/**
 * Load, save and delete the transactional email templates of the database, for the admin page and the API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

class Mage_Core_Service_Email_Template
{
    /**
     * Return an unsaved HTML template. A delete of the returned model also resets each config path that uses it.
     */
    public function newTemplate(): Mage_Adminhtml_Model_Email_Template
    {
        /** @var Mage_Adminhtml_Model_Email_Template $template */
        $template = Mage::getModel('adminhtml/email_template');
        return $template->setTemplateType(Mage_Core_Model_Template::TYPE_HTML);
    }

    /**
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id): Mage_Adminhtml_Model_Email_Template
    {
        /** @var Mage_Adminhtml_Model_Email_Template $template */
        $template = Mage::getModel('adminhtml/email_template');
        try {
            $template->load($id);
        } catch (\Doctrine\DBAL\Exception\DriverException) {
            // An ID that the column cannot hold matches no row
        }
        if (!$template->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('core')->__('This email template no longer exists.'));
        }
        return $template;
    }

    /**
     * Save $template. The transport puts its input into the template through the setters first.
     * The model checks the fields and refuses a name that another template has.
     *
     * @throws Mage_Core_Exception_Conflict when another template has the name
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     */
    public function save(Mage_Adminhtml_Model_Email_Template $template): Mage_Adminhtml_Model_Email_Template
    {
        $template->save();
        return $template;
    }

    /**
     * Delete $template. Each config path that uses it goes back to its default template.
     */
    public function delete(Mage_Adminhtml_Model_Email_Template $template): void
    {
        $template->delete();
    }
}
