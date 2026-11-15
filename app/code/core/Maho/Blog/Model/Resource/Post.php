<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

class Maho_Blog_Model_Resource_Post extends Maho_Blog_Model_Resource_Abstract
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
            'short_content',
            'meta_description',
            'meta_keywords',
            'meta_title',
            'meta_robots',
            'created_at',
            'updated_at',
        ];
    }

    /**
     * The codes of the attributes that are columns of the entity table.
     *
     * @return list<string>
     */
    public function getStaticAttributeCodes(): array
    {
        return [
            'title',
            'url_key',
            'is_active',
            'publish_date',
            'content',
            'short_content',
            'meta_description',
            'meta_keywords',
            'meta_title',
            'meta_robots',
        ];
    }

    #[\Override]
    protected function _afterSave(\Maho\DataObject $object)
    {
        if ($object->hasStores()) {
            $this->_saveStoreRelations($object);
        }

        if ($object->hasData('categories')) {
            $this->_saveCategoryRelations($object);
        }

        return parent::_afterSave($object);
    }

    protected function _saveStoreRelations(\Maho\DataObject $post): void
    {
        $oldStores = $this->lookupStoreIds((int) $post->getId());
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


    /**
     * @param bool|null $isActive null matches an inactive post too
     */
    public function getPostIdByUrlKey(string $urlKey, int $storeId, ?bool $isActive = true): ?int
    {
        $stores = [Mage_Core_Model_App::ADMIN_STORE_ID, $storeId];
        $select = $this->getLoadByUrlKeySelect($urlKey, $stores, $isActive);
        $select->reset(Maho\Db\Select::COLUMNS)
            ->columns('bp.entity_id')
            ->order('bps.store_id DESC')
            ->limit(1);

        $result = $this->_getReadAdapter()->fetchOne($select);
        return $result ? (int) $result : null;
    }

    protected function getLoadByUrlKeySelect(string $urlKey, array $store, ?bool $isActive = null): Maho\Db\Select
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
    protected function _beforeSave(\Maho\DataObject $object): self
    {
        $coreHelper = Mage::helper('core');

        $object->setData('attribute_set_id', Mage::helper('blog')->getValidAttributeSetId(
            (int) $object->getData('attribute_set_id'),
            $this->getEntityType(),
        ));

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

        // Validate publish_date and set to today (in store TZ — matches admin's view) if empty/invalid
        if (!$object->hasData('publish_date') || empty($object->getData('publish_date')) || !$coreHelper->isValidDate($object->getData('publish_date'))) {
            $object->setData('publish_date', Mage::app()->getLocale()->utcToStore()->format(Mage_Core_Model_Locale::DATE_FORMAT));
        }

        // Sanitize the markup authored in these fields on save, keeping the template directives it
        // contains intact. This is not a complete boundary: what a directive resolves to at render
        // is not sanitized, so a crafted parameter still reaches the page.
        // Maho_Blog_Model_Post::getFilteredContent() renders the content with the same processor.
        Mage::helper('blog')->sanitizeAttributeValues($object, $this->getEntityType(), ['content', 'short_content']);

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

        if ($object instanceof Maho_Blog_Model_Post) {
            $this->_validate($object);
        }

        return parent::_beforeSave($object);
    }

    /**
     * A post that was saved before these checks keeps its URL key, so only a new or a changed URL key is checked.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when another post of the same store view has the URL key
     */
    protected function _validate(Maho_Blog_Model_Post $object): void
    {
        $helper = Mage::helper('blog');
        $errors = new Mage_Core_Exception_Input();
        if ((!$object->getId() || $object->hasData('title')) && (string) $object->getData('title') === '') {
            $errors->addError('title', $helper->__('The post title is required.'));
        }

        $urlKey = (string) $object->getData('url_key');
        $urlKeyChanged = !$object->getId() || $object->dataHasChangedFor('url_key');
        if ($urlKeyChanged && !preg_match(Maho_Blog_Model_Post::URL_KEY_PATTERN, $urlKey)) {
            $errors->addError('url_key', $helper->__('The URL key "%s" must have only lowercase letters, digits and dashes.', $urlKey));
        }

        $categoryIds = $object->hasData('categories') ? array_map(intval(...), (array) $object->getData('categories')) : [];
        if ($categoryIds !== []) {
            $known = Mage::getResourceModel('blog/category_collection')->addFieldToFilter('entity_id', ['in' => $categoryIds])->getAllIds();
            $unknown = array_diff($categoryIds, array_map(intval(...), $known));
            if ($unknown !== []) {
                $errors->addError('category_ids', $helper->__('Unknown blog category ID(s): %s', implode(', ', $unknown)));
            }
        }
        $errors->throwIfErrors();

        if (($urlKeyChanged || $object->dataHasChangedFor('stores')) && $this->_isUrlKeyUsed($urlKey, $object)) {
            throw new Mage_Core_Exception_Conflict($helper->__('Another post of the same store view has the URL key "%s".', $urlKey));
        }
    }

    /**
     * Return whether another post with $urlKey shares a store view with $post. A post of all store views shares every store view.
     */
    protected function _isUrlKeyUsed(string $urlKey, \Maho\DataObject $post): bool
    {
        $stores = array_map(intval(...), $post->hasData('stores') ? (array) $post->getData('stores') : $this->lookupStoreIds((int) $post->getId()));
        if ($stores === []) {
            return false;
        }
        $select = $this->_getReadAdapter()->select()
            ->from(['bp' => $this->getEntityTable()], ['entity_id'])
            ->join(['bps' => $this->_storeTable], 'bp.entity_id = bps.post_id', [])
            ->where('bp.url_key = ?', $urlKey)
            ->limit(1);
        if (!in_array(Mage_Core_Model_App::ADMIN_STORE_ID, $stores, true)) {
            $select->where('bps.store_id IN (?)', [Mage_Core_Model_App::ADMIN_STORE_ID, ...$stores]);
        }
        if ($post->getId()) {
            $select->where('bp.entity_id != ?', (int) $post->getId());
        }
        return (bool) $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * Save static attributes directly to main entity table
     * Override to handle hybrid EAV/static approach
     */
    #[\Override]
    public function save(\Maho\DataObject $object)
    {
        $now = Mage::app()->getLocale()->formatDateForDb('now');

        if (!$object->getId()) {
            $object->setCreatedAt($now);
        }
        $object->setUpdatedAt($now);

        // First do the EAV save (creates entity and gets ID)
        parent::save($object);

        // Then save static attributes to main table (needs ID from parent save)
        $this->_saveStaticAttributes($object);

        return $this;
    }

    protected function _saveStaticAttributes(\Maho\DataObject $object): self
    {
        $table = $this->getEntityTable();
        $data = array_intersect_key($this->_prepareDataForTable($object, $table), array_flip($this->getStaticAttributeCodes()));

        if (!empty($data) && $object->getId()) {
            $this->_getWriteAdapter()->update($table, $data, ['entity_id = ?' => $object->getId()]);
        }

        return $this;
    }

    /**
     * Load static attributes from main entity table
     */
    #[\Override]
    protected function _afterLoad(\Maho\DataObject $object): self
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds((int) $object->getId());
            $object->setData('stores', $stores);

            $categoryIds = $this->lookupCategoryIds($object->getId());
            $object->setData('category_ids', $categoryIds);
        }

        return parent::_afterLoad($object);
    }

    public function lookupCategoryIds(int $postId): array
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getTable('blog/post_category'), 'category_id')
            ->where('post_id = ?', (int) $postId);

        return $adapter->fetchCol($select);
    }

    protected function _saveCategoryRelations(\Maho\DataObject $post): void
    {
        $oldCategoryIds = array_map(intval(...), $this->lookupCategoryIds((int) $post->getId()));
        $newCategoryIds = array_map(intval(...), (array) $post->getData('categories'));
        $positions = array_map(intval(...), (array) $post->getData('category_positions'));

        $table = $this->getTable('blog/post_category');
        $adapter = $this->_getWriteAdapter();

        $delete = array_diff($oldCategoryIds, $newCategoryIds);
        if (!empty($delete)) {
            $adapter->delete($table, [
                'post_id = ?' => (int) $post->getId(),
                'category_id IN (?)' => $delete,
            ]);
        }

        $insert = array_diff($newCategoryIds, $oldCategoryIds);
        if (!empty($insert)) {
            $data = [];
            foreach ($insert as $categoryId) {
                $data[] = [
                    'post_id' => (int) $post->getId(),
                    'category_id' => $categoryId,
                    'position' => $positions[$categoryId] ?? 0,
                ];
            }
            $adapter->insertMultiple($table, $data);
        }

        foreach (array_intersect($newCategoryIds, $oldCategoryIds) as $categoryId) {
            if (isset($positions[$categoryId])) {
                $adapter->update($table, ['position' => $positions[$categoryId]], [
                    'post_id = ?' => (int) $post->getId(),
                    'category_id = ?' => $categoryId,
                ]);
            }
        }
    }

    /**
     * The content and the short content of every blog post, for the Tailwind build.
     *
     * @return iterable<string>
     */
    #[Maho\Config\TailwindSource('blog_post', model: Maho_Blog_Model_Post::class, fields: ['content', 'short_content'])]
    public function getTailwindContent(): iterable
    {
        return Mage::getResourceSingleton('core/design_content')->readColumns($this->getEntityTable(), $this->getEntityIdField(), ['content', 'short_content']);
    }
}
