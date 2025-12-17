<?php

/**
 * Maho
 *
 * @package    MahoLib
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2021-2022 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Filter;

class ObjectFilter
{
    /**
     * @var array
     */
    protected $_filters = [];
    protected array $_columnFilters = [];

    /**
     * Add filter to apply to all values or specific column
     */
    public function addFilter(callable|object $filter, string $column = ''): self
    {
        if ('' === $column) {
            $this->_filters[] = $filter;
        } else {
            $this->_columnFilters[$column][] = $filter;
        }
        return $this;
    }

    /**
     * Filter \Maho\DataObject properties
     */
    public function filter(\Maho\DataObject|array $object): \Maho\DataObject|array
    {
        if (is_array($object)) {
            // For arrays, just return the array as-is
            return $object;
        }

        $class = $object::class;
        $out = new $class();

        foreach ($object->getData() as $column => $value) {
            // Apply general filters
            foreach ($this->_filters as $filter) {
                if (is_callable($filter)) {
                    $value = $filter($value);
                } elseif (is_object($filter) && method_exists($filter, 'filter')) {
                    $value = $filter->filter($value);
                }
            }

            // Apply column-specific filters
            if (isset($this->_columnFilters[$column])) {
                foreach ($this->_columnFilters[$column] as $filter) {
                    if (is_callable($filter)) {
                        $value = $filter($value);
                    } elseif (is_object($filter) && method_exists($filter, 'filter')) {
                        $value = $filter->filter($value);
                    }
                }
            }

            $out->setData($column, $value);
        }

        return $out;
    }
}
