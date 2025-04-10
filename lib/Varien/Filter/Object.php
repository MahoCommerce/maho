<?php

/**
 * Maho
 *
 * @package    Varien_Filter
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Varien_Filter_Object extends Zend_Filter
{
    /**
     * @var array
     */
    protected $_columnFilters = [];

    /**
     * @param string $column
     * @return $this
     */
    #[\Override]
    public function addFilter(Zend_Filter_Interface $filter, $column = '')
    {
        if ('' === $column) {
            parent::addFilter($filter);
        } else {
            if (!isset($this->_columnFilters[$column])) {
                $this->_columnFilters[$column] = new Zend_Filter();
            }
            $this->_columnFilters[$column]->addFilter($filter);
        }
        return $this;
    }

    /**
     * @param Varien_Object $object
     * @return mixed
     * @throws Exception
     */
    #[\Override]
    public function filter($object)
    {
        if (!$object instanceof Varien_Object) {
            throw new Exception('Expecting an instance of Varien_Object');
        }
        $class = $object::class;
        $out = new $class();
        foreach ($object->getData() as $column => $value) {
            $value = parent::filter($value);
            if (isset($this->_columnFilters[$column])) {
                $value = $this->_columnFilters[$column]->filter($value);
            }
            $out->setData($column, $value);
        }
        return $out;
    }
}
