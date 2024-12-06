<?php
/**
 * Maho
 *
 * @category   Varien
 * @package    Varien_Convert
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Convert zend cache adapter
 *
 * @category   Varien
 * @package    Varien_Convert
 */
class Varien_Convert_Adapter_Zend_Cache extends Varien_Convert_Adapter_Abstract
{
    #[\Override]
    public function getResource()
    {
        if (!$this->_resource) {
            $this->_resource = Zend_Cache::factory($this->getVar('frontend', 'Core'), $this->getVar('backend', 'File'));
        }
        return $this->_resource;
    }

    #[\Override]
    public function load()
    {
        $this->setData($this->getResource()->load($this->getVar('id')));
        return $this;
    }

    #[\Override]
    public function save()
    {
        $this->getResource()->save($this->getData(), $this->getVar('id'));
        return $this;
    }
}
