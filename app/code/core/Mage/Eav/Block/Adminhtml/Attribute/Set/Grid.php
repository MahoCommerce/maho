<?php
/**
 * Maho
 *
 * @category   Mage
 * @package    Mage_Eav
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @category   Mage
 * @package    Mage_Eav
 */
class Mage_Eav_Block_Adminhtml_Attribute_Set_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();

        /** @var Mage_Eav_Model_Entity_Type $entityType */
        $entityType = Mage::registry('entity_type');

        $gridId = 'attributeSetGrid';
        if ($entityType && $entityType->getEntityTypeId()) {
            $gridId .= '_' . $entityType->getEntityTypeCode();
        }

        $this->setId($gridId);
        $this->setDefaultSort('set_name');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _prepareCollection()
    {
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $collection */
        $collection = Mage::getResourceModel('eav/entity_attribute_set_collection');

        $collection->setEntityTypeFilter(Mage::registry('entity_type')->getEntityTypeId());

        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('set_name', [
            'header'    => Mage::helper('eav')->__('Set Name'),
            'align'     => 'left',
            'index'     => 'attribute_set_name',
        ]);

        return $this;
    }

    /**
     * @inheritDoc
     */
    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getAttributeSetId()]);
    }
}
