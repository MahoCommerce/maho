<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
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
    public const COL_CATEGORY_PATH = 'category_path';
    public const COL_CATEGORY_ID = 'category_id';

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
    protected $_permanentAttributes = [self::COL_CATEGORY_PATH];

    /**
     * Particular attributes.
     *
     * @var array
     */
    protected $_particularAttributes = [self::COL_STORE];

    /**
     * Existing categories by path.
     *
     * @var array
     */
    protected $_categoriesByPath = [];

    /**
     * Category paths to IDs mapping.
     *
     * @var array
     */
    protected $_pathToId = [];

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
        self::ERROR_INVALID_ATTRIBUTE_TYPE => 'Invalid value "%s" for attribute "%s" in category "%s"',
        self::ERROR_MISSING_REQUIRED_ATTRIBUTE => 'Required attribute "%s" is missing for category "%s"',
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
     * Initialize existing categories.
     *
     * @return $this
     */
    protected function _initCategories(): self
    {
        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = Mage::getResourceModel('catalog/category_collection');
        $collection->addAttributeToSelect(['url_key', 'name'])
                   ->addAttributeToFilter('level', ['gt' => 0])
                   ->setOrder('level', 'ASC')
                   ->setOrder('entity_id', 'ASC')
                   ->load();

        // First, detect duplicate URL keys - this is a halting error
        $urlKeyCount = [];
        $categoriesById = [];

        foreach ($collection as $category) {
            /** @var Mage_Catalog_Model_Category $category */
            $categoriesById[$category->getId()] = $category;

            $urlKey = $category->getUrlKey();
            if (!$urlKey && $category->getName()) {
                // Load fresh category if URL key not properly loaded
                $freshCategory = Mage::getModel('catalog/category')->load($category->getId());
                $urlKey = $freshCategory->getUrlKey();
                if (!$urlKey && $freshCategory->getName()) {
                    $urlKey = $this->_formatUrlKey($freshCategory->getName());
                }
            }

            if ($urlKey) {
                if (!isset($urlKeyCount[$urlKey])) {
                    $urlKeyCount[$urlKey] = 0;
                }
                $urlKeyCount[$urlKey]++;
            }
        }

        // Check for duplicate URL keys - this can cause issues with path resolution
        $duplicates = [];
        foreach ($urlKeyCount as $urlKey => $count) {
            if ($count > 1) {
                $duplicates[] = $urlKey;
            }
        }

        // Log warning for duplicates but don't halt - use the first occurrence
        if (!empty($duplicates)) {
            Mage::log(
                'Warning: Duplicate URL keys found in existing categories: ' . implode(', ', $duplicates) .
                '. Using first occurrence for path mapping. Consider cleaning up category data for better import/export reliability.',
                Mage::LOG_WARNING,
            );
        }

        // Now build the path mappings - each URL key is guaranteed to be unique
        foreach ($collection as $category) {
            /** @var Mage_Catalog_Model_Category $category */
            $pathIds = explode('/', $category->getPath());
            $pathSegments = [];

            foreach ($pathIds as $pathId) {
                if ($pathId == Mage_Catalog_Model_Category::TREE_ROOT_ID) {
                    continue;
                }

                if (isset($categoriesById[$pathId])) {
                    $pathCategory = $categoriesById[$pathId];
                    $urlKey = $pathCategory->getUrlKey();

                    if (!$urlKey && $pathCategory->getName()) {
                        // Load fresh category if URL key not properly loaded
                        $freshCategory = Mage::getModel('catalog/category')->load($pathId);
                        $urlKey = $freshCategory->getUrlKey();
                        if (!$urlKey && $freshCategory->getName()) {
                            $urlKey = $this->_formatUrlKey($freshCategory->getName());
                        }
                    }

                    if ($urlKey) {
                        $pathSegments[] = $urlKey;
                    }
                }
            }

            if (!empty($pathSegments)) {
                $categoryPath = implode('/', $pathSegments);
                $this->_categoriesByPath[$categoryPath] = $category;
                $this->_pathToId[$categoryPath] = (int) $category->getId();
            }
        }

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
        $this->_saveValidatedBunches();

        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            return $this->_deleteCategories();
        } elseif (Mage_ImportExport_Model_Import::BEHAVIOR_REPLACE == $this->getBehavior()) {
            return $this->_saveAndReplaceCategories();
        } elseif (Mage_ImportExport_Model_Import::BEHAVIOR_APPEND == $this->getBehavior()) {
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
            $entityRowsUp = [];
            $attributes = [];
            $storeRows = []; // Store scope rows to process after default scope
            $lastCategoryPath = null; // Track last category path for empty path store rows

            // Group rows by hierarchy level and process in order
            $rowsByLevel = [];
            $seenPaths = []; // Track paths to prevent duplicates

            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }

                $rowScope = $this->getRowScope($rowData);
                if (self::SCOPE_DEFAULT == $rowScope) {
                    $categoryPath = $rowData[self::COL_CATEGORY_PATH];
                    $lastCategoryPath = $categoryPath; // Track last category path

                    // Skip duplicate paths within the same batch
                    if (!isset($seenPaths[$categoryPath])) {
                        $seenPaths[$categoryPath] = true;

                        $level = substr_count($categoryPath, '/');

                        if (!isset($rowsByLevel[$level])) {
                            $rowsByLevel[$level] = [];
                        }
                        $rowsByLevel[$level][] = ['rowData' => $rowData, 'rowNum' => $rowNum];
                    } else {
                        // Add error for duplicate path but don't process
                        $this->addRowError(self::ERROR_DUPLICATE_PATH, $rowNum, $categoryPath);
                    }
                } else {
                    // Store scope rows for later processing (after categories are created)
                    $storeRows[] = [
                        'rowData' => $rowData,
                        'rowNum' => $rowNum,
                        'rowScope' => $rowScope,
                        'categoryPath' => $lastCategoryPath,  // Use last category path if current path is empty
                    ];
                }
            }

            // Process rows level by level (parents first)
            ksort($rowsByLevel);

            foreach ($rowsByLevel as $level => $rows) {
                foreach ($rows as $rowInfo) {
                    $rowData = $rowInfo['rowData'];
                    $rowNum = $rowInfo['rowNum'];

                    $this->_processedEntitiesCount++;
                    $categoryPath = $rowData[self::COL_CATEGORY_PATH];

                    $entityId = $this->_findCategoryByPath($categoryPath);
                    if ($entityId) {
                        // Update existing category
                        $entityRowsUp[] = [
                            'entity_id' => $entityId,
                            'updated_at' => Mage_Core_Model_Locale::now(),
                        ];
                    } else {
                        // Create new category immediately
                        $parentId = $this->_getParentId($categoryPath);
                        if (!$parentId) {
                            continue;
                        }

                        $categoryLevel = count(explode('/', $categoryPath)) + 1; // +1 because root is level 1
                        $position = $this->_getNextPosition($parentId);

                        $entityRow = [
                            'entity_type_id' => $this->_entityTypeId,
                            'attribute_set_id' => $this->_defaultAttributeSetId,
                            'parent_id' => $parentId,
                            'created_at' => Mage_Core_Model_Locale::now(),
                            'updated_at' => Mage_Core_Model_Locale::now(),
                            'path' => '',  // Will be updated after insert
                            'position' => $position,
                            'level' => $categoryLevel,
                            'children_count' => 0,
                        ];

                        // Insert immediately so mapping is available for children
                        $this->_connection->insert($this->_connection->getTableName('catalog_category_entity'), $entityRow);
                        $entityId = (int) $this->_connection->lastInsertId();

                        // Update mapping immediately
                        $this->_pathToId[$categoryPath] = $entityId;

                        // Update path
                        $parentPath = $this->_getPathById($parentId);
                        $path = $parentPath ? $parentPath . '/' . $entityId : $entityId;

                        $this->_connection->update(
                            $this->_connection->getTableName('catalog_category_entity'),
                            ['path' => $path],
                            ['entity_id = ?' => $entityId],
                        );
                    }

                    // Collect attribute data for this category
                    $this->_collectAttributeData($rowData, self::SCOPE_DEFAULT, $categoryPath, $attributes, true);
                }
            }

            // Update existing categories
            if ($entityRowsUp) {
                $this->_connection->insertOnDuplicate($entityTable, $entityRowsUp, ['updated_at']);
            }

            // Process store scope rows now that categories exist
            foreach ($storeRows as $storeRowInfo) {
                $rowData = $storeRowInfo['rowData'];
                $rowScope = $storeRowInfo['rowScope'];
                $categoryPath = $rowData[self::COL_CATEGORY_PATH];

                // Use tracked category path if current path is empty
                if (empty($categoryPath) && !empty($storeRowInfo['categoryPath'])) {
                    $categoryPath = $storeRowInfo['categoryPath'];
                }

                if (!empty($categoryPath) && isset($this->_pathToId[$categoryPath])) {
                    $this->_collectAttributeData($rowData, $rowScope, $categoryPath, $attributes, true);
                }
            }


            // Save attributes
            $this->_saveAttributes($attributes);
        }

        return true;
    }

    /**
     * Get parent category ID for given path.
     */
    protected function _getParentId(string $categoryPath): ?int
    {
        $pathParts = explode('/', $categoryPath);
        if (count($pathParts) <= 1) {
            return 2; // Default category ID - top-level categories go under default category
        }

        array_pop($pathParts); // Remove last segment
        $parentPath = implode('/', $pathParts);

        return isset($this->_pathToId[$parentPath]) ? (int) $this->_pathToId[$parentPath] : null;
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
    protected function _insertCategories(array $entityRows): void
    {
        $entityTable = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity');

        foreach ($entityRows as &$row) {

            // Extract temporary data before database insert
            $categoryPath = $row['_temp_category_path'] ?? '';
            unset($row['_temp_category_path']);

            $this->_connection->insert($entityTable, $row);
            $entityId = $this->_connection->lastInsertId();

            // Update path-to-ID mapping for the newly created category
            if ($categoryPath) {
                $this->_pathToId[$categoryPath] = $entityId;
            }

            // Update path
            $parentPath = '';
            if ($row['parent_id'] != Mage_Catalog_Model_Category::TREE_ROOT_ID) {
                $parentPath = $this->_getPathById($row['parent_id']);
            }
            $path = $parentPath ? $parentPath . '/' . $entityId : $entityId;

            $this->_connection->update(
                $entityTable,
                ['path' => $path],
                ['entity_id = ?' => $entityId],
            );

            $row['entity_id'] = $entityId;
        }
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
    protected function _collectAttributeData(array $rowData, int $rowScope, string $categoryPath, array &$attributes, bool $categoryExists = true): void
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
            $entityId = $this->_findCategoryByPath($categoryPath);
            if (!$entityId) {
                return;
            }
        } else {
            // For new categories, use the category path as a temporary key
            // We'll replace this with the actual entity ID after insertion
            $entityId = '__NEW__' . $categoryPath;
        }

        // Generate url_key if not provided
        if (!isset($rowData['url_key']) && !empty($categoryPath)) {
            $pathParts = explode('/', $categoryPath);
            $urlKey = end($pathParts);
            $rowData['url_key'] = $urlKey;
        }

        foreach ($rowData as $attrCode => $value) {
            // Skip system columns and null values (but allow empty strings)
            if (in_array($attrCode, [self::COL_CATEGORY_PATH, self::COL_STORE]) || is_null($value)) {
                continue;
            }

            if (!isset($attributes[$attrCode])) {
                $attributes[$attrCode] = [];
            }

            $attributeId = $this->_getAttributeId($attrCode);
            if (!$attributeId) {
                continue;
            }

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

            // Replace temporary entity IDs with real ones
            foreach ($attrData as &$attrRow) {
                $entityId = $attrRow['entity_id'];
                if (is_string($entityId) && str_starts_with($entityId, '__NEW__')) {
                    $categoryPath = substr($entityId, 7); // Remove '__NEW__' prefix
                    if (isset($this->_pathToId[$categoryPath])) {
                        $attrRow['entity_id'] = $this->_pathToId[$categoryPath];
                    } else {
                        continue 2; // Skip this attribute completely
                    }
                }
            }

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
                    // Priority: use category_id if provided, otherwise fall back to category_path
                    if (isset($rowData[self::COL_CATEGORY_ID]) && !empty(trim($rowData[self::COL_CATEGORY_ID]))) {
                        $categoryId = (int) trim($rowData[self::COL_CATEGORY_ID]);
                        // Verify the category exists before adding to delete list
                        $category = Mage::getModel('catalog/category')->load($categoryId);
                        if ($category->getId() && $categoryId > 2) { // Don't delete root or default category
                            $idsToDelete[] = $categoryId;
                        }
                    } elseif (isset($rowData[self::COL_CATEGORY_PATH]) && !empty(trim($rowData[self::COL_CATEGORY_PATH]))) {
                        $categoryPath = trim($rowData[self::COL_CATEGORY_PATH]);

                        // First try exact path match
                        if (isset($this->_pathToId[$categoryPath])) {
                            $idsToDelete[] = $this->_pathToId[$categoryPath];
                        } else {
                            // If not found, try to find by the last segment (single-level path)
                            // For user convenience, allow 'category-name' to match 'parent/category-name'
                            foreach ($this->_pathToId as $fullPath => $id) {
                                $pathSegments = explode('/', $fullPath);
                                $lastSegment = end($pathSegments);
                                if ($lastSegment === $categoryPath) {
                                    $idsToDelete[] = $id;
                                    break; // Take the first match to avoid duplicates
                                }
                            }
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
     * REPLACE behavior: Delete non-system categories not in import, then save/update categories from import
     */
    protected function _saveAndReplaceCategories(): bool
    {
        // For REPLACE behavior, we'll delete existing non-system categories first,
        // then proceed with normal save logic
        $entityTable = Mage::getSingleton('core/resource')->getTableName('catalog_category_entity');

        // Delete all non-system categories (this implements the "replace all" behavior)
        // Keep root (1) and default category (2)
        $categoriesToDelete = [];
        foreach ($this->_pathToId as $path => $id) {
            if ($id > 2) { // Skip root (1) and default category (2)
                $categoriesToDelete[] = $id;
            }
        }

        if (!empty($categoriesToDelete)) {
            // Use cascade deletion to also delete child categories
            $allIdsToDelete = $this->_expandIdsWithChildren($categoriesToDelete);
            $this->_connection->delete($entityTable, ['entity_id IN (?)' => $allIdsToDelete]);

            // Clear the path mappings since we deleted everything
            $this->_pathToId = [];

            // Rebuild path mapping with just system categories
            $this->_initCategories();
        }

        // Now save/update categories from import data (normal behavior)
        return $this->_saveCategories();
    }


    /**
     * Obtain scope of the row from row data.
     */
    public function getRowScope(array $rowData): int
    {
        $hasPath = isset($rowData[self::COL_CATEGORY_PATH]) && strlen(trim($rowData[self::COL_CATEGORY_PATH]));
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

        // For non-DELETE behaviors, require category_path
        if ($hasPath && !$hasStore) {
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

        // Check for empty category path (SCOPE_NULL means empty path)
        if (self::SCOPE_NULL == $rowScope) {
            $this->addRowError(self::ERROR_CATEGORY_PATH_EMPTY, $rowNum);
            return false;
        }

        if (self::SCOPE_DEFAULT == $rowScope) {
            $categoryPath = trim($rowData[self::COL_CATEGORY_PATH]);

            if (!$this->_isValidCategoryPath($categoryPath)) {
                $this->addRowError(self::ERROR_CATEGORY_PATH_INVALID, $rowNum);
                return false;
            }

            if (!$this->_hasValidParent($categoryPath)) {
                $this->addRowError(self::ERROR_PARENT_NOT_FOUND, $rowNum);
                return false;
            }

            // Check if name is missing or empty (required for non-DELETE behaviors)
            if (!isset($rowData['name']) || empty(trim($rowData['name']))) {
                $this->addRowError(self::ERROR_INVALID_NAME, $rowNum, $categoryPath);
                return false;
            }

            // Validate attribute data types
            if (!$this->_validateAttributeTypes($rowData, $rowNum, $categoryPath)) {
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
        $hasCategoryPath = isset($rowData[self::COL_CATEGORY_PATH]) && !empty(trim($rowData[self::COL_CATEGORY_PATH]));

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
            $categoryPath = trim($rowData[self::COL_CATEGORY_PATH]);
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
        $booleanAttrs = ['is_active', 'include_in_menu', 'is_anchor', 'display_mode'];
        foreach ($booleanAttrs as $attrCode) {
            if (isset($rowData[$attrCode]) && !empty($rowData[$attrCode])) {
                $value = trim($rowData[$attrCode]);
                // Accept 0, 1, '0', '1', 'true', 'false', 'yes', 'no'
                if (!in_array(strtolower($value), ['0', '1', 'true', 'false', 'yes', 'no'], true)) {
                    $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum);
                    $valid = false;
                }
            }
        }

        // Validate numeric attributes
        $numericAttrs = ['position', 'sort_order'];
        foreach ($numericAttrs as $attrCode) {
            if (isset($rowData[$attrCode]) && !empty($rowData[$attrCode])) {
                $value = trim($rowData[$attrCode]);
                if (!is_numeric($value)) {
                    $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum);
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
     * Find category ID by path, handling both exact paths and default category fallback.
     */
    protected function _findCategoryByPath(string $categoryPath): ?int
    {
        // Try exact path first
        if (isset($this->_pathToId[$categoryPath])) {
            return $this->_pathToId[$categoryPath];
        }

        // If it's a single-level path, also try looking for it under default-category
        if (!str_contains($categoryPath, '/')) {
            $fullPath = 'default-category/' . $categoryPath;
            if (isset($this->_pathToId[$fullPath])) {
                return $this->_pathToId[$fullPath];
            }
        }

        return null;
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
        // For DELETE behavior, adjust permanent attributes to allow either category_id or category_path
        if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
            // Initialize paths even for DELETE operations (needed for path-based deletion fallback)
            $this->_collectNewCategoryPaths();

            $originalPermanentAttributes = $this->_permanentAttributes;

            // Check if we have either category_id or category_path column
            $columns = $this->_getSource()->getColNames();
            if (in_array(self::COL_CATEGORY_ID, $columns) || in_array(self::COL_CATEGORY_PATH, $columns)) {
                // Temporarily allow validation to pass - we'll validate in _validateDeleteRow
                $this->_permanentAttributes = [];
                parent::validateData();
                $this->_permanentAttributes = $originalPermanentAttributes;
                return $this;
            } else {
                // Neither column present - add a custom error and return this
                $this->addRowError(self::ERROR_DELETE_IDENTIFIER_MISSING, 0);
                $this->_permanentAttributes = $originalPermanentAttributes;
                return $this;
            }
        }

        // For non-DELETE behaviors, require category_path and collect paths
        $this->_collectNewCategoryPaths();
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
            if (isset($rowData[self::COL_CATEGORY_PATH]) && !empty($rowData[self::COL_CATEGORY_PATH])) {
                $categoryPath = trim($rowData[self::COL_CATEGORY_PATH]);
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
