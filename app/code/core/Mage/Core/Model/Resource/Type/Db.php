<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2020-2024 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
abstract class Mage_Core_Model_Resource_Type_Db extends Mage_Core_Model_Resource_Type_Abstract
{
    public function __construct()
    {
        $this->_entityClass = 'Mage_Core_Model_Resource_Entity_Table';
    }
}
