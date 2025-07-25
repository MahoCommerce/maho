<?php

/**
 * Maho
 *
 * @package    Mage_Catalog
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2017-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2025 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

abstract class Mage_Catalog_Model_Product_Type_Abstract
{
    /**
     * Cache key for Editable Products
     */
    protected string $cacheKeyEditableAttributes = '_cache_editable_attributes';

    /**
     * Product model instance, if not used as a singleton
     *
     * @var Mage_Catalog_Model_Product
     */
    protected $_product;

    /**
     * Product type instance id
     *
     * @var string
     */
    protected $_typeId;

    /**
     * Is a composite product type
     *
     * @var bool
     */
    protected $_isComposite = false;

    /**
     * Is a configurable product type
     *
     * @var bool
     */
    protected $_canConfigure = false;

    /**
     * Whether product quantity is fractional number or not
     *
     * @var bool
     */
    protected $_canUseQtyDecimals  = true;

    /**
     * File queue array
     *
     * @var array
     */
    protected $_fileQueue       = [];

    public const CALCULATE_CHILD = 0;
    public const CALCULATE_PARENT = 1;

    /**
     * values for shipment type (invoice etc)
     *
     */
    public const SHIPMENT_SEPARATELY = 1;
    public const SHIPMENT_TOGETHER = 0;

    /**
     * Process modes
     *
     * Full validation - all required options must be set, whole configuration
     * must be valid
     */
    public const PROCESS_MODE_FULL = 'full';

    /**
     * Process modes
     *
     * Lite validation - only received options are validated
     */
    public const PROCESS_MODE_LITE = 'lite';

    /**
     * Item options prefix
     */
    public const OPTION_PREFIX = 'option_';

    /**
     * Specify type instance product, if not used as a singleton
     *
     * @param   Mage_Catalog_Model_Product $product
     * @return  Mage_Catalog_Model_Product_Type_Abstract
     */
    public function setProduct($product)
    {
        $this->_product = $product;
        return $this;
    }

    /**
     * Specify type identifier
     *
     * @param   string $typeId
     * @return  Mage_Catalog_Model_Product_Type_Abstract
     */
    public function setTypeId($typeId)
    {
        $this->_typeId = $typeId;
        return $this;
    }

    /**
     * Retrieve catalog product object
     *
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Product
     */
    public function getProduct($product = null)
    {
        if (is_object($product)) {
            return $product;
        }
        return $this->_product;
    }

    /**
     * Return relation info about used products for specific type instance
     *
     * @return Varien_Object Object with information data
     */
    public function getRelationInfo()
    {
        return new Varien_Object();
    }

    /**
     * Retrieve Required children ids
     * Return grouped array, ex array(
     *   group => array(ids)
     * )
     *
     * @param int $parentId
     * @param bool $required
     * @return array
     */
    public function getChildrenIds($parentId, $required = true)
    {
        return [];
    }

    /**
     * Retrieve parent ids array by required child
     *
     * @param int|array $childId
     * @return array
     */
    public function getParentIdsByChild($childId)
    {
        return [];
    }

    /**
     * Get array of product set attributes
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getSetAttributes($product = null)
    {
        return $this->getProduct($product)->getResource()
            ->loadAllAttributes($this->getProduct($product))
            ->getSortedAttributes($this->getProduct($product)->getAttributeSetId());
    }

    /**
     * Compare attributes sorting
     *
     * @param Mage_Catalog_Model_Entity_Attribute $attribute1
     * @param Mage_Catalog_Model_Entity_Attribute $attribute2
     * @return int
     */
    public function attributesCompare($attribute1, $attribute2)
    {
        $sort1 =  ($attribute1->getGroupSortPath() * 1000) + ($attribute1->getSortPath() * 0.0001);
        $sort2 =  ($attribute2->getGroupSortPath() * 1000) + ($attribute2->getSortPath() * 0.0001);

        if ($sort1 > $sort2) {
            return 1;
        } elseif ($sort1 < $sort2) {
            return -1;
        }

        return 0;
    }

    /**
     * Retrieve product type attributes
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getEditableAttributes($product = null)
    {
        if (!$this->getProduct($product)->hasData($this->cacheKeyEditableAttributes)) {
            $editableAttributes = [];
            foreach ($this->getSetAttributes($product) as $attributeCode => $attribute) {
                if (!is_array($attribute->getApplyTo())
                    || count($attribute->getApplyTo()) == 0
                    || in_array($this->getProduct($product)->getTypeId(), $attribute->getApplyTo())
                ) {
                    $editableAttributes[$attributeCode] = $attribute;
                }
            }
            $this->getProduct($product)->setData($this->cacheKeyEditableAttributes, $editableAttributes);
        }
        return $this->getProduct($product)->getData($this->cacheKeyEditableAttributes);
    }

    /**
     * Retrieve product attribute by identifier
     *
     * @param int|string $attributeId
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Eav_Model_Entity_Attribute_Abstract|null
     */
    public function getAttributeById($attributeId, $product = null)
    {
        foreach ($this->getSetAttributes($product) as $attribute) {
            if ($attribute->getId() == $attributeId) {
                return $attribute;
            }
        }
        return null;
    }

    /**
     * Check is virtual product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return false
     */
    public function isVirtual($product = null)
    {
        return false;
    }

    /**
     * Check is product available for sale
     *
     * @param Mage_Catalog_Model_Product $product
     * @return bool|null
     */
    public function isSalable($product = null)
    {
        $salable = $this->getProduct($product)->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_ENABLED;
        if ($salable && $this->getProduct($product)->hasData('is_salable')) {
            $salable = $this->getProduct($product)->getData('is_salable');
        } elseif ($salable && $this->isComposite()) {
            return null;
        }

        return (bool) (int) $salable;
    }

    /**
     * Prepare product and its configuration to be added to some products list.
     * Perform standard preparation process and then prepare options belonging to specific product type.
     *
     * @param  Mage_Catalog_Model_Product $product
     * @param  string $processMode
     * @return array|string
     */
    protected function _prepareProduct(Varien_Object $buyRequest, $product, $processMode)
    {
        $product = $this->getProduct($product);
        /** @var Mage_Catalog_Model_Product $product */
        // try to add custom options
        try {
            $options = $this->_prepareOptions($buyRequest, $product, $processMode);
        } catch (Mage_Core_Exception $e) {
            return $e->getMessage();
        }

        if (is_string($options)) {
            return $options;
        }
        // try to found super product configuration
        // (if product was buying within grouped product)
        $superProductConfig = $buyRequest->getSuperProductConfig();
        if (!empty($superProductConfig['product_id'])
            && !empty($superProductConfig['product_type'])
        ) {
            $superProductId = (int) $superProductConfig['product_id'];
            if ($superProductId) {
                if (!$superProduct = Mage::registry('used_super_product_' . $superProductId)) {
                    $superProduct = Mage::getModel('catalog/product')->load($superProductId);
                    Mage::register('used_super_product_' . $superProductId, $superProduct);
                }
                if ($superProduct->getId()) {
                    $assocProductIds = $superProduct->getTypeInstance(true)->getAssociatedProductIds($superProduct);
                    if (in_array($product->getId(), $assocProductIds)) {
                        $productType = $superProductConfig['product_type'];
                        $product->addCustomOption('product_type', $productType, $superProduct);

                        $buyRequest->setData('super_product_config', [
                            'product_type' => $productType,
                            'product_id'   => $superProduct->getId(),
                        ]);
                    }
                }
            }
        }

        $product->prepareCustomOptions();
        $buyRequest->unsetData('_processing_params'); // One-time params only
        $product->addCustomOption('info_buyRequest', serialize($buyRequest->getData()));

        if ($options) {
            $optionIds = array_keys($options);
            $product->addCustomOption('option_ids', implode(',', $optionIds));
            foreach ($options as $optionId => $optionValue) {
                $product->addCustomOption(self::OPTION_PREFIX . $optionId, $optionValue);
            }
        }

        // set quantity in cart
        if ($this->_isStrictProcessMode($processMode)) {
            $product->setCartQty($buyRequest->getQty());
        }
        $product->setQty($buyRequest->getQty());

        return [$product];
    }

    /**
     * Process product configuration
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $processMode
     * @return array|string
     */
    public function processConfiguration(
        Varien_Object $buyRequest,
        $product = null,
        $processMode = self::PROCESS_MODE_LITE,
    ) {
        $_products = $this->_prepareProduct($buyRequest, $product, $processMode);

        $this->processFileQueue();

        return $_products;
    }

    /**
     * Initialize product(s) for add to cart process.
     * Advanced version of func to prepare product for cart - processMode can be specified there.
     *
     * @param Mage_Catalog_Model_Product $product
     * @param null|string $processMode
     * @return array|string
     */
    public function prepareForCartAdvanced(Varien_Object $buyRequest, $product = null, $processMode = null)
    {
        if (!$processMode) {
            $processMode = self::PROCESS_MODE_FULL;
        }
        $_products = $this->_prepareProduct($buyRequest, $product, $processMode);
        $this->processFileQueue();
        return $_products;
    }

    /**
     * Initialize product(s) for add to cart process
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array|string
     */
    public function prepareForCart(Varien_Object $buyRequest, $product = null)
    {
        return $this->prepareForCartAdvanced($buyRequest, $product, self::PROCESS_MODE_FULL);
    }

    /**
     * Process File Queue
     * @return Mage_Catalog_Model_Product_Type_Abstract
     */
    public function processFileQueue()
    {
        if (empty($this->_fileQueue)) {
            return $this;
        }

        foreach ($this->_fileQueue as &$queueOptions) {
            if (isset($queueOptions['operation']) && $operation = $queueOptions['operation']) {
                switch ($operation) {
                    case 'receive_uploaded_file':
                        $src = $queueOptions['src_name'] ?? '';
                        $dst = $queueOptions['dst_name'] ?? '';
                        /** @var Zend_File_Transfer_Adapter_Http $uploader */
                        $uploader = $queueOptions['uploader'] ?? null;

                        $path = dirname($dst);
                        $io = new Varien_Io_File();
                        if (!$io->isWriteable($path) && !$io->mkdir($path, 0777, true)) {
                            Mage::throwException(Mage::helper('catalog')->__("Cannot create writeable directory '%s'.", $path));
                        }

                        $uploader->setDestination($path);

                        if (empty($src) || empty($dst) || !$uploader->receive($src)) {
                            /**
                             * @todo: show invalid option
                             */
                            if (isset($queueOptions['option'])) {
                                $queueOptions['option']->setIsValid(false);
                            }
                            Mage::throwException(Mage::helper('catalog')->__('File upload failed'));
                        }
                        Mage::helper('core/file_storage_database')->saveFile($dst);
                        break;
                    case 'move_uploaded_file':
                        $src = $queueOptions['src_name'];
                        $dst = $queueOptions['dst_name'];
                        move_uploaded_file($src, $dst);
                        Mage::helper('core/file_storage_database')->saveFile($dst);
                        break;
                    default:
                        break;
                }
            }
            $queueOptions = null;
        }

        return $this;
    }

    /**
     * Add file to File Queue
     * @param array $queueOptions   Array of File Queue
     *                              (eg. ['operation'=>'move',
     *                                    'src_name'=>'filename',
     *                                    'dst_name'=>'filename2'])
     */
    public function addFileQueue($queueOptions)
    {
        $this->_fileQueue[] = $queueOptions;
    }

    /**
     * Check if current process mode is strict
     *
     * @param string $processMode
     * @return bool
     */
    protected function _isStrictProcessMode($processMode)
    {
        return $processMode == self::PROCESS_MODE_FULL;
    }

    /**
     * Retrieve message for specify option(s)
     *
     * @return string
     */
    public function getSpecifyOptionMessage()
    {
        return Mage::helper('catalog')->__('Please specify the product\'s required option(s).');
    }

    /**
     * Process custom defined options for product
     *
     * @param Mage_Catalog_Model_Product $product
     * @param string $processMode
     * @return array
     */
    protected function _prepareOptions(Varien_Object $buyRequest, $product, $processMode)
    {
        $transport = new stdClass();
        $transport->options = [];
        foreach ($this->getProduct($product)->getOptions() as $option) {
            /** @var Mage_Catalog_Model_Product_Option $option */
            $group = $option->groupFactory($option->getType())
                ->setOption($option)
                ->setProduct($this->getProduct($product))
                ->setRequest($buyRequest)
                ->setProcessMode($processMode)
                ->validateUserValue($buyRequest->getOptions());

            $preparedValue = $group->prepareForCart();
            if ($preparedValue !== null) {
                $transport->options[$option->getId()] = $preparedValue;
            }
        }

        $eventName = sprintf('catalog_product_type_prepare_%s_options', $processMode);
        Mage::dispatchEvent($eventName, [
            'transport'   => $transport,
            'buy_request' => $buyRequest,
            'product' => $product,
        ]);
        return $transport->options;
    }

    /**
     * Process product custom defined options for cart
     *
     * @deprecated after 1.4.2.0
     * @see _prepareOptions()
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    protected function _prepareOptionsForCart(Varien_Object $buyRequest, $product = null)
    {
        return $this->_prepareOptions($buyRequest, $product, self::PROCESS_MODE_FULL);
    }

    /**
     * Check if product can be bought
     *
     * @param  Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Product_Type_Abstract
     * @throws Mage_Core_Exception
     */
    public function checkProductBuyState($product = null)
    {
        if (!$this->getProduct($product)->getSkipCheckRequiredOption()) {
            foreach ($this->getProduct($product)->getOptions() as $option) {
                if ($option->getIsRequire()) {
                    $customOption = $this->getProduct($product)
                        ->getCustomOption(self::OPTION_PREFIX . $option->getId());
                    if (!$customOption || $customOption->getValue() === null || (string) $customOption->getValue() === '') {
                        $this->getProduct($product)->setSkipCheckRequiredOption(true);
                        Mage::throwException(
                            Mage::helper('catalog')->__('The product has required options'),
                        );
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Prepare additional options/information for order item which will be
     * created from this product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getOrderOptions($product = null)
    {
        $optionArr = [];
        if ($info = $this->getProduct($product)->getCustomOption('info_buyRequest')) {
            $optionArr['info_buyRequest'] = unserialize($info->getValue(), ['allowed_classes' => false]);
        }

        if ($optionIds = $this->getProduct($product)->getCustomOption('option_ids')) {
            foreach (explode(',', $optionIds->getValue()) as $optionId) {
                if ($option = $this->getProduct($product)->getOptionById($optionId)) {
                    $confItemOption = $this->getProduct($product)
                        ->getCustomOption(self::OPTION_PREFIX . $option->getId());

                    $group = $option->groupFactory($option->getType())
                        ->setOption($option)
                        ->setProduct($this->getProduct())
                        ->setConfigurationItemOption($confItemOption);

                    $optionArr['options'][] = [
                        'label' => $option->getTitle(),
                        'value' => $group->getFormattedOptionValue($confItemOption->getValue()),
                        'print_value' => $group->getPrintableOptionValue($confItemOption->getValue()),
                        'option_id' => $option->getId(),
                        'option_type' => $option->getType(),
                        'option_value' => $confItemOption->getValue(),
                        'custom_view' => $group->isCustomizedView(),
                    ];
                }
            }
        }

        if ($productTypeConfig = $this->getProduct($product)->getCustomOption('product_type')) {
            $optionArr['super_product_config'] = [
                'product_code'  => $productTypeConfig->getCode(),
                'product_type'  => $productTypeConfig->getValue(),
                'product_id'    => $productTypeConfig->getProductId(),
            ];
        }

        return $optionArr;
    }

    /**
     * Save type related data
     *
     * @param Mage_Catalog_Model_Product $product
     * @return $this
     */
    public function save($product = null)
    {
        return $this;
    }

    /**
     * Remove don't applicable attributes data
     *
     * @param Mage_Catalog_Model_Product $product
     */
    protected function _removeNotApplicableAttributes($product = null)
    {
        $product    = $this->getProduct($product);
        $eavConfig  = Mage::getSingleton('eav/config');
        $entityType = $product->getResource()->getEntityType();
        foreach ($eavConfig->getEntityAttributeCodes($entityType, $product) as $attributeCode) {
            $attribute = $eavConfig->getAttribute($entityType, $attributeCode);
            $applyTo   = $attribute->getApplyTo();
            if (is_array($applyTo) && count($applyTo) > 0 && !in_array($product->getTypeId(), $applyTo)) {
                $product->unsetData($attribute->getAttributeCode());
            }
        }
    }

    /**
     * Before save type related data
     *
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Product_Type_Abstract
     */
    public function beforeSave($product = null)
    {
        $this->_removeNotApplicableAttributes($product);
        $this->getProduct($product)->canAffectOptions(true);
        return $this;
    }

    /**
     * Check if product is composite (grouped, configurable, etc)
     *
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    public function isComposite($product = null)
    {
        return $this->_isComposite;
    }

    /**
     * Check if product is configurable
     *
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    public function canConfigure($product = null)
    {
        return $this->_canConfigure;
    }

    /**
     * Check if product qty is fractional number
     *
     * @return bool
     */
    public function canUseQtyDecimals()
    {
        return $this->_canUseQtyDecimals;
    }

    /**
     * Default action to get sku of product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return string
     */
    public function getSku($product = null)
    {
        $sku = $this->getProduct($product)->getData('sku');
        if ($this->getProduct($product)->getCustomOption('option_ids')) {
            $sku = $this->getOptionSku($product, $sku);
        }
        return $sku;
    }

    /**
     * Default action to get sku of product with option
     *
     * @param Mage_Catalog_Model_Product $product Product with Custom Options
     * @param string $sku Product SKU without option
     * @return string
     */
    public function getOptionSku($product = null, $sku = '')
    {
        $skuDelimiter = '-';
        if (empty($sku)) {
            $sku = $this->getProduct($product)->getData('sku');
        }
        if ($optionIds = $this->getProduct($product)->getCustomOption('option_ids')) {
            foreach (explode(',', $optionIds->getValue()) as $optionId) {
                if ($option = $this->getProduct($product)->getOptionById($optionId)) {
                    $confItemOption = $this->getProduct($product)->getCustomOption(self::OPTION_PREFIX . $optionId);

                    $group = $option->groupFactory($option->getType())
                        ->setOption($option)->setListener(new Varien_Object());

                    if ($optionSku = $group->getOptionSku($confItemOption->getValue(), $skuDelimiter)) {
                        $sku .= $skuDelimiter . $optionSku;
                    }

                    if ($group->getListener()->getHasError()) {
                        $this->getProduct($product)
                                ->setHasError(true)
                                ->setMessage(
                                    $group->getListener()->getMessage(),
                                );
                    }
                }
            }
        }
        return $sku;
    }
    /**
     * Default action to get weight of product
     *
     * @param Mage_Catalog_Model_Product $product
     * @return float
     */
    public function getWeight($product = null)
    {
        return $this->getProduct($product)->getData('weight');
    }

    /**
     * Return true if product has options
     *
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    public function hasOptions($product = null)
    {
        if ($this->getProduct($product)->getHasOptions()) {
            return true;
        }
        if ($this->getProduct($product)->isRecurring()) {
            return true;
        }
        return false;
    }

    /**
     * Method is needed for specific actions to change given configuration options values
     * according current product type logic
     * Example: the cataloginventory validation of decimal qty can change qty to int,
     * so need to change configuration item qty option value too.
     *
     * @param array $options
     * @param mixed $value
     * @param Mage_Catalog_Model_Product $product
     * @return $this
     */
    public function updateQtyOption($options, Varien_Object $option, $value, $product = null)
    {
        return $this;
    }

    /**
     * Check if product has required options
     *
     * @param Mage_Catalog_Model_Product $product
     * @return bool
     */
    public function hasRequiredOptions($product = null)
    {
        if ($this->getProduct($product)->getRequiredOptions()) {
            return true;
        }
        return false;
    }

    /**
     * Retrieve store filter for associated products
     *
     * @param Mage_Catalog_Model_Product|null $product
     * @return Mage_Core_Model_Store|int|null
     */
    public function getStoreFilter($product = null)
    {
        $cacheKey = '_cache_instance_store_filter';
        return $this->getProduct($product)->getData($cacheKey);
    }

    /**
     * Set store filter for associated products
     *
     * @param int|Mage_Core_Model_Store $store
     * @param Mage_Catalog_Model_Product|null $product
     * @return $this
     */
    public function setStoreFilter($store = null, $product = null)
    {
        $cacheKey = '_cache_instance_store_filter';
        $this->getProduct($product)->setData($cacheKey, $store);
        return $this;
    }

    /**
     * Allow for updates of children quantities
     * (applicable for complicated product types. As default returns false)
     *
     * @param Mage_Catalog_Model_Product|null $product
     * @return bool
     */
    public function getForceChildItemQtyChanges($product = null)
    {
        return false;
    }

    /**
     * Prepare Quote Item Quantity
     *
     * @param mixed $qty
     * @param Mage_Catalog_Model_Product|null $product
     * @return float
     */
    public function prepareQuoteItemQty($qty, $product = null)
    {
        return (float) $qty;
    }

    /**
     * Implementation of product specify logic of which product needs to be assigned to option.
     * For example if product which was added to option already removed from catalog.
     *
     * @param Mage_Catalog_Model_Product $optionProduct
     * @param Mage_Sales_Model_Quote_Item_Option $option
     * @param Mage_Catalog_Model_Product $product
     * @return Mage_Catalog_Model_Product_Type_Abstract
     */
    public function assignProductToOption($optionProduct, $option, $product = null)
    {
        $option->setProduct($optionProduct ?: $this->getProduct($product));
        return $this;
    }

    /**
     * Setting specified product type variables
     *
     * @param array $config
     * @return Mage_Catalog_Model_Product_Type_Abstract
     */
    public function setConfig($config)
    {
        if (isset($config['composite'])) {
            $this->_isComposite = (bool) $config['composite'];
        }

        if (isset($config['can_use_qty_decimals'])) {
            $this->_canUseQtyDecimals = (bool) $config['can_use_qty_decimals'];
        }

        return $this;
    }

    /**
     * Retrieve additional searchable data from type instance
     * Using based on product id and store_id data
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getSearchableData($product = null)
    {
        $product    = $this->getProduct($product);
        $searchData = [];
        if ($product->getHasOptions()) {
            $searchData = Mage::getSingleton('catalog/product_option')
                ->getSearchableData($product->getId(), $product->getStoreId());
        }

        return $searchData;
    }

    /**
     * Retrieve products divided into groups required to purchase
     * At least one product in each group has to be purchased
     *
     * @param Mage_Catalog_Model_Product $product
     * @return array
     */
    public function getProductsToPurchaseByReqGroups($product = null)
    {
        $product = $this->getProduct($product);
        if ($this->isComposite($product)) {
            return [];
        }
        return [[$product]];
    }

    /**
     * Prepare selected options for product
     *
     * @param  Mage_Catalog_Model_Product $product
     * @param  Varien_Object $buyRequest
     * @return array
     */
    public function processBuyRequest($product, $buyRequest)
    {
        return [];
    }

    /**
     * Check product's options configuration
     *
     * @param  Mage_Catalog_Model_Product $product
     * @param  Varien_Object $buyRequest
     * @return array
     */
    public function checkProductConfiguration($product, $buyRequest)
    {
        $errors = [];

        try {
            /**
             * cloning product because prepareForCart() method will modify it
             */
            $productForCheck = clone $product;
            $buyRequestForCheck = clone $buyRequest;
            $result = $this->prepareForCart($buyRequestForCheck, $productForCheck);

            if (is_string($result)) {
                $errors[] = $result;
            }
        } catch (Mage_Core_Exception $e) {
            $errors[] = $e->getMessages();
        } catch (Exception $e) {
            Mage::logException($e);
            $errors[] = Mage::helper('catalog')->__('There was an error while request processing.');
        }

        return $errors;
    }

    /**
     * Check if Minimum advertise price is enabled at least in one option
     *
     * @param Mage_Catalog_Model_Product $product
     * @param int $visibility
     * @return false
     */
    public function isMapEnabledInOptions($product, $visibility = null)
    {
        return false;
    }
}
