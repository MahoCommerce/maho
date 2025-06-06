<?php

/**
 * Maho
 *
 * @package    Mage_Core
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * @method Mage_Core_Model_Resource_Website _getResource()
 * @method Mage_Core_Model_Resource_Website getResource()
 * @method Mage_Core_Model_Resource_Website_Collection getCollection()
 * @method Mage_Core_Model_Resource_Website_Collection getResourceCollection()
 *
 * @method $this setCode(string $value)
 * @method string getName()
 * @method $this setName(string $value)
 * @method int getSortOrder()
 * @method $this setSortOrder(int $value)
 * @method $this setDefaultGroupId(int $value)
 * @method int getIsDefault()
 * @method $this setIsDefault(int $value)
 * @method int getGroupId()
 * @method int getStoreId()
 * @method $this setStoreId(int $value)
 * @method array getStoresIds()
 * @method bool hasWebsiteId()
 * @method int getWebsiteId()
 * @method bool hasDefaultGroupId()
 */
class Mage_Core_Model_Website extends Mage_Core_Model_Abstract
{
    public const ENTITY    = 'core_website';
    public const CACHE_TAG = 'website';
    protected $_cacheTag = true;

    /**
     * @var string
     */
    protected $_eventPrefix = 'website';

    /**
     * @var string
     */
    protected $_eventObject = 'website';

    /**
     * Cache configuration array
     *
     * @var array
     */
    protected $_configCache = [];

    /**
     * Website Group Coleection array
     *
     * @var array|null
     */
    protected $_groups;

    /**
     * Website group ids array
     *
     * @var array
     */
    protected $_groupIds = [];

    /**
     * The number of groups in a website
     *
     * @var int
     */
    protected $_groupsCount;

    /**
     * Website Store collection array
     *
     * @var array|null
     */
    protected $_stores;

    /**
     * Website store ids array
     *
     * @var array
     */
    protected $_storeIds = [];

    /**
     * Website store codes array
     *
     * @var array
     */
    protected $_storeCodes = [];

    /**
     * The number of stores in a website
     *
     * @var int
     */
    protected $_storesCount = 0;

    /**
     * Website default group
     *
     * @var Mage_Core_Model_Store_Group
     */
    protected $_defaultGroup;

    /**
     * Website default store
     *
     * @var Mage_Core_Model_Store
     */
    protected $_defaultStore;

    /**
     * is can delete website
     *
     * @var bool|null
     */
    protected $_isCanDelete;

    /**
     * @var bool
     */
    private $_isReadOnly = false;

    /**
     * init model
     *
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('core/website');
    }

    #[\Override]
    public function load($id, $field = null)
    {
        if (!is_numeric($id) && is_null($field)) {
            $this->_getResource()->load($this, $id, 'code');
            return $this;
        }
        return parent::load($id, $field);
    }

    /**
     * Load website configuration
     *
     * @param   string $code
     * @return  Mage_Core_Model_Website
     */
    public function loadConfig($code)
    {
        if (!Mage::getConfig()->getNode('websites')) {
            return $this;
        }
        if (is_numeric($code)) {
            foreach (Mage::getConfig()->getNode('websites')->children() as $websiteCode => $website) {
                if ((int) $website->system->website->id == $code) {
                    $code = $websiteCode;
                    break;
                }
            }
        } else {
            $website = Mage::getConfig()->getNode('websites/' . $code);
        }
        if (!empty($website)) {
            $this->setCode($code);
            $id = (int) $website->system->website->id;
            $this->setId($id)->setStoreId($id);
        }
        return $this;
    }

    /**
     * Get website config data
     *
     * @param string $path
     * @return mixed
     */
    public function getConfig($path)
    {
        if (!isset($this->_configCache[$path])) {
            $config = Mage::getConfig()->getNode('websites/' . $this->getCode() . '/' . $path);
            if (!$config) {
                return false;
                #throw Mage::exception('Mage_Core', Mage::helper('core')->__('Invalid website\'s configuration path: %s', $path));
            }
            if ($config->hasChildren()) {
                $value = [];
                foreach ($config->children() as $k => $v) {
                    $value[$k] = $v;
                }
            } else {
                $value = (string) $config;
            }
            $this->_configCache[$path] = $value;
        }
        return $this->_configCache[$path];
    }

    /**
     * Load group collection and set internal data
     *
     */
    protected function _loadGroups()
    {
        $this->_groups = [];
        $this->_groupsCount = 0;
        foreach ($this->getGroupCollection() as $group) {
            $groupId = $group->getId();
            $this->_groups[$groupId] = $group;
            $this->_groupIds[$groupId] = $groupId;
            if ($this->getDefaultGroupId() == $groupId) {
                $this->_defaultGroup = $group;
            }
            $this->_groupsCount++;
        }
    }

    /**
     * Set website groups
     *
     * @param array $groups
     * @return $this
     */
    public function setGroups($groups)
    {
        $this->_groups = [];
        $this->_groupsCount = 0;
        foreach ($groups as $group) {
            $groupId = $group->getId();
            $this->_groups[$groupId] = $group;
            $this->_groupIds[$groupId] = $groupId;
            if ($this->getDefaultGroupId() == $groupId) {
                $this->_defaultGroup = $group;
            }
            $this->_groupsCount++;
        }
        return $this;
    }

    /**
     * Retrieve new (not loaded) Group collection object with website filter
     *
     * @return Mage_Core_Model_Resource_Store_Group_Collection
     */
    public function getGroupCollection()
    {
        return Mage::getModel('core/store_group')
            ->getCollection()
            ->addWebsiteFilter($this->getId());
    }

    /**
     * Retrieve website groups
     *
     * @return Mage_Core_Model_Store_Group[]
     */
    public function getGroups()
    {
        if (is_null($this->_groups)) {
            $this->_loadGroups();
        }
        return $this->_groups;
    }

    /**
     * Retrieve website group ids
     *
     * @return array
     */
    public function getGroupIds()
    {
        if (is_null($this->_groups)) {
            $this->_loadGroups();
        }
        return $this->_groupIds;
    }

    /**
     * Retrieve number groups in a website
     *
     * @return int
     */
    public function getGroupsCount()
    {
        if (is_null($this->_groups)) {
            $this->_loadGroups();
        }
        return $this->_groupsCount;
    }

    /**
     * Retrieve default group model
     *
     * @return Mage_Core_Model_Store_Group|false
     */
    public function getDefaultGroup()
    {
        if (!$this->hasDefaultGroupId()) {
            return false;
        }
        if (is_null($this->_groups)) {
            $this->_loadGroups();
        }
        return $this->_defaultGroup;
    }

    /**
     * Load store collection and set internal data
     *
     */
    protected function _loadStores()
    {
        $this->_stores = [];
        $this->_storesCount = 0;
        foreach ($this->getStoreCollection() as $store) {
            $storeId = $store->getId();
            $this->_stores[$storeId] = $store;
            $this->_storeIds[$storeId] = $storeId;
            $this->_storeCodes[$storeId] = $store->getCode();
            if ($this->getDefaultGroup() && $this->getDefaultGroup()->getDefaultStoreId() == $storeId) {
                $this->_defaultStore = $store;
            }
            $this->_storesCount++;
        }
    }

    /**
     * Set website stores
     *
     * @param array $stores
     */
    public function setStores($stores)
    {
        $this->_stores = [];
        $this->_storesCount = 0;
        foreach ($stores as $store) {
            $storeId = $store->getId();
            $this->_stores[$storeId] = $store;
            $this->_storeIds[$storeId] = $storeId;
            $this->_storeCodes[$storeId] = $store->getCode();
            if ($this->getDefaultGroup() && $this->getDefaultGroup()->getDefaultStoreId() == $storeId) {
                $this->_defaultStore = $store;
            }
            $this->_storesCount++;
        }
    }

    /**
     * Retrieve new (not loaded) Store collection object with website filter
     *
     * @return Mage_Core_Model_Resource_Store_Collection
     */
    public function getStoreCollection()
    {
        return Mage::getModel('core/store')
            ->getCollection()
            ->addWebsiteFilter($this->getId());
    }

    /**
     * Retrieve wersite store objects
     *
     * @return Mage_Core_Model_Store[]
     */
    public function getStores()
    {
        if (is_null($this->_stores)) {
            $this->_loadStores();
        }
        return $this->_stores;
    }

    /**
     * Retrieve website store ids
     *
     * @return array
     */
    public function getStoreIds()
    {
        if (is_null($this->_stores)) {
            $this->_loadStores();
        }
        return $this->_storeIds;
    }

    /**
     * Retrieve website store codes
     *
     * @return array
     */
    public function getStoreCodes()
    {
        if (is_null($this->_stores)) {
            $this->_loadStores();
        }
        return $this->_storeCodes;
    }

    /**
     * Retrieve number stores in a website
     *
     * @return int
     */
    public function getStoresCount()
    {
        if (is_null($this->_stores)) {
            $this->_loadStores();
        }
        return $this->_storesCount;
    }

    /**
     * is can delete website
     *
     * @return bool
     */
    public function isCanDelete()
    {
        if ($this->_isReadOnly || !$this->getId()) {
            return false;
        }
        if (is_null($this->_isCanDelete)) {
            $this->_isCanDelete = (Mage::getModel('core/website')->getCollection()->getSize() > 2)
                && !$this->getIsDefault();
        }
        return $this->_isCanDelete;
    }

    /**
     * Retrieve unique website-group-store key for collection with groups and stores
     *
     * @return string
     */
    public function getWebsiteGroupStore()
    {
        return implode('-', [$this->getWebsiteId(), $this->getGroupId(), $this->getStoreId()]);
    }

    /**
     * @return int
     */
    public function getDefaultGroupId()
    {
        return $this->_getData('default_group_id');
    }

    /**
     * @return string
     */
    public function getCode()
    {
        return $this->_getData('code');
    }

    #[\Override]
    protected function _beforeDelete()
    {
        $this->_protectFromNonAdmin();
        return parent::_beforeDelete();
    }

    /**
     * rewrite in order to clear configuration cache
     *
     * @return $this
     */
    #[\Override]
    protected function _afterDelete()
    {
        Mage::app()->clearWebsiteCache($this->getId());

        parent::_afterDelete();
        Mage::getConfig()->removeCache();
        return $this;
    }

    /**
     * Retrieve website base currency code
     *
     * @return string
     */
    public function getBaseCurrencyCode()
    {
        if ($this->getConfig(Mage_Core_Model_Store::XML_PATH_PRICE_SCOPE)
            == Mage_Core_Model_Store::PRICE_SCOPE_GLOBAL
        ) {
            return Mage::app()->getBaseCurrencyCode();
        }

        return $this->getConfig(Mage_Directory_Model_Currency::XML_PATH_CURRENCY_BASE);
    }

    /**
     * Retrieve website base currency
     *
     * @return Mage_Directory_Model_Currency
     */
    public function getBaseCurrency()
    {
        $currency = $this->getData('base_currency');
        if (is_null($currency)) {
            $currency = Mage::getModel('directory/currency')->load($this->getBaseCurrencyCode());
            $this->setData('base_currency', $currency);
        }
        return $currency;
    }

    /**
     * Retrieve Default Website Store or null
     *
     * @return Mage_Core_Model_Store
     */
    public function getDefaultStore()
    {
        // init stores if not loaded
        $this->getStores();
        return $this->_defaultStore;
    }

    /**
     * Retrieve default stores select object
     * Select fields website_id, store_id
     *
     * @param bool $withDefault include/exclude default admin website
     * @return Varien_Db_Select
     */
    public function getDefaultStoresSelect($withDefault = false)
    {
        return $this->getResource()->getDefaultStoresSelect($withDefault);
    }

    /**
     * Get/Set isReadOnly flag
     *
     * @param bool $value
     * @return bool
     */
    public function isReadOnly($value = null)
    {
        if ($value !== null) {
            $this->_isReadOnly = (bool) $value;
        }
        return $this->_isReadOnly;
    }
}
