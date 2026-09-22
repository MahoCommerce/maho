<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);


class Mage_Core_Block_Text extends Mage_Core_Block_Abstract
{
    /**
     * @param string $text
     * @return $this
     */
    public function setText($text)
    {
        $this->setData('text', $text);
        return $this;
    }

    /**
     * @return string
     */
    public function getText()
    {
        return $this->getData('text');
    }

    /**
     * @param string $text
     * @param bool $before
     */
    public function addText($text, $before = false)
    {
        if ($before) {
            $this->setText($text . $this->getText());
        } else {
            $this->setText($this->getText() . $text);
        }
    }

    /**
     * @return string
     */
    #[\Override]
    protected function _toHtml()
    {
        if (!$this->_beforeToHtml()) {
            return '';
        }

        return $this->getText();
    }

    public function getLiParams(): ?array
    {
        return $this->getData('li_params');
    }

    public function setLiParams(?array $value): static
    {
        return $this->setData('li_params', $value);
    }

    public function getAParams(): ?array
    {
        return $this->getData('a_params');
    }

    public function setAParams(?array $value): static
    {
        return $this->setData('a_params', $value);
    }

    public function getInnerText(): ?string
    {
        $value = $this->getData('inner_text');
        return $value === null ? null : (string) $value;
    }

    public function setInnerText(?string $value): static
    {
        return $this->setData('inner_text', $value);
    }

    public function getAfterText(): ?string
    {
        $value = $this->getData('after_text');
        return $value === null ? null : (string) $value;
    }

    public function setAfterText(?string $value): static
    {
        return $this->setData('after_text', $value);
    }
}
