<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Block_Sales_Order_Comments_View extends Mage_Adminhtml_Block_Template
{
    /**
     * Retrieve required options from parent
     */
    #[\Override]
    protected function _beforeToHtml()
    {
        if (!$this->getParentBlock()) {
            Mage::throwException(Mage::helper('adminhtml')->__('Invalid parent block for this block.'));
        }
        $this->setEntity($this->getParentBlock()->getSource());
        return parent::_beforeToHtml();
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $button = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setData([
                'id'      => 'submit_comment_button',
                'label'   => Mage::helper('sales')->__('Submit Comment'),
                'class'   => 'save',
            ]);
        $this->setChild('submit_button', $button);

        return parent::_prepareLayout();
    }

    public function getSubmitUrl()
    {
        return $this->getUrl('*/*/addComment', ['id' => $this->getEntity()->getId()]);
    }

    public function canSendCommentEmail()
    {
        return match ($this->getParentType()) {
            'invoice' => Mage::helper('sales')->canSendInvoiceCommentEmail(
                $this->getEntity()->getOrder()->getStore()->getId(),
            ),
            'shipment' => Mage::helper('sales')->canSendShipmentCommentEmail(
                $this->getEntity()->getOrder()->getStore()->getId(),
            ),
            'creditmemo' => Mage::helper('sales')->canSendCreditmemoCommentEmail(
                $this->getEntity()->getOrder()->getStore()->getId(),
            ),
            default => true,
        };
    }

    /**
     * Replace links in string
     *
     * @param null|string|string[] $data
     * @param null|string[] $allowedTags
     * @return ($data is array ? array<?string> : ?string)
     */
    #[\Override]
    public function escapeHtml($data, $allowedTags = null)
    {
        return Mage::helper('adminhtml/sales')->escapeHtmlWithLinks($data, $allowedTags);
    }
}
