<?php

/**
 * Maho
 *
 * @package    Mage_Wishlist
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2025 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Wishlist model
 *
 * @package    Mage_Wishlist
 *
 * @method Mage_Wishlist_Model_Resource_Wishlist _getResource()
 * @method Mage_Wishlist_Model_Resource_Wishlist getResource()
 * @method Mage_Wishlist_Model_Resource_Wishlist_Collection getCollection()
 *
 * @method int getShared()
 * @method $this setShared(int $value)
 * @method string getSharingCode()
 * @method $this setSharingCode(string $value)
 * @method string getUpdatedAt()
 * @method $this setUpdatedAt(string $value)
 * @method string getVisibility()
 */
class Mage_Wishlist_Model_Wishlist extends Mage_Core_Model_Abstract
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    protected $_eventPrefix = 'wishlist';
    /**
     * Wishlist item collection
     *
     * @var Mage_Wishlist_Model_Resource_Item_Collection|null
     */
    protected $_itemCollection = null;

    /**
     * Store filter for wishlist
     *
     * @var Mage_Core_Model_Store|null
     */
    protected $_store = null;

    /**
     * Shared store ids (website stores)
     *
     * @var array|null
     */
    protected $_storeIds = null;

    /**
     * Entity cache tag
     *
     * @var string|bool|array
     */
    protected $_cacheTag = 'wishlist';

    /**
     * Initialize resource model
     */
    #[\Override]
    protected function _construct()
    {
        $this->_init('wishlist/wishlist');
    }

    /**
     * Set date of last update for wishlist
     */
    #[\Override]
    protected function _beforeSave()
    {
        $this->setUpdatedAt(Mage::getSingleton('core/date')->gmtDate());
        return parent::_beforeSave();
    }

    /**
     * Save related items
     */
    #[\Override]
    protected function _afterSave()
    {
        parent::_afterSave();
        if ($this->_itemCollection !== null) {
            $this->getItemsCollection()->save();
        }
        return $this;
    }

    #[\Override]
    public function save()
    {
        $this->_hasDataChanges = true;
        return parent::save();
    }

    /**
     * Load wishlist by customer
     *
     * @param int|Mage_Customer_Model_Customer $customer
     * @param bool $create Create wishlist if don't exists
     * @return $this
     */
    public function loadByCustomer($customer, $create = false)
    {
        if ($customer instanceof Mage_Customer_Model_Customer) {
            $customerId = $customer->getId();
        } else {
            $customerId = (int) $customer;
        }
        $this->_getResource()->loadByCustomerId($this, $customerId);
        $this->_afterLoad();
        if (!$this->getId() && $create) {
            $this->setCustomerId($customerId);
            $this->setSharingCode($this->_getSharingRandomCode());
            $this->save();
        }
        $this->setOrigData();
        return $this;
    }

    /**
     * Load by sharing code
     *
     * @param string $code
     * @return $this
     */
    public function loadByCode($code)
    {
        $this->_getResource()->load($this, $code, 'sharing_code');
        if (!$this->getShared()) {
            $this->setId(null);
        }
        $this->setOrigData();
        return $this;
    }

    /**
     * Get wishlist store identifier
     *
     * @return int
     */
    public function getStoreId()
    {
        if (!$this->hasStoreId()) {
            return Mage::app()->getStore()->getId();
        }
        return (int) $this->_getData('store_id');
    }

    /**
     * Get wishlist store model object
     *
     * @return  Mage_Core_Model_Store
     */
    public function getStore()
    {
        return Mage::app()->getStore($this->getStoreId());
    }

    /**
     * Declare wishlist store model
     *
     * @return  $this
     */
    public function setStore(Mage_Core_Model_Store $store)
    {
        if ($this->getStoreId() != $store->getId()) {
            $this->setStoreId($store->getId());
        }
        return $this;
    }

    /**
     * Get all available store ids for wishlist
     *
     * @return array
     */
    public function getSharedStoreIds()
    {
        $ids = $this->_getData('shared_store_ids');
        if (is_null($ids) || !is_array($ids)) {
            if ($website = $this->getWebsite()) {
                return $website->getStoreIds();
            }
            return $this->getStore()->getWebsite()->getStoreIds();
        }
        return $ids;
    }

    /**
     * Retrieve wishlist name
     *
     * @return string
     */
    public function getName()
    {
        $name = $this->_getData('name');
        if (!strlen($name)) {
            return Mage::helper('wishlist')->getDefaultWishlistName();
        }
        return $name;
    }

    /**
     * Set random sharing code
     *
     * @return $this
     */
    public function generateSharingCode()
    {
        $this->setSharingCode($this->_getSharingRandomCode());
        return $this;
    }

    /**
     * Retrieve sharing code (random string)
     *
     * @return string
     */
    protected function _getSharingRandomCode()
    {
        return Mage::helper('core')->uniqHash();
    }

    /**
     * Retrieve wishlist has salable item(s)
     *
     * @return bool
     */
    public function isSalable()
    {
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getProduct()->getIsSalable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check customer is owner this wishlist
     *
     * @param int $customerId
     * @return bool
     */
    public function isOwner($customerId)
    {
        return $customerId == $this->getCustomerId();
    }

    /**
     * Set customer id
     *
     * @param int $customerId
     * @return $this
     */
    public function setCustomerId($customerId)
    {
        return $this->setData($this->_getResource()->getCustomerIdFieldName(), $customerId);
    }

    /**
     * Retrieve customer id
     *
     * @return int
     */
    public function getCustomerId()
    {
        return $this->getData($this->_getResource()->getCustomerIdFieldName());
    }

    /**
     * Retrieve data for save
     *
     * @return array
     */
    public function getDataForSave()
    {
        $data = [];
        $data[$this->_getResource()->getCustomerIdFieldName()] = $this->getCustomerId();
        $data['shared']      = (int) $this->getShared();
        $data['sharing_code'] = $this->getSharingCode();
        return $data;
    }

    /**
     * Retrieve wishlist item collection
     *
     * @throws Mage_Core_Model_Store_Exception
     */
    public function getItemsCollection(): Mage_Wishlist_Model_Resource_Item_Collection
    {
        if ($this->hasItemsCollection()) {
            return $this->getData('items_collection');
        }
        if (is_null($this->_itemCollection)) {
            $this->_itemCollection = Mage::getModel('wishlist/item')->getCollection();
            $this->_itemCollection
                ->setWishlist($this)
                ->setVisibilityFilter();
        }
        return $this->_itemCollection;
    }

    /**
     * Retrieve wishlist items array
     *
     * @return Mage_Wishlist_Model_Item[]
     */
    public function getAllItems(): array
    {
        $items = [];
        foreach ($this->getItemsCollection() as $item) {
            if (!$item->isDeleted()) {
                $items[] = $item;
            }
        }
        return $items;
    }

    /**
     * Checking items availability
     */
    public function hasItems(): bool
    {
        return count($this->getAllItems()) > 0;
    }

    /**
     * Retrieve wishlist items count
     *
     * @return int
     */
    public function getItemsCount()
    {
        return $this->getItemsCollection()->getSize();
    }

    /**
     * Retrieve item model object by item identifier
     */
    public function getItemById(int $itemId): ?Mage_Wishlist_Model_Item
    {
        if ($item = $this->getItemsCollection()->getItemById($itemId)) {
            return $item;
        }
        foreach ($this->getItemsCollection() as $item) {
            if ($item->getId() == $itemId) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Retrieve wishlist item by product id
     */
    public function getItemByProduct(Mage_Catalog_Model_Product $product): Mage_Wishlist_Model_Item|false
    {
        foreach ($this->getAllItems() as $item) {
            if ($item->representProduct($product)) {
                return $item;
            }
        }
        return false;
    }

    /**
     * Remove wishlist item by item identifier
     */
    public function removeItem(int $itemId): self
    {
        $item = $this->getItemById($itemId);

        if ($item) {
            $item->setWishlist($this);

            $item->isDeleted(true);
            if ($item->getHasChildren()) {
                foreach ($item->getChildren() as $child) {
                    $child->isDeleted(true);
                }
            }

            $parent = $item->getParentItem();
            if ($parent) {
                $parent->isDeleted(true);
            }

            Mage::dispatchEvent('wishlist_remove_item', ['item' => $item]);
        }

        return $this;
    }

    /**
     * Mark all wishlist items as deleted (empty wishlist)
     *
     * @return $this
     */
    public function removeAllItems()
    {
        foreach ($this->getItemsCollection() as $itemId => $item) {
            if (is_null($item->getId())) {
                $this->getItemsCollection()->removeItemByKey($itemId);
            } else {
                $item->isDeleted(true);
            }
        }
        return $this;
    }

    /**
     * Adding item to wishlist
     *
     * @return  $this
     */
    public function addItem(Mage_Wishlist_Model_Item $item)
    {
        $item->setWishlist($this);
        if (!$item->getId()) {
            $this->getItemsCollection()->addItem($item);
            Mage::dispatchEvent('wishlist_add_item', ['item' => $item]);
        }
        return $this;
    }

    /**
     * Adds new product to wishlist.
     * Returns new item or string on error.
     *
     * @param int|Mage_Catalog_Model_Product $product
     * @param mixed $buyRequest
     * @param bool $forciblySetQty
     * @return Mage_Wishlist_Model_Item|string
     */
    public function addNewItem($product, $buyRequest = null, $forciblySetQty = false)
    {
        if (is_array($buyRequest)) {
            $buyRequest = new \Maho\DataObject($buyRequest);
        } elseif (is_string($buyRequest)) {
            $buyRequest = new \Maho\DataObject(unserialize($buyRequest, ['allowed_classes' => false]));
        } elseif (!$buyRequest instanceof \Maho\DataObject) {
            $buyRequest = new \Maho\DataObject();
        }

        if (!$product instanceof Mage_Catalog_Model_Product) {
            $product = Mage::getModel('catalog/product')
                ->setStoreId($this->getStore()->getId())
                ->load((int) $product);
        }
        if (!$product->getId()) {
            Mage::throwException('No Product Found.');
        }

        $cartCandidates = $product->getTypeInstance(true)
            ->processConfiguration($buyRequest, $product);

        // Error message
        if (is_string($cartCandidates)) {
            return $cartCandidates;
        }

        // Ensure array if prepare process return one object
        if (!is_array($cartCandidates)) {
            $cartCandidates = [$cartCandidates];
        }

        $errors = [];
        $items = [];
        $item = null;

        foreach ($cartCandidates as $candidate) {
            if ($candidate->getParentProductId()) {
                continue;
            }
            $candidate->setStoreId($product->getStoreId());
            $qty = max($candidate->getQty(), 1);

            $item = $this->_addCatalogProduct($candidate, $qty, $forciblySetQty);
            $items[] = $item;

            // Collect errors instead of throwing first one
            if ($item->getHasError()) {
                $errors[] = $item->getMessage();
            }
        }

        if (!empty($errors)) {
            Mage::throwException(implode("\n", array_unique($errors)));
        }

        Mage::dispatchEvent('wishlist_product_add_after', ['items' => $items]);

        return $item;
    }

    /**
     * Add catalog product object data to wishlist
     *
     * @param   int $qty
     * @param   bool $forciblySetQty
     * @return  Mage_Wishlist_Model_Item
     */
    protected function _addCatalogProduct(Mage_Catalog_Model_Product $product, $qty = 1, $forciblySetQty = false)
    {
        $newItem = false;
        $item = $this->getItemByProduct($product);
        if ($item === false) {
            $item = Mage::getModel('wishlist/item')
                ->setWishlist($this)
                ->setWishlistId($this->getId());
            if (Mage::app()->getStore()->isAdmin()) {
                $item->setStoreId($product->getStoreId());
            } else {
                $item->setStoreId(Mage::app()->getStore()->getId());
            }
            $newItem = true;
        }

        // We can't modify existing child items
        if ($item->getId() && $product->getParentProductId()) {
            return $item;
        }

        $item->setOptions($product->getCustomOptions())
            ->setProductId($product->getId());

        if ($newItem || $forciblySetQty) {
            $item->setQty($qty);
        } else {
            $item->setQty($item->getQty() + $qty);
        }

        // Add only item that is not in wislist already (there can be other new or already saved item)
        if ($newItem) {
            $this->addItem($item);
        }

        return $item;
    }

    /**
     * Update wishlist Item and set data from request
     *
     * $params sets how current item configuration must be taken into account and additional options.
     * It's passed to Mage_Catalog_Helper_Product->addParamsToBuyRequest() to compose resulting buyRequest.
     *
     * Basically it can hold
     * - 'current_config', \Maho\DataObject or array - current buyRequest that configures product in this item,
     *   used to restore currently attached files
     * - 'files_prefix': string[a-z0-9_] - prefix that was added at frontend to names of file options (file inputs), so they won't
     *   intersect with other submitted options
     *
     * For more options see Mage_Catalog_Helper_Product->addParamsToBuyRequest()
     *
     * @param int|Mage_Wishlist_Model_Item $itemId
     * @param \Maho\DataObject $buyRequest
     * @param null|array|\Maho\DataObject $params
     * @return $this
     *
     * @see Mage_Catalog_Helper_Product::addParamsToBuyRequest()
     */
    public function updateItem($itemId, $buyRequest, $params = null)
    {
        $item = $this->getItemById($itemId);
        if (!$item) {
            Mage::throwException(Mage::helper('wishlist')->__('Cannot specify wishlist item.'));
        }
        $productId = $item->getProduct()->getId();

        // We need to create new clear product instance with same $productId
        // to set new option values from $buyRequest
        $product = Mage::getModel('catalog/product')
            ->setStoreId($this->getStore()->getId())
            ->load($productId);

        if (is_array($params)) {
            $params = new \Maho\DataObject($params);
        } elseif (!$params instanceof \Maho\DataObject) {
            $params = new \Maho\DataObject();
        }
        $params->setCurrentConfig($item->getBuyRequest());
        $buyRequest = Mage::helper('catalog/product')->addParamsToBuyRequest($buyRequest, $params);

        $isForceSetQuantity = !array_any(
            $this->getItemsCollection()->getItems(),
            fn($wishlistItem) => $wishlistItem->getProductId() == $product->getId()
                && $wishlistItem->representProduct($product)
                && $wishlistItem->getId() != $item->getId(),
        );

        $resultItem = $this->addNewItem($product, $buyRequest, $isForceSetQuantity);

        // Error message
        if (is_string($resultItem)) {
            Mage::throwException(Mage::helper('checkout')->__($resultItem));
        }

        // Check if product configuration didn't stick to original wishlist item if it either has the same
        // configuration as some other wishlist item's product or is a completely new configuration.
        if ($resultItem->getId() != $item->getId()) {
            $resultItem->addData([
                'store_id' => $item->getStoreId(),
                'description' => $item->getDescription(),
            ]);
            $this->removeItem($item->getId());
        }

        return $this;
    }
}
