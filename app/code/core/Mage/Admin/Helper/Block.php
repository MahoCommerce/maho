<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Admin_Helper_Block extends Mage_Core_Helper_Abstract
{
    /**
     * Types cache
     *
     * @var array
     */
    protected $_allowedTypes;

    public function __construct()
    {
        $this->_allowedTypes = Mage::getResourceModel('admin/block')->getAllowedTypes();
    }

    /**
     * @param string $type
     * @return bool
     */
    public function isTypeAllowed($type)
    {
        return isset($this->_allowedTypes[$type]);
    }

    /**
     *  Get disallowed names for block
     *
     * @return array
     */
    public function getDisallowedBlockNames()
    {
        return Mage::getResourceModel('admin/block')->getDisallowedBlockNames();
    }
}
