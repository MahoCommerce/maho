<?php

/**
 * Maho
 *
 * @package    Mage_Customer
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2023 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Address renderer interface
 *
 * @package    Mage_Customer
 */
interface Mage_Customer_Block_Address_Renderer_Interface
{
    /**
     * Set format type object
     */
    public function setType(Varien_Object $type);

    /**
     * Retrieve format type object
     *
     * @return Varien_Object
     */
    public function getType();

    /**
     * Render address
     *
     * @return mixed
     */
    public function render(Mage_Customer_Model_Address_Abstract $address);
}
