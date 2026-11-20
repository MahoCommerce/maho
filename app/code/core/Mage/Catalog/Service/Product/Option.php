<?php

/**
 * Load, save and delete the custom options of a product.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Mage_Catalog_Model_Product as Product;
use Mage_Catalog_Model_Product_Option as Option;

/**
 * A write saves the product with all its options in the format of the admin product save, so the product and the
 * options follow the same code: the option model checks each option, and the product sets has_options and
 * required_options. The write uses the admin scope.
 */
class Mage_Catalog_Service_Product_Option
{
    private const FIELDS = [
        'title', 'type', 'is_require', 'sort_order', 'price', 'price_type', 'sku',
        'max_characters', 'file_extension', 'image_size_x', 'image_size_y',
    ];

    private const VALUE_FIELDS = ['title', 'price', 'price_type', 'sku', 'sort_order'];

    /**
     * @throws Mage_Core_Exception_NoSuchEntity when $product has no option $optionId
     */
    public function getById(Product $product, int $optionId): Option
    {
        /** @var Option $option */
        $option = Mage::getResourceModel('catalog/product_option_collection')
            ->addProductToFilter($product->getId())
            ->addIdsToFilter($optionId)
            ->addTitleToResult(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->addPriceToResult(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->getFirstItem();
        if (!$option->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Option not found'));
        }
        return $option;
    }

    /**
     * Save $option, a new option of $product or one from getById(). For a select type, a list of value arrays in the
     * data "values" replaces the values of the option. Without this list, the values stay.
     *
     * @throws Mage_Core_Exception_Input for an invalid title, type or value, or a negative price of an option that has no values
     * @throws Mage_Core_Exception_Conflict when the product cannot have custom options, as a bundle with a dynamic price
     * @throws Mage_Core_Exception for a forbidden file extension
     */
    public function save(Product $product, Option $option): Option
    {
        if ($option->getGroupByType() !== Option::OPTION_GROUP_SELECT && (float) $option->getData('price') < 0) {
            throw new Mage_Core_Exception_Input()->addError('price', Mage::helper('catalog')->__('Price must not be negative'));
        }
        $product = $this->write($product, $option, false);
        if ($option->getId()) {
            return $this->getById($product, (int) $option->getId());
        }
        // The option of the save is the last one, so the option instance of the product holds its ID
        return $this->getById($product, (int) $product->getOptionInstance()->getId());
    }

    public function delete(Product $product, Option $option): void
    {
        $this->write($product, $option, true);
    }

    /**
     * Open the file that a shopper uploaded for a file option of a cart item. $quoteItemOptionId is the ID of the
     * option of the cart item, and $key is the secret key of the file. The caller closes the stream.
     *
     * @return array{stream: resource, size: int|null, title: string, type: string}
     * @throws Mage_Core_Exception_NoSuchEntity when the cart item has no such file option, or the file is gone
     * @throws Mage_Core_Exception_Forbidden when $key is not the secret key of the file
     */
    public function openUploadedFile(int $quoteItemOptionId, string $key): array
    {
        return $this->openItemOptionFile(Mage::getModel('sales/quote_item_option')->load($quoteItemOptionId), $key);
    }

    /**
     * Open the file that a shopper uploaded for a file option. $itemOption is the option of a cart item or of a
     * wishlist item, and $key is the secret key of the file. The caller closes the stream.
     *
     * @return array{stream: resource, size: int|null, title: string, type: string}
     * @throws Mage_Core_Exception_NoSuchEntity when $itemOption is not a file option, or the file is gone
     * @throws Mage_Core_Exception_Forbidden when $key is not the secret key of the file
     */
    public function openItemOptionFile(Mage_Sales_Model_Quote_Item_Option|Mage_Wishlist_Model_Item_Option $itemOption, string $key): array
    {
        $helper = Mage::helper('catalog');

        // Only a file option holds a stored file: the value of another option is the text of the shopper
        $optionId = str_starts_with((string) $itemOption->getCode(), Mage_Catalog_Model_Product_Type_Abstract::OPTION_PREFIX)
            ? substr((string) $itemOption->getCode(), strlen(Mage_Catalog_Model_Product_Type_Abstract::OPTION_PREFIX))
            : '';
        $productOption = ctype_digit($optionId) ? Mage::getModel('catalog/product_option')->load((int) $optionId) : null;
        if (!$itemOption->getId()
            || !$productOption?->getId()
            || (int) $productOption->getProductId() !== (int) $itemOption->getProductId()
            || $productOption->getType() !== Option::OPTION_TYPE_FILE
        ) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('File not found'));
        }

        try {
            $value = Mage::helper('core/unserializeArray')->unserialize((string) $itemOption->getValue());
        } catch (Exception) {
            $value = null;
        }
        if (!is_array($value)) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('File not found'));
        }
        if (!isset($value['secret_key']) || !hash_equals((string) $value['secret_key'], $key)) {
            throw new Mage_Core_Exception_Forbidden($helper->__('Invalid key'));
        }

        $file = Mage::getModel('catalog/product_option_type_file')->openStoredFile($value);
        if ($file === null) {
            throw new Mage_Core_Exception_NoSuchEntity($helper->__('File not found on disk'));
        }

        return $file + [
            'title' => (string) ($value['title'] ?? 'file'),
            'type' => (string) ($value['type'] ?? 'application/octet-stream'),
        ];
    }

    private function write(Product $product, Option $target, bool $delete): Product
    {
        $product = Mage::getService('catalog/product')->getById((int) $product->getId());
        if (!$delete && !$product->getTypeInstance(true)->allowsCustomOptions($product)) {
            throw new Mage_Core_Exception_Conflict(Mage::helper('catalog')->__('This product cannot have custom options.'));
        }
        $options = [];
        foreach ($product->getProductOptionsCollection() as $option) {
            if ((int) $option->getId() !== (int) $target->getId()) {
                $options[] = $this->toArray($option, array_values($option->getValues() ?? []), null);
            }
        }
        $values = $target->getId() ? array_values($target->getValuesCollection()->getItems()) : [];
        $newValues = $target->getData('values');
        $data = $this->toArray($target, $values, is_array($newValues) ? $newValues : null);
        $data['is_delete'] = $delete ? '1' : '';
        $data['previous_type'] = (string) $target->getOrigData('type');
        $options[] = $data;

        $product->setProductOptions($options);
        $product->setCanSaveCustomOptions(true);
        Mage::getService('catalog/product')->savePart($product);
        return $product;
    }

    /**
     * Return $option in the format of the admin product save.
     *
     * @param list<Mage_Catalog_Model_Product_Option_Value> $values the values that the option has now
     * @param list<array<string, mixed>>|null $newValues the values that replace $values, or null to keep them
     * @return array<string, mixed>
     */
    private function toArray(Option $option, array $values, ?array $newValues): array
    {
        $data = array_intersect_key($option->getData(), array_flip(self::FIELDS));
        $data['option_id'] = (string) ($option->getId() ?: '0');
        $data['is_delete'] = '';
        if ($option->getGroupByType() !== Option::OPTION_GROUP_SELECT) {
            return $data;
        }

        $data['values'] = [];
        foreach ($newValues ?? [] as $value) {
            $data['values'][] = array_intersect_key($value, array_flip(self::VALUE_FIELDS)) + ['option_type_id' => '-1', 'is_delete' => ''];
        }
        foreach ($values as $value) {
            $data['values'][] = $newValues === null
                ? array_intersect_key($value->getData(), array_flip(self::VALUE_FIELDS)) + ['option_type_id' => $value->getId(), 'is_delete' => '']
                : ['option_type_id' => $value->getId(), 'is_delete' => '1'];
        }
        return $data;
    }
}
