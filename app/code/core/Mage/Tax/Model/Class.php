<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Tax
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @method Mage_Tax_Model_Resource_Class _getResource()
 * @method Mage_Tax_Model_Resource_Class getResource()
 * @method Mage_Tax_Model_Resource_Class_Collection getCollection()
 *
 * @method string getClassName()
 * @method $this setClassName(string $value)
 * @method string getClassType()
 * @method $this setClassType(string $value)
 */

class Mage_Tax_Model_Class extends Mage_Core_Model_Abstract
{
    public const TAX_CLASS_TYPE_CUSTOMER   = 'CUSTOMER';
    public const TAX_CLASS_TYPE_PRODUCT    = 'PRODUCT';

    #[\Override]
    protected function _construct()
    {
        $this->_init('tax/class');
    }
}
