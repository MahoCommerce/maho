<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Customer_SetController extends Mage_Adminhtml_Controller_Eav_Set
{
    /**
     * ACL resource
     * @see Mage_Adminhtml_Controller_Action::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'customer/attributes/customer_sets';

    #[\Override]
    protected function getEntityTypeCode(): string
    {
        return 'customer';
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('Customers'), $this->__('Attributes'), $this->__('Customer Attribute Sets')];
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_set/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_set/setGrid')]
    public function setGridAction(): void
    {
        parent::setGridAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_set/add')]
    public function addAction(): void
    {
        parent::addAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_set/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_set/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/customer_set/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
