<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Export_Entity_Category extends Mage_ImportExport_Model_Export_Entity_Abstract
{
    /**
     * Permanent column names.
     *
     * Names that begins with underscore is not an attribute. This name convention is for
     * to avoid interference with same attribute name.
     */
    public const COL_STORE = '_store';
    public const COL_CATEGORY_PATH = 'category_path';
    public const COL_CATEGORY_ID = 'category_id';

    /**
     * Categories ID to URL key path hash.
     *
     * @var array
     */
    protected $_categoryPaths = [];

    /**
     * Attributes that should use value index instead of label
     *
     * @var array
     */
    protected $_indexValueAttributes = [];

    /**
     * Disabled attributes for export.
     *
     * @var array
     */
    protected $_disabledAttrs = [
        'all_children',
        'children',
        'children_count',
        'level',
        'path',
        'path_in_store',
        'position',
        'url_path',  // Also disable url_path since we have category_path
    ];

    /**
     * Permanent attributes.
     *
     * @var array
     */
    protected $_permanentAttributes = [self::COL_CATEGORY_PATH];

    /**
     * Constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->_initStores()
             ->_initWebsites()
             ->_initBooleanAttributes()
             ->_initAttrValues();

        $this->_categoryPaths = [];
    }

    /**
     * Initialize category paths hash.
     *
     * @return $this
     */
    protected function _initCategoryPaths(): self
    {
        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = Mage::getResourceModel('catalog/category_collection');
        $collection->addAttributeToFilter('level', ['gt' => 0])
                   ->load();

        // Cache for individually loaded categories to avoid repeated loads
        $loadedCategories = [];

        foreach ($collection as $category) {
            /** @var Mage_Catalog_Model_Category $category */
            $categoryId = $category->getId();
            $pathIds = explode('/', $category->getPath());
            $pathSegments = [];

            foreach ($pathIds as $pathId) {
                if ($pathId == Mage_Catalog_Model_Category::TREE_ROOT_ID) {
                    continue; // Skip root category ID
                }

                // Use individual loading for EAV attributes - collections don't work reliably
                if (!isset($loadedCategories[$pathId])) {
                    $loadedCategories[$pathId] = Mage::getModel('catalog/category')->load($pathId);
                }

                $pathCategory = $loadedCategories[$pathId];
                if ($pathCategory->getId()) {
                    $urlKey = $pathCategory->getUrlKey();

                    // Generate URL key from name if missing
                    if (!$urlKey && $pathCategory->getName()) {
                        $urlKey = $this->_formatUrlKey($pathCategory->getName());
                    }

                    if ($urlKey) {
                        $pathSegments[] = $urlKey;
                    }
                }
            }

            if (!empty($pathSegments)) {
                $this->_categoryPaths[$categoryId] = implode('/', $pathSegments);
            }
        }

        return $this;
    }

    /**
     * Format string as URL key.
     */
    protected function _formatUrlKey(string $name): string
    {
        return strtolower(preg_replace('/[^a-zA-Z0-9-_]/', '-', $name));
    }

    /**
     * Initialize boolean attributes that should export values instead of labels
     *
     * @return $this
     */
    protected function _initBooleanAttributes(): self
    {
        foreach ($this->getAttributeCollection() as $attribute) {
            // Check if attribute uses boolean source model or has only Yes/No options
            if ($attribute->usesSource()) {
                $source = $attribute->getSource();
                $options = [];

                try {
                    foreach ($source->getAllOptions(false) as $option) {
                        $innerOptions = is_array($option['value']) ? $option['value'] : [$option];
                        foreach ($innerOptions as $innerOption) {
                            if ($innerOption['value'] !== '' && $innerOption['value'] !== null) {
                                $options[$innerOption['value']] = $innerOption['label'];
                            }
                        }
                    }

                    // If we have exactly 2 options with 0/1 values and Yes/No labels, treat as boolean
                    if (count($options) === 2 &&
                        ((isset($options['0'], $options['1']) &&
                          (($options['0'] === 'No' && $options['1'] === 'Yes') ||
                           ($options['0'] === 'Yes' && $options['1'] === 'No'))) ||
                         (isset($options[0], $options[1]) &&
                          (($options[0] === 'No' && $options[1] === 'Yes') ||
                           ($options[0] === 'Yes' && $options[1] === 'No'))))) {
                        $this->_indexValueAttributes[] = $attribute->getAttributeCode();
                    }
                } catch (Exception $e) {
                    // Skip attributes that can't provide options
                    continue;
                }
            }
        }

        return $this;
    }

    /**
     * Export process.
     *
     * @return string
     */
    #[\Override]
    public function export()
    {
        // Prepare headers
        $writer = $this->getWriter();
        $validAttrCodes = $this->_getExportAttrCodes();
        $writer->setHeaderCols(array_merge(
            [self::COL_CATEGORY_PATH, self::COL_STORE],
            $validAttrCodes,
        ));

        // Export data
        $this->_exportCategories();

        return $writer->getContents();
    }

    /**
     * Export data and return temporary file.
     *
     * @return array
     */
    #[\Override]
    public function exportFile()
    {
        $writer = $this->getWriter();
        $validAttrCodes = $this->_getExportAttrCodes();

        $writer->setHeaderCols(array_merge(
            [self::COL_CATEGORY_ID, self::COL_STORE, self::COL_CATEGORY_PATH],
            $validAttrCodes,
        ));

        $this->_exportCategories();

        $writeAdapter = $this->getWriter();
        if ($writeAdapter instanceof Mage_ImportExport_Model_Export_Adapter_Abstract) {
            return [
                'rows'  => $this->_processedEntitiesCount,
                'value' => $writeAdapter->getContents(),
                'type'  => 'string',
            ];
        }

        return [];
    }

    /**
     * Export categories data.
     */
    protected function _exportCategories(): void
    {
        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        $collection = $this->_prepareEntityCollection(
            Mage::getResourceModel('catalog/category_collection'),
        );

        $collection->addAttributeToFilter('level', ['gt' => 0]) // Exclude root category
                   ->setOrder('level', 'ASC')
                   ->setOrder('position', 'ASC');

        $validAttrCodes = $this->_getExportAttrCodes();
        $writer = $this->getWriter();
        $defaultStoreId = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;

        foreach ($collection as $category) {
            /** @var Mage_Catalog_Model_Category $category */
            $categoryId = $category->getId();

            if (!isset($this->_categoryPaths[$categoryId])) {
                // Debug: Log when categories are skipped due to missing paths
                if (defined('MAHO_TEST_DEBUG')) {
                    Mage::log("Skipping category ID $categoryId - no path found", Mage::LOG_DEBUG);
                }
                continue; // Skip categories without valid path
            }

            $categoryPath = $this->_categoryPaths[$categoryId];

            // Export default store data first
            $dataRow = [
                self::COL_CATEGORY_ID => (string) $categoryId,
                self::COL_STORE => '',
                self::COL_CATEGORY_PATH => $categoryPath,
            ];

            // Load default store data
            $defaultCategory = Mage::getModel('catalog/category');
            $defaultCategory->setStoreId($defaultStoreId);
            $defaultCategory->load($categoryId);

            // Add attribute values for default store - read directly from database to avoid fallback issues
            foreach ($validAttrCodes as $attrCode) {
                $attrValue = $this->_getAttributeValueDirectly((int) $categoryId, $attrCode, $defaultStoreId);

                if ($attrValue !== null && $attrValue !== '') {
                    if (isset($this->_attributeValues[$attrCode][$attrValue])) {
                        $attrValue = $this->_attributeValues[$attrCode][$attrValue];
                    }
                    $dataRow[$attrCode] = $attrValue;
                } else {
                    $dataRow[$attrCode] = '';
                }
            }

            $writer->writeRow($dataRow);

            // Export store-specific data if different from default
            foreach ($this->_storeIdToCode as $storeId => $storeCode) {
                if ($storeId == $defaultStoreId) {
                    continue; // Already exported default
                }

                // Load store-specific category data
                $storeCategory = Mage::getModel('catalog/category');
                $storeCategory->setStoreId($storeId);
                $storeCategory->load($categoryId);

                $storeDataRow = [
                    self::COL_CATEGORY_ID => (string) $categoryId,
                    self::COL_STORE => $storeCode,
                    self::COL_CATEGORY_PATH => '',  // Empty for store rows
                ];

                $hasStoreSpecificData = false;

                // Add attribute values for this store - read directly from database
                foreach ($validAttrCodes as $attrCode) {
                    $storeValue = $this->_getAttributeValueDirectly((int) $categoryId, $attrCode, $storeId);
                    $defaultValue = $this->_getAttributeValueDirectly((int) $categoryId, $attrCode, $defaultStoreId);

                    // Only include if different from default
                    if ($storeValue !== null && $storeValue !== '' && $storeValue != $defaultValue) {
                        if (isset($this->_attributeValues[$attrCode][$storeValue])) {
                            $storeValue = $this->_attributeValues[$attrCode][$storeValue];
                        }
                        $storeDataRow[$attrCode] = $storeValue;
                        $hasStoreSpecificData = true;
                    } else {
                        $storeDataRow[$attrCode] = '';
                    }
                }

                // Only write store row if it has store-specific data
                if ($hasStoreSpecificData) {
                    $writer->writeRow($storeDataRow);
                }
            }

            $this->_processedEntitiesCount++;
        }
    }

    /**
     * Entity attributes collection getter.
     *
     * @return Mage_Catalog_Model_Resource_Category_Attribute_Collection
     */
    #[\Override]
    public function getAttributeCollection()
    {
        return Mage::getResourceModel('catalog/category_attribute_collection');
    }

    /**
     * EAV entity type code getter.
     */
    #[\Override]
    public function getEntityTypeCode(): string
    {
        return 'catalog_category';
    }

    /**
     * Refresh category paths after import or changes.
     *
     * @return $this
     */
    public function refreshCategoryPaths(): self
    {
        $this->_categoryPaths = [];
        $this->_initCategoryPaths();
        return $this;
    }

    /**
     * Get attribute value directly from database to avoid fallback issues.
     */
    protected function _getAttributeValueDirectly(int $categoryId, string $attrCode, int $storeId): ?string
    {
        // Find the attribute in our collection
        $attribute = null;
        foreach ($this->getAttributeCollection() as $attr) {
            if ($attr->getAttributeCode() === $attrCode) {
                $attribute = $attr;
                break;
            }
        }

        if (!$attribute) {
            return null;
        }

        $connection = Mage::getSingleton('core/resource')->getConnection('core_read');
        $attributeId = $attribute->getAttributeId();

        // Determine the backend table based on attribute backend type
        $backendType = $attribute->getBackendType();
        $tableName = 'catalog_category_entity_' . $backendType;
        $table = Mage::getSingleton('core/resource')->getTableName($tableName);

        // Query the specific store value
        $select = $connection->select()
            ->from($table, 'value')
            ->where('entity_id = ?', $categoryId)
            ->where('attribute_id = ?', $attributeId)
            ->where('store_id = ?', $storeId)
            ->limit(1);

        $value = $connection->fetchOne($select);

        // If no value found for this store and it's not the admin store, try admin store as fallback
        if (($value === false || $value === null) && $storeId != 0) {
            $select = $connection->select()
                ->from($table, 'value')
                ->where('entity_id = ?', $categoryId)
                ->where('attribute_id = ?', $attributeId)
                ->where('store_id = ?', 0)
                ->limit(1);

            $value = $connection->fetchOne($select);
        }

        return $value !== false ? (string) $value : null;
    }
}
