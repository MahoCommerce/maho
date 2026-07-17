<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Hidden extends AbstractElement
{
    /**
     * Hidden constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('hidden');
        $this->setExtType('hiddenfield');
    }

    /**
     * @return mixed|string
     */
    #[\Override]
    public function getDefaultHtml()
    {
        $html = $this->getData('default_html');
        if (is_null($html)) {
            $html = $this->getElementHtml();
        }
        return $html;
    }
}
