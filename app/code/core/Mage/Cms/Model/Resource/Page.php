<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2023 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Cms
 */

class Mage_Cms_Model_Resource_Page extends Mage_Core_Model_Resource_Db_Abstract
{
    /**
     * Store model
     *
     * @var null|Mage_Core_Model_Store
     */
    protected $_store = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('cms/page', 'page_id');
    }

    /**
     * @throws Mage_Core_Exception_Conflict when the configuration uses the page
     */
    #[\Override]
    protected function _beforeDelete(Mage_Core_Model_Abstract $object)
    {
        if ($object instanceof Mage_Cms_Model_Page) {
            $isUsedInConfig = $this->getUsedInStoreConfigCollection($object);
            if ($isUsedInConfig->count()) {
                // prevent delete
                $object->setId(null);
                throw new Mage_Core_Exception_Conflict(Mage::helper('cms')->__(
                    'Cannot delete page, it is used in "%s".',
                    implode(', ', $isUsedInConfig->getColumnValues('path')),
                ));
            }
        }

        $condition = [
            'page_id = ?' => (int) $object->getId(),
        ];

        $this->_getWriteAdapter()->delete($this->getTable('cms/page_store'), $condition);

        return parent::_beforeDelete($object);
    }

    /**
     * Check the identifier and the custom attributes. A page that the configuration uses stays enabled.
     *
     * @param Mage_Cms_Model_Page $object
     * @throws Mage_Core_Exception_Input with one error for each invalid field
     * @throws Mage_Core_Exception_Conflict when a page of a store view of the page has the identifier
     */
    #[\Override]
    protected function _beforeSave(Mage_Core_Model_Abstract $object)
    {
        /**
         * For two attributes which represent timestamp data in DB
         * we should make converting such as:
         * If they are empty we need to convert them into DB
         * type NULL so in DB they will be empty and not some default value
         */
        foreach (['custom_theme_from', 'custom_theme_to'] as $field) {
            $value = $object->getData($field) ?: null;
            $object->setData($field, Mage::app()->getLocale()->formatDateForDb($value));
        }

        if (empty($object->getData('identifier'))) {
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
            $object->setData('identifier', $urlkey);
        }

        if (!$object->getIsActive()) {
            $isUsedInConfig = $this->getUsedInStoreConfigCollection($object);
            if ($isUsedInConfig->count()) {
                $object->setIsActive();
                Mage::getSingleton('adminhtml/session')->addWarning(
                    Mage::helper('cms')->__(
                        'Cannot disable page, it is used in configuration "%s".',
                        implode(', ', $isUsedInConfig->getColumnValues('path')),
                    ),
                );
            }
        }

        // Sanitize the markup authored in this field on save, keeping the template directives it
        // contains intact. This is not a complete boundary: what a directive resolves to at render
        // is not sanitized, so a crafted parameter still reaches the page. No link filtering:
        // page content is ordinary site navigation, which must not be forced into a new tab.
        Mage::getSingleton('core/input_filter_maliciousCode')->sanitizeFields(
            $object,
            ['content'],
            renderer: Mage::helper('cms')->getPageTemplateProcessor(),
        );

        $errors = new Mage_Core_Exception_Input();
        $identifierError = $this->getIdentifierError((string) $object->getData('identifier'));
        if ($identifierError !== null) {
            $errors->addError('identifier', $identifierError);
        }
        try {
            $this->prepareCustomAttributes($object);
        } catch (Mage_Core_Exception_Input $e) {
            foreach ($e->getErrors() as $error) {
                $errors->addError($error['field'], $error['message']);
            }
        }
        $errors->throwIfErrors();

        if (!$this->getIsUniquePageToStores($object)) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('cms')->__('A page URL key for specified store already exists.'));
        }

        // modify create / update dates
        if ($object->isObjectNew() && !$object->hasCreationTime()) {
            $object->setCreationTime(Mage::app()->getLocale()->formatDateForDb('now'));
        }

        $object->setUpdateTime(Mage::app()->getLocale()->formatDateForDb('now'));

        return parent::_beforeSave($object);
    }

    /**
     * Return the custom attributes of CMS pages that have a column and that the attribute set of the
     * pages holds, keyed by code, in the order of the set. Every page uses the default set.
     *
     * @return array<string, Mage_Eav_Model_Entity_Attribute_Abstract>
     */
    public function getCustomAttributes(): array
    {
        $attributes = [];
        foreach ($this->getCustomAttributeGroups() as ['attributes' => $groupAttributes]) {
            foreach ($groupAttributes as $attribute) {
                $attributes[(string) $attribute->getAttributeCode()] = $attribute;
            }
        }
        return $attributes;
    }

    /**
     * Return the groups of the attribute set of the pages in the order of the set, each with its
     * custom attributes in the order of the group.
     *
     * @return list<array{group: Mage_Eav_Model_Entity_Attribute_Group, attributes: list<Mage_Eav_Model_Entity_Attribute_Abstract>}>
     */
    public function getCustomAttributeGroups(): array
    {
        try {
            $setId = (int) Mage::getSingleton('eav/config')->getEntityType(Mage_Cms_Model_Page::ENTITY)->getDefaultAttributeSetId();
            $all = Mage::getSingleton('eav/config')->getAttributes(Mage_Cms_Model_Page::ENTITY);
        } catch (Mage_Core_Exception) {
            // The install saves pages before the cms_setup upgrade adds the entity type
            return [];
        }
        if (!$setId) {
            return [];
        }
        $columns = $this->_getReadAdapter()->describeTable($this->getMainTable());
        $byId = [];
        foreach ($all as $attribute) {
            if ($attribute->getIsUserDefined() && isset($columns[(string) $attribute->getAttributeCode()])) {
                $byId[(int) $attribute->getId()] = $attribute;
            }
        }

        $adapter = $this->_getReadAdapter();
        $rows = $adapter->fetchAll(
            $adapter->select()
                ->from($this->getTable('eav/entity_attribute'), ['attribute_id', 'attribute_group_id'])
                ->where('attribute_set_id = ?', $setId)
                ->order(['sort_order', 'attribute_id']),
        );
        $byGroup = [];
        foreach ($rows as $row) {
            if (isset($byId[(int) $row['attribute_id']])) {
                $byGroup[(int) $row['attribute_group_id']][] = $byId[(int) $row['attribute_id']];
            }
        }

        $groups = [];
        foreach (Mage::getResourceModel('eav/entity_attribute_group_collection')->setAttributeSetFilter($setId)->setSortOrder() as $group) {
            $groups[] = ['group' => $group, 'attributes' => $byGroup[(int) $group->getId()] ?? []];
        }
        return $groups;
    }

    /**
     * Turn the custom attribute values of $page into column values, and check the required and unique attributes.
     * An attribute that $page does not hold keeps its stored value, and a new page gets its default value. An
     * attribute that its condition hides has no value.
     * Each error names the attribute code after $fieldPrefix, for example customAttributes. for the API.
     *
     * @throws Mage_Core_Exception_Input
     */
    public function prepareCustomAttributes(Mage_Core_Model_Abstract $page, string $fieldPrefix = ''): void
    {
        $helper = Mage::helper('eav');
        $errors = new Mage_Core_Exception_Input();
        $hidden = $helper->clearHiddenValues($page, $this->getCustomAttributes());
        foreach ($this->getCustomAttributes() as $code => $attribute) {
            if (in_array($code, $hidden, true)) {
                continue;
            }
            $label = (string) $attribute->getFrontend()->getLabel();
            if (!$page->hasData($code)) {
                if ($page->getId()) {
                    continue;
                }
                $default = (string) $attribute->getDefaultValue();
                if ($default === '') {
                    if ($attribute->getIsRequired()) {
                        $errors->addError($fieldPrefix . $code, $helper->__('"%s" is a required value.', $label));
                    }
                    continue;
                }
                $page->setData($code, $default);
            }

            $value = $page->getData($code);
            if (is_array($value)) {
                $value = implode(',', array_filter(array_map(strval(...), $value), fn(string $item): bool => $item !== ''));
            }
            if (is_bool($value)) {
                $value = (int) $value;
            }
            if (is_string($value) && trim($value) === '') {
                $value = null;
            }
            if ($value !== null && $attribute->getFrontendInput() === 'date') {
                try {
                    $value = Mage::app()->getLocale()->formatDateForDb($value, withTime: false);
                } catch (Exception) {
                    $errors->addError($fieldPrefix . $code, $helper->__('The value of "%s" must be a date.', $label));
                    continue;
                }
            }
            $length = $attribute->getColumnDefinition()['length'];
            if ($length === 255 && $value !== null && mb_strlen((string) $value) > $length) {
                $errors->addError($fieldPrefix . $code, $helper->__('"%s" length must be equal or less than %s characters.', $label, $length));
                continue;
            }
            $page->setData($code, $value);

            if ($value === null) {
                if ($attribute->getIsRequired()) {
                    $errors->addError($fieldPrefix . $code, $helper->__('"%s" is a required value.', $label));
                }
                continue;
            }
            if ($attribute->getIsUnique() && $this->isCustomAttributeValueUsed($code, $value, (int) $page->getId())) {
                $errors->addError($fieldPrefix . $code, $helper->__('The value of attribute "%s" must be unique', $label));
            }
        }
        $errors->throwIfErrors();
    }

    protected function isCustomAttributeValueUsed(string $code, mixed $value, int $pageId): bool
    {
        $adapter = $this->_getReadAdapter();
        $select = $adapter->select()
            ->from($this->getMainTable(), ['page_id'])
            ->where($adapter->quoteIdentifier($code) . ' = ?', $value)
            ->where('page_id != ?', $pageId)
            ->limit(1);
        return (bool) $adapter->fetchOne($select);
    }

    /**
     * @param Mage_Cms_Model_Page $object
     */
    #[\Override]
    protected function _afterSave(Mage_Core_Model_Abstract $object)
    {
        $oldStores = $this->lookupStoreIds($object->getId());
        $newStores = (array) $object->getStores();
        if (empty($newStores)) {
            $newStores = (array) $object->getStoreId();
        }
        $table  = $this->getTable('cms/page_store');
        $insert = array_diff($newStores, $oldStores);
        $delete = array_diff($oldStores, $newStores);

        if ($delete) {
            $where = [
                'page_id = ?' => (int) $object->getId(),
                'store_id IN (?)' => $delete,
            ];

            $this->_getWriteAdapter()->delete($table, $where);
        }

        if ($insert) {
            $data = [];

            foreach ($insert as $storeId) {
                $data[] = [
                    'page_id'  => (int) $object->getId(),
                    'store_id' => (int) $storeId,
                ];
            }

            $this->_getWriteAdapter()->insertMultiple($table, $data);
        }

        // Layout cache is tagged by handle; a page render only contributes `cms_page`.
        Mage::app()->cleanCache(['cms_page']);

        return parent::_afterSave($object);
    }

    #[\Override]
    public function load(Mage_Core_Model_Abstract $object, $value, $field = null)
    {
        if (!is_numeric($value) && is_null($field)) {
            $field = 'identifier';
        }

        return parent::load($object, $value, $field);
    }

    #[\Override]
    protected function _afterLoad(Mage_Core_Model_Abstract $object)
    {
        if ($object->getId()) {
            $stores = $this->lookupStoreIds($object->getId());

            $object->setData('store_id', $stores);
        }

        return parent::_afterLoad($object);
    }

    /**
     * Retrieve select object for load object data
     *
     * @param string $field
     * @param mixed $value
     * @param Mage_Cms_Model_Page $object
     * @return Maho\Db\Select
     */
    #[\Override]
    protected function _getLoadSelect($field, $value, $object)
    {
        $select = parent::_getLoadSelect($field, $value, $object);

        if ($object->getStoreId()) {
            $storeIds = [Mage_Core_Model_App::ADMIN_STORE_ID, (int) $object->getStoreId()];
            $select->join(
                ['cms_page_store' => $this->getTable('cms/page_store')],
                $this->getMainTable() . '.page_id = cms_page_store.page_id',
                [],
            )
                ->where('is_active = ?', 1)
                ->where('cms_page_store.store_id IN (?)', $storeIds)
                ->order('cms_page_store.store_id DESC')
                ->limit(1);
        }

        return $select;
    }

    /**
     * Retrieve load select with filter by identifier, store and activity
     *
     * @param string $identifier
     * @param int|array $store
     * @param int $isActive
     * @return Maho\Db\Select
     */
    protected function _getLoadByIdentifierSelect($identifier, $store, $isActive = null)
    {
        $select = $this->_getReadAdapter()->select()
            ->from(['cp' => $this->getMainTable()])
            ->join(
                ['cps' => $this->getTable('cms/page_store')],
                'cp.page_id = cps.page_id',
                [],
            )
            ->where('cp.identifier = ?', $identifier)
            ->where('cps.store_id IN (?)', $store);

        if (!is_null($isActive)) {
            $select->where('cp.is_active = ?', $isActive);
        }

        return $select;
    }

    /**
     * Check for unique of identifier of page to selected store(s).
     *
     * @return bool
     */
    public function getIsUniquePageToStores(Mage_Core_Model_Abstract $object)
    {
        if (!$object->hasStores()) {
            $stores = [Mage_Core_Model_App::ADMIN_STORE_ID];
        } else {
            $stores = (array) $object->getData('stores');
        }

        $select = $this->_getLoadByIdentifierSelect($object->getData('identifier'), $stores);

        if ($object->getId()) {
            $select->where('cps.page_id <> ?', $object->getId());
        }

        if ($this->_getWriteAdapter()->fetchRow($select)) {
            return false;
        }

        return true;
    }

    #[\Deprecated(message: 'Use getIdentifierError() instead')]
    protected function isNumericPageIdentifier(Mage_Core_Model_Abstract $object): int|false
    {
        return preg_match('/^[0-9]+$/', (string) $object->getData('identifier'));
    }

    #[\Deprecated(message: 'Use getIdentifierError() instead')]
    protected function isValidPageIdentifier(Mage_Core_Model_Abstract $object): int|false
    {
        return preg_match('/^[a-z0-9][a-z0-9_\/-]+(\.[a-z0-9_-]+)?$/', (string) $object->getData('identifier'));
    }

    /**
     * Return the problem of $identifier as the URL key of a page, or null when it has none.
     */
    public function getIdentifierError(string $identifier): ?string
    {
        $helper = Mage::helper('cms');
        return match (true) {
            $identifier === '' => $helper->__('Identifier is required.'),
            !preg_match('/^[a-z0-9][a-z0-9_\/-]+(\.[a-z0-9_-]+)?$/', $identifier) => $helper->__('The page URL key contains capital letters or disallowed symbols.'),
            (bool) preg_match('/^[0-9]+$/', $identifier) => $helper->__('The page URL key cannot consist only of numbers.'),
            default => null,
        };
    }

    public function getUsedInStoreConfigCollection(Mage_Cms_Model_Page $page, ?array $paths = []): Mage_Core_Model_Resource_Db_Collection_Abstract
    {
        $storeIds   = (array) $page->getStoreId();
        $storeIds[] = Mage_Core_Model_App::ADMIN_STORE_ID;
        $config     = Mage::getResourceModel('core/config_data_collection')
            ->addFieldToFilter('value', $page->getIdentifier())
            ->addFieldToFilter('scope_id', ['in' => $storeIds]);

        if (!is_null($paths)) {
            $paths = Mage_Cms_Helper_Page::getUsedInStoreConfigPaths($paths);
            $config->addFieldToFilter('path', ['in' => $paths]);
        }

        return $config;
    }

    public function isUsedInStoreConfig(?Mage_Cms_Model_Page $page = null, ?array $paths = []): bool
    {
        return (bool) $this->getUsedInStoreConfigCollection($page, $paths)->count();
    }

    /**
     * Check if page identifier exist for specific store
     * return page id if page exists
     *
     * @param string $identifier
     * @param int $storeId
     * @return string
     */
    public function checkIdentifier($identifier, $storeId)
    {
        $stores = [Mage_Core_Model_App::ADMIN_STORE_ID, $storeId];
        $select = $this->_getLoadByIdentifierSelect($identifier, $stores, 1);
        $select->reset(Maho\Db\Select::COLUMNS)
            ->columns('cp.page_id')
            ->order('cps.store_id DESC')
            ->limit(1);

        return $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * Retrieves cms page title from DB by passed identifier.
     *
     * @param string $identifier
     * @return string
     */
    public function getCmsPageTitleByIdentifier($identifier)
    {
        $stores = [Mage_Core_Model_App::ADMIN_STORE_ID];
        if ($this->_store) {
            $stores[] = (int) $this->getStore()->getId();
        }

        $select = $this->_getLoadByIdentifierSelect($identifier, $stores);
        $select->reset(Maho\Db\Select::COLUMNS)
            ->columns('cp.title')
            ->order('cps.store_id DESC')
            ->limit(1);

        return $this->_getReadAdapter()->fetchOne($select);
    }

    /**
     * Retrieves cms page title from DB by passed id.
     *
     * @param string $id
     * @return string
     */
    public function getCmsPageTitleById($id)
    {
        $adapter = $this->_getReadAdapter();

        $select  = $adapter->select()
            ->from($this->getMainTable(), 'title')
            ->where('page_id = :page_id');

        $binds = [
            'page_id' => (int) $id,
        ];

        return $adapter->fetchOne($select, $binds);
    }

    /**
     * Retrieves cms page identifier from DB by passed id.
     *
     * @param string $id
     * @return string|false
     */
    public function getCmsPageIdentifierById($id)
    {
        $adapter = $this->_getReadAdapter();

        $select  = $adapter->select()
            ->from($this->getMainTable(), 'identifier')
            ->where('page_id = :page_id');

        $binds = [
            'page_id' => (int) $id,
        ];

        return $adapter->fetchOne($select, $binds);
    }

    /**
     * Get store ids to which specified item is assigned
     *
     * @param int|string $pageId
     * @return array
     */
    public function lookupStoreIds($pageId)
    {
        $adapter = $this->_getReadAdapter();

        $select  = $adapter->select()
            ->from($this->getTable('cms/page_store'), 'store_id')
            ->where('page_id = ?', (int) $pageId);

        return $adapter->fetchCol($select);
    }

    /**
     * Set store model
     *
     * @param Mage_Core_Model_Store $store
     * @return $this
     */
    public function setStore($store)
    {
        $this->_store = $store;
        return $this;
    }

    /**
     * Retrieve store model
     *
     * @return Mage_Core_Model_Store
     */
    public function getStore()
    {
        return Mage::app()->getStore($this->_store);
    }

    /**
     * The content of every CMS page, for the Tailwind build.
     *
     * @return iterable<string>
     */
    #[Maho\Config\TailwindSource('cms_page', model: Mage_Cms_Model_Page::class, fields: ['content'])]
    public function getTailwindContent(): iterable
    {
        return Mage::getResourceSingleton('core/design_content')->readColumns($this->getMainTable(), $this->getIdFieldName(), ['content']);
    }
}
