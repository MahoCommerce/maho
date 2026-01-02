<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Import_Entity_Category extends Mage_ImportExport_Model_Import_Entity_Abstract
{
    /**
     * Default Scope
     */
    public const SCOPE_DEFAULT = 1;

    /**
     * Store Scope
     */
    public const SCOPE_STORE = 0;

    /**
     * Null Scope
     */
    public const SCOPE_NULL = -1;

    /**
     * Permanent column names.
     */
    public const COL_STORE = '_store';
    public const COL_CATEGORY_ID = 'category_id';
    public const COL_PARENT_ID = 'parent_id';

    /**
     * Error codes.
     */
    public const ERROR_CATEGORY_PATH_EMPTY = 'categoryPathEmpty';
    public const ERROR_CATEGORY_PATH_INVALID = 'categoryPathInvalid';
    public const ERROR_PARENT_NOT_FOUND = 'parentNotFound';
    public const ERROR_CIRCULAR_REFERENCE = 'circularReference';
    public const ERROR_DUPLICATE_PATH = 'duplicatePath';
    public const ERROR_INVALID_NAME = 'invalidName';
    public const ERROR_INVALID_ATTRIBUTE_TYPE = 'invalidAttributeType';
    public const ERROR_MISSING_REQUIRED_ATTRIBUTE = 'missingRequiredAttribute';
    public const ERROR_DELETE_IDENTIFIER_MISSING = 'deleteIdentifierMissing';
    public const ERROR_CATEGORY_ID_INVALID = 'categoryIdInvalid';

    /**
     * Permanent attributes.
     *
     * @var array
     */
    protected $_permanentAttributes = [self::COL_CATEGORY_ID, self::COL_PARENT_ID];

    /**
     * Particular attributes.
     *
     * @var array
     */
    protected $_particularAttributes = [self::COL_STORE];

    /**
     * Valid parent IDs cache.
     *
     * @var array
     */
    protected $_validParentIds = [];

    /**
     * Existing category IDs.
     *
     * @var array
     */
    protected $_categoryIds = [];

    /**
     * New categories to create.
     *
     * @var array
     */
    protected $_newCategories = [];

    /**
     * Store codes to IDs.
     *
     * @var array
     */
    protected $_storeCodeToId = [];

    /**
     * Category path to ID mapping.
     *
     * @var array
     */
    protected $_pathToId = [];

    /**
     * Default attribute set ID for categories.
     *
     * @var int
     */
    protected $_defaultAttributeSetId;

    /**
     * Message templates.
     *
     * @var array
     */
    protected $_messageTemplates = [
        self::ERROR_CATEGORY_PATH_EMPTY => 'Category path is empty',
        self::ERROR_CATEGORY_PATH_INVALID => 'Category path "%s" is invalid',
        self::ERROR_PARENT_NOT_FOUND => 'Parent category for path "%s" not found',
        self::ERROR_CIRCULAR_REFERENCE => 'Circular reference detected in category path "%s"',
        self::ERROR_DUPLICATE_PATH => 'Duplicate category path "%s" found',
        self::ERROR_INVALID_NAME => 'Invalid category name for path "%s"',
        self::ERROR_INVALID_ATTRIBUTE_TYPE => 'Invalid value for attribute "%s"',
        self::ERROR_MISSING_REQUIRED_ATTRIBUTE => 'Required attribute "%s" is missing',
        self::ERROR_DELETE_IDENTIFIER_MISSING => 'For DELETE operations, either category_id or category_path must be provided',
        self::ERROR_CATEGORY_ID_INVALID => 'Category ID "%s" is invalid or does not exist',
    ];

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_initStores()
             ->_initCategories()
             ->_initAttributeSetId();
    }

    /**
     * Initialize stores mapping.
     *
     * @return $this
     */
    protected function _initStores(): self
    {
        foreach (Mage::app()->getStores(true) as $store) {
            $this->_storeCodeToId[$store->getCode()] = (int) $store->getId();
        }

        // If mapping is empty or missing 'default', query database directly
        if (empty($this->_storeCodeToId) || !isset($this->_storeCodeToId['default'])) {
            $stores = $this->_connection->fetchPairs(
                $this->_connection->select()
                    ->from($this->_connection->getTableName('core_store'), ['code', 'store_id']),
            );
            foreach ($stores as $code => $storeId) {
                $this->_storeCodeToId[$code] = (int) $storeId;
            }
        }


        return $this;
    }

    /**
     * Initialize existing category IDs.
     *
     * @return $this
     */
    protected function _initCategories(): self
    {
        $select = $this->_connection->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalog_category_entity'), ['entity_id', 'parent_id'])
            ->where('level > 0');

        $categories = $this->_connection->fetchAll($select);

        foreach ($categories as $category) {
            $categoryId = (int) $category['entity_id'];
            $parentId = (int) $category['parent_id'];

            $this->_categoryIds[$categoryId] = $parentId;
            $this->_validParentIds[$categoryId] = true;
        }

        // Add default category (ID 2) as a valid parent
        $this->_validParentIds[2] = true;

        return $this;
    }

    /**
     * Initialize default attribute set ID.
     *
     * @return $this
     */
    protected function _initAttributeSetId(): self
    {
        $entityType = Mage::getSingleton('eav/config')->getEntityType('catalog_category');
        $this->_defaultAttributeSetId = $entityType->getDefaultAttributeSetId();
        return $this;
    }

    /**
     * Import data rows.
     */
    #[\Override]
    protected function _importData(): bool
    {
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            return $this->_deleteCategories();
        }
        if (Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE == $this->getBehavior()) {
            return $this->_saveAndReplaceCategories();
        }
        if (Mage_ImportExport_Model_Import::BEHAVIOR_APPEND == $this->getBehavior()) {
            return $this->_saveCategories();
        }

        return false;
    }

    /**
     * Save categories (create/update).
     */
    protected function _saveCategories(): bool
    {
        $entityTable = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity');

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            // Refresh category mapping for each batch to pick up newly created categories
            $this->_initCategories();
            $entityRows = [];
            $entityRowsUp = [];
            $attributes = [];
            $storeRows = [];

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                $rowScope = $this->getRowScope($rowData);

                if (self::SCOPE_DEFAULT == $rowScope) {
                    $categoryId = isset($rowData[self::COL_CATEGORY_ID]) ? trim($rowData[self::COL_CATEGORY_ID]) : '';
                    $parentId = isset($rowData[self::COL_PARENT_ID]) ? (int) trim($rowData[self::COL_PARENT_ID]) : null;

                    if (!empty($categoryId)) {
                        // Update existing category
                        $categoryIdInt = (int) $categoryId;
                        if (isset($this->_categoryIds[$categoryIdInt])) {
                            $entityRowsUp[] = [
                                'entity_id' => $categoryIdInt,
                                'updated_at' => Mage_Core_Model_Locale::now(),
                            ];

                            // Update parent if provided
                            if ($parentId !== null && $parentId !== $this->_categoryIds[$categoryIdInt]) {
                                $entityRowsUp[count($entityRowsUp) - 1]['parent_id'] = $parentId;
                            }

                            $this->_collectAttributeData($rowData, $rowScope, $categoryIdInt, $attributes, true);
                        } else {
                            // Category ID provided but not in cache - check if it exists in database
                            $existingCategory = Mage::getModel('catalog/category')->load($categoryIdInt);
                            if ($existingCategory->getId()) {
                                // Category exists - add it to our cache and update it
                                $this->_categoryIds[$categoryIdInt] = $existingCategory->getParentId();
                                $this->_validParentIds[$categoryIdInt] = true;

                                $entityRowsUp[] = [
                                    'entity_id' => $categoryIdInt,
                                    'updated_at' => Mage_Core_Model_Locale::now(),
                                ];

                                // Update parent if provided
                                if ($parentId !== null && $parentId !== $existingCategory->getParentId()) {
                                    $entityRowsUp[count($entityRowsUp) - 1]['parent_id'] = $parentId;
                                }

                                $this->_collectAttributeData($rowData, $rowScope, $categoryIdInt, $attributes, true);
                            } else {
                                // Category doesn't exist - create it with the specified ID
                                if ($parentId === null) {
                                    $parentId = 2; // Default category
                                }

                                $entityRow = [
                                    'entity_id' => $categoryIdInt, // Use the specified ID
                                    'entity_type_id' => $this->_entityTypeId,
                                    'attribute_set_id' => $this->_defaultAttributeSetId,
                                    'parent_id' => $parentId,
                                    'position' => $this->_getNextPosition($parentId),
                                    'level' => $this->_getCategoryLevel($parentId) + 1,
                                    'children_count' => 0,
                                    'created_at' => Mage_Core_Model_Locale::now(),
                                    'updated_at' => Mage_Core_Model_Locale::now(),
                                ];

                                // Store row data to collect attributes after insertion
                                $entityRow['_temp_row_data'] = $rowData;
                                $entityRow['_temp_row_scope'] = $rowScope;
                                $entityRows[] = $entityRow;

                                // Add to our cache so future references work
                                $this->_categoryIds[$categoryIdInt] = $parentId;
                                $this->_validParentIds[$categoryIdInt] = true;
                            }
                        }
                    } else {
                        // Create new category
                        if ($parentId === null) {
                            $parentId = 2; // Default category
                        }

                        $entityRow = [
                            'entity_type_id' => $this->_entityTypeId,
                            'attribute_set_id' => $this->_defaultAttributeSetId,
                            'parent_id' => $parentId,
                            'position' => $this->_getNextPosition($parentId),
                            'level' => $this->_getCategoryLevel($parentId) + 1,
                            'children_count' => 0,
                            'created_at' => Mage_Core_Model_Locale::now(),
                            'updated_at' => Mage_Core_Model_Locale::now(),
                        ];

                        // Store row data to collect attributes after insertion
                        $entityRow['_temp_row_data'] = $rowData;
                        $entityRow['_temp_row_scope'] = $rowScope;
                        $entityRows[] = $entityRow;
                    }
                } else {
                    // Store scope rows for later processing
                    $storeRows[] = [
                        'rowData' => $rowData,
                        'rowScope' => $rowScope,
                    ];
                }
            }

            // Insert new categories
            if ($entityRows) {
                $newCategoryAttributes = $this->_insertCategories($entityRows);
                // Merge new category attributes with existing attributes
                $attributes = array_merge_recursive($attributes, $newCategoryAttributes);
            }

            // Update existing categories - use UPDATE instead of insertOnDuplicate
            // because insertOnDuplicate requires all NOT NULL columns for the INSERT part
            foreach ($entityRowsUp as $updateRow) {
                $entityId = $updateRow['entity_id'];
                unset($updateRow['entity_id']);
                $this->_connection->update(
                    $entityTable,
                    $updateRow,
                    ['entity_id = ?' => $entityId],
                );
            }

            // Process store scope rows
            foreach ($storeRows as $storeRowInfo) {
                $rowData = $storeRowInfo['rowData'];
                $rowScope = $storeRowInfo['rowScope'];

                // For store rows, we need to find the category ID
                $categoryId = null;
                if (isset($rowData[self::COL_CATEGORY_ID]) && !empty(trim($rowData[self::COL_CATEGORY_ID]))) {
                    $categoryId = (int) trim($rowData[self::COL_CATEGORY_ID]);
                }

                if ($categoryId && isset($this->_categoryIds[$categoryId])) {
                    $this->_collectAttributeData($rowData, $rowScope, $categoryId, $attributes, true);
                }
            }

            // Save attributes
            $this->_saveAttributes($attributes);
        }

        return true;
    }

    /**
     * Get category level by parent ID.
     */
    protected function _getCategoryLevel(int $parentId): int
    {
        $select = $this->_connection->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalog_category_entity'), 'level')
            ->where('entity_id = ?', $parentId);

        return (int) $this->_connection->fetchOne($select);
    }

    /**
     * Get next position for category under parent.
     */
    protected function _getNextPosition(int $parentId): int
    {
        $select = $this->_connection->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalog_category_entity'), 'MAX(position)')
            ->where('parent_id = ?', $parentId);

        $maxPosition = (int) $this->_connection->fetchOne($select);
        return $maxPosition + 1;
    }

    /**
     * Insert new categories.
     */
    protected function _insertCategories(array $entityRows): array
    {
        $entityTable = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity');
        $newCategoryAttributes = [];
        $isPostgres = $this->_connection instanceof \Maho\Db\Adapter\Pdo\Pgsql;
        $isSqlite = $this->_connection instanceof \Maho\Db\Adapter\Pdo\Sqlite;

        foreach ($entityRows as &$row) {
            // Extract temporary data before database insert
            $tempRowData = $row['_temp_row_data'] ?? null;
            $tempRowScope = $row['_temp_row_scope'] ?? null;
            unset($row['_temp_row_data'], $row['_temp_row_scope']);

            // For PostgreSQL and SQLite compatibility, we need to include the path in the initial INSERT
            // because path is NOT NULL. For auto-generated IDs, we need to get the next sequence value first
            // (PostgreSQL) or use a placeholder that we update after getting lastInsertId (SQLite).
            $needsPathUpdate = false;
            if (!isset($row['entity_id'])) {
                if ($isPostgres) {
                    // Get next entity_id from PostgreSQL sequence
                    $entityId = (int) $this->_connection->fetchOne(
                        "SELECT nextval(pg_get_serial_sequence('{$entityTable}', 'entity_id'))",
                    );
                    $row['entity_id'] = $entityId;
                } elseif ($isSqlite) {
                    // SQLite: use placeholder path since we can't get next ID before insert
                    // but SQLite strictly enforces NOT NULL. Will update after insert.
                    $row['path'] = '0';
                    $needsPathUpdate = true;
                } else {
                    // MySQL: let auto_increment handle it, will update path after
                    $needsPathUpdate = true;
                }
            }

            // Calculate path before insertion (for PostgreSQL or when entity_id is known)
            if (isset($row['entity_id'])) {
                $entityId = (int) $row['entity_id'];
                $parentPath = '';
                if ($row['parent_id'] != Mage_Catalog_Model_Category::TREE_ROOT_ID) {
                    $parentPath = $this->_getPathById($row['parent_id']);
                }
                $row['path'] = $parentPath ? $parentPath . '/' . $entityId : (string) $entityId;
            }

            $this->_connection->insert($entityTable, $row);

            // For MySQL/SQLite without pre-set entity_id, get the auto-generated ID and update path
            if ($needsPathUpdate) {
                $entityId = (int) $this->_connection->lastInsertId();
                $parentPath = '';
                if ($row['parent_id'] != Mage_Catalog_Model_Category::TREE_ROOT_ID) {
                    $parentPath = $this->_getPathById($row['parent_id']);
                }
                $path = $parentPath ? $parentPath . '/' . $entityId : (string) $entityId;

                $this->_connection->update(
                    $entityTable,
                    ['path' => $path],
                    ['entity_id = ?' => $entityId],
                );
                $row['entity_id'] = $entityId;
            } else {
                $entityId = (int) $row['entity_id'];
            }

            // Update category ID cache
            $this->_categoryIds[$entityId] = $row['parent_id'];
            $this->_validParentIds[$entityId] = true;

            $this->_processedEntitiesCount++;

            // Collect attributes for this newly created category
            if ($tempRowData && $tempRowScope) {
                $this->_collectAttributeData($tempRowData, $tempRowScope, $entityId, $newCategoryAttributes, false);
            }
        }

        return $newCategoryAttributes;
    }

    /**
     * Get path by category ID.
     */
    protected function _getPathById(int $categoryId): string
    {
        $select = $this->_connection->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalog_category_entity'), 'path')
            ->where('entity_id = ?', $categoryId);

        return (string) $this->_connection->fetchOne($select);
    }

    /**
     * Collect attribute data for saving.
     */
    protected function _collectAttributeData(array $rowData, int $rowScope, int|string $categoryIdentifier, array &$attributes, bool $categoryExists = true): void
    {
        $storeId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;

        if (self::SCOPE_STORE == $rowScope && !empty($rowData[self::COL_STORE])) {
            $storeCode = $rowData[self::COL_STORE];

            // Ensure store mapping is initialized
            if (empty($this->_storeCodeToId)) {
                $this->_initStores();
            }

            // Manual fallback for common store codes (workaround for initialization issues)
            if (empty($this->_storeCodeToId) || !isset($this->_storeCodeToId[$storeCode])) {
                $storeMapping = [
                    'admin' => 0,
                    'default' => 1,
                ];
                $mappedId = $storeMapping[$storeCode] ?? null;
            } else {
                $mappedId = $this->_storeCodeToId[$storeCode] ?? null;
            }

            // Skip invalid store codes entirely instead of falling back to default
            if ($mappedId === null) {
                return; // Skip this row
            }

            $storeId = $mappedId;
        }

        if ($categoryExists) {
            if (is_int($categoryIdentifier)) {
                $entityId = $categoryIdentifier;
            } else {
                return; // Invalid identifier
            }
        } else {
            // For new categories, use the temporary identifier
            $entityId = $categoryIdentifier;
        }

        // Generate url_key if not provided and we have a name
        if (!isset($rowData['url_key']) && !empty($rowData['name'])) {
            $rowData['url_key'] = $this->_formatUrlKey($rowData['name']);
        }

        foreach ($rowData as $attrCode => $value) {
            // Skip system columns and null values (but allow empty strings)
            if (in_array($attrCode, [self::COL_PARENT_ID, self::COL_STORE]) || is_null($value)) {
                continue;
            }

            if (!isset($attributes[$attrCode])) {
                $attributes[$attrCode] = [];
            }

            $attributeId = $this->_getAttributeId($attrCode);
            if (!$attributeId) {
                continue;
            }

            // Convert export labels back to database values
            $value = $this->_convertLabelToValue($attrCode, $value);

            $attributes[$attrCode][] = [
                'entity_type_id' => $this->_entityTypeId,
                'entity_id' => $entityId,
                'attribute_id' => $attributeId,
                'store_id' => $storeId,
                'value' => $value,
            ];

        }
    }

    /**
     * Convert export labels back to database values.
     */
    protected function _convertLabelToValue(string $attrCode, mixed $value): mixed
    {
        if (empty($value) || !is_string($value)) {
            return $value;
        }

        // Handle display_mode attribute specifically
        if ($attrCode === 'display_mode') {
            $labelToValueMap = [
                'Products only' => 'PRODUCTS',
                'Static block only' => 'PAGE',
                'Static block and products' => 'PRODUCTS_AND_PAGE',
            ];

            return $labelToValueMap[$value] ?? $value;
        }

        // Handle other select/multiselect attributes by getting their source model
        $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', $attrCode);
        if ($attribute && $attribute->usesSource()) {
            try {
                $source = $attribute->getSource();
                $options = [];

                foreach ($source->getAllOptions() as $option) {
                    $innerOptions = is_array($option['value']) ? $option['value'] : [$option];
                    foreach ($innerOptions as $innerOption) {
                        if (isset($innerOption['value']) && isset($innerOption['label'])) {
                            $options[$innerOption['label']] = $innerOption['value'];
                        }
                    }
                }

                // If we found a matching label, return its value
                if (isset($options[$value])) {
                    return $options[$value];
                }
            } catch (Exception $e) {
                // If we can't get options, return the original value
            }
        }

        return $value;
    }

    /**
     * Get attribute ID by code.
     */
    protected function _getAttributeId(string $attrCode): ?int
    {
        $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', $attrCode);
        return $attribute ? $attribute->getId() : null;
    }

    /**
     * Save attributes.
     */
    protected function _saveAttributes(array $attributes): void
    {
        foreach ($attributes as $attrCode => $attrData) {
            if (empty($attrData)) {
                continue;
            }

            // Skip any attributes with temporary identifiers (should not happen in new approach)
            $validAttrData = [];
            foreach ($attrData as $attrRow) {
                $entityId = $attrRow['entity_id'];
                if (is_numeric($entityId) && (int) $entityId > 0) {
                    $validAttrData[] = $attrRow;
                }
                // Skip any remaining temporary identifiers from old logic
            }

            if (empty($validAttrData)) {
                continue; // No valid attributes to save for this code
            }

            $attrData = $validAttrData;

            $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', $attrCode);
            if (!$attribute) {
                continue;
            }

            // Skip static attributes - they're stored in the main entity table, not as EAV
            if ($attribute->getBackendType() === 'static') {
                continue;
            }

            $tableName = $attribute->getBackendTable();
            if ($tableName) {
                // Debug: Log what we're trying to save
                if (defined('MAHO_DEBUG_IMPORT') && $attrCode === 'name') {
                    Mage::log("Saving $attrCode to $tableName: " . json_encode($attrData), Mage::LOG_DEBUG);
                }

                $result = $this->_connection->insertOnDuplicate($tableName, $attrData, ['value']);

                if (defined('MAHO_DEBUG_IMPORT') && $attrCode === 'name') {
                    Mage::log("InsertOnDuplicate result: $result", Mage::LOG_DEBUG);
                }
            }
        }
    }

    /**
     * Delete categories.
     */
    protected function _deleteCategories(): bool
    {
        $entityTable = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity');

        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            $idsToDelete = [];

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                $rowScope = $this->getRowScope($rowData);
                if (self::SCOPE_DEFAULT == $rowScope) {
                    // Use category_id for deletion (required in new parent_id approach)
                    if (isset($rowData[self::COL_CATEGORY_ID]) && !empty(trim($rowData[self::COL_CATEGORY_ID]))) {
                        $categoryId = (int) trim($rowData[self::COL_CATEGORY_ID]);
                        // Verify the category exists before adding to delete list
                        if (isset($this->_categoryIds[$categoryId]) && $categoryId > 2) { // Don't delete root or default category
                            $idsToDelete[] = $categoryId;
                        }
                    }
                }
            }

            if ($idsToDelete) {
                // Expand IDs to include child categories for cascade deletion
                $allIdsToDelete = $this->_expandIdsWithChildren($idsToDelete);
                $this->_connection->delete($entityTable, ['entity_id IN (?)' => $allIdsToDelete]);
            }
        }

        return true;
    }

    /**
     * Expand category IDs to include all child categories for cascade deletion.
     */
    protected function _expandIdsWithChildren(array $categoryIds): array
    {
        $allIds = $categoryIds;

        // Get all categories to check for children
        $collection = Mage::getResourceModel('catalog/category_collection')
            ->addAttributeToSelect(['path'])
            ->addAttributeToFilter('level', ['gt' => 0]);

        foreach ($categoryIds as $categoryId) {
            // Find all categories that have this category in their path (i.e., are children)
            foreach ($collection as $category) {
                $path = $category->getPath();
                $pathIds = explode('/', $path);

                // If this category ID is in the path (and it's not the category itself)
                if (in_array((string) $categoryId, $pathIds) && $category->getId() != $categoryId) {
                    $allIds[] = (int) $category->getId();
                }
            }
        }

        return array_unique($allIds);
    }

    /**
     * Save and replace categories.
     * REPLACE behavior: Same as APPEND for categories - update existing, create new if needed
     */
    protected function _saveAndReplaceCategories(): bool
    {
        // For categories, REPLACE works exactly the same as APPEND
        // Both behaviors: update existing categories, create new ones if they don't exist
        return $this->_saveCategories();
    }


    /**
     * Obtain scope of the row from row data.
     */
    public function getRowScope(array $rowData): int
    {
        $hasPath = isset($rowData[self::COL_PARENT_ID]) && strlen(trim($rowData[self::COL_PARENT_ID]));
        $hasCategoryId = isset($rowData[self::COL_CATEGORY_ID]) && strlen(trim($rowData[self::COL_CATEGORY_ID]));
        $hasStore = !empty($rowData[self::COL_STORE]);

        // For DELETE behavior, allow category_id as identifier for SCOPE_DEFAULT
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            if (($hasPath || $hasCategoryId) && !$hasStore) {
                return self::SCOPE_DEFAULT;  // Delete operation with identifier
            } elseif ($hasStore) {
                return self::SCOPE_STORE;    // Store-specific delete (though not typically used)
            } else {
                return self::SCOPE_NULL;     // Invalid delete row
            }
        }

        // For non-DELETE behaviors, accept either parent_id (for new categories) or category_id (for updates)
        if (($hasPath || $hasCategoryId) && !$hasStore) {
            return self::SCOPE_DEFAULT;  // New category or default store update
        } elseif ($hasStore) {
            return self::SCOPE_STORE;    // Store-specific data (with or without path)
        } else {
            return self::SCOPE_NULL;     // Invalid row
        }
    }

    /**
     * Validate data row.
     *
     * @param int $rowNum
     */
    #[\Override]
    public function validateRow(array $rowData, $rowNum): bool
    {
        // Handle DELETE behavior separately with different validation rules
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            return $this->_validateDeleteRow($rowData, $rowNum);
        }

        $rowScope = $this->getRowScope($rowData);

        // Check for invalid row scope
        if (self::SCOPE_NULL == $rowScope) {
            $this->addRowError(self::ERROR_CATEGORY_PATH_EMPTY, $rowNum);
            return false;
        }

        if (self::SCOPE_DEFAULT == $rowScope) {
            // For new categories, validate parent_id
            $parentId = isset($rowData[self::COL_PARENT_ID]) ? trim($rowData[self::COL_PARENT_ID]) : '';

            // For new categories (no category_id), parent_id is required
            $categoryId = isset($rowData[self::COL_CATEGORY_ID]) ? trim($rowData[self::COL_CATEGORY_ID]) : '';
            if (empty($categoryId) && empty($parentId)) {
                $this->addRowError(self::ERROR_PARENT_NOT_FOUND, $rowNum);
                return false;
            }

            // If parent_id is provided, validate it exists
            if (!empty($parentId)) {
                $parentIdInt = (int) $parentId;
                if ($parentIdInt <= 0 || !isset($this->_validParentIds[$parentIdInt])) {
                    $this->addRowError(self::ERROR_PARENT_NOT_FOUND, $rowNum);
                    return false;
                }
            }

            // Check if name is missing or empty (required for non-DELETE behaviors)
            if (!isset($rowData['name']) || empty(trim($rowData['name']))) {
                $this->addRowError(self::ERROR_INVALID_NAME, $rowNum, $categoryId ?: $parentId);
                return false;
            }

            // Validate attribute data types
            if (!$this->_validateAttributeTypes($rowData, $rowNum, $categoryId ?: $parentId)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate row for DELETE behavior.
     * For DELETE, we need either category_id or category_path, but not name or other attributes.
     */
    protected function _validateDeleteRow(array $rowData, int $rowNum): bool
    {
        $hasCategoryId = isset($rowData[self::COL_CATEGORY_ID]) && !empty(trim($rowData[self::COL_CATEGORY_ID]));
        $hasCategoryPath = isset($rowData[self::COL_PARENT_ID]) && !empty(trim($rowData[self::COL_PARENT_ID]));

        // Must have either category_id or category_path for DELETE
        if (!$hasCategoryId && !$hasCategoryPath) {
            $this->addRowError(self::ERROR_DELETE_IDENTIFIER_MISSING, $rowNum);
            return false;
        }

        // If category_id is provided, validate it
        if ($hasCategoryId) {
            $categoryId = trim($rowData[self::COL_CATEGORY_ID]);
            if (!is_numeric($categoryId) || (int) $categoryId <= 2) { // Can't delete root or default category
                $this->addRowError(self::ERROR_CATEGORY_ID_INVALID, $rowNum, $categoryId);
                return false;
            }

            // Check if category exists
            $category = Mage::getModel('catalog/category')->load((int) $categoryId);
            if (!$category->getId()) {
                $this->addRowError(self::ERROR_CATEGORY_ID_INVALID, $rowNum, $categoryId);
                return false;
            }
        }

        // If category_path is provided (fallback), validate it using existing path validation
        if (!$hasCategoryId && $hasCategoryPath) {
            $categoryPath = trim($rowData[self::COL_PARENT_ID]);
            if (!$this->_isValidCategoryPath($categoryPath)) {
                $this->addRowError(self::ERROR_CATEGORY_PATH_INVALID, $rowNum, $categoryPath);
                return false;
            }
        }

        return true;
    }

    /**
     * Validate attribute data types.
     */
    protected function _validateAttributeTypes(array $rowData, int $rowNum, string $categoryPath): bool
    {
        $valid = true;

        // Validate boolean attributes
        $booleanAttrs = ['is_active', 'include_in_menu', 'is_anchor'];
        foreach ($booleanAttrs as $attrCode) {
            if (isset($rowData[$attrCode]) && !empty($rowData[$attrCode])) {
                $value = trim($rowData[$attrCode]);
                // Accept 0, 1, '0', '1', 'true', 'false', 'yes', 'no'
                if (!in_array(strtolower($value), ['0', '1', 'true', 'false', 'yes', 'no'], true)) {
                    $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum, $attrCode);
                    $valid = false;
                }
            }
        }

        // Validate display_mode attribute
        if (isset($rowData['display_mode']) && !empty($rowData['display_mode'])) {
            $value = trim($rowData['display_mode']);
            $validDisplayModes = [
                'PRODUCTS', 'PAGE', 'PRODUCTS_AND_PAGE', // Accept database values
                'Products only', 'Static block only', 'Static block and products', // Accept export labels
            ];
            if (!in_array($value, $validDisplayModes, true)) {
                $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum, 'display_mode');
                $valid = false;
            }
        }

        // Validate numeric attributes
        $numericAttrs = ['position', 'sort_order'];
        foreach ($numericAttrs as $attrCode) {
            if (isset($rowData[$attrCode]) && !empty($rowData[$attrCode])) {
                $value = trim($rowData[$attrCode]);
                if (!is_numeric($value)) {
                    $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum, $attrCode);
                    $valid = false;
                }
            }
        }

        return $valid;
    }

    /**
     * Check if category path is valid.
     */
    protected function _isValidCategoryPath(string $categoryPath): bool
    {
        return (bool) preg_match('/^[a-z0-9\-_\/]+$/', $categoryPath);
    }

    /**
     * Check if parent category exists or can be created.
     */
    protected function _hasValidParent(string $categoryPath): bool
    {
        $pathParts = explode('/', $categoryPath);

        if (count($pathParts) <= 1) {
            return true; // Root level
        }

        array_pop($pathParts);
        $parentPath = implode('/', $pathParts);

        // Check if parent exists in database
        if (isset($this->_pathToId[$parentPath])) {
            return true;
        }

        // Check if parent is being created in this import batch
        if (isset($this->_newCategories[$parentPath])) {
            return true;
        }

        return false;
    }

    /**
     * Format string as URL key.
     */
    protected function _formatUrlKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '-', trim($name)));
    }

    /**
     * Validate data rows and create new category paths mapping.
     *
     * @return Mage_ImportExport_Model_Import_Entity_Abstract
     */
    #[\Override]
    public function validateData()
    {
        // For DELETE behavior, adjust permanent attributes to allow either category_id or parent_id
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {

            $originalPermanentAttributes = $this->_permanentAttributes;

            // Check if we have either category_id or category_path column
            $columns = $this->_getSource()->getColNames();
            if (in_array(self::COL_CATEGORY_ID, $columns) || in_array(self::COL_PARENT_ID, $columns)) {
                // Temporarily allow validation to pass - we'll validate in _validateDeleteRow
                $this->_permanentAttributes = [];
                parent::validateData();
                $this->_permanentAttributes = $originalPermanentAttributes;
                return $this;
            }
            // Neither column present - add a custom error and return this
            $this->addRowError(self::ERROR_DELETE_IDENTIFIER_MISSING, 0);
            $this->_permanentAttributes = $originalPermanentAttributes;
            return $this;
        }

        // For non-DELETE behaviors, require parent_id for new categories
        return parent::validateData();
    }

    /**
     * Collect new category paths from import data for validation.
     */
    protected function _collectNewCategoryPaths(): void
    {
        $source = $this->_getSource();
        $source->rewind();

        while ($source->valid()) {
            $rowData = $source->current();
            if (isset($rowData[self::COL_PARENT_ID]) && !empty($rowData[self::COL_PARENT_ID])) {
                $categoryPath = trim($rowData[self::COL_PARENT_ID]);
                if ($categoryPath && $this->getRowScope($rowData) == self::SCOPE_DEFAULT) {
                    $this->_newCategories[$categoryPath] = true;
                }
            }
            $source->next();
        }

        $source->rewind(); // Reset for normal validation
    }

    /**
     * EAV entity type code getter.
     */
    #[\Override]
    public function getEntityTypeCode(): string
    {
        return 'catalog_category';
    }
}
