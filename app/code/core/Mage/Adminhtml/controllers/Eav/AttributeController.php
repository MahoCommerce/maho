<?php

/**
 * The attribute pages of System > Attributes, for every entity type that gives admin page settings.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Eav_AttributeController extends Mage_Adminhtml_Controller_Eav_Attribute
{
    use Mage_Adminhtml_Controller_Eav_EntityTypeTrait;

    /**
     * The parent of the ACL resource of each entity type
     * @see Mage_Adminhtml_Controller_Eav_EntityTypeTrait::_isAllowed()
     */
    public const ADMIN_RESOURCE = 'system/attributes';

    #[\Override]
    protected function getPageTitles(): array
    {
        return [$this->__('System'), $this->__('Attributes'), (string) $this->getAdminPageSettings()['title']];
    }

    #[\Override]
    protected function getMainTabBlock(): string
    {
        return $this->getAdminPageSettings()['main_tab'] ?? parent::getMainTabBlock();
    }

    #[\Override]
    protected function getAllowedTags(): array
    {
        return $this->getAdminPageSettings()['allowed_tags'] ?? [];
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_attribute/index')]
    public function indexAction(): void
    {
        $settings = (array) $this->getAdminPageSettings();
        $this->_initAction();
        $container = $this->getLayout()->createBlock($this->gridContainerBlock);
        assert($container instanceof Mage_Adminhtml_Block_Widget_Container);
        $container->setHeaderText($settings['title']);
        if ($settings['sets_title'] !== null) {
            $container->addButton('attribute_sets', [
                'label' => $this->__('Attribute Sets'),
                'onclick' => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/eav_set/')),
            ]);
        }
        $this->_addContent($container)->renderLayout();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_attribute/new')]
    public function newAction(): void
    {
        parent::newAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_attribute/edit')]
    public function editAction(): void
    {
        parent::editAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_attribute/validate')]
    public function validateAction(): void
    {
        parent::validateAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_attribute/save')]
    public function saveAction(): void
    {
        parent::saveAction();
    }

    #[\Override]
    #[Maho\Config\Route('/admin/eav_attribute/delete')]
    public function deleteAction(): void
    {
        parent::deleteAction();
    }
}
