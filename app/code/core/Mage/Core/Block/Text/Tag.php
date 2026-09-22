<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

class Mage_Core_Block_Text_Tag extends Mage_Core_Block_Text
{
    #[\Override]
    protected function _construct()
    {
        parent::_construct();
        $this->setTagParams([]);
    }

    /**
     * @param string|array $param
     * @param string|null $value
     * @return $this
     */
    public function setTagParam($param, $value = null)
    {
        if (is_array($param) && is_null($value)) {
            foreach ($param as $k => $v) {
                $this->setTagParam($k, $v);
            }
        } else {
            $params = $this->getTagParams();
            $params[$param] = $value;
            $this->setTagParams($params);
        }
        return $this;
    }

    /**
     * @param string $text
     * @return $this
     */
    public function setContents($text)
    {
        $this->setTagContents($text);
        return $this;
    }

    #[\Override]
    protected function _toHtml()
    {
        $this->setText('<' . $this->getTagName() . ' ');
        if ($this->getTagParams()) {
            foreach ($this->getTagParams() as $k => $v) {
                $this->addText($k . '="' . $v . '" ');
            }
        }

        $this->addText('>' . $this->getTagContents() . '</' . $this->getTagName() . '>' . "\r\n");
        return parent::_toHtml();
    }

    public function getTagContents(): ?string
    {
        $value = $this->getData('tag_contents');
        return $value === null ? null : (string) $value;
    }

    public function setTagContents(?string $value): static
    {
        return $this->setData('tag_contents', $value);
    }

    public function getTagName(): ?string
    {
        $value = $this->getData('tag_name');
        return $value === null ? null : (string) $value;
    }

    public function getTagParams(): ?array
    {
        return $this->getData('tag_params');
    }

    public function setTagParams(?array $value): static
    {
        return $this->setData('tag_params', $value);
    }
}
