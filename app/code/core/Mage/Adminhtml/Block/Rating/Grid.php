<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_Rating_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('ratingsGrid');
        $this->setDefaultSort('rating_code');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getModel('rating/rating')
            ->getResourceCollection()
            ->addEntityFilter(Mage::registry('entityId'));
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    /**
     * Prepare Rating Grid colunms
     *
     * @return $this
     */
    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn('rating_id', [
            'header'    => Mage::helper('rating')->__('ID'),
            'align'     => 'right',
            'width'     => '50px',
            'index'     => 'rating_id',
        ]);

        $this->addColumn('rating_code', [
            'header'    => Mage::helper('rating')->__('Rating Name'),
            'index'     => 'rating_code',
        ]);

        $this->addColumn('position', [
            'header' => Mage::helper('rating')->__('Sort Order'),
            'align' => 'left',
            'width' => '100px',
            'index' => 'position',
        ]);

        return parent::_prepareColumns();
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
