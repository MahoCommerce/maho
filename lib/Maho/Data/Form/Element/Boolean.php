<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Boolean extends Select
{
    /**
     * Boolean constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setValues([
            [
                'label' => \Mage::helper('core')->__('Yes'),
                'value' => 1,
            ],
            [
                'label' => \Mage::helper('core')->__('No'),
                'value' => 0,
            ],
        ]);
    }
}
