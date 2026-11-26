<?php

/**
 * The text of one version of a checkout agreement. A row is never changed after its insert.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

/**
 * @method Mage_Checkout_Model_Resource_Agreement_Version getResource()
 * @method Mage_Checkout_Model_Resource_Agreement_Version_Collection getCollection()
 */
class Mage_Checkout_Model_Agreement_Version extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('checkout/agreement_version');
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

    public function getVersion(): ?int
    {
        $value = $this->getData('version');
        return $value === null ? null : (int) $value;
    }

    public function setVersion(?int $value): static
    {
        return $this->setData('version', $value);
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

    public function getCheckboxText(): ?string
    {
        $value = $this->getData('checkbox_text');
        return $value === null ? null : (string) $value;
    }

    public function setCheckboxText(?string $value): static
    {
        return $this->setData('checkbox_text', $value);
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
}
