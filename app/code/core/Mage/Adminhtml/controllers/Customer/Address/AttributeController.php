<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Customer_Address_AttributeController extends Mage_Adminhtml_Controller_Customer_Attribute
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/attributes/address_attributes';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return 'customer_address';
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('Customers'), $this->__('Attributes'), $this->__('Manage Address Attributes')];
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_address_attribute/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_address_attribute/new')]
    public function newAction(): void
    {
        parent::newAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_address_attribute/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_address_attribute/validate')]
    public function validateAction(): void
    {
        parent::validateAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_address_attribute/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_address_attribute/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
