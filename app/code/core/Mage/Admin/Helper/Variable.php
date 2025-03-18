<?php

/**
 * Maho
 *
 * @package    Mage_Admin
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Admin Variable Helper
 *
 * @package    Mage_Admin
 */
class Mage_Admin_Helper_Variable extends Mage_Core_Helper_Abstract
{
    /**
     * Paths cache
     *
     * @var array
     */
    protected $_allowedPaths;

    public function __construct()
    {
        $this->_allowedPaths = Mage::getResourceModel('admin/variable')->getAllowedPaths();
    }

    /**
     * @param string $path
     * @return bool
     */
    public function isPathAllowed($path)
    {
        return isset($this->_allowedPaths[$path]);
    }
}
