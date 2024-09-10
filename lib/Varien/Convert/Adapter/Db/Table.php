<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Convert
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2023 The OpenMage Contributors (https://openmage.org)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Convert db table adapter
 *
 * @category   Varien
 * @package    Varien_Convert
 */
class Varien_Convert_Adapter_Db_Table extends Varien_Convert_Adapter_Abstract
{
    #[\Override]
    public function getResource()
    {
        if (!$this->_resource) {
            $this->_resource = Zend_Db::factory($this->getVar('type'), $this->getVars());
        }
        return $this->_resource;
    }

    #[\Override]
    public function load()
    {
    }

    #[\Override]
    public function save()
    {
    }
}
