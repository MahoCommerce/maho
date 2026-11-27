<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Request as Request;

class Maho_PrivacyCenter_Block_Adminhtml_Request_View extends Mage_Adminhtml_Block_Widget_Container
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('privacycenter/request/view.phtml');
    }

    #[\Override]
    protected function _prepareLayout()
    {
        $helper = Mage::helper('privacycenter');
        $js = Mage::helper('core/js');
        $request = $this->getPrivacyRequest();

        $this->_addButton('back', [
            'label' => $helper->__('Back'),
            'onclick' => $js->getSetLocationJs($this->getUrl('*/*/')),
            'class' => 'back',
        ]);
        if (!$request || !Mage::getSingleton('admin/session')->isAllowed('customer/privacy/process')) {
            return parent::_prepareLayout();
        }

        $status = $request->getStatus();
        if ($request->isErasure() && $status === Request::STATUS_CONFIRMED && !$request->getApprovedAt()) {
            $this->_addButton('approve', [
                'label' => $helper->__('Approve'),
                'onclick' => $js->getConfirmSetLocationJs($this->getActionUrl('approve'), $helper->__('Approve this erasure? It runs at the end of its cooling-off period.')),
                'class' => 'save',
            ]);
        }
        if (in_array($status, [Request::STATUS_CONFIRMED, Request::STATUS_PROCESSING], true)) {
            $this->_addButton('process', [
                'label' => $request->isExport() ? $helper->__('Export Now') : $helper->__('Erase Now'),
                'onclick' => $js->getConfirmSetLocationJs(
                    $this->getActionUrl('process', ['force' => 1]),
                    $request->isExport()
                        ? $helper->__('Write the export now?')
                        : $helper->__('Erase the data now? This skips the cooling-off period and the open orders check, and cannot be undone.'),
                ),
                'class' => $request->isErasure() ? 'delete' : 'save',
            ]);
        }
        if ($status === Request::STATUS_PENDING || ($request->isExport() && $status === Request::STATUS_COMPLETED && $request->getExportFile())) {
            $this->_addButton('resend', [
                'label' => $helper->__('Resend'),
                'onclick' => $js->getConfirmSetLocationJs($this->getActionUrl('resend'), $helper->__('Send the email again to %s?', $request->getEmail())),
            ]);
        }
        if ($request->isExport() && $request->getExportFile()) {
            $this->_addButton('download', [
                'label' => $helper->__('Download'),
                'onclick' => $js->getSetLocationJs($this->getActionUrl('download')),
            ]);
        }
        if ($request->isOpen()) {
            $this->_addButton('reject', [
                'label' => $helper->__('Reject'),
                'onclick' => $js->getConfirmSetLocationJs($this->getActionUrl('reject'), $helper->__('Reject this request?')),
                'class' => 'delete',
            ]);
        }
        return parent::_prepareLayout();
    }

    public function getPrivacyRequest(): ?Request
    {
        $request = Mage::registry('current_privacy_request');
        return $request instanceof Request ? $request : null;
    }

    #[\Override]
    public function getHeaderText(): string
    {
        return Mage::helper('privacycenter')->__('Privacy Request #%s', $this->getPrivacyRequest()?->getId());
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getActionUrl(string $action, array $params = []): string
    {
        return $this->getUrl("*/*/{$action}", $params + ['id' => $this->getPrivacyRequest()?->getId()]);
    }

    public function getCustomerUrl(): ?string
    {
        $customerId = $this->getPrivacyRequest()?->getCustomerId();
        return $customerId ? $this->getUrl('*/customer/edit', ['id' => $customerId]) : null;
    }

    public function formatDateTime(?string $utc): string
    {
        return $utc ? Mage::helper('core')->formatDate($utc, Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM, true) : '';
    }

    /**
     * The notes of the request, newest first. A line without a date continues the note above it.
     *
     * @return list<array{date: string, text: string}>
     */
    public function getHistory(): array
    {
        $entries = [];
        foreach (explode("\n", (string) $this->getPrivacyRequest()?->getAdminNote()) as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) UTC\] (.*)$/s', $line, $match)) {
                $entries[] = ['date' => $this->formatDateTime($match[1]), 'text' => $match[2]];
            } elseif (trim($line) !== '') {
                if ($entries === []) {
                    $entries[] = ['date' => '', 'text' => $line];
                } else {
                    $last = array_key_last($entries);
                    $entries[$last] = ['date' => $entries[$last]['date'], 'text' => $entries[$last]['text'] . "\n" . $line];
                }
            }
        }
        return array_reverse($entries);
    }

    public function canProcess(): bool
    {
        return Mage::getSingleton('admin/session')->isAllowed('customer/privacy/process');
    }
}
