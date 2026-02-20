<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    MahoLib
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
