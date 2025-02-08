<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Invoice view  comments form
 *
 * @package    Mage_Adminhtml
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
        switch ($this->getParentType()) {
            case 'invoice':
                return Mage::helper('sales')->canSendInvoiceCommentEmail(
                    $this->getEntity()->getOrder()->getStore()->getId(),
                );
            case 'shipment':
                return Mage::helper('sales')->canSendShipmentCommentEmail(
                    $this->getEntity()->getOrder()->getStore()->getId(),
                );
            case 'creditmemo':
                return Mage::helper('sales')->canSendCreditmemoCommentEmail(
                    $this->getEntity()->getOrder()->getStore()->getId(),
                );
        }

        return true;
    }

    /**
     * Replace links in string
     *
     * @param string|string[] $data
     * @param array|null $allowedTags
     * @return null|string|string[]
     */
    #[\Override]
    public function escapeHtml($data, $allowedTags = null)
    {
        return Mage::helper('adminhtml/sales')->escapeHtmlWithLinks($data, $allowedTags);
    }
}
