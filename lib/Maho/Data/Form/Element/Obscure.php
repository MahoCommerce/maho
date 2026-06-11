<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Obscure extends Password
{
    /**
     * @var string
     */
    protected $_obscuredValue = '******';

    /**
     * Hide value to make sure it will not show in HTML
     *
     * @param string $index
     * @return string
     */
    #[\Override]
    public function getEscapedValue($index = null)
    {
        $value = parent::getEscapedValue($index);
        if (!empty($value)) {
            return $this->_obscuredValue;
        }
        return $value;
    }

    /**
     * Returns list of html attributes possible to output in HTML
     *
     * @return array
     */
    #[\Override]
    public function getHtmlAttributes()
    {
        return ['type', 'title', 'class', 'style', 'onclick', 'onchange', 'onkeyup', 'disabled', 'readonly', 'maxlength', 'tabindex'];
    }
}
