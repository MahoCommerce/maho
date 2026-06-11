<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_System_Store_Delete_Website extends Mage_Adminhtml_Block_Template
{
    #[\Override]
    protected function _prepareLayout()
    {
        $itemId = $this->getRequest()->getParam('website_id');

        $this->setTemplate('system/store/delete_website.phtml');
        $this->setAction($this->getUrl('*/*/deleteWebsitePost', ['website_id' => $itemId]));
        $this->setChild(
            'confirm_deletion_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('core')->__('Delete Website'),
                    'onclick'   => 'deleteForm.submit()',
                    'class'     => 'cancel',
                ]),
        );
        $onClick = Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/editWebsite', ['website_id' => $itemId]));
        $this->setChild(
            'cancel_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('core')->__('Cancel'),
                    'onclick'   => $onClick,
                    'class'     => 'cancel',
                ]),
        );
        $this->setChild(
            'back_button',
            $this->getLayout()->createBlock('adminhtml/widget_button')
                ->setData([
                    'label'     => Mage::helper('core')->__('Back'),
                    'onclick'   => $onClick,
                    'class'     => 'cancel',
                ]),
        );
        return parent::_prepareLayout();
    }
}
