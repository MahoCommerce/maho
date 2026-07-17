<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

/**
 * @method string getText()
 */
class Note extends AbstractElement
{
    /**
     * Note constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('note');
        //$this->setExtType('textfield');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getElementHtml()
    {
        $html = '<span id="' . $this->getHtmlId() . '">' . $this->getText() . '</span>';
        $html .= $this->getAfterElementHtml();
        return $html;
    }
}
