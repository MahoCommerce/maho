<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Customer_AttributeController extends Mage_Adminhtml_Controller_Customer_Attribute
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/attributes/customer_attributes';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return 'customer';
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('Customers'), $this->__('Attributes'), $this->__('Manage Customer Attributes')];
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_attribute/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_attribute/new')]
    public function newAction(): void
    {
        parent::newAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_attribute/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_attribute/validate')]
    public function validateAction(): void
    {
        parent::validateAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_attribute/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_attribute/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
