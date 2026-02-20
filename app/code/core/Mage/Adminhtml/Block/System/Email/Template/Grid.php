<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Adminhtml_Block_System_Email_Template_Grid extends Mage_Adminhtml_Block_Widget_Grid
{
    #[\Override]
    protected function _construct()
    {
        $this->setEmptyText(Mage::helper('adminhtml')->__('No Templates Found'));
        $this->setId('systemEmailTemplateGrid');
        $this->setUseAjax(true);
        $this->setSaveParametersInSession(true);
    }

    #[\Override]
    protected function _prepareCollection()
    {
        $collection = Mage::getResourceSingleton('core/email_template_collection');

        $this->setCollection($collection);

        return parent::_prepareCollection();
    }

    #[\Override]
    protected function _prepareColumns()
    {
        $this->addColumn(
            'template_id',
            [
                'header' => Mage::helper('adminhtml')->__('ID'),
                'index' => 'template_id',
            ],
        );

        $this->addColumn(
            'code',
            [
                'header' => Mage::helper('adminhtml')->__('Template Name'),
                'index' => 'template_code',
            ],
        );

        $this->addColumn(
            'added_at',
            [
                'header' => Mage::helper('adminhtml')->__('Date Added'),
                'index' => 'added_at',
                'gmtoffset' => true,
                'type' => 'datetime',
            ],
        );

        $this->addColumn(
            'modified_at',
            [
                'header' => Mage::helper('adminhtml')->__('Date Updated'),
                'index' => 'modified_at',
                'gmtoffset' => true,
                'type' => 'datetime',
            ],
        );

        $this->addColumn(
            'subject',
            [
                'header' => Mage::helper('adminhtml')->__('Subject'),
                'index' => 'template_subject',
            ],
        );
        /*
        $this->addColumn('sender',
            array(
                'header'=>Mage::helper('adminhtml')->__('Sender'),
                'index'=>'template_sender_email',
                'renderer' => 'adminhtml/system_email_template_grid_renderer_sender'
        ));
        */
        $this->addColumn(
            'type',
            [
                'header' => Mage::helper('adminhtml')->__('Template Type'),
                'index' => 'template_type',
                'filter' => 'adminhtml/system_email_template_grid_filter_type',
                'renderer' => 'adminhtml/system_email_template_grid_renderer_type',
            ],
        );

        $this->addColumn(
            'action',
            [
                'type'      => 'action',
                'index'     => 'template_id',
                'width'     => '100',
                'renderer'  => 'adminhtml/system_email_template_grid_renderer_action',
            ],
        );
        return $this;
    }

    #[\Override]
    public function getRowUrl($row)
    {
        return $this->getUrl('*/*/edit', ['id' => $row->getId()]);
    }
}
