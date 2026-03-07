<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Core_Helper_UnserializeArray extends Mage_Core_Helper_Abstract
{
    /**
     * @param string $str
     * @return array
     * @throws Exception
     * @SuppressWarnings("PHPMD.ErrorControlOperator")
     */
    public function unserialize($str)
    {
        $str = is_null($str) ? '' : $str;

        if (json_validate($str)) {
            return Mage::helper('core')->jsonDecode($str);
        }

        try {
            $result = @unserialize($str, ['allowed_classes' => false]);
            if ($result === false && $str !== serialize(false)) {
                throw new Exception('Error unserializing data.');
            }
            return $result;
        } catch (Error $e) {
            throw new Exception('Error unserializing data: ' . $e->getMessage(), 0, $e);
        }
    }
}
