<?php

/**
 * Maho
 *
 * @package    Varien_Directory
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Varien_Directory_Factory
{
    /**
     * Return Varien_Directory_Collection or Varien_File_Object object
     *
     * @param string $path - path to directory
     * @param bool $is_recursion - use or not recursion
     * @param int $recurse_level - recurse level
     * @return Varien_Directory_Collection|Varien_File_Object
     */
    public static function getFactory($path, $is_recursion = true, $recurse_level = 0)
    {
        if (is_dir($path)) {
            $obj = new Varien_Directory_Collection($path, $is_recursion, $recurse_level + 1);
            return $obj;
        } else {
            return new Varien_File_Object($path);
        }
    }
}
