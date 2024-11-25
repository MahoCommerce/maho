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
    protected Mage_Eav_Model_Entity_Type $entityType;

    public function __construct()
    {
        $this->entityType = Mage::registry('entity_type');

        $this->setId('attributeSetGrid_' . $this->entityType->getEntityTypeCode());
        $this->setDefaultSort('set_name');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);

        parent::__construct();
    }

    #[\Override]
    protected function _prepareCollection()
    {
        /** @var Mage_Eav_Model_Resource_Entity_Attribute_Set_Collection $collection */
        $collection = $this->entityType->getAttributeSetCollection();

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

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

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getAttributeSetId()]);
    }
}
