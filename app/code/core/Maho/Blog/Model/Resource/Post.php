<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Maho_Blog_Model_Resource_Post extends Mage_Eav_Model_Entity_Abstract
{
    protected string $_storeTable;

    public function __construct()
    {
        $this->setType('blog_post');
        $this->_storeTable = $this->getTable('blog/post_store');

        $resource = Mage::getSingleton('core/resource');
        $this->setConnection(
            $resource->getConnection('core_read'),
            $resource->getConnection('core_write'),
        );
    }

    #[\Override]
    protected function _getDefaultAttributes()
    {
        return [
            'entity_type_id',
            'attribute_set_id',
            'increment_id',
            'title',
            'url_key',
            'is_active',
            'publish_date',
            'content',
            'meta_description',
            'meta_keywords',
            'meta_title',
            'meta_robots',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * Get static attribute codes
     */
    public function getStaticAttributeCodes(): array
    {
        return [
            'title',
            'url_key',
            'is_active',
            'publish_date',
            'content',
            'meta_description',
            'meta_keywords',
            'meta_title',
            'meta_robots',
        ];
    }

    #[\Override]
    protected function _afterSave(Varien_Object $object)
    {
        if ($object->hasStores()) {
            $this->_saveStoreRelations($object);
        }

        return parent::_afterSave($object);
    }

    protected function _saveStoreRelations(Varien_Object $post): void
    {
        $oldStores = $this->lookupStoreIds($post->getId());
        $newStores = (array) $post->getStores();

        $table = $this->_storeTable;
        $adapter = $this->_getWriteAdapter();

        // Delete removed stores
        $delete = array_diff($oldStores, $newStores);
        if (!empty($delete)) {
            $adapter->delete($table, [
                'post_id = ?' => (int) $post->getId(),
                'store_id IN (?)' => $delete,
            ]);
        }

        // Insert new stores
        $insert = array_diff($newStores, $oldStores);
        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $storeId) {
                $data[] = [
                    'post_id' => (int) $post->getId(),
                    'store_id' => (int) $storeId,
                ];
            }
            $adapter->insertMultiple($table, $data);
        }
    }

    public function lookupStoreIds(int $postId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->_storeTable, 'store_id')
            ->where('post_id = ?', (int) $postId);

        return $adapter->fetchCol($select);
    }


    public function getPostIdByUrlKey(string $urlKey, int $storeId): ?int
    {
        $stores = [Mage_Core_Model_App::ADMIN_STORE_ID, $storeId];
        $select = $this->getLoadByUrlKeySelect($urlKey, $stores, true);
        $select->reset(Zend_Db_Select::COLUMNS)
            ->columns('bp.entity_id')
            ->order('bps.store_id DESC')
            ->limit(1);

        $result = $this->_getReadAdapter()->fetchOne($select);
        return $result ? (int) $result : null;
    }

    protected function getLoadByUrlKeySelect(string $urlKey, array $store, ?bool $isActive = null): Varien_Db_Select
    {
        $select = $this->_getReadAdapter()->select()
            ->from(['bp' => $this->getEntityTable()])
            ->join(
                ['bps' => $this->getTable('blog/post_store')],
                'bp.entity_id = bps.post_id',
                [],
            )
            ->where('bp.url_key = ?', $urlKey)
            ->where('bps.store_id IN (?)', $store);

        if (!is_null($isActive)) {
            $select->where('bp.is_active = ?', (int) $isActive);
        }

        return $select;
    }

    #[\Override]
    protected function _beforeSave(Varien_Object $object): self
    {
        $coreHelper = Mage::helper('core');

        // Strip all HTML tags from title and meta fields
        $stringFields = ['title', 'url_key', 'meta_title', 'meta_keywords', 'meta_description'];
        foreach ($stringFields as $field) {
            if ($object->hasData($field)) {
                $value = $coreHelper->filterStripTags($object->getData($field));
                $object->setData($field, trim($value)); // Remove leading/trailing whitespace
            }
        }

        // Ensure is_active is a valid boolean
        $isActive = $coreHelper->filterInt($object->getData('is_active') ?? 0) ? 1 : 0;
        $object->setData('is_active', $isActive);

        // Validate publish_date and set to today if empty/invalid
        if (!$object->hasData('publish_date') || empty($object->getData('publish_date')) || !$coreHelper->isValidDate($object->getData('publish_date'))) {
            $locale = Mage::app()->getLocale();
            $today = $locale->utcDate(null, null, true, Mage_Core_Model_Locale::DATE_FORMAT);
            $object->setData('publish_date', $today);
        }

        // Filter HTML content
        if ($object->hasData('content')) {
            $content = $object->getData('content');
            $filter = Mage::getModel('core/input_filter_maliciousCode');
            $filteredContent = $filter->linkFilter($filter->filter($content));
            $object->setData('content', $filteredContent);
        }

        // Auto-generate URL key from title if empty
        if (empty($object->getData('url_key'))) {
            $storeId = null;
            if (is_array($object->getData('stores'))) {
                foreach ($object->getData('stores') as $store) {
                    if (!empty($store)) {
                        $storeId = $store;
                        break;
                    }
                }
            }
            $locale = Mage::getStoreConfig(Mage_Core_Model_Locale::XML_PATH_DEFAULT_LOCALE, $storeId);
            $urlkey = Mage::getModel('catalog/product_url')->formatUrlKey($object->getData('title'), $locale);
            $object->setData('url_key', $urlkey);
        }

        return parent::_beforeSave($object);
    }

    /**
     * Save static attributes directly to main entity table
     * Override to handle hybrid EAV/static approach
     */
    #[\Override]
    public function save(Varien_Object $object)
    {
        $locale = Mage::app()->getLocale();

        if (!$object->getId()) {
            $object->setCreatedAt($locale->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT));
        }
        $object->setUpdatedAt($locale->utcDate(null, null, true)->format(Mage_Core_Model_Locale::DATETIME_FORMAT));


        // Save static attributes to main table
        $this->_saveStaticAttributes($object);

        // Continue with EAV save for non-static attributes
        return parent::save($object);
    }

    protected function _saveStaticAttributes(Varien_Object $object): self
    {
        $adapter = $this->_getWriteAdapter();
        $table = $this->getEntityTable();
        $staticAttributes = $this->getStaticAttributeCodes();

        $data = [];
        foreach ($staticAttributes as $attributeCode) {
            if ($object->hasData($attributeCode)) {
                $data[$attributeCode] = $object->getData($attributeCode);
            }
        }

        if (!empty($data)) {
            if ($object->getId()) {
                $adapter->update($table, $data, ['entity_id = ?' => $object->getId()]);
            }
        }

        return $this;
    }

    /**
     * Load static attributes from main entity table
     */
    #[\Override]
    protected function _afterLoad(Varien_Object $object): self
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds($object->getId());
            $object->setData('stores', $stores);
        }

        return parent::_afterLoad($object);
    }
}
