<?php

/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Data
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Form field renderer
 *
 * @category   Varien
 * @package    Varien_Data
 */
interface Varien_Data_Form_Element_Renderer_Interface
{
    /**
     * @return mixed
     */
    public function render(Varien_Data_Form_Element_Abstract $element);
}
