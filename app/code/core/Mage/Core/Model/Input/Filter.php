<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Filter data collector
 *
 * Model for multi-filtering all data which set to models
 * Example:
 * <code>
 * $filter = Mage::getModel('core/input_filter');
 * $filter->setFilters([
 *      'list_values' => [
 *          'children_filters' => [ //filters will applied to all children
 *              'striptags',  // Simple string filter using core helper
 *              function($value) { return strtoupper($value); }  // Callable filter
 *          ]
 *      ],
 *      'list_values_with_name' => [
 *          'children_filters' => [
 *              'item1' => [
 *                  function($value) { return strtoupper($value); }
 *              ],
 *              'item2' => [
 *                  ['model' => 'core/input_filter_maliciousCode']
 *              ],
 *              'item3' => [
 *                  [
 *                      'helper' => 'core',
 *                      'method' => 'stripTags',
 *                      'args' => ['<p> <div>', true]]
 *              ]
 *          ]
 *      ]
 *  ]);
 *  $filter->addFilter('name2', function($value) { return preg_replace('/[^a-zA-Z0-9]/', '', $value); });
 *  $filter->addFilter('name1', 'striptags');
 *  $filter->addFilter('name1', 'email');
 *  $filter->addFilters([
 *      'list_values_with_name' => [
 *          'children_filters' => [
 *              'deep_list' => [
 *                  'children_filters' => [
 *                      'sub1' => [
 *                          function($value) { return strtolower($value); }
 *                      ],
 *                      'sub2' => ['int']
 *                  ]
 *              ]
 *          ]
 *      ]
 *  ]);
 *  $filter->filter([
 *      'name1' => 'some <b>string</b>',
 *      'name2' => '888 555',
 *      'list_values' => [
 *          'some <b>string2</b>',
 *          'some <p>string3</p>',
 *      ],
 *      'list_values_with_name' => [
 *          'item1' => 'some <b onclick="alert(\'2\')">string4</b>',
 *         'item2' => 'some <b onclick="alert(\'1\')">string5</b>',
 *          'item3' => 'some <p>string5</p> <b>bold</b> <div>div</div>',
 *          'deep_list' => [
 *              'sub1' => 'toLowString',
 *              'sub2' => '5 TO INT',
 *          ]
 *      ]
 *  ]);
 * </code>
 * @see Mage_Core_Model_Input_FilterTest See this class for manual
 */
class Mage_Core_Model_Input_Filter
{
    /**
     * Filters data collectors
     *
     * @var array
     */
    protected $_filters = [];

    /**
     * Add filter
     *
     * @param string $name
     * @param callable|array $filter
     * @param string $placement
     * @return $this
     */
    public function addFilter($name, $filter, $placement = 'append')
    {
        if ($placement == 'prepend') {
            array_unshift($this->_filters[$name], $filter);
        } else {
            $this->_filters[$name][] = $filter;
        }
        return $this;
    }

    /**
     * Add a filter to the end of the chain
     *
     * @return $this
     */
    public function appendFilter(string $name, callable|array $filter): self
    {
        return $this->addFilter($name, $filter, 'append');
    }

    /**
     * Add a filter to the start of the chain
     *
     * @return $this
     */
    public function prependFilter(string $name, callable|array $filter): self
    {
        return $this->addFilter($name, $filter, 'prepend');
    }

    /**
     * Add filters
     *
     * Filters data must be has view as
     *      [
     *          'key1' => $filters,
     *          'key2' => [ ... ], //array filters data
     *          'key2' => $filters
     *      ]
     *
     * @return $this
     */
    public function addFilters(array $filters)
    {
        $this->_filters = array_merge_recursive($this->_filters, $filters);
        return $this;
    }

    /**
     * Set filters
     *
     * @return $this
     */
    public function setFilters(array $filters)
    {
        $this->_filters = $filters;
        return $this;
    }

    /**
     * Get filters
     *
     * @param string|null $name     Get filter for selected name
     * @return array
     */
    public function getFilters($name = null)
    {
        if ($name === null) {
            return $this->_filters;
        }
        return $this->_filters[$name] ?? null;
    }

    /**
     * Filter data
     *
     * @param array $data
     * @return array    Return filtered data
     */
    public function filter($data)
    {
        return $this->_filter($data);
    }

    /**
     * Recursive filtering
     *
     * @param array|null $filters
     * @param bool $isFilterListSimple
     * @param-out array $filters
     * @return array
     * @throws Exception    Exception when filter is not found or not instance of defined instances
     */
    protected function _filter(array $data, &$filters = null, $isFilterListSimple = false)
    {
        if ($filters === null) {
            $filters = &$this->_filters;
        }
        foreach ($data as $key => $value) {
            if (!$isFilterListSimple && !empty($filters[$key])) {
                $itemFilters = $filters[$key];
            } elseif ($isFilterListSimple && !empty($filters)) {
                $itemFilters = $filters;
            } else {
                continue;
            }

            if (!$isFilterListSimple && is_array($value) && isset($filters[$key]['children_filters'])) {
                $isChildrenFilterListSimple = is_numeric(implode('', array_keys($filters[$key]['children_filters'])));
                $value = $this->_filter($value, $filters[$key]['children_filters'], $isChildrenFilterListSimple);
            } else {
                foreach ($itemFilters as $filterData) {
                    if (is_callable($filterData)) {
                        $value = $filterData($value);
                    } elseif (is_string($filterData)) {
                        // Simple string filter using core helper
                        $value = Mage::helper('core')->filter($value, $filterData);
                    } elseif (is_array($filterData)) {
                        if (isset($filterData['helper'])) {
                            $value = $this->_applyFiltrationWithHelper($value, Mage::helper($filterData['helper']), $filterData);
                        } elseif (isset($filterData['filter'])) {
                            $value = Mage::helper('core')->filter($value, $filterData['filter']);
                        }
                    }
                }
            }
            $data[$key] = $value;
        }
        return $data;
    }

    /**
     * Call specified helper method for $value filtration
     *
     * @param mixed $value
     * @return mixed
     */
    protected function _applyFiltrationWithHelper($value, Mage_Core_Helper_Abstract $helper, array $filterData)
    {
        if (!isset($filterData['method']) || empty($filterData['method'])) {
            throw new Exception('Helper filtration method is not set');
        }
        if (!isset($filterData['args']) || empty($filterData['args'])) {
            $filterData['args'] = [];
        }
        $filterData['args'] = [-100 => $value] + $filterData['args'];
        // apply filter
        $value = call_user_func_array([$helper, $filterData['method']], $filterData['args']);
        return $value;
    }

    /**
     * Try to create Maho helper for filtration based on $filterData. Return false on failure
     *
     * @param array $filterData
     * @return Mage_Core_Helper_Abstract|false
     * @throws Exception
     */
    protected function _getFiltrationHelper($filterData)
    {
        $helper = false;
        if (isset($filterData['helper'])) {
            $helper = $filterData['helper'];
            if (is_string($helper)) {
                $helper = Mage::helper($helper);
            }
            if (!($helper instanceof Mage_Core_Helper_Abstract)) {
                throw new Exception("Filter '{$filterData['helper']}' not found");
            }
        }
        return $helper;
    }

}
