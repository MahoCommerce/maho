<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

/**
 * @method Mage_Checkout_Model_Resource_Agreement _getResource()
 * @method Mage_Checkout_Model_Resource_Agreement getResource()
 * @method Mage_Checkout_Model_Resource_Agreement_Collection getCollection()
 */

class Mage_Checkout_Model_Agreement extends Mage_Core_Model_Abstract
{
    public const string LOCATION_CHECKOUT = 'checkout';
    public const string LOCATION_REGISTRATION = 'registration';

    #[\Override]
    protected function _construct()
    {
        $this->_init('checkout/agreement');
    }

    public function getName(): ?string
    {
        $value = $this->getData('name');
        return $value === null ? null : (string) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function getContent(): ?string
    {
        $value = $this->getData('content');
        return $value === null ? null : (string) $value;
    }

    public function setContent(?string $value): static
    {
        return $this->setData('content', $value);
    }

    public function getContentHeight(): ?string
    {
        $value = $this->getData('content_height');
        return $value === null ? null : (string) $value;
    }

    public function setContentHeight(?string $value): static
    {
        return $this->setData('content_height', $value);
    }

    public function getCheckboxText(): ?string
    {
        $value = $this->getData('checkbox_text');
        return $value === null ? null : (string) $value;
    }

    public function setCheckboxText(?string $value): static
    {
        return $this->setData('checkbox_text', $value);
    }

    public function getIsActive(): ?bool
    {
        $value = $this->getData('is_active');
        return $value === null ? null : (bool) $value;
    }

    public function setIsActive(?bool $value = true): static
    {
        return $this->setData('is_active', $value);
    }

    public function getIsHtml(): ?bool
    {
        $value = $this->getData('is_html');
        return $value === null ? null : (bool) $value;
    }

    public function setIsHtml(?bool $value = true): static
    {
        return $this->setData('is_html', $value);
    }

    public function getPosition(): ?int
    {
        $value = $this->getData('position');
        return $value === null ? null : (int) $value;
    }

    public function setPosition(?int $value): static
    {
        return $this->setData('position', $value);
    }

    /**
     * The number of the current text. The resource model increments it when the text changes.
     */
    public function getVersion(): ?int
    {
        $value = $this->getData('version');
        return $value === null ? null : (int) $value;
    }

    public function setVersion(?int $value): static
    {
        return $this->setData('version', $value);
    }

    public function getShowOnCheckout(): ?bool
    {
        $value = $this->getData('show_on_checkout');
        return $value === null ? null : (bool) $value;
    }

    public function setShowOnCheckout(?bool $value = true): static
    {
        return $this->setData('show_on_checkout', $value);
    }

    public function getShowOnRegistration(): ?bool
    {
        $value = $this->getData('show_on_registration');
        return $value === null ? null : (bool) $value;
    }

    public function setShowOnRegistration(?bool $value = true): static
    {
        return $this->setData('show_on_registration', $value);
    }

    /**
     * True when a customer who accepted an older version must accept the current version again.
     */
    public function getRequiresReconsent(): ?bool
    {
        $value = $this->getData('requires_reconsent');
        return $value === null ? null : (bool) $value;
    }

    public function setRequiresReconsent(?bool $value = true): static
    {
        return $this->setData('requires_reconsent', $value);
    }
}
