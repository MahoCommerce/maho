<?php

/**
 * The data of the Privacy Center pages of the customer account.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_PrivacyCenter
 */

declare(strict_types=1);

use Maho_PrivacyCenter_Model_Request as Request;

class Maho_PrivacyCenter_Block_Account extends Mage_Core_Block_Template
{
    public function getCustomer(): Mage_Customer_Model_Customer
    {
        return Mage::getSingleton('customer/session')->getCustomer();
    }

    /**
     * @return list<Request>
     */
    public function getPrivacyRequests(?string $type = null): array
    {
        $requests = Mage::getService('privacycenter/request')->getForCustomer((int) $this->getCustomer()->getId());
        if ($type !== null) {
            $requests->addFieldToFilter('type', $type);
        }
        return array_values($requests->getItems());
    }

    /**
     * The newest request of $type of the customer, or null.
     */
    public function getLatestRequest(string $type): ?Request
    {
        return $this->getPrivacyRequests($type)[0] ?? null;
    }

    public function hasOpenRequest(string $type): bool
    {
        return array_any($this->getPrivacyRequests($type), fn($request) => $request->isOpen());
    }

    /**
     * @return list<Mage_Checkout_Model_Agreement>
     */
    public function getPendingAgreements(): array
    {
        return Mage::getService('checkout/agreement')->getPendingForCustomer(
            $this->getCustomer(),
            (int) Mage::app()->getStore()->getId(),
        );
    }

    public function getAcceptances(): Mage_Checkout_Model_Resource_Agreement_Acceptance_Collection
    {
        return Mage::getService('checkout/agreement')->getAcceptances((int) $this->getCustomer()->getId());
    }

    /**
     * The sections of an export, from its exporters, so that the exporter of another module shows too.
     *
     * @return list<string>
     */
    public function getExportLabels(): array
    {
        $labels = [];
        foreach (Mage::getService('privacycenter/request')->getExporters() as $code => $exporter) {
            if ($code !== 'privacy_requests') {
                $labels[] = $exporter->getLabel();
            }
        }
        return $labels;
    }

    /**
     * What an erasure removes, from its erasers, so that the eraser of another module shows too.
     *
     * @return list<string>
     */
    public function getErasureLabels(): array
    {
        return array_values(array_map(
            fn(Maho_PrivacyCenter_Model_Eraser_AbstractEraser $eraser): string => $eraser->getLabel(),
            Mage::getService('privacycenter/request')->getErasers(),
        ));
    }

    public function getStatusBadgeHtml(Request $request): string
    {
        return $this->getLayout()->createBlock('core/template')
            ->setTemplate('privacycenter/account/_status.phtml')
            ->setData('privacy_request', $request)
            ->toHtml();
    }

    public function getTypeLabel(Request $request): string
    {
        return Mage::getModel('privacycenter/source_type')->toOptionHash()[$request->getType()] ?? (string) $request->getType();
    }

    public function getStatusLabel(Request $request): string
    {
        return Mage::getModel('privacycenter/source_status')->toOptionHash()[$request->getStatus()] ?? (string) $request->getStatus();
    }

    public function formatUtcDate(?string $utc, bool $withTime = false): string
    {
        return $utc ? Mage::helper('core')->formatDate($utc, Mage_Core_Model_Locale::FORMAT_TYPE_MEDIUM, $withTime) : '';
    }

    public function getCoolingOffDays(): int
    {
        return Mage::helper('privacycenter')->getCoolingOffDays();
    }

    /**
     * @param array<string, mixed> $params
     */
    public function getActionUrl(string $action, array $params = []): string
    {
        return $this->getUrl("privacy/account/{$action}", $params + ['_secure' => true]);
    }

    public function getOverviewUrl(): string
    {
        return $this->getUrl('privacy/account', ['_secure' => true]);
    }
}
