<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

/**
 * @method string getTagContents()
 * @method $this setTagContents(string $value)
 * @method getTagName()
 * @method array getTagParams()
 * @method $this setTagParams(array $value)
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
}
