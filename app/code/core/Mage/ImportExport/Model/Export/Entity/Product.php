<?php

/**
 * Maho
 *
 * @package    Mage_ImportExport
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2019-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

class Mage_ImportExport_Model_Export_Entity_Product extends Mage_ImportExport_Model_Export_Entity_Abstract
{
    public const CONFIG_KEY_PRODUCT_TYPES = 'global/importexport/export_product_types';

    /**
     * Value that means all entities (e.g. websites, groups etc.)
     */
    public const VALUE_ALL = 'all';

    /**
     * Permanent column names.
     *
     * Names that begins with underscore is not an attribute. This name convention is for
     * to avoid interference with same attribute name.
     */
    public const COL_STORE    = '_store';
    public const COL_ATTR_SET = '_attribute_set';
    public const COL_TYPE     = '_type';
    public const COL_CATEGORY = '_category';
    public const COL_ROOT_CATEGORY = '_root_category';
    public const COL_SKU      = 'sku';

    /**
     * Pairs of attribute set ID-to-name.
     *
     * @var array
     */
    protected $_attrSetIdToName = [];

    /**
     * Categories ID to text-path hash.
     *
     * @var array
     */
    protected $_categories = [];

    /**
     * Root category names for each category
     *
     * @var array
     */
    protected $_rootCategories = [];

    /**
     * Attributes with index (not label) value.
     *
     * @var array
     */
    protected $_indexValueAttributes = [
        'status',
        'tax_class_id',
        'visibility',
        'gift_message_available',
        'custom_design',
    ];

    /**
     * Permanent entity columns.
     *
     * @var array
     */
    protected $_permanentAttributes = [self::COL_SKU];

    /**
     * Array of supported product types as keys with appropriate model object as value.
     *
     * @var array
     */
    protected $_productTypeModels = [];

    /**
     * Attribute types
     *
     * @var array
     */
    protected $_attributeTypes = [];

    /**
     * Attribute scopes
     *
     * @var array
     */
    protected $_attributeScopes = [];

    public function __construct()
    {
        parent::__construct();

        $this->_initTypeModels()
                ->_initAttributes()
                ->_initStores()
                ->_initAttributeSets()
                ->_initWebsites()
                ->_initCategories();
    }

    /**
     * Initialize attribute sets code-to-id pairs.
     *
     * @return $this
     */
    protected function _initAttributeSets()
    {
        $productTypeId = Mage::getModel('catalog/product')->getResource()->getTypeId();
        foreach (Mage::getResourceModel('eav/entity_attribute_set_collection')
                ->setEntityTypeFilter($productTypeId) as $attributeSet
        ) {
            $this->_attrSetIdToName[$attributeSet->getId()] = $attributeSet->getAttributeSetName();
        }
        return $this;
    }

    /**
     * Initialize categories ID to text-path hash.
     *
     * @return $this
     */
    protected function _initCategories()
    {
        $collection = Mage::getResourceModel('catalog/category_collection')->addNameToResult();
        /** @var Mage_Catalog_Model_Resource_Category_Collection $collection */
        foreach ($collection as $category) {
            $structure = preg_split('#/+#', $category->getPath());
            $pathSize  = count($structure);
            if ($pathSize > 1) {
                $path = [];
                for ($i = 1; $i < $pathSize; $i++) {
                    $path[] = $collection->getItemById($structure[$i])->getName();
                }
                $this->_rootCategories[$category->getId()] = array_shift($path);
                if ($pathSize > 2) {
                    $this->_categories[$category->getId()] = implode('/', $path);
                }
            }
        }
        return $this;
    }

    /**
     * Initialize product type models.
     *
     * @throws Exception
     * @return $this
     */
    protected function _initTypeModels()
    {
        $config = Mage::getConfig()->getNode(self::CONFIG_KEY_PRODUCT_TYPES)->asCanonicalArray();
        foreach ($config as $type => $typeModel) {
            if (!($model = Mage::getModel($typeModel, [$this, $type]))) {
                Mage::throwException("Entity type model '{$typeModel}' is not found");
            }
            if (!$model instanceof Mage_ImportExport_Model_Export_Entity_Product_Type_Abstract) {
                Mage::throwException(
                    Mage::helper('importexport')->__('Entity type model must be an instance of Mage_ImportExport_Model_Export_Entity_Product_Type_Abstract'),
                );
            }
            if ($model->isSuitable()) {
                $this->_productTypeModels[$type] = $model;
                $this->_disabledAttrs            = array_merge($this->_disabledAttrs, $model->getDisabledAttrs());
                $this->_indexValueAttributes     = array_merge(
                    $this->_indexValueAttributes,
                    $model->getIndexValueAttributes(),
                );
            }
        }
        if (!$this->_productTypeModels) {
            Mage::throwException(Mage::helper('importexport')->__('There are no product types available for export'));
        }
        $this->_disabledAttrs = array_unique($this->_disabledAttrs);

        return $this;
    }

    /**
     * Initialize website values.
     *
     * @return $this
     */
    #[\Override]
    protected function _initWebsites()
    {
        foreach (Mage::app()->getWebsites() as $website) {
            $this->_websiteIdToCode[$website->getId()] = $website->getCode();
        }
        return $this;
    }

    /**
     * Prepare products tier prices
     *
     * @return array
     */
    protected function _prepareTierPrices(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from($resource->getTableName('catalog/product_attribute_tier_price'))
            ->where('entity_id IN(?)', $productIds);

        $rowTierPrices = [];
        $stmt = $this->_connection->query($select);
        while ($tierRow = $stmt->fetch()) {
            $rowTierPrices[$tierRow['entity_id']][] = [
                '_tier_price_customer_group' => $tierRow['all_groups']
                                                ? self::VALUE_ALL : $tierRow['customer_group_id'],
                '_tier_price_website'        => $tierRow['website_id'] == 0
                                                ? self::VALUE_ALL
                                                : $this->_websiteIdToCode[$tierRow['website_id']],
                '_tier_price_qty'            => $tierRow['qty'],
                '_tier_price_price'          => $tierRow['value'],
            ];
        }

        return $rowTierPrices;
    }

    /**
     * Prepare products group prices
     *
     * @return array
     */
    protected function _prepareGroupPrices(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from($resource->getTableName('catalog/product_attribute_group_price'))
            ->where('entity_id IN(?)', $productIds);

        $rowGroupPrices = [];
        $statement = $this->_connection->query($select);
        while ($groupRow = $statement->fetch()) {
            $rowGroupPrices[$groupRow['entity_id']][] = [
                '_group_price_customer_group' => $groupRow['all_groups']
                    ? self::VALUE_ALL
                    : $groupRow['customer_group_id'],
                '_group_price_website'        => ($groupRow['website_id'] == 0)
                    ? self::VALUE_ALL
                    : $this->_websiteIdToCode[$groupRow['website_id']],
                '_group_price_price'          => $groupRow['value'],
            ];
        }

        return $rowGroupPrices;
    }

    /**
     * Prepare products media gallery
     *
     * @return array
     */
    protected function _prepareMediaGallery(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
                ->from(
                    ['mg' => $resource->getTableName('catalog/product_attribute_media_gallery')],
                    [
                        'mg.entity_id', 'mg.attribute_id', 'filename' => 'mg.value', 'mgv.label',
                        'mgv.position', 'mgv.disabled',
                    ],
                )
                ->joinLeft(
                    ['mgv' => $resource->getTableName('catalog/product_attribute_media_gallery_value')],
                    '(mg.value_id = mgv.value_id AND mgv.store_id = 0)',
                    [],
                )
                ->where('entity_id IN(?)', $productIds);

        $rowMediaGallery = [];
        $stmt = $this->_connection->query($select);
        while ($mediaRow = $stmt->fetch()) {
            $rowMediaGallery[$mediaRow['entity_id']][] = [
                '_media_attribute_id'   => $mediaRow['attribute_id'],
                '_media_image'          => $mediaRow['filename'],
                '_media_lable'          => $mediaRow['label'],
                '_media_position'       => $mediaRow['position'],
                '_media_is_disabled'    => $mediaRow['disabled'],
            ];
        }

        return $rowMediaGallery;
    }

    /**
     * Prepare catalog inventory
     *
     * @return array
     */
    protected function _prepareCatalogInventory(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        $select = $this->_connection->select()
            ->from(Mage::getResourceModel('cataloginventory/stock_item')->getMainTable())
            ->where('product_id IN (?)', $productIds);

        $stmt = $this->_connection->query($select);
        $stockItemRows = [];
        while ($stockItemRow = $stmt->fetch()) {
            $productId = $stockItemRow['product_id'];
            unset(
                $stockItemRow['item_id'],
                $stockItemRow['product_id'],
                $stockItemRow['low_stock_date'],
                $stockItemRow['stock_id'],
                $stockItemRow['stock_status_changed_automatically'],
            );
            $stockItemRows[$productId] = $stockItemRow;
        }
        return $stockItemRows;
    }

    /**
     * Prepare product links
     *
     * @return array
     */
    protected function _prepareLinks(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        $resource = Mage::getSingleton('core/resource');
        $adapter = $this->_connection;
        $select = $adapter->select()
            ->from(
                ['cpl' => $resource->getTableName('catalog/product_link')],
                [
                    'cpl.product_id', 'cpe.sku', 'cpl.link_type_id',
                    'position' => 'cplai.value', 'default_qty' => 'cplad.value',
                ],
            )
            ->joinLeft(
                ['cpe' => $resource->getTableName('catalog/product')],
                '(cpe.entity_id = cpl.linked_product_id)',
                [],
            )
            ->joinLeft(
                ['cpla' => $resource->getTableName('catalog/product_link_attribute')],
                $adapter->quoteInto(
                    '(cpla.link_type_id = cpl.link_type_id AND cpla.product_link_attribute_code = ?)',
                    'position',
                ),
                [],
            )
            ->joinLeft(
                ['cplaq' => $resource->getTableName('catalog/product_link_attribute')],
                $adapter->quoteInto(
                    '(cplaq.link_type_id = cpl.link_type_id AND cplaq.product_link_attribute_code = ?)',
                    'qty',
                ),
                [],
            )
            ->joinLeft(
                ['cplai' => $resource->getTableName('catalog/product_link_attribute_int')],
                '(cplai.link_id = cpl.link_id AND cplai.product_link_attribute_id = cpla.product_link_attribute_id)',
                [],
            )
            ->joinLeft(
                ['cplad' => $resource->getTableName('catalog/product_link_attribute_decimal')],
                '(cplad.link_id = cpl.link_id AND cplad.product_link_attribute_id = cplaq.product_link_attribute_id)',
                [],
            )
            ->where('cpl.link_type_id IN (?)', [
                Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED,
                Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL,
                Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL,
                Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED,
            ])
            ->where('cpl.product_id IN (?)', $productIds);

        $stmt = $adapter->query($select);
        $linksRows = [];
        while ($linksRow = $stmt->fetch()) {
            $linksRows[$linksRow['product_id']][$linksRow['link_type_id']][] = [
                'sku'         => $linksRow['sku'],
                'position'    => $linksRow['position'],
                'default_qty' => $linksRow['default_qty'],
            ];
        }

        return $linksRows;
    }

    /**
     * Prepare configurable product data
     *
     * @deprecated since 1.6.1.0
     * @see Mage_Catalog_Model_Resource_Product_Type_Configurable::getConfigurableOptions()
     * @return array
     */
    protected function _prepareConfigurableProductData(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from(
                ['cpsl' => $resource->getTableName('catalog/product_super_link')],
                ['cpsl.parent_id', 'cpe.sku'],
            )
            ->joinLeft(
                ['cpe' => $resource->getTableName('catalog/product')],
                '(cpe.entity_id = cpsl.product_id)',
                [],
            )
            ->where('parent_id IN (?)', $productIds);
        $stmt = $this->_connection->query($select);
        $configurableData = [];
        while ($cfgLinkRow = $stmt->fetch()) {
            $configurableData[$cfgLinkRow['parent_id']][] = ['_super_products_sku' => $cfgLinkRow['sku']];
        }

        return $configurableData;
    }

    /**
     * Prepare configurable product price
     *
     * @deprecated since 1.6.1.0
     * @see Mage_Catalog_Model_Resource_Product_Type_Configurable::getConfigurableOptions()
     * @return array
     */
    protected function _prepareConfigurableProductPrice(array $productIds)
    {
        if (empty($productIds)) {
            return [];
        }
        $resource = Mage::getSingleton('core/resource');
        $select = $this->_connection->select()
            ->from(
                ['cpsa' => $resource->getTableName('catalog/product_super_attribute')],
                [
                    'cpsa.product_id', 'ea.attribute_code', 'eaov.value', 'cpsap.pricing_value', 'cpsap.is_percent',
                ],
            )
            ->joinLeft(
                ['cpsap' => $resource->getTableName('catalog/product_super_attribute_pricing')],
                '(cpsap.product_super_attribute_id = cpsa.product_super_attribute_id)',
                [],
            )
            ->joinLeft(
                ['ea' => $resource->getTableName('eav/attribute')],
                '(ea.attribute_id = cpsa.attribute_id)',
                [],
            )
            ->joinLeft(
                ['eaov' => $resource->getTableName('eav/attribute_option_value')],
                '(eaov.option_id = cpsap.value_index AND store_id = 0)',
                [],
            )
            ->where('cpsa.product_id IN (?)', $productIds);
        $configurablePrice = [];
        $stmt = $this->_connection->query($select);
        while ($priceRow = $stmt->fetch()) {
            $configurablePrice[$priceRow['product_id']][] = [
                '_super_attribute_code'       => $priceRow['attribute_code'],
                '_super_attribute_option'     => $priceRow['value'],
                '_super_attribute_price_corr' => $priceRow['pricing_value'] . ($priceRow['is_percent'] ? '%' : ''),
            ];
        }
        return $configurablePrice;
    }

    /**
     * Update data row with information about categories. Return true, if data row was updated
     *
     * @param array $dataRow
     * @param array $rowCategories
     * @param int $productId
     * @return bool
     */
    protected function _updateDataWithCategoryColumns(&$dataRow, &$rowCategories, $productId)
    {
        if (!isset($rowCategories[$productId])) {
            return false;
        }

        $categoryId = array_shift($rowCategories[$productId]);
        if (isset($this->_rootCategories[$categoryId])) {
            $dataRow[self::COL_ROOT_CATEGORY] = $this->_rootCategories[$categoryId];
        }
        if (isset($this->_categories[$categoryId])) {
            $dataRow[self::COL_CATEGORY] = $this->_categories[$categoryId];
        }

        return true;
    }

    /**
     * Export process and return contents of temporary file.
     *
     * @deprecated after ver 1.9.2.4 use $this->exportFile() instead
     *
     * @return string
     */
    #[\Override]
    public function export()
    {
        $this->_prepareExport();

        return $this->getWriter()->getContents();
    }

    /**
     * Export process and return temporary file through array.
     *
     * This method will return following array:
     *
     * array(
     *     'rows'  => count of written rows,
     *     'value' => path to created file
     * )
     *
     * @return array
     */
    #[\Override]
    public function exportFile()
    {
        $this->_prepareExport();

        $writer = $this->getWriter();

        return [
            'rows'  => $writer->getRowsCount(),
            'value' => $writer->getDestination(),
        ];
    }

    /**
     * Prepare data for export.
     *
     * @return string
     */
    protected function _prepareExport()
    {
        //Execution time may be very long
        set_time_limit(0);

        $validAttrCodes  = $this->_getExportAttrCodes();
        $writer          = $this->getWriter();
        $defaultStoreId  = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;

        $memoryLimit = trim(ini_get('memory_limit'));
        $lastMemoryLimitLetter = strtolower($memoryLimit[strlen($memoryLimit) - 1]);
        $memoryLimit = (int) filter_var($memoryLimit, FILTER_SANITIZE_NUMBER_INT);
        switch ($lastMemoryLimitLetter) {
            case 'g':
                $memoryLimit *= 1024;
                // no break
            case 'm':
                $memoryLimit *= 1024;
                // no break
            case 'k':
                $memoryLimit *= 1024;
                break;
            default:
                // minimum memory required by Magento
                $memoryLimit = 250000000;
        }

        // Tested one product to have up to such size
        $memoryPerProduct = 100000;
        // Decrease memory limit to have supply
        $memoryUsagePercent = 0.8;
        // Minimum Products limit
        $minProductsLimit = 500;

        $limitProducts = (int) (($memoryLimit  * $memoryUsagePercent - memory_get_usage(true)) / $memoryPerProduct);
        if ($limitProducts < $minProductsLimit) {
            $limitProducts = $minProductsLimit;
        }
        $offsetProducts = 0;

        while (true) {
            ++$offsetProducts;

            $dataRows        = [];
            $rowCategories   = [];
            $rowWebsites     = [];
            $rowTierPrices   = [];
            $rowGroupPrices  = [];
            $rowMultiselects = [];
            $mediaGalery     = [];

            // prepare multi-store values and system columns values
            foreach ($this->_storeIdToCode as $storeId => &$storeCode) { // go through all stores
                /** @var Mage_Catalog_Model_Resource_Product_Collection $collection */
                $collection = $this->_prepareEntityCollection(Mage::getResourceModel('catalog/product_collection'));
                $collection
                    ->setStoreId($storeId)
                    ->setPage($offsetProducts, $limitProducts);
                if ($collection->getCurPage() < $offsetProducts) {
                    break;
                }
                $collection->load();

                if ($collection->count() == 0) {
                    break;
                }

                if ($defaultStoreId == $storeId) {
                    $collection->addCategoryIds()->addWebsiteNamesToResult();

                    // tier and group price data getting only once
                    $rowTierPrices = $this->_prepareTierPrices($collection->getAllIds());
                    $rowGroupPrices = $this->_prepareGroupPrices($collection->getAllIds());

                    // getting media gallery data
                    $mediaGalery = $this->_prepareMediaGallery($collection->getAllIds());
                }
                foreach ($collection as $itemId => $item) { // go through all products
                    $rowIsEmpty = true; // row is empty by default

                    foreach ($validAttrCodes as &$attrCode) { // go through all valid attribute codes
                        $attrValue = $item->getData($attrCode);

                        if (!empty($this->_attributeValues[$attrCode])) {
                            if ($this->_attributeTypes[$attrCode] == 'multiselect') {
                                $attrValue = explode(',', $attrValue);
                                $attrValue = array_intersect_key(
                                    $this->_attributeValues[$attrCode],
                                    array_flip($attrValue),
                                );

                                switch ($this->_attributeScopes[$attrCode]) {
                                    case Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE:
                                        if (isset($rowMultiselects[$itemId][0][$attrCode])
                                            && $attrValue == $rowMultiselects[$itemId][0][$attrCode]
                                        ) {
                                            $attrValue = null;
                                        }
                                        break;

                                    case Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL:
                                        if ($storeId != $defaultStoreId) {
                                            $attrValue = null;
                                        }
                                        break;

                                    case Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE:
                                        $websiteId      = $this->_storeIdToWebsiteId[$storeId];
                                        $websiteStoreId = array_search($websiteId, $this->_storeIdToWebsiteId);
                                        if ((isset($rowMultiselects[$itemId][$websiteStoreId][$attrCode])
                                            && $attrValue == $rowMultiselects[$itemId][$websiteStoreId][$attrCode])
                                            || $attrValue == $rowMultiselects[$itemId][0][$attrCode]
                                        ) {
                                            $attrValue = null;
                                        }
                                        break;

                                    default:
                                        break;
                                }

                                if ($attrValue) {
                                    $rowMultiselects[$itemId][$storeId][$attrCode] = $attrValue;
                                    $rowIsEmpty = false;
                                }
                            } elseif (isset($this->_attributeValues[$attrCode][$attrValue])) {
                                $attrValue = $this->_attributeValues[$attrCode][$attrValue];
                            } else {
                                $attrValue = null;
                            }
                        }
                        // do not save value same as default or not existent
                        if ($storeId != $defaultStoreId
                            && isset($dataRows[$itemId][$defaultStoreId][$attrCode])
                            && $dataRows[$itemId][$defaultStoreId][$attrCode] == $attrValue
                        ) {
                            $attrValue = null;
                        }
                        if (is_scalar($attrValue)) {
                            $dataRows[$itemId][$storeId][$attrCode] = $attrValue;
                            $rowIsEmpty = false; // mark row as not empty
                        }
                    }
                    if ($rowIsEmpty) { // remove empty rows
                        unset($dataRows[$itemId][$storeId]);
                    } else {
                        $attrSetId = $item->getAttributeSetId();
                        $dataRows[$itemId][$storeId][self::COL_STORE]    = $storeCode;
                        $dataRows[$itemId][$storeId][self::COL_ATTR_SET] = $this->_attrSetIdToName[$attrSetId];
                        $dataRows[$itemId][$storeId][self::COL_TYPE]     = $item->getTypeId();

                        if ($defaultStoreId == $storeId) {
                            $rowWebsites[$itemId]   = $item->getWebsites();
                            $rowCategories[$itemId] = $item->getCategoryIds();
                        }
                    }
                    $item = null;
                }
                $collection->clear();
            }

            if (isset($collection) && $collection->getCurPage() < $offsetProducts) {
                break;
            }

            // remove unused categories
            $allCategoriesIds = array_merge(array_keys($this->_categories), array_keys($this->_rootCategories));
            foreach ($rowCategories as &$categories) {
                $categories = array_intersect($categories, $allCategoriesIds);
            }

            // prepare catalog inventory information
            $productIds = array_keys($dataRows);
            $stockItemRows = $this->_prepareCatalogInventory($productIds);

            // prepare links information
            $linksRows = $this->_prepareLinks($productIds);
            $linkIdColPrefix = [
                Mage_Catalog_Model_Product_Link::LINK_TYPE_RELATED   => '_links_related_',
                Mage_Catalog_Model_Product_Link::LINK_TYPE_UPSELL    => '_links_upsell_',
                Mage_Catalog_Model_Product_Link::LINK_TYPE_CROSSSELL => '_links_crosssell_',
                Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED   => '_associated_',
            ];
            $configurableProductsCollection = Mage::getResourceModel('catalog/product_collection');
            $configurableProductsCollection->addAttributeToFilter(
                'entity_id',
                [
                    'in'    => $productIds,
                ],
            )->addAttributeToFilter(
                'type_id',
                [
                    'eq'    => Mage_Catalog_Model_Product_Type_Configurable::TYPE_CODE,
                ],
            );
            $configurableData = [];
            while ($product = $configurableProductsCollection->fetchItem()) {
                $productAttributesOptions = $product->getTypeInstance(true)->getConfigurableOptions($product);

                foreach ($productAttributesOptions as $productAttributeOption) {
                    $configurableData[$product->getId()] = [];
                    foreach ($productAttributeOption as $optionValues) {
                        $priceType = $optionValues['pricing_is_percent'] ? '%' : '';
                        $configurableData[$product->getId()][] = [
                            '_super_products_sku'           => $optionValues['sku'],
                            '_super_attribute_code'         => $optionValues['attribute_code'],
                            '_super_attribute_option'       => $optionValues['option_title'],
                            '_super_attribute_price_corr'   => $optionValues['pricing_value'] . $priceType,
                        ];
                    }
                }
            }

            // prepare custom options information
            $customOptionsData    = [];
            $customOptionsDataPre = [];
            $customOptCols        = [
                '_custom_option_store', '_custom_option_type', '_custom_option_title', '_custom_option_is_required',
                '_custom_option_price', '_custom_option_sku', '_custom_option_max_characters',
                '_custom_option_sort_order', '_custom_option_row_title', '_custom_option_row_price',
                '_custom_option_row_sku', '_custom_option_row_sort',
            ];

            foreach (array_keys($this->_storeIdToCode) as &$storeId) {
                $skip = false;
                $options = Mage::getResourceModel('catalog/product_option_collection')
                    ->reset()
                    ->addTitleToResult($storeId)
                    ->addPriceToResult($storeId)
                    ->addProductToFilter($productIds)
                    ->addValuesToResult($storeId);

                foreach ($options as $option) {
                    $row = [];
                    $productId = $option['product_id'];
                    $optionId  = $option['option_id'];
                    $customOptions = $customOptionsDataPre[$productId][$optionId] ?? [];

                    if ($defaultStoreId == $storeId) {
                        $row['_custom_option_type']           = $option['type'];
                        $row['_custom_option_title']          = $option['title'];
                        $row['_custom_option_is_required']    = $option['is_require'];
                        $row['_custom_option_price'] = $option['price']
                            . ($option['price_type'] == 'percent' ? '%' : '');
                        $row['_custom_option_sku']            = $option['sku'];
                        $row['_custom_option_max_characters'] = $option['max_characters'];
                        $row['_custom_option_sort_order']     = $option['sort_order'];

                        // remember default title for later comparisons
                        $defaultTitles[$option['option_id']] = $option['title'];
                    } elseif ($option['title'] != $customOptions[0]['_custom_option_title']) {
                        $row['_custom_option_title'] = $option['title'];
                    }
                    $values = $option->getValues();
                    if ($values) {
                        $firstValue = reset($values);
                        $priceType  = $firstValue['price_type'] == 'percent' ? '%' : '';

                        if ($defaultStoreId == $storeId) {
                            $row['_custom_option_row_title'] = $firstValue['title'];
                            $row['_custom_option_row_price'] = $firstValue['price'] . $priceType;
                            $row['_custom_option_row_sku']   = $firstValue['sku'];
                            $row['_custom_option_row_sort']  = $firstValue['sort_order'];

                            $defaultValueTitles[$firstValue['option_type_id']] = $firstValue['title'];
                        } elseif ($firstValue['title'] != $customOptions[0]['_custom_option_row_title']) {
                            $row['_custom_option_row_title'] = $firstValue['title'];
                        }
                    }
                    if ($row) {
                        if ($defaultStoreId != $storeId) {
                            $row['_custom_option_store'] = $this->_storeIdToCode[$storeId];
                        }
                        $customOptionsDataPre[$productId][$optionId][] = $row;
                        $skip = true;
                    }
                    foreach ($values as $value) {
                        if ($skip) {
                            $skip = false;
                            continue;
                        }

                        $row = [];
                        $valuePriceType = $value['price_type'] == 'percent' ? '%' : '';

                        if ($defaultStoreId == $storeId) {
                            $row['_custom_option_row_title'] = $value['title'];
                            $row['_custom_option_row_price'] = $value['price'] . $valuePriceType;
                            $row['_custom_option_row_sku']   = $value['sku'];
                            $row['_custom_option_row_sort']  = $value['sort_order'];
                        } else {
                            $row['_custom_option_row_title'] = $value['title'];
                        }
                        if ($row) {
                            if ($defaultStoreId != $storeId) {
                                $row['_custom_option_store'] = $this->_storeIdToCode[$storeId];
                            }
                            $customOptionsDataPre[$option['product_id']][$option['option_id']][] = $row;
                        }
                    }
                    $option = null;
                }
                $options = null;
            }
            foreach ($customOptionsDataPre as $productId => &$optionsData) {
                $customOptionsData[$productId] = [];

                foreach ($optionsData as $optionId => &$optionRows) {
                    $customOptionsData[$productId] = array_merge($customOptionsData[$productId], $optionRows);
                }
                unset($optionRows, $optionsData);
            }
            unset($customOptionsDataPre);

            if ($offsetProducts == 1) {
                // create export file
                $headerCols = array_merge(
                    [
                        self::COL_SKU, self::COL_STORE, self::COL_ATTR_SET,
                        self::COL_TYPE, self::COL_CATEGORY, self::COL_ROOT_CATEGORY, '_product_websites',
                    ],
                    $validAttrCodes,
                    reset($stockItemRows) ? array_keys(end($stockItemRows)) : [],
                    [],
                    [
                        '_links_related_sku', '_links_related_position', '_links_crosssell_sku',
                        '_links_crosssell_position', '_links_upsell_sku', '_links_upsell_position',
                        '_associated_sku', '_associated_default_qty', '_associated_position',
                    ],
                    ['_tier_price_website', '_tier_price_customer_group', '_tier_price_qty', '_tier_price_price'],
                    ['_group_price_website', '_group_price_customer_group', '_group_price_price'],
                    [
                        '_media_attribute_id',
                        '_media_image',
                        '_media_lable',
                        '_media_position',
                        '_media_is_disabled',
                    ],
                    $customOptCols,
                    [
                        '_super_products_sku',
                        '_super_attribute_code',
                        '_super_attribute_option',
                        '_super_attribute_price_corr',
                    ],
                );

                $writer->setHeaderCols($headerCols);
            }

            foreach ($dataRows as $productId => &$productData) {
                foreach ($productData as $storeId => &$dataRow) {
                    if ($defaultStoreId != $storeId) {
                        $dataRow[self::COL_SKU]      = null;
                        $dataRow[self::COL_ATTR_SET] = null;
                        $dataRow[self::COL_TYPE]     = null;
                    } else {
                        $dataRow[self::COL_STORE] = null;
                        if (!empty($stockItemRows[$productId]) && is_array($stockItemRows[$productId])) {
                            $dataRow += $stockItemRows[$productId];
                        }
                    }

                    $this->_updateDataWithCategoryColumns($dataRow, $rowCategories, $productId);
                    if ($rowWebsites[$productId]) {
                        $dataRow['_product_websites'] = $this->_websiteIdToCode[array_shift($rowWebsites[$productId])];
                    }
                    if (!empty($rowTierPrices[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($rowTierPrices[$productId]));
                    }
                    if (!empty($rowGroupPrices[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($rowGroupPrices[$productId]));
                    }
                    if (!empty($mediaGalery[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($mediaGalery[$productId]));
                    }
                    foreach ($linkIdColPrefix as $linkId => &$colPrefix) {
                        if (!empty($linksRows[$productId][$linkId])) {
                            $linkData = array_shift($linksRows[$productId][$linkId]);
                            $dataRow[$colPrefix . 'position'] = $linkData['position'];
                            $dataRow[$colPrefix . 'sku'] = $linkData['sku'];

                            if ($linkData['default_qty'] !== null) {
                                $dataRow[$colPrefix . 'default_qty'] = $linkData['default_qty'];
                            }
                        }
                    }
                    if (!empty($customOptionsData[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($customOptionsData[$productId]));
                    }
                    if (!empty($configurableData[$productId])) {
                        $dataRow = array_merge($dataRow, array_shift($configurableData[$productId]));
                    }
                    if (!empty($rowMultiselects[$productId][$storeId])) {
                        foreach (array_keys($rowMultiselects[$productId][$storeId]) as $attrKey) {
                            if (isset($rowMultiselects[$productId][$storeId][$attrKey])) {
                                $dataRow[$attrKey] = array_shift($rowMultiselects[$productId][$storeId][$attrKey]);
                            }
                        }
                    }

                    $writer->writeRow($dataRow);
                    // calculate largest links block
                    $largestLinks = 0;

                    if (isset($linksRows[$productId])) {
                        $linksRowsKeys = array_keys($linksRows[$productId]);
                        foreach ($linksRowsKeys as $linksRowsKey) {
                            $largestLinks = max($largestLinks, count($linksRows[$productId][$linksRowsKey]));
                        }
                    }
                    $additionalRowsCount = max(
                        count($rowCategories[$productId]),
                        count($rowWebsites[$productId]),
                        $largestLinks,
                    );
                    if (!empty($rowTierPrices[$productId])) {
                        $additionalRowsCount = max($additionalRowsCount, count($rowTierPrices[$productId]));
                    }
                    if (!empty($rowGroupPrices[$productId])) {
                        $additionalRowsCount = max($additionalRowsCount, count($rowGroupPrices[$productId]));
                    }
                    if (!empty($mediaGalery[$productId])) {
                        $additionalRowsCount = max($additionalRowsCount, count($mediaGalery[$productId]));
                    }
                    if (!empty($customOptionsData[$productId])) {
                        $additionalRowsCount = max($additionalRowsCount, count($customOptionsData[$productId]));
                    }
                    if (!empty($configurableData[$productId])) {
                        $additionalRowsCount = max($additionalRowsCount, count($configurableData[$productId]));
                    }
                    if (!empty($rowMultiselects[$productId][$storeId])) {
                        foreach ($rowMultiselects[$productId][$storeId] as $attributes) {
                            $additionalRowsCount = max($additionalRowsCount, count($attributes));
                        }
                    }
                    if ($additionalRowsCount) {
                        for ($i = 0; $i < $additionalRowsCount; $i++) {
                            $dataRow = [];

                            $this->_updateDataWithCategoryColumns($dataRow, $rowCategories, $productId);
                            if ($rowWebsites[$productId]) {
                                $dataRow['_product_websites'] = $this
                                    ->_websiteIdToCode[array_shift($rowWebsites[$productId])];
                            }
                            if (!empty($rowTierPrices[$productId])) {
                                $dataRow = array_merge($dataRow, array_shift($rowTierPrices[$productId]));
                            }
                            if (!empty($rowGroupPrices[$productId])) {
                                $dataRow = array_merge($dataRow, array_shift($rowGroupPrices[$productId]));
                            }
                            if (!empty($mediaGalery[$productId])) {
                                $dataRow = array_merge($dataRow, array_shift($mediaGalery[$productId]));
                            }
                            foreach ($linkIdColPrefix as $linkId => &$colPrefix) {
                                if (!empty($linksRows[$productId][$linkId])) {
                                    $linkData = array_shift($linksRows[$productId][$linkId]);
                                    $dataRow[$colPrefix . 'position'] = $linkData['position'];
                                    $dataRow[$colPrefix . 'sku']      = $linkData['sku'];

                                    if ($linkData['default_qty'] !== null) {
                                        $dataRow[$colPrefix . 'default_qty'] = $linkData['default_qty'];
                                    }
                                }
                            }
                            if (!empty($customOptionsData[$productId])) {
                                $dataRow = array_merge($dataRow, array_shift($customOptionsData[$productId]));
                            }
                            if (!empty($configurableData[$productId])) {
                                $dataRow = array_merge($dataRow, array_shift($configurableData[$productId]));
                            }
                            if (!empty($rowMultiselects[$productId][$storeId])) {
                                foreach (array_keys($rowMultiselects[$productId][$storeId]) as $attrKey) {
                                    if (isset($rowMultiselects[$productId][$storeId][$attrKey])) {
                                        $dataRow[$attrKey] = array_shift($rowMultiselects[$productId][$storeId][$attrKey]);
                                    }
                                }
                            }
                            $writer->writeRow($dataRow);
                        }
                    }
                }
            }
        }
        return $writer->getContents();
    }

    /**
     * Clean up already loaded attribute collection.
     *
     * @return Mage_Eav_Model_Resource_Entity_Attribute_Collection
     */
    #[\Override]
    public function filterAttributeCollection(Mage_Eav_Model_Resource_Entity_Attribute_Collection $collection)
    {
        $validTypes = array_keys($this->_productTypeModels);

        foreach (parent::filterAttributeCollection($collection) as $attribute) {
            $attrApplyTo = $attribute->getApplyTo();
            $attrApplyTo = $attrApplyTo ? array_intersect($attrApplyTo, $validTypes) : $validTypes;

            if ($attrApplyTo) {
                foreach ($attrApplyTo as $productType) { // override attributes by its product type model
                    if ($this->_productTypeModels[$productType]->overrideAttribute($attribute)) {
                        break;
                    }
                }
            } else { // remove attributes of not-supported product types
                $collection->removeItemByKey($attribute->getId());
            }
        }
        return $collection;
    }

    /**
     * Entity attributes collection getter.
     *
     * @return Mage_Catalog_Model_Resource_Product_Attribute_Collection
     */
    #[\Override]
    public function getAttributeCollection()
    {
        return Mage::getResourceModel('catalog/product_attribute_collection');
    }

    /**
     * EAV entity type code getter.
     *
     * @return string
     */
    #[\Override]
    public function getEntityTypeCode()
    {
        return 'catalog_product';
    }

    /**
     * Initialize attribute option values and types.
     *
     * @return $this
     */
    protected function _initAttributes()
    {
        foreach ($this->getAttributeCollection() as $attribute) {
            $this->_attributeValues[$attribute->getAttributeCode()] = $this->getAttributeOptions($attribute);
            $this->_attributeTypes[$attribute->getAttributeCode()] =
                Mage_ImportExport_Model_Import::getAttributeType($attribute);
            $this->_attributeScopes[$attribute->getAttributeCode()] = $attribute->getIsGlobal();
        }
        return $this;
    }
}
