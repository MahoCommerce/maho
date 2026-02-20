<?php

/**
 * Maho
 *
 * @package    Mage_Sales
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_Sales_Model_Resource_Sale_Collection extends \Maho\Data\Collection\Db
{
    /**
     * Totals data
     *
     * @var array
     */
    protected $_totals = [
        'lifetime' => 0, 'base_lifetime' => 0, 'base_avgsale' => 0, 'num_orders' => 0];

    /**
     * Customer model
     *
     * @var Mage_Customer_Model_Customer
     */
    protected $_customer;

    /**
     * Order state value
     *
     * @var null|string|array
     */
    protected $_state = null;

    /**
     * Order state condition
     *
     * @var string
     */
    protected $_orderStateCondition = null;

    /**
     * @var array|null
     */
    protected $_orderStateValue;

    /**
     * Set sales order entity and establish read connection
     */
    public function __construct()
    {
        $conn = Mage::getResourceSingleton('sales/order')->getReadConnection();
        $this->setConnection($conn);
    }

    /**
     * Set filter by customer
     *
     * @return $this
     */
    public function setCustomerFilter(Mage_Customer_Model_Customer $customer)
    {
        $this->_customer = $customer;
        return $this;
    }

    /**
     * Add filter by stores
     *
     * @param array $storeIds
     * @return $this
     */
    public function addStoreFilter($storeIds)
    {
        return $this->addFieldToFilter('store_id', ['in' => $storeIds]);
    }

    /**
     * Set filter by order state
     *
     * @param string|array $state
     * @param bool $exclude
     * @return $this
     */
    public function setOrderStateFilter($state, $exclude = false)
    {
        $this->_orderStateCondition = $exclude ? 'NOT IN' : 'IN';
        $this->_orderStateValue     = is_array($state) ? $state : [$state];
        return $this;
    }

    /**
     * Before load action
     *
     * @return \Maho\Data\Collection\Db
     */
    #[\Override]
    protected function _beforeLoad()
    {
        $this->getSelect()
            ->from(
                ['sales' => Mage::getResourceSingleton('sales/order')->getMainTable()],
                [
                    'store_id',
                    'lifetime'      => new Maho\Db\Expr('SUM(sales.base_grand_total)'),
                    'base_lifetime' => new Maho\Db\Expr('SUM(sales.base_grand_total * sales.base_to_global_rate)'),
                    'avgsale'       => new Maho\Db\Expr('AVG(sales.base_grand_total)'),
                    'base_avgsale'  => new Maho\Db\Expr('AVG(sales.base_grand_total * sales.base_to_global_rate)'),
                    'num_orders'    => new Maho\Db\Expr('COUNT(sales.base_grand_total)'),
                ],
            )
            ->group('sales.store_id');

        if ($this->_customer instanceof Mage_Customer_Model_Customer) {
            $this->addFieldToFilter('sales.customer_id', $this->_customer->getId());
        }

        if (!is_null($this->_orderStateValue)) {
            $condition = '';
            switch ($this->_orderStateCondition) {
                case 'IN':
                    $condition = 'in';
                    break;
                case 'NOT IN':
                    $condition = 'nin';
                    break;
            }
            $this->addFieldToFilter('state', [$condition => $this->_orderStateValue]);
        }

        Mage::dispatchEvent('sales_sale_collection_query_before', ['collection' => $this]);
        return $this;
    }

    /**
     * Load data
     *
     * @param bool $printQuery
     * @param bool $logQuery
     * @return \Maho\Data\Collection\Db
     * @throws Mage_Core_Model_Store_Exception
     */
    #[\Override]
    public function load($printQuery = false, $logQuery = false)
    {
        if ($this->isLoaded()) {
            return $this;
        }

        $this->_beforeLoad();

        $this->_renderFilters()
             ->_renderOrders()
             ->_renderLimit();

        $this->printLogQuery($printQuery, $logQuery);

        $data = $this->getData();
        $this->resetData();

        $stores = Mage::getResourceModel('core/store_collection')
            ->setWithoutDefaultFilter()
            ->load()
            ->toOptionHash();
        $this->_items = [];
        foreach ($data as $v) {
            $storeObject = new \Maho\DataObject($v);
            $storeId     = $v['store_id'];
            $storeName   = $stores[$storeId] ?? null;
            $storeObject->setStoreName($storeName)
                ->setWebsiteId(Mage::app()->getStore($storeId)->getWebsiteId())
                ->setAvgNormalized($v['avgsale'] * $v['num_orders']);
            $this->_items[$storeId] = $storeObject;
            foreach (array_keys($this->_totals) as $key) {
                $this->_totals[$key] += $storeObject->getData($key);
            }
        }

        if ($this->_totals['num_orders']) {
            $this->_totals['avgsale'] = $this->_totals['base_lifetime'] / $this->_totals['num_orders'];
        }

        $this->_setIsLoaded();
        $this->_afterLoad();
        return $this;
    }

    /**
     * Retrieve totals data converted into \Maho\DataObject
     *
     * @return \Maho\DataObject
     */
    public function getTotals()
    {
        return new \Maho\DataObject($this->_totals);
    }
}
