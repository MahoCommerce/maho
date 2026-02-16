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

class Maho_ContentVersion_Block_Adminhtml_Version_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    protected string $entityType;
    protected int $entityId;

    public function __construct()
    {
        parent::__construct();
        $this->setId('contentversion_grid');
        $this->setDefaultSort('version_number');
        $this->setDefaultDir('DESC');
        $this->setSaveParametersInSession(false);
        $this->setUseAjax(false);
        $this->setFilterVisibility(false);
        $this->setPagerVisibility(false);
    }

    public function setEntityContext(string $entityType, int $entityId): self
    {
        $this->entityType = $entityType;
        $this->entityId = $entityId;
        return $this;
    }

    #[\Override]
    protected function _prepareCollection(): self
    {
        /** @var Maho_ContentVersion_Model_Resource_Version_Collection $collection */
        $collection = Mage::getResourceModel('contentversion/version_collection');
        $collection->addFieldToFilter('entity_type', $this->entityType)
            ->addFieldToFilter('entity_id', $this->entityId)
            ->setOrder('version_number', 'DESC');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns(): self
    {
        $this->addColumn('version_number', [
            'header' => Mage::helper('contentversion')->__('Version'),
            'index' => 'version_number',
            'type' => 'number',
            'width' => '60px',
        ]);

        $this->addColumn('editor', [
            'header' => Mage::helper('contentversion')->__('Editor'),
            'index' => 'editor',
            'width' => '200px',
        ]);

        $this->addColumn('created_at', [
            'header' => Mage::helper('contentversion')->__('Date'),
            'index' => 'created_at',
            'type' => 'datetime',
            'width' => '180px',
        ]);

        $this->addColumn('action', [
            'header' => Mage::helper('contentversion')->__('Action'),
            'type' => 'action',
            'getter' => 'getId',
            'actions' => [
                [
                    'caption' => Mage::helper('contentversion')->__('Restore'),
                    'confirm' => Mage::helper('contentversion')->__('Are you sure you want to restore this version? The current content will be saved as a new version first.'),
                    'url' => ['base' => 'adminhtml/contentversion/restore'],
                    'field' => 'version_id',
                ],
            ],
            'filter' => false,
            'sortable' => false,
            'width' => '80px',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row): string
    {
        return $this->getUrl('adminhtml/contentversion/preview', ['version_id' => $row->getId()]);
    }
}
