<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2021-2022 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho\Filter;

class ArrayFilter
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
     * Filter array values
     */
    public function filter(array $array): array
    {
        $out = [];
        foreach ($array as $column => $value) {
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

            $out[$column] = $value;
        }
        return $out;
    }
}
