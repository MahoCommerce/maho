<?php

/**
 * Imports categories. A row finds its category by _root and _path, or by category_id when it has no _root.
 *
 * _root is the name of the root category, and _path is the url keys below the root, joined by a slash.
 * An empty _path is the root category itself. When category_id names the same category as the key,
 * the file comes from this database, and a different parent_id moves the category. An empty cell
 * leaves the value of the category as it is.
 *
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ImportExport
 */

declare(strict_types=1);

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
    public const COL_ROOT = '_root';
    public const COL_PATH = '_path';
    public const COL_CATEGORY_ID = 'category_id';
    public const COL_PARENT_ID = 'parent_id';

    /**
     * The folder that the image column is relative to. Without it, the image must already be in media/catalog/category.
     */
    public const PARAM_MEDIA_DIR = 'media_dir';

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
    public const ERROR_INVALID_STORE = 'invalidStore';
    public const ERROR_CATEGORY_NOT_FOUND = 'categoryNotFound';
    public const ERROR_URL_KEY_MISMATCH = 'urlKeyMismatch';
    public const ERROR_IMAGE_NOT_FOUND = 'imageNotFound';

    /**
     * Columns that are not attributes, or attributes that the tree sets and an import never writes.
     */
    protected const SKIPPED_COLUMNS = [
        self::COL_STORE, self::COL_ROOT, self::COL_PATH, self::COL_CATEGORY_ID, self::COL_PARENT_ID,
        'all_children', 'children', 'children_count', 'level', 'path', 'path_in_store', 'url_path',
    ];

    protected const FLAG_ATTRIBUTES = ['is_active', 'include_in_menu', 'is_anchor'];

    /**
     * Particular attributes.
     *
     * @var array
     */
    #[\Override]
    protected $_particularAttributes = [self::COL_STORE, self::COL_ROOT, self::COL_PATH];

    /**
     * Category ID to parent ID, for every category below the tree root.
     *
     * @var array<int, int>
     */
    protected $_categoryIds = [];

    /**
     * Store code to store ID.
     *
     * @var array<string, int>
     */
    protected $_storeCodeToId = [];

    protected Mage_ImportExport_Model_Category_KeyMap $_keyMap;

    /**
     * The key of each category that a row creates, to the number of that row.
     *
     * @var array<string, int>
     */
    protected array $_newKeys = [];

    /**
     * CMS block ID, identifier and title to the block ID.
     *
     * @var array<string, int>|null
     */
    protected ?array $_landingPageIds = null;

    /**
     * Message templates.
     *
     * @var array
     */
    #[\Override]
    protected $_messageTemplates = [
        self::ERROR_CATEGORY_PATH_EMPTY => 'Category path is empty',
        self::ERROR_CATEGORY_PATH_INVALID => 'Category path "%s" is invalid',
        self::ERROR_PARENT_NOT_FOUND => 'Parent category for path "%s" not found',
        self::ERROR_CIRCULAR_REFERENCE => 'Circular reference detected in category path "%s"',
        self::ERROR_DUPLICATE_PATH => 'Duplicate category path "%s" found',
        self::ERROR_INVALID_NAME => 'Invalid category name for path "%s"',
        self::ERROR_INVALID_ATTRIBUTE_TYPE => 'Invalid value for attribute "%s"',
        self::ERROR_MISSING_REQUIRED_ATTRIBUTE => 'Required attribute "%s" is missing',
        self::ERROR_DELETE_IDENTIFIER_MISSING => 'A row to delete needs category_id, or _root and _path',
        self::ERROR_CATEGORY_ID_INVALID => 'Category ID "%s" is invalid or does not exist',
        self::ERROR_INVALID_STORE => 'Store code "%s" is invalid',
        self::ERROR_CATEGORY_NOT_FOUND => 'Category "%s" not found, a store row cannot create it',
        self::ERROR_URL_KEY_MISMATCH => 'The url_key of the new category "%s" must be the last part of _path',
        self::ERROR_IMAGE_NOT_FOUND => 'Image "%s" not found, or not an image file',
    ];

    public function __construct()
    {
        parent::__construct();

        $this->_initStores()
             ->_initCategories();
        $this->_keyMap = new Mage_ImportExport_Model_Category_KeyMap();
    }

    protected function _initStores(): self
    {
        $this->_storeCodeToId = array_map(intval(...), $this->_connection->fetchPairs(
            $this->_connection->select()
                ->from(Mage::getSingleton('core/resource')->getTableName('core/store'), ['code', 'store_id']),
        ));
        return $this;
    }

    protected function _initCategories(): self
    {
        $select = $this->_connection->select()
            ->from(Mage::getSingleton('core/resource')->getTableName('catalog_category_entity'), ['entity_id', 'parent_id'])
            ->where('level > 0');

        $this->_categoryIds = [];
        foreach ($this->_connection->fetchAll($select) as $category) {
            $this->_categoryIds[(int) $category['entity_id']] = (int) $category['parent_id'];
        }
        return $this;
    }

    /**
     * A file needs category_id or parent_id for the ID columns, or _root for the key columns.
     */
    #[\Override]
    public function validateData()
    {
        $columns = $this->_getSource()->getColNames();
        if (!array_intersect([self::COL_CATEGORY_ID, self::COL_PARENT_ID, self::COL_ROOT], $columns)) {
            Mage::throwException(
                Mage::helper('importexport')->__('Can not find required columns: %s', 'category_id, parent_id or _root'),
            );
        }
        return parent::validateData();
    }

    #[\Override]
    protected function _importData(): bool
    {
        while ($bunch = $this->_dataSourceModel->getNextBunch()) {
            foreach ($bunch as $rowNum => $rowData) {
                if (!$this->validateRow($rowData, $rowNum)) {
                    continue;
                }
                if (Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()) {
                    $this->_deleteRow($rowData);
                } else {
                    $this->_saveRow($rowData);
                }
            }
        }
        return true;
    }

    public function getRowScope(array $rowData): int
    {
        if ($this->_value($rowData, self::COL_STORE) !== '') {
            return self::SCOPE_STORE;
        }
        if ($this->_getRowKey($rowData) !== null
            || $this->_value($rowData, self::COL_CATEGORY_ID) !== ''
            || $this->_value($rowData, self::COL_PARENT_ID) !== ''
        ) {
            return self::SCOPE_DEFAULT;
        }
        return self::SCOPE_NULL;
    }

    /**
     * @param int $rowNum
     */
    #[\Override]
    public function validateRow(array $rowData, $rowNum): bool
    {
        if (!isset($this->_validatedRows[$rowNum])) {
            $this->_validatedRows[$rowNum] = Mage_ImportExport_Model_Import::BEHAVIOR_DELETE == $this->getBehavior()
                ? $this->_validateDeleteRow($rowData, (int) $rowNum)
                : $this->_validateSaveRow($rowData, (int) $rowNum);
            if ($this->_validatedRows[$rowNum] && $this->getRowScope($rowData) === self::SCOPE_DEFAULT) {
                $this->_processedEntitiesCount++;
            }
        }
        return $this->_validatedRows[$rowNum];
    }

    protected function _validateSaveRow(array $rowData, int $rowNum): bool
    {
        $rowScope = $this->getRowScope($rowData);
        if ($rowScope === self::SCOPE_NULL) {
            $this->addRowError(self::ERROR_CATEGORY_PATH_EMPTY, $rowNum);
            return false;
        }

        $key = $this->_getRowKey($rowData);
        $label = $this->_getRowLabel($rowData);
        $categoryId = $this->_findCategoryId($rowData);

        if ($rowScope === self::SCOPE_STORE) {
            $storeCode = $this->_value($rowData, self::COL_STORE);
            if (!isset($this->_storeCodeToId[$storeCode])) {
                $this->addRowError(self::ERROR_INVALID_STORE, $rowNum, $storeCode);
                return false;
            }
            if ($categoryId === null && ($key === null || !$this->_isCreatedBefore($key, $rowNum))) {
                $this->addRowError(self::ERROR_CATEGORY_NOT_FOUND, $rowNum, $label);
                return false;
            }
            return $this->_validateValues($rowData, $rowNum);
        }

        if ($categoryId !== null) {
            $parentId = $this->_value($rowData, self::COL_PARENT_ID);
            if ($parentId !== '' && $this->_isSameDatabase($rowData, $categoryId)) {
                if (!$this->_isParentId((int) $parentId)) {
                    $this->addRowError(self::ERROR_PARENT_NOT_FOUND, $rowNum, $label);
                    return false;
                }
                if ($this->_isInTree((int) $parentId, $categoryId)) {
                    $this->addRowError(self::ERROR_CIRCULAR_REFERENCE, $rowNum, $label);
                    return false;
                }
            }
            return $this->_validateValues($rowData, $rowNum);
        }

        if ($key === null) {
            if ($this->_value($rowData, self::COL_CATEGORY_ID) !== '') {
                $this->addRowError(self::ERROR_CATEGORY_ID_INVALID, $rowNum, $this->_value($rowData, self::COL_CATEGORY_ID));
                return false;
            }
            if (!$this->_isParentId((int) $this->_value($rowData, self::COL_PARENT_ID))) {
                $this->addRowError(self::ERROR_PARENT_NOT_FOUND, $rowNum, $label);
                return false;
            }
        } else {
            [$rootName, $path] = $key;
            $keyString = $rootName . '/' . $path;
            if (isset($this->_newKeys[$keyString]) && $this->_newKeys[$keyString] !== $rowNum) {
                $this->addRowError(self::ERROR_DUPLICATE_PATH, $rowNum, $label);
                return false;
            }
            if ($path !== '') {
                $segments = explode('/', $path);
                $urlKey = array_pop($segments);
                if (in_array('', $segments, true) || Mage::getModel('catalog/category')->formatUrlKey($urlKey) !== $urlKey) {
                    $this->addRowError(self::ERROR_CATEGORY_PATH_INVALID, $rowNum, $label);
                    return false;
                }
                $columnUrlKey = $this->_value($rowData, 'url_key');
                if ($columnUrlKey !== '' && $columnUrlKey !== $urlKey) {
                    $this->addRowError(self::ERROR_URL_KEY_MISMATCH, $rowNum, $label);
                    return false;
                }
                $parentKey = [$rootName, implode('/', $segments)];
                if ($this->_keyMap->getId(...$parentKey) === null && !$this->_isCreatedBefore($parentKey, $rowNum)) {
                    $this->addRowError(self::ERROR_PARENT_NOT_FOUND, $rowNum, $label);
                    return false;
                }
            }
            $this->_newKeys[$keyString] = $rowNum;
        }

        // A new root category takes its name from _root, and the next run finds it by that name
        $name = $this->_value($rowData, 'name');
        $isNewRoot = $key !== null && $key[1] === '';
        if ($isNewRoot ? $name !== '' && $name !== $key[0] : $name === '') {
            $this->addRowError(self::ERROR_INVALID_NAME, $rowNum, $label);
            return false;
        }
        return $this->_validateValues($rowData, $rowNum);
    }

    protected function _validateDeleteRow(array $rowData, int $rowNum): bool
    {
        if ($this->_getRowKey($rowData) === null && $this->_value($rowData, self::COL_CATEGORY_ID) === '') {
            $this->addRowError(self::ERROR_DELETE_IDENTIFIER_MISSING, $rowNum);
            return false;
        }
        $categoryId = $this->_findCategoryId($rowData);
        // A root category belongs to a store, so an import never deletes it
        if ($categoryId === null || $this->_categoryIds[$categoryId] === Mage_Catalog_Model_Category::TREE_ROOT_ID) {
            $this->addRowError(self::ERROR_CATEGORY_ID_INVALID, $rowNum, $this->_getRowLabel($rowData));
            return false;
        }
        return true;
    }

    /**
     * Check the values that the save converts: flags, numbers, the display mode, the landing page and the image.
     */
    protected function _validateValues(array $rowData, int $rowNum): bool
    {
        foreach (self::FLAG_ATTRIBUTES as $attrCode) {
            $value = strtolower($this->_value($rowData, $attrCode));
            if ($value !== '' && !in_array($value, ['0', '1', 'true', 'false', 'yes', 'no'], true)) {
                $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum, $attrCode);
                return false;
            }
        }
        foreach (['position', 'sort_order'] as $attrCode) {
            $value = $this->_value($rowData, $attrCode);
            if ($value !== '' && !is_numeric($value)) {
                $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum, $attrCode);
                return false;
            }
        }
        $displayMode = $this->_value($rowData, 'display_mode');
        $displayModes = [
            Mage_Catalog_Model_Category::DM_PRODUCT, Mage_Catalog_Model_Category::DM_PAGE, Mage_Catalog_Model_Category::DM_MIXED,
            'Products only', 'Static block only', 'Static block and products',
        ];
        if ($displayMode !== '' && !in_array($displayMode, $displayModes, true)) {
            $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum, 'display_mode');
            return false;
        }
        $landingPage = $this->_value($rowData, 'landing_page');
        if ($landingPage !== '' && !isset($this->_getLandingPageIds()[$landingPage])) {
            $this->addRowError(self::ERROR_INVALID_ATTRIBUTE_TYPE, $rowNum, 'landing_page');
            return false;
        }
        $image = $this->_value($rowData, 'image');
        if ($image !== '' && isset($this->_parameters[self::PARAM_MEDIA_DIR])) {
            $source = $this->_getImageSource($image);
            $extension = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            if (!is_file($source) || !in_array($extension, \Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS, true)) {
                $this->addRowError(self::ERROR_IMAGE_NOT_FOUND, $rowNum, $image);
                return false;
            }
        }
        return true;
    }

    protected function _saveRow(array $rowData): void
    {
        $key = $this->_getRowKey($rowData);
        $categoryId = $this->_findCategoryId($rowData);
        $storeId = $this->getRowScope($rowData) === self::SCOPE_STORE
            ? $this->_storeCodeToId[$this->_value($rowData, self::COL_STORE)]
            : Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;

        if ($categoryId !== null) {
            $parentId = (int) $this->_value($rowData, self::COL_PARENT_ID);
            if ($storeId === Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID && $parentId > 0
                && $this->_isSameDatabase($rowData, $categoryId) && $parentId !== $this->_categoryIds[$categoryId]
            ) {
                Mage::getModel('catalog/category')->load($categoryId)->move($parentId, null);
                $this->_categoryIds[$categoryId] = $parentId;
            }
            $category = Mage::getModel('catalog/category')->setStoreId($storeId)->load($categoryId);
            $this->_applyValues($category, $rowData);
            $category->save();
            return;
        }

        $category = $this->_createCategory($rowData, $key);
        $this->_applyValues($category, $rowData);
        $category->save();
        $position = $this->_value($rowData, 'position');
        if ($position !== '' && (int) $category->getPosition() !== (int) $position) {
            $category->setPosition((int) $position)->save();
        }

        $categoryId = (int) $category->getId();
        $this->_categoryIds[$categoryId] = (int) $category->getParentId();
        if ($key !== null) {
            $this->_keyMap->add($categoryId, ...$key);
        }
    }

    /**
     * A new category with the defaults of the admin form, below the parent of the row.
     *
     * @param array{0: string, 1: string}|null $key
     */
    protected function _createCategory(array $rowData, ?array $key): Mage_Catalog_Model_Category
    {
        $category = Mage::getModel('catalog/category')->setStoreId(Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID);
        $category->setAttributeSetId($category->getDefaultAttributeSetId())
            ->setIsActive(1)
            ->setIncludeInMenu(1)
            ->setIsAnchor(1)
            ->setDisplayMode(Mage_Catalog_Model_Category::DM_PRODUCT);

        if ($key === null) {
            $parentId = (int) $this->_value($rowData, self::COL_PARENT_ID);
        } elseif ($key[1] === '') {
            $parentId = Mage_Catalog_Model_Category::TREE_ROOT_ID;
            $category->setName($key[0]);
        } else {
            $segments = explode('/', $key[1]);
            $category->setUrlKey(array_pop($segments));
            $parentId = (int) $this->_keyMap->getId($key[0], implode('/', $segments));
        }

        $parentPath = $parentId === Mage_Catalog_Model_Category::TREE_ROOT_ID
            ? (string) $parentId
            : (string) Mage::getModel('catalog/category')->load($parentId)->getPath();
        return $category->setPath($parentPath);
    }

    protected function _applyValues(Mage_Catalog_Model_Category $category, array $rowData): void
    {
        foreach ($rowData as $attrCode => $value) {
            $value = (string) $value;
            if ($value === '' || in_array($attrCode, self::SKIPPED_COLUMNS, true)) {
                continue;
            }
            if ($attrCode === 'position') {
                $category->setPosition((int) $value);
                continue;
            }
            $attribute = Mage::getSingleton('eav/config')->getAttribute('catalog_category', $attrCode);
            if (!$attribute || !$attribute->getId() || $attribute->getBackendType() === 'static') {
                continue;
            }
            $category->setData($attrCode, $this->_convertValue($attrCode, $value));
        }
    }

    protected function _convertValue(string $attrCode, string $value): mixed
    {
        if (in_array($attrCode, self::FLAG_ATTRIBUTES, true)) {
            return in_array(strtolower($value), ['1', 'true', 'yes'], true) ? 1 : 0;
        }
        if ($attrCode === 'landing_page') {
            return $this->_getLandingPageIds()[$value];
        }
        if ($attrCode === 'image') {
            return $this->_importImage($value);
        }
        return $this->_convertLabelToValue($attrCode, $value);
    }

    protected function _deleteRow(array $rowData): void
    {
        $categoryId = $this->_findCategoryId($rowData);
        $category = Mage::getModel('catalog/category')->load($categoryId);
        if ($category->getId()) {
            $category->delete();
        }
    }

    /**
     * The root name and the path of the row, or null when the row has no _root.
     *
     * @return array{0: string, 1: string}|null
     */
    protected function _getRowKey(array $rowData): ?array
    {
        $rootName = $this->_value($rowData, self::COL_ROOT);
        if ($rootName === '') {
            return null;
        }
        return [$rootName, trim($this->_value($rowData, self::COL_PATH), '/')];
    }

    /**
     * The ID of the category that the row names, or null when that category does not exist yet.
     */
    protected function _findCategoryId(array $rowData): ?int
    {
        $key = $this->_getRowKey($rowData);
        if ($key !== null) {
            return $this->_keyMap->getId(...$key);
        }
        $categoryId = (int) $this->_value($rowData, self::COL_CATEGORY_ID);
        return isset($this->_categoryIds[$categoryId]) ? $categoryId : null;
    }

    /**
     * Whether category_id names the category that the row changes, so parent_id comes from this database too.
     */
    protected function _isSameDatabase(array $rowData, int $categoryId): bool
    {
        return (int) $this->_value($rowData, self::COL_CATEGORY_ID) === $categoryId
            || $this->_getRowKey($rowData) === null;
    }

    /**
     * Whether a category can take children: the tree root, or a category below it.
     */
    protected function _isParentId(int $categoryId): bool
    {
        return $categoryId === Mage_Catalog_Model_Category::TREE_ROOT_ID || isset($this->_categoryIds[$categoryId]);
    }

    /**
     * Whether the category is the top category or one of its descendants.
     */
    protected function _isInTree(int $categoryId, int $topCategoryId): bool
    {
        while (isset($this->_categoryIds[$categoryId])) {
            if ($categoryId === $topCategoryId) {
                return true;
            }
            $categoryId = $this->_categoryIds[$categoryId];
        }
        return false;
    }

    /**
     * @param array{0: string, 1: string} $key
     */
    protected function _isCreatedBefore(array $key, int $rowNum): bool
    {
        $createdAt = $this->_newKeys[$key[0] . '/' . $key[1]] ?? null;
        return $createdAt !== null && $createdAt < $rowNum;
    }

    protected function _getRowLabel(array $rowData): string
    {
        $key = $this->_getRowKey($rowData);
        if ($key !== null) {
            return rtrim($key[0] . '/' . $key[1], '/');
        }
        return $this->_value($rowData, self::COL_CATEGORY_ID) ?: $this->_value($rowData, self::COL_PARENT_ID);
    }

    /**
     * The trimmed cell, or an empty string. The bunch table stores an empty cell as null.
     */
    protected function _value(array $rowData, string $column): string
    {
        return trim((string) ($rowData[$column] ?? ''));
    }

    /**
     * @return array<string, int>
     */
    protected function _getLandingPageIds(): array
    {
        if ($this->_landingPageIds === null) {
            $this->_landingPageIds = [];
            $blocks = Mage::getResourceModel('cms/block_collection');
            foreach ($blocks as $block) {
                $this->_landingPageIds[(string) $block->getTitle()] = (int) $block->getId();
            }
            foreach ($blocks as $block) {
                $this->_landingPageIds[(string) $block->getId()] = (int) $block->getId();
                $this->_landingPageIds[(string) $block->getIdentifier()] = (int) $block->getId();
            }
        }
        return $this->_landingPageIds;
    }

    protected function _getImageSource(string $image): string
    {
        return rtrim((string) $this->_parameters[self::PARAM_MEDIA_DIR], '/') . '/' . ltrim($image, '/');
    }

    /**
     * Copy the image from the media folder of the import into catalog/category on the media mount, and return its file name.
     */
    protected function _importImage(string $image): string
    {
        if (!isset($this->_parameters[self::PARAM_MEDIA_DIR])) {
            return $image;
        }
        $source = $this->_getImageSource($image);
        $path = Mage_Catalog_Model_Category_Attribute_Backend_Image::STORAGE_PATH . '/' . basename($source);
        $mount = Mage::getStorage('media');
        $root = $mount->localRoot();
        // A copy of a file onto itself truncates it
        if ($root === null || realpath($source) !== realpath($root . '/' . $path)) {
            $mount->copyFromLocalFile($source, $path);
        }
        return basename($source);
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
            } catch (Exception) {
                // If we can't get options, return the original value
            }
        }

        return $value;
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
