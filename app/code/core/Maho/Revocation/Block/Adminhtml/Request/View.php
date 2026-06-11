<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Revocation
 */

declare(strict_types=1);

class Maho_Revocation_Block_Adminhtml_Request_View extends Mage_Adminhtml_Block_Widget_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('revocation/view.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $helper = Mage::helper('revocation');
        $request = $this->getRevocationRequest();

        $this->_addButton('back', [
            'label' => $helper->__('Back'),
            'onclick' => Mage::helper('core/js')->getSetLocationJs($this->getUrl('*/*/')),
            'class' => 'back',
        ]);

        if ($request && $this->_isProcessAllowed()) {
            $this->_addButton('accept', [
                'label' => $helper->__('Accept Revocation'),
                'onclick' => "revocationProcess.submit('accept')",
                'class' => 'save',
            ]);
            $this->_addButton('reject', [
                'label' => $helper->__('Reject Revocation'),
                'onclick' => "revocationProcess.submit('reject')",
                'class' => 'delete',
            ]);
            $this->_addButton('resend', [
                'label' => $helper->__('Resend Receipt Email'),
                'onclick' => Mage::helper('core/js')->getConfirmSetLocationJs(
                    $this->getResendUrl(),
                    $helper->__('Resend the receipt email to %s?', $request->getEmail()),
                ),
            ]);
        }

        return parent::_prepareLayout();
    }

    public function getRevocationRequest(): ?Maho_Revocation_Model_Request
    {
        $request = Mage::registry('current_revocation_request');
        return $request instanceof Maho_Revocation_Model_Request ? $request : null;
    }

    #[\Override]
    public function getHeaderText(): string
    {
        return Mage::helper('revocation')->__('Revocation Request #%s', $this->getRevocationRequest()?->getId());
    }

    public function getProcessUrl(): string
    {
        return $this->getUrl('*/*/process', ['id' => $this->getRevocationRequest()?->getId()]);
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save', ['id' => $this->getRevocationRequest()?->getId()]);
    }

    public function getLinkOrderUrl(): string
    {
        return $this->getUrl('*/*/linkOrder', ['id' => $this->getRevocationRequest()?->getId()]);
    }

    public function getResendUrl(): string
    {
        return $this->getUrl('*/*/resend', ['id' => $this->getRevocationRequest()?->getId(), 'form_key' => Mage::getSingleton('core/session')->getFormKey()]);
    }

    public function getOrderViewUrl(Mage_Sales_Model_Order $order): string
    {
        return $this->getUrl('*/sales_order/view', ['order_id' => $order->getId()]);
    }

    public function getCreditmemoUrl(Mage_Sales_Model_Order $order): string
    {
        return $this->getUrl('*/sales_order_creditmemo/start', ['order_id' => $order->getId()]);
    }

    public function getProcessedStatusLabel(): ?string
    {
        $status = $this->getRevocationRequest()?->getProcessedStatus();
        if (!$status) {
            return null;
        }
        return Mage::getModel('revocation/source_processedStatus')->toOptionHash()[$status] ?? $status;
    }

    public function formatDateTime(?string $utcDate): string
    {
        if (!$utcDate) {
            return '';
        }
        return Mage::helper('core')->formatDate($utcDate, 'medium', true);
    }

    protected function _isProcessAllowed(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/revocation/process');
    }
}
