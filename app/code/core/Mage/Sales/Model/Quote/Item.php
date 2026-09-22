<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2019-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Sales
 */

/**
 * @method Mage_Sales_Model_Resource_Quote_Item _getResource()
 * @method Mage_Sales_Model_Resource_Quote_Item getResource()
 * @method Mage_Sales_Model_Resource_Quote_Item_Collection getCollection()
 *
 * @method $this unsHasConfigurationUnavailableError()
 */
class Mage_Sales_Model_Quote_Item extends Mage_Sales_Model_Quote_Item_Abstract
{
    /**
     * Prefix of model events names
     *
     * @var string
     */
    #[\Override]
    protected $_eventPrefix = 'sales_quote_item';

    /**
     * Parameter name in event
     *
     * In observe method you can use $observer->getEvent()->getObject() in this case
     *
     * @var string
     */
    #[\Override]
    protected $_eventObject = 'item';

    /**
     * Quote model object
     *
     * @var Mage_Sales_Model_Quote|null
     */
    protected $_quote;

    /**
     * Item options array
     *
     * @var array
     */
    protected $_options = [];

    /**
     * Item options by code cache
     *
     * @var array
     */
    #[\Override]
    protected $_optionsByCode = [];

    /**
     * Not Represent options
     *
     * @var array
     */
    protected $_notRepresentOptions = ['info_buyRequest'];

    /**
     * Flag stating that options were successfully saved
     */
    protected $_flagOptionsSaved = null;

    /**
     * Array of errors associated with this quote item
     *
     * @var Mage_Sales_Model_Status_List
     */
    protected $_errorInfos = null;

    #[\Override]
    protected function _construct()
    {
        $this->_init('sales/quote_item');
        $this->_errorInfos = Mage::getModel('sales/status_list');
    }

    /**
     * Init mapping array of short fields to
     * its full names
     *
     * @return $this
     */
    #[\Override]
    protected function _initOldFieldsMap()
    {
        return $this;
    }

    /**
     * Quote Item Before Save prepare data process
     *
     * @return $this
     */
    #[\Override]
    protected function _beforeSave()
    {
        parent::_beforeSave();
        $this->setIsVirtual($this->getProduct()->getIsVirtual());
        if ($this->getQuote()) {
            $this->setQuoteId($this->getQuote()->getId());
        }
        return $this;
    }

    /**
     * Declare quote model object
     *
     * @return  $this
     */
    public function setQuote(Mage_Sales_Model_Quote $quote)
    {
        $this->_quote = $quote;
        if ($this->getQuoteId() != $quote->getId()) {
            $this->setQuoteId($quote->getId());
        }
        return $this;
    }

    /**
     * Retrieve quote model object
     *
     * @return Mage_Sales_Model_Quote
     */
    #[\Override]
    public function getQuote()
    {
        $this->_quote ??= Mage::getModel('sales/quote')->load($this->getQuoteId());
        return $this->_quote;
    }

    /**
     * Prepare quantity
     *
     * @param float|int $qty
     * @return int|float
     */
    protected function _prepareQty($qty)
    {
        $qty = Mage::app()->getLocale()->getNumber($qty);
        $qty = ($qty > 0) ? $qty : 1;
        return $qty;
    }

    /**
     * Get Maho App instance
     *
     * @return Mage_Core_Model_App
     */
    protected function _getApp()
    {
        return Mage::app();
    }

    /**
     * Adding quantity to quote item
     *
     * @param float $qty
     * @return $this
     */
    public function addQty($qty)
    {
        $oldQty = $this->getQty();
        $qty = $this->_prepareQty($qty);

        /**
         * We can't modify quontity of existing items which have parent
         * This qty declared just once duering add process and is not editable
         */
        if (!$this->getParentItem() || !$this->getId()) {
            $this->setQtyToAdd($qty);
            $this->setQty($oldQty + $qty);
        }
        return $this;
    }

    /**
     * Declare quote item quantity
     *
     * @param float $qty
     */
    #[\Override]
    public function setQty($qty): static
    {
        $qty = $this->_prepareQty($qty);
        $oldQty = $this->_getData('qty');
        $this->setData('qty', $qty);

        Mage::dispatchEvent('sales_quote_item_qty_set_after', ['item' => $this]);

        if ($this->getQuote() && $this->getQuote()->getIgnoreOldQty()) {
            return $this;
        }
        if ($this->getUseOldQty()) {
            $this->setData('qty', $oldQty);
        }

        return $this;
    }

    /**
     * Retrieve option product with Qty
     *
     * Return array
     * 'qty'        => the qty
     * 'product'    => the product model
     *
     * @return array
     */
    public function getQtyOptions()
    {
        $qtyOptions = $this->getData('qty_options');
        if (is_null($qtyOptions)) {
            $productIds = [];
            $qtyOptions = [];
            foreach ($this->getOptions() as $option) {
                /** @var Mage_Sales_Model_Quote_Item_Option $option */
                if (is_object($option->getProduct())
                    && $option->getProduct()->getId() != $this->getProduct()->getId()
                ) {
                    $productIds[$option->getProduct()->getId()] = $option->getProduct()->getId();
                }
            }

            foreach ($productIds as $productId) {
                $option = $this->getOptionByCode('product_qty_' . $productId);
                if ($option) {
                    $qtyOptions[$productId] = $option;
                }
            }

            $this->setData('qty_options', $qtyOptions);
        }

        return $qtyOptions;
    }

    /**
     * Set option product with Qty
     *
     * @param array $qtyOptions
     * @return $this
     */
    public function setQtyOptions($qtyOptions)
    {
        return $this->setData('qty_options', $qtyOptions);
    }

    /**
     * Setup product for quote item
     *
     * @param   Mage_Catalog_Model_Product $product
     */
    #[\Override]
    public function setProduct($product): static
    {
        if ($this->getQuote()) {
            $product->setStoreId($this->getQuote()->getStoreId());
            $product->setCustomerGroupId($this->getQuote()->getCustomerGroupId());
        }
        $this->setData('product', $product)
            ->setProductId($product->getId())
            ->setProductType($product->getTypeId())
            ->setSku($this->getProduct()->getSku())
            ->setName($product->getName())
            ->setWeight($this->getProduct()->getWeight())
            ->setTaxClassId($product->getTaxClassId())
            ->setBaseCost($product->getCost())
            ->setIsRecurring((bool) $product->getIsRecurring());

        if ($product->getStockItem()) {
            $this->setIsQtyDecimal((bool) $product->getStockItem()->getIsQtyDecimal());
        }

        Mage::dispatchEvent('sales_quote_item_set_product', [
            'product' => $product,
            'quote_item' => $this,
        ]);

        return $this;
    }

    /**
     * Check product representation in item
     *
     * @param   Mage_Catalog_Model_Product $product
     * @return  bool
     */
    public function representProduct($product)
    {
        $itemProduct = $this->getProduct();
        if (!$product || $itemProduct->getId() != $product->getId()) {
            return false;
        }

        /**
         * Check maybe product is planned to be a child of some quote item - in this case we limit search
         * only within same parent item
         */
        $stickWithinParent = $product->getStickWithinParent();
        if ($stickWithinParent) {
            if ($this->getParentItem() !== $stickWithinParent) {
                return false;
            }
        }

        // Check options
        $itemOptions = $this->getOptionsByCode();
        $productOptions = $product->getCustomOptions();

        if (!$this->compareOptions($itemOptions, $productOptions)) {
            return false;
        }
        if (!$this->compareOptions($productOptions, $itemOptions)) {
            return false;
        }
        return true;
    }

    /**
     * Check if two options array are identical
     * First options array is prerogative
     * Second options array checked against first one
     *
     * @param array $options1
     * @param array $options2
     * @return bool
     */
    public function compareOptions($options1, $options2)
    {
        foreach ($options1 as $option) {
            $code = $option->getCode();
            if (in_array($code, $this->_notRepresentOptions)) {
                continue;
            }
            if (!isset($options2[$code])
                || ($options2[$code]->getValue() === null)
                || $options2[$code]->getValue() != $option->getValue()
            ) {
                return false;
            }
        }
        return true;
    }

    /**
     * Compare item
     *
     * @param   Mage_Sales_Model_Quote_Item $item
     * @return  bool
     */
    public function compare($item)
    {
        if ($this->getProductId() != $item->getProductId()) {
            return false;
        }
        foreach ($this->getOptions() as $option) {
            if (in_array($option->getCode(), $this->_notRepresentOptions)
                && !$item->getProduct()->hasCustomOptions()
            ) {
                continue;
            }
            if ($itemOption = $item->getOptionByCode($option->getCode())) {
                $itemOptionValue = $itemOption->getValue();
                $optionValue = $option->getValue();

                // dispose of some options params, that can cramp comparing of arrays
                if (is_string($itemOptionValue) && is_string($optionValue)) {
                    try {
                        /**
                         * @var Mage_Core_Helper_UnserializeArray $parser
                         * @var Mage_Core_Helper_String $stringHelper
                         */
                        $parser = Mage::helper('core/unserializeArray');
                        $stringHelper = Mage::helper('core/string');

                        // only ever try to unserialize, if it looks like a serialized array
                        $_itemOptionValue = $stringHelper->isSerializedArrayOrObject($itemOptionValue) ? $parser->unserialize($itemOptionValue) : $itemOptionValue;
                        $_optionValue = $stringHelper->isSerializedArrayOrObject($optionValue) ? $parser->unserialize($optionValue) : $optionValue;

                        if (is_array($_itemOptionValue) && is_array($_optionValue)) {
                            $itemOptionValue = $_itemOptionValue;
                            $optionValue = $_optionValue;
                            // looks like it does not break bundle selection qty
                            foreach (['qty', 'uenc', 'form_key', 'item', 'original_qty'] as $key) {
                                unset($itemOptionValue[$key], $optionValue[$key]);
                            }
                        }
                    } catch (Exception $e) {
                        Mage::logException($e);
                    }
                }

                if ($itemOptionValue != $optionValue) {
                    return false;
                }
            } else {
                return false;
            }
        }
        return true;
    }

    /**
     * Get item product type
     */
    #[\Override]
    public function getProductType(): ?string
    {
        if ($option = $this->getOptionByCode('product_type')) {
            return $option->getValue();
        }
        if ($product = $this->getProduct()) {
            return $product->getTypeId();
        }
        return $this->_getData('product_type');
    }

    /**
     * Return real product type of item
     *
     * @return string
     */
    public function getRealProductType()
    {
        return $this->_getData('product_type');
    }

    /**
     * Convert Quote Item to array
     *
     * @return array
     */
    #[\Override]
    public function toArray(array $arrAttributes = [])
    {
        $data = parent::toArray($arrAttributes);

        if ($product = $this->getProduct()) {
            $data['product'] = $product->toArray();
        }
        return $data;
    }

    /**
     * Initialize quote item options
     *
     * @param   array $options
     * @return  $this
     */
    public function setOptions($options)
    {
        foreach ($options as $option) {
            $this->addOption($option);
        }
        return $this;
    }

    /**
     * Get all item options
     *
     * @return array
     */
    public function getOptions()
    {
        return $this->_options;
    }

    /**
     * Get all item options as array with codes in array key
     *
     * @return array
     */
    public function getOptionsByCode()
    {
        return $this->_optionsByCode;
    }

    /**
     * Add option to item
     *
     * @param Mage_Sales_Model_Quote_Item_Option|\Maho\DataObject|array $option
     * @return $this
     * @throws Mage_Core_Exception
     */
    public function addOption($option)
    {
        if (is_array($option)) {
            $option = Mage::getModel('sales/quote_item_option')->setData($option)
                ->setItem($this);
        } elseif (($option instanceof \Maho\DataObject) && !($option instanceof Mage_Sales_Model_Quote_Item_Option)) {
            $option = Mage::getModel('sales/quote_item_option')->setData($option->getData())
                ->setProduct($option->getProduct())
                ->setItem($this);
        } else {
            $option->setItem($this);
        }

        if ($exOption = $this->getOptionByCode($option->getCode())) {
            $exOption->addData($option->getData());
        } else {
            $this->_addOptionCode($option);
            $this->_options[] = $option;
        }
        return $this;
    }

    /**
     * Can specify specific actions for ability to change given quote options values
     * Example: cataloginventory decimal qty validation may change qty to int,
     * so need to change quote item qty option value.
     *
     * @param int|float|null $value
     * @return $this
     */
    public function updateQtyOption(\Maho\DataObject $option, $value)
    {
        $optionProduct = $option->getProduct();
        $options = $this->getQtyOptions();

        if (isset($options[$optionProduct->getId()])) {
            $options[$optionProduct->getId()]->setValue($value);
        }

        $this->getProduct()->getTypeInstance(true)
            ->updateQtyOption($this->getOptions(), $option, $value, $this->getProduct());

        return $this;
    }

    /**
     *Remove option from item options
     *
     * @param string $code
     * @return $this
     */
    public function removeOption($code)
    {
        $option = $this->getOptionByCode($code);
        if ($option) {
            $option->isDeleted(true);
        }
        return $this;
    }

    /**
     * Register option code
     *
     * @param   Mage_Sales_Model_Quote_Item_Option $option
     * @return  $this
     */
    protected function _addOptionCode($option)
    {
        if (!isset($this->_optionsByCode[$option->getCode()])) {
            $this->_optionsByCode[$option->getCode()] = $option;
        } else {
            Mage::throwException(Mage::helper('sales')->__('An item option with code %s already exists.', $option->getCode()));
        }
        return $this;
    }

    /**
     * Get item option by code
     *
     * @param   string $code
     * @return  Mage_Sales_Model_Quote_Item_Option|null
     */
    #[\Override]
    public function getOptionByCode($code)
    {
        if (isset($this->_optionsByCode[$code]) && !$this->_optionsByCode[$code]->isDeleted()) {
            return $this->_optionsByCode[$code];
        }
        return null;
    }

    /**
     * Checks that item model has data changes.
     * Call save item options if model isn't need to save in DB
     *
     * @return bool
     */
    #[\Override]
    protected function _hasModelChanged()
    {
        if (!$this->hasDataChanges()) {
            return false;
        }

        return $this->_getResource()->hasDataChanged($this);
    }

    /**
     * Save item options
     *
     * @return $this
     */
    protected function _saveItemOptions()
    {
        foreach ($this->_options as $index => $option) {
            if ($option->isDeleted()) {
                $option->delete();
                unset($this->_options[$index]);
                unset($this->_optionsByCode[$option->getCode()]);
            } else {
                $option->save();
            }
        }

        $this->_flagOptionsSaved = true; // Report to watchers that options were saved

        return $this;
    }

    /**
     * Save model plus its options
     * Ensures saving options in case when resource model was not changed
     */
    #[\Override]
    public function save()
    {
        $hasDataChanges = $this->hasDataChanges();
        $this->_flagOptionsSaved = false;

        parent::save();

        if ($hasDataChanges && !$this->_flagOptionsSaved) {
            $this->_saveItemOptions();
        }

        return $this;
    }

    /**
     * Save item options after item saved
     */
    #[\Override]
    protected function _afterSave()
    {
        $this->_saveItemOptions();
        return parent::_afterSave();
    }

    /**
     * Delete custom option files before deleting quote item
     */
    #[\Override]
    protected function _beforeDelete()
    {
        // Load options if not already loaded
        if (empty($this->_options) && $this->getId()) {
            $optionCollection = Mage::getResourceModel('sales/quote_item_option_collection')
                ->addItemFilter([$this->getId()]);
            $this->setOptions($optionCollection->getOptionsByItem($this));
        }

        // Delete any uploaded files associated with file-type custom options
        $optionFile = Mage::getModel('catalog/product_option_type_file');
        foreach ($this->getOptions() as $option) {
            // Check if this is a file option
            if (str_starts_with($option->getCode(), Mage_Catalog_Model_Product_Type_Abstract::OPTION_PREFIX)) {
                try {
                    $optionValue = Mage::helper('core/string')->unserialize($option->getValue());
                    if (is_array($optionValue) && isset($optionValue['quote_path'])) {
                        $filePath = $optionFile->resolveStoredPath($optionValue, 'quote_path');
                        if ($filePath !== null && is_file($filePath)) {
                            @unlink($filePath);
                        }
                    }
                } catch (Exception $e) {
                    // Log but don't stop the deletion process
                    Mage::logException($e);
                }
            }
        }

        return parent::_beforeDelete();
    }

    /**
     * Clone quote item
     */
    #[\Override]
    public function __clone()
    {
        parent::__clone();
        $options = $this->getOptions();
        $this->_quote = null;
        $this->_options = [];
        $this->_optionsByCode = [];
        foreach ($options as $option) {
            $this->addOption(clone $option);
        }
    }

    /**
     * Returns formatted buy request - object, holding request received from
     * product view page with keys and options for configured product
     *
     * @return \Maho\DataObject
     */
    public function getBuyRequest()
    {
        $option = $this->getOptionByCode('info_buyRequest');
        $buyRequest = new \Maho\DataObject($option
            ? Mage::helper('core/string')->unserialize($option->getValue())
            : null);

        // Overwrite standard buy request qty, because item qty could have changed since adding to quote
        $buyRequest->setOriginalQty($buyRequest->getQty())
            ->setQty($this->getQty() * 1);

        return $buyRequest;
    }

    /**
     * Sets flag, whether this quote item has some error associated with it.
     *
     * @param bool $flag
     * @return $this
     */
    protected function _setHasError($flag)
    {
        return $this->setData('has_error', $flag);
    }

    /**
     * Sets flag, whether this quote item has some error associated with it.
     * When TRUE - also adds 'unknown' error information to list of quote item errors.
     * When FALSE - clears whole list of quote item errors.
     * It's recommended to use addErrorInfo() instead - to be able to remove error statuses later.
     *
     * @see addErrorInfo()
     */
    #[\Override]
    public function setHasError(?bool $flag = true): static
    {
        if ($flag) {
            $this->addErrorInfo();
        } else {
            $this->_clearErrorInfo();
        }
        return $this;
    }

    /**
     * Clears list of errors, associated with this quote item.
     * Also automatically removes error-flag from oneself.
     *
     * @return $this
     */
    protected function _clearErrorInfo()
    {
        $this->_errorInfos->clear();
        $this->_setHasError(false);
        return $this;
    }

    /**
     * Adds error information to the quote item.
     * Automatically sets error flag.
     *
     * @param string|null $origin Usually a name of module, that embeds error
     * @param int|null $code Error code, unique for origin, that sets it
     * @param string|null $message Error message
     * @param \Maho\DataObject|null $additionalData Any additional data, that caller would like to store
     * @return $this
     */
    public function addErrorInfo($origin = null, $code = null, $message = null, $additionalData = null)
    {
        $this->_errorInfos->addItem($origin, $code, $message, $additionalData);
        if ($message !== null) {
            $this->setMessage($message);
        }
        $this->_setHasError(true);

        return $this;
    }

    /**
     * Retrieves all error infos, associated with this item
     *
     * @return array
     */
    public function getErrorInfos()
    {
        return $this->_errorInfos->getItems();
    }

    /**
     * Removes error infos, that have parameters equal to passed in $params.
     * $params can have following keys (if not set - then any item is good for this key):
     *   'origin', 'code', 'message'
     *
     * @param array $params
     * @return $this
     */
    public function removeErrorInfosByParams($params)
    {
        $removedItems = $this->_errorInfos->removeItemsByParams($params);
        foreach ($removedItems as $item) {
            if ($item['message'] !== null) {
                $this->removeMessageByText($item['message']);
            }
        }

        if (!$this->_errorInfos->getItems()) {
            $this->_setHasError(false);
        }

        return $this;
    }

    /**
     * Get discount amount with proper float casting
     * DBAL returns DECIMAL as string, so we cast to float
     */
    #[\Override]
    public function getDiscountAmount(): ?float
    {
        $value = $this->getData('discount_amount');
        return $value !== null ? (float) $value : null;
    }

    public function getAdditionalData(): ?string
    {
        $value = $this->getData('additional_data');
        return $value === null ? null : (string) $value;
    }

    public function setAdditionalData(?string $value): static
    {
        return $this->setData('additional_data', $value);
    }

    public function getAppliedRuleIds(): ?string
    {
        $value = $this->getData('applied_rule_ids');
        return $value === null ? null : (string) $value;
    }

    public function setBackorders(?int $value): static
    {
        return $this->setData('backorders', $value);
    }

    public function getBaseCost(): ?float
    {
        $value = $this->getData('base_cost');
        return $value === null ? null : (float) $value;
    }

    public function setBaseCost(?float $value): static
    {
        return $this->setData('base_cost', $value);
    }

    public function getBaseHiddenTaxAmount(): ?float
    {
        $value = $this->getData('base_hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function getBasePrice(): ?float
    {
        $value = $this->getData('base_price');
        return $value === null ? null : (float) $value;
    }

    public function getBasePriceInclTax(): ?float
    {
        $value = $this->getData('base_price_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function getCost(): ?float
    {
        $value = $this->getData('cost');
        return $value === null ? null : (float) $value;
    }

    public function getDescription(): ?string
    {
        $value = $this->getData('description');
        return $value === null ? null : (string) $value;
    }

    public function setDescription(?string $value): static
    {
        return $this->setData('description', $value);
    }

    public function setGiftMessage(?string $value): static
    {
        return $this->setData('gift_message', $value);
    }

    public function getGiftMessageId(): ?int
    {
        $value = $this->getData('gift_message_id');
        return $value === null ? null : (int) $value;
    }

    public function setGiftMessageId(?int $value): static
    {
        return $this->setData('gift_message_id', $value);
    }

    public function setHasConfigurationUnavailableError(?bool $value = true): static
    {
        return $this->setData('has_configuration_unavailable_error', $value);
    }

    public function getHasError(): ?bool
    {
        $value = $this->getData('has_error');
        return $value === null ? null : (bool) $value;
    }

    public function getHiddenTaxAmount(): ?float
    {
        $value = $this->getData('hidden_tax_amount');
        return $value === null ? null : (float) $value;
    }

    public function getIsQtyDecimal(): ?bool
    {
        $value = $this->getData('is_qty_decimal');
        return $value === null ? null : (bool) $value;
    }

    public function setIsQtyDecimal(?bool $value = true): static
    {
        return $this->setData('is_qty_decimal', $value);
    }

    public function setIsRecurring(?bool $value = true): static
    {
        return $this->setData('is_recurring', $value);
    }

    public function getItemId(): ?int
    {
        $value = $this->getData('item_id');
        return $value === null ? null : (int) $value;
    }

    public function setName(?string $value): static
    {
        return $this->setData('name', $value);
    }

    public function setNoDiscount(?bool $value = true): static
    {
        return $this->setData('no_discount', $value);
    }

    public function setOriginalCustomPrice(?float $value): static
    {
        return $this->setData('original_custom_price', $value);
    }

    public function setParentProductId(?int $value): static
    {
        return $this->setData('parent_product_id', $value);
    }

    public function setProductId(?int $value): static
    {
        return $this->setData('product_id', $value);
    }

    public function setProductOrderOptions(?array $value): static
    {
        return $this->setData('product_order_options', $value);
    }

    public function setProductType(?string $value): static
    {
        return $this->setData('product_type', $value);
    }

    public function getPriceInclTax(): ?float
    {
        $value = $this->getData('price_incl_tax');
        return $value === null ? null : (float) $value;
    }

    public function getQuoteId(): ?int
    {
        $value = $this->getData('quote_id');
        return $value === null ? null : (int) $value;
    }

    public function setQuoteId(?int $value): static
    {
        return $this->setData('quote_id', $value);
    }

    public function setQuoteItemId(?int $value): static
    {
        return $this->setData('quote_item_id', $value);
    }

    public function setQuoteMessage(?string $value): static
    {
        return $this->setData('quote_message', $value);
    }

    public function setQuoteMessageIndex(?string $value): static
    {
        return $this->setData('quote_message_index', $value);
    }

    public function getQtyToAdd(): ?float
    {
        $value = $this->getData('qty_to_add');
        return $value === null ? null : (float) $value;
    }

    public function setQtyToAdd(?float $value): static
    {
        return $this->setData('qty_to_add', $value);
    }

    public function getRedirectUrl(): ?string
    {
        $value = $this->getData('redirect_url');
        return $value === null ? null : (string) $value;
    }

    public function setRedirectUrl(?string $value): static
    {
        return $this->setData('redirect_url', $value);
    }

    public function setRowWeight(?float $value): static
    {
        return $this->setData('row_weight', $value);
    }

    public function getSku(): ?string
    {
        $value = $this->getData('sku');
        return $value === null ? null : (string) $value;
    }

    public function setSku(?string $value): static
    {
        return $this->setData('sku', $value);
    }

    public function setStoreId(?int $value): static
    {
        return $this->setData('store_id', $value);
    }

    public function setTaxClassId(?int $value): static
    {
        return $this->setData('tax_class_id', $value);
    }

    public function getUpdatedAt(): ?string
    {
        $value = $this->getData('updated_at');
        return $value === null ? null : (string) $value;
    }

    public function getUseOldQty(): ?bool
    {
        $value = $this->getData('use_old_qty');
        return $value === null ? null : (bool) $value;
    }

    public function getIsVirtual(): ?bool
    {
        $value = $this->getData('is_virtual');
        return $value === null ? null : (bool) $value;
    }

    public function setIsVirtual(?bool $value = true): static
    {
        return $this->setData('is_virtual', $value);
    }

    public function getWeight(): ?float
    {
        $value = $this->getData('weight');
        return $value === null ? null : (float) $value;
    }

    public function setWeight(?float $value): static
    {
        return $this->setData('weight', $value);
    }
}
