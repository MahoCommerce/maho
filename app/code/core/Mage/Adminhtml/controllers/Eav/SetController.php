<?php

/**
 * The attribute set pages of System > Attributes, for every entity type that has sets.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Eav_SetController extends Mage_Adminhtml_Controller_Eav_Set
{
    use Mage_Adminhtml_Controller_Eav_EntityTypeTrait;

    /**
     * The parent of the ACL resource of each entity type
     * @see Mage_Adminhtml_Controller_Eav_EntityTypeTrait::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/attributes';

    #[\Override]
    protected function _isAllowed()
    {
        return $this->isEntityTypeAllowed() && $this->getAdminPageSettings()['sets_title'] !== null;
    }

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('System'), $this->__('Attributes'), (string) $this->getAdminPageSettings()['sets_title']];
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_set/index')]
    public function indexAction(): void
    {
        parent::indexAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_set/setGrid')]
    public function setGridAction(): void
    {
        parent::setGridAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_set/add')]
    public function addAction(): void
    {
        parent::addAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_set/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_set/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_set/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
