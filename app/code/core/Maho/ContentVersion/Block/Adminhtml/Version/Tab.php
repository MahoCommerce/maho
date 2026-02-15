<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ContentVersion
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_ContentVersion_Block_Adminhtml_Version_Tab extends Mage_Adminhtml_Block_Template implements Mage_Adminhtml_Block_Widget_Tab_Interface
{
    protected string $entityType;
    protected string $registryKey;

    #[\Override]
    protected function _construct(): void
    {
        parent::_construct();
        $this->setTemplate('contentversion/tab.phtml');
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function setRegistryKey(string $key): self
    {
        $this->registryKey = $key;
        return $this;
    }

    public function getGridHtml(): string
    {
        $model = Mage::registry($this->registryKey);
        if (!$model || !$model->getId()) {
            return '';
        }

        /** @var Maho_ContentVersion_Block_Adminhtml_Version_Grid $grid */
        $grid = $this->getLayout()->createBlock('contentversion/adminhtml_version_grid');
        $grid->setEntityContext($this->entityType, (int) $model->getId());
        return $grid->toHtml();
    }

    public function hasVersions(): bool
    {
        $model = Mage::registry($this->registryKey);
        return $model && $model->getId();
    }

    #[\Override]
    public function getTabLabel()
    {
        return Mage::helper('contentversion')->__('Versions');
    }

    #[\Override]
    public function getTabTitle()
    {
        return Mage::helper('contentversion')->__('Content Version History');
    }

    #[\Override]
    public function canShowTab()
    {
        $model = Mage::registry($this->registryKey);
        return $model && $model->getId();
    }

    #[\Override]
    public function isHidden()
    {
        return !$this->canShowTab();
    }
}
