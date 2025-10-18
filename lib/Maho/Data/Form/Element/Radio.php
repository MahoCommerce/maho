<?php

/**
 * Maho
 *
 * @package    Maho_Data
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Data\Form\Element;

use Maho\Data\Form\Element\AbstractElement;

class Radio extends AbstractElement
{
    /**
     * Radio constructor.
     * @param array $attributes
     */
    public function __construct($attributes = [])
    {
        parent::__construct($attributes);
        $this->setType('radio');
        $this->setExtType('radio');
    }
}
