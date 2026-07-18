<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2020-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Submit extends AbstractElement
{
    /**
     * Submit constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setExtType('submit');
        $this->setType('submit');
    }

    /**
     * @return string
     */
    #[\Override]
    public function getHtml()
    {
        $this->addClass('submit');
        return parent::getHtml();
    }
}
