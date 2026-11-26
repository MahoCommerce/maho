<?php

/**
 * The proof that a customer accepted one version of a checkout agreement. A row is never changed after its insert.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

/**
 * @method Mage_Checkout_Model_Resource_Agreement_Acceptance getResource()
 * @method Mage_Checkout_Model_Resource_Agreement_Acceptance_Collection getCollection()
 */
class Mage_Checkout_Model_Agreement_Acceptance extends Mage_Core_Model_Abstract
{
    public const string CONTEXT_CHECKOUT = 'checkout';
    public const string CONTEXT_REGISTRATION = 'registration';
    public const string CONTEXT_ACCOUNT = 'account';

    #[\Override]
    protected $_eventPrefix = 'checkout_agreement_acceptance';

    #[\Override]
    protected $_eventObject = 'acceptance';

    #[\Override]
    protected function _construct(): void
    {
        $this->_init('checkout/agreement_acceptance');
    }

    public function getVersionId(): ?int
    {
        $value = $this->getData('version_id');
        return $value === null ? null : (int) $value;
    }

    public function setVersionId(?int $value): static
    {
        return $this->setData('version_id', $value);
    }

    public function getAgreementId(): ?int
    {
        $value = $this->getData('agreement_id');
        return $value === null ? null : (int) $value;
    }

    public function setAgreementId(?int $value): static
    {
        return $this->setData('agreement_id', $value);
    }

    public function getCustomerId(): ?int
    {
        $value = $this->getData('customer_id');
        return $value === null ? null : (int) $value;
    }

    public function setCustomerId(?int $value): static
    {
        return $this->setData('customer_id', $value);
    }

    public function getEmail(): ?string
    {
        $value = $this->getData('email');
        return $value === null ? null : (string) $value;
    }

    public function setEmail(?string $value): static
    {
        return $this->setData('email', $value);
    }

    public function getOrderId(): ?int
    {
        $value = $this->getData('order_id');
        return $value === null ? null : (int) $value;
    }

    public function setOrderId(?int $value): static
    {
        return $this->setData('order_id', $value);
    }

    public function getStoreId(): ?int
    {
        $value = $this->getData('store_id');
        return $value === null ? null : (int) $value;
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    /**
     * Where the customer accepted: checkout, registration or account.
     */
    public function getContext(): ?string
    {
        $value = $this->getData('context');
        return $value === null ? null : (string) $value;
    }

    public function setContext(?string $value): static
    {
        return $this->setData('context', $value);
    }

    public function getRemoteIp(): ?string
    {
        $value = $this->getData('remote_ip');
        return $value === null ? null : (string) $value;
    }

    public function setRemoteIp(?string $value): static
    {
        return $this->setData('remote_ip', $value);
    }

    public function getUserAgent(): ?string
    {
        $value = $this->getData('user_agent');
        return $value === null ? null : (string) $value;
    }

    public function setUserAgent(?string $value): static
    {
        return $this->setData('user_agent', $value);
    }

    /**
     * The name of the accepted version. Only a collection of Mage_Checkout_Service_Agreement::getAcceptances() has it.
     */
    public function getAgreementName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    /**
     * The number of the accepted version. Only a collection of Mage_Checkout_Service_Agreement::getAcceptances() has it.
     */
    public function getAgreementVersion(): ?int
    {
        $value = $this->getData('version');
        return $value === null ? null : (int) $value;
    }
}
