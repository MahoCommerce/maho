<?php

/**
 * Create, update and delete product attributes and their options.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;

final class ProductAttributeProcessor extends \Maho\ApiPlatform\Processor
{
    public const INPUT_TYPES = ['text', 'textarea', 'date', 'boolean', 'select', 'multiselect', 'price'];

    private const OPTION_INPUT_TYPES = ['select', 'multiselect'];

    private const CODE_PATTERN = '/^[a-z][a-z_0-9]{0,29}$/';

    private const SCOPES = [
        'store' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'global' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
        'website' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_WEBSITE,
    ];

    private const BOOLEAN_FIELDS = [
        'isRequired' => 'is_required',
        'isUnique' => 'is_unique',
        'isSearchable' => 'is_searchable',
        'isFilterableInSearch' => 'is_filterable_in_search',
        'isComparable' => 'is_comparable',
        'isVisibleOnFront' => 'is_visible_on_front',
        'isHtmlAllowedOnFront' => 'is_html_allowed_on_front',
        'isUsedForPriceRules' => 'is_used_for_price_rules',
        'usedInProductListing' => 'used_in_product_listing',
        'usedForSortBy' => 'used_for_sort_by',
        'isVisibleInAdvancedSearch' => 'is_visible_in_advanced_search',
        'isWysiwygEnabled' => 'is_wysiwyg_enabled',
        'isConfigurable' => 'is_configurable',
    ];

    private const WRITABLE_FIELDS = [
        'attributeCode', 'frontendLabel', 'frontendInput', 'scope', 'isGlobal', 'defaultValue', 'isFilterable',
        'applyTo', 'frontendClass', 'note', 'position',
    ];

    /**
     * A client can send back an attribute that it read, so these keys do not cause an error.
     */
    private const READ_ONLY_FIELDS = ['id', 'backendType', 'isUserDefined', 'options', 'extensions', '@context', '@id', '@type'];

    /**
     * The admin form locks these fields on a system attribute.
     */
    private const SYSTEM_LOCKED_FIELDS = ['isUnique'];

    private \Mage_Core_Exception_Input $errors;

    public function __construct(
        Security $security,
        private readonly ProductAttributeProvider $provider,
    ) {
        parent::__construct($security);
        $this->errors = new \Mage_Core_Exception_Input();
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductAttribute
    {
        $this->requireUser();
        $id = (int) ($uriVariables['id'] ?? 0);
        $optionId = (int) ($uriVariables['optionId'] ?? 0);
        $name = (string) $operation->getName();

        if ($operation instanceof DeleteOperationInterface) {
            if ($name === 'product_attribute_option_delete') {
                $this->deleteOption($id, $optionId);
            } else {
                $this->delete($id);
            }
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);

        return match ($name) {
            'product_attribute_option_create' => $this->createOption($id, $body),
            'product_attribute_option_update' => $this->updateOption($id, $optionId, $body),
            default => $id > 0 ? $this->update($id, $body) : $this->create($body),
        };
    }

    /**
     * @param array<string, mixed> $body
     */
    private function create(array $body): ProductAttribute
    {
        $this->errors = new \Mage_Core_Exception_Input();
        $this->rejectUnknownFields($body);

        /** @var \Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = \Mage::getModel('catalog/resource_eav_attribute');

        $code = $body['attributeCode'] ?? null;
        if (!is_string($code) || !preg_match(self::CODE_PATTERN, $code)) {
            $this->addError('attributeCode', 'attributeCode must start with a letter a-z and contain only letters a-z, digits and underscores, at most 30 characters');
        } elseif (in_array($code, \Mage::getModel('catalog/product')->getReservedAttributes(), true)) {
            $this->addError('attributeCode', "attributeCode '{$code}' is reserved by the product model");
        } elseif ($this->attributeExists($code)) {
            $this->addError('attributeCode', "A product attribute with the code '{$code}' already exists");
        }

        $input = $body['frontendInput'] ?? 'text';
        if (!in_array($input, self::INPUT_TYPES, true)) {
            $this->addError('frontendInput', 'frontendInput must be one of: ' . implode(', ', self::INPUT_TYPES));
            $input = 'text';
        }
        if (!array_key_exists('frontendLabel', $body)) {
            $this->addError('frontendLabel', 'frontendLabel is required');
        }

        /** @var \Mage_Catalog_Helper_Product $helper */
        $helper = \Mage::helper('catalog/product');
        $attribute->addData([
            'entity_type_id' => $this->productEntityTypeId(),
            'attribute_code' => is_string($code) ? $code : null,
            'frontend_input' => $input,
            'backend_type' => $attribute->getBackendTypeByInput($input),
            'source_model' => $helper->getAttributeSourceModelByInputType($input),
            'backend_model' => $helper->getAttributeBackendModelByInputType($input),
            'frontend_model' => $helper->getAttributeFrontendModelByInputType($input),
            'is_user_defined' => 1,
            'is_global' => \Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL,
            'is_configurable' => 0,
            'is_filterable' => 0,
            'is_filterable_in_search' => 0,
            'apply_to' => [],
        ]);

        $this->applyFields($attribute, $body, true);
        $this->errors->throwIfErrors();

        $this->safeSave($attribute, 'create product attribute');
        \Mage::app()->cleanCache([\Mage_Core_Model_Translate::CACHE_TAG]);
        $this->logApiActivity('product_attribute', 'create', null, $attribute);

        return $this->provider->attributeDto($this->loadAttribute((int) $attribute->getId()));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function update(int $id, array $body): ProductAttribute
    {
        $attribute = $this->loadAttribute($id);
        $oldData = $attribute->getData();

        $this->errors = new \Mage_Core_Exception_Input();
        $this->rejectUnknownFields($body);

        if (array_key_exists('attributeCode', $body) && $body['attributeCode'] !== $attribute->getAttributeCode()) {
            $this->addError('attributeCode', 'attributeCode cannot change after creation');
        }
        if (array_key_exists('frontendInput', $body) && $body['frontendInput'] !== $attribute->getFrontendInput()) {
            $this->addError('frontendInput', 'frontendInput cannot change after creation');
        }
        if (!$attribute->getIsUserDefined()) {
            foreach (self::SYSTEM_LOCKED_FIELDS as $field) {
                $snake = self::BOOLEAN_FIELDS[$field];
                if (array_key_exists($field, $body) && (bool) $body[$field] !== (bool) $attribute->getData($snake)) {
                    $this->addError($field, "{$field} cannot change on a system attribute");
                }
            }
        }

        $this->applyFields($attribute, $body, false);
        $this->errors->throwIfErrors();

        $this->safeSave($attribute, 'update product attribute');
        \Mage::app()->cleanCache([\Mage_Core_Model_Translate::CACHE_TAG]);
        $this->logApiActivity('product_attribute', 'update', $oldData, $attribute);

        return $this->provider->attributeDto($this->loadAttribute($id));
    }

    private function delete(int $id): void
    {
        $attribute = $this->loadAttribute($id);
        if (!$attribute->getIsUserDefined()) {
            throw new UnprocessableEntityHttpException('A system attribute cannot be deleted');
        }
        $oldData = $attribute->getData();
        $this->safeDelete($attribute, 'delete product attribute');
        $this->logApiActivity('product_attribute', 'delete', $oldData, null);
    }

    /**
     * @param array<string, mixed> $body
     */
    private function applyFields(\Mage_Catalog_Model_Resource_Eav_Attribute $attribute, array $body, bool $isNew): void
    {
        if (array_key_exists('frontendLabel', $body)) {
            $label = is_string($body['frontendLabel']) ? trim(\Mage::helper('catalog')->stripTags($body['frontendLabel'])) : '';
            if ($label === '' || mb_strlen($label) > 255) {
                $this->addError('frontendLabel', 'frontendLabel must be a text of 1 to 255 characters');
            } else {
                $attribute->setData('frontend_label', $label);
            }
        }

        foreach (self::BOOLEAN_FIELDS as $field => $column) {
            if (array_key_exists($field, $body)) {
                $value = $body[$field];
                if (!is_bool($value) && $value !== 0 && $value !== 1) {
                    $this->addError($field, "{$field} must be true or false");
                } else {
                    $attribute->setData($column, (int) (bool) $value);
                }
            }
        }

        if (array_key_exists('isFilterable', $body)) {
            $value = $this->readInteger($body['isFilterable']);
            if ($value === null || $value < 0 || $value > 2) {
                $this->addError('isFilterable', 'isFilterable must be 0 (no), 1 (filterable with results) or 2 (filterable no results)');
            } else {
                $attribute->setData('is_filterable', $value);
            }
        }

        if (array_key_exists('position', $body)) {
            $value = $this->readInteger($body['position']);
            if ($value === null || $value < 0) {
                $this->addError('position', 'position must be an integer of 0 or more');
            } else {
                $attribute->setData('position', $value);
            }
        }

        $this->applyScope($attribute, $body);

        if (array_key_exists('defaultValue', $body)) {
            $value = $body['defaultValue'];
            if (in_array($attribute->getFrontendInput(), self::OPTION_INPUT_TYPES, true)) {
                // A client can send back the value that it read.
                if (($value !== null && !is_scalar($value)) || (string) $value !== (string) $attribute->getData('default_value')) {
                    $this->addError('defaultValue', 'The default of a select or multiselect attribute is set with isDefault on an option');
                }
            } elseif ($value !== null && !is_scalar($value)) {
                $this->addError('defaultValue', 'defaultValue must be a text, a number, a boolean or null');
            } else {
                $attribute->setData('default_value', $value === null ? null : (is_bool($value) ? (int) $value : (string) $value));
            }
        }

        if (array_key_exists('applyTo', $body)) {
            $types = array_keys(\Mage_Catalog_Model_Product_Type::getTypes());
            $value = $body['applyTo'];
            if (!is_array($value) || !array_is_list($value)) {
                $this->addError('applyTo', 'applyTo must be a list of product type codes: ' . implode(', ', $types));
            } else {
                $unknown = array_diff($value, $types);
                if ($unknown !== []) {
                    $this->addError('applyTo', 'applyTo must contain only: ' . implode(', ', $types));
                } else {
                    $attribute->setApplyTo(array_values(array_unique(array_map(strval(...), $value))));
                }
            }
        }

        if (array_key_exists('frontendClass', $body)) {
            $value = $body['frontendClass'] === '' ? null : $body['frontendClass'];
            $classes = array_values(array_filter(array_column(\Mage::helper('eav')->getFrontendClasses(\Mage_Catalog_Model_Product::ENTITY), 'value')));
            if ($value !== null && !in_array($value, $classes, true)) {
                $this->addError('frontendClass', 'frontendClass must be null or one of: ' . implode(', ', $classes));
            } else {
                $attribute->setData('frontend_class', $value);
            }
        }

        if (array_key_exists('note', $body)) {
            if ($body['note'] !== null && !is_string($body['note'])) {
                $this->addError('note', 'note must be a text or null');
            } else {
                $attribute->setData('note', $body['note'] === null ? null : \Mage::helper('catalog')->stripTags($body['note']));
            }
        }

        if ($isNew && $attribute->getFrontendInput() === 'textarea' && $attribute->getData('is_wysiwyg_enabled') === null) {
            $attribute->setData('is_wysiwyg_enabled', 0);
        }
    }

    /**
     * scope and isGlobal are two forms of the same value. The body may give one or both, but they must agree.
     *
     * @param array<string, mixed> $body
     */
    private function applyScope(\Mage_Catalog_Model_Resource_Eav_Attribute $attribute, array $body): void
    {
        $fromScope = null;
        if (array_key_exists('scope', $body)) {
            if (!is_string($body['scope']) || !array_key_exists($body['scope'], self::SCOPES)) {
                $this->addError('scope', 'scope must be one of: ' . implode(', ', array_keys(self::SCOPES)));
                return;
            }
            $fromScope = self::SCOPES[$body['scope']];
        }

        $fromFlag = null;
        if (array_key_exists('isGlobal', $body)) {
            $fromFlag = $this->readInteger($body['isGlobal']);
            if ($fromFlag === null || !in_array($fromFlag, self::SCOPES, true)) {
                $this->addError('isGlobal', 'isGlobal must be 0 (store view), 1 (global) or 2 (website)');
                return;
            }
        }

        if ($fromScope !== null && $fromFlag !== null && $fromScope !== $fromFlag) {
            $this->addError('scope', 'scope and isGlobal give two different scopes');
            return;
        }

        $value = $fromScope ?? $fromFlag;
        if ($value !== null) {
            $attribute->setData('is_global', $value);
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function createOption(int $id, array $body): ProductAttribute
    {
        $attribute = $this->loadOptionAttribute($id);
        $options = $this->loadOptions($id);
        $defaults = $this->defaultOptionIds($attribute);

        $this->errors = new \Mage_Core_Exception_Input();
        $this->rejectUnknownOptionFields($body);
        if (!array_key_exists('label', $body)) {
            $this->addError('label', 'label is required');
        }

        $maxOrder = 0;
        foreach ($options as $option) {
            $maxOrder = max($maxOrder, $option['order']);
        }
        [$options['option_new'], $defaults] = $this->applyOptionFields(['order' => $maxOrder + 1, 'labels' => []], $defaults, 'option_new', $body, $attribute);
        $this->errors->throwIfErrors();

        $oldData = $attribute->getData();
        $this->saveOptions($attribute, $options, $defaults, []);
        $this->logApiActivity('product_attribute', 'update', $oldData, $attribute);

        return $this->provider->attributeDto($this->loadAttribute($id));
    }

    /**
     * @param array<string, mixed> $body
     */
    private function updateOption(int $id, int $optionId, array $body): ProductAttribute
    {
        $attribute = $this->loadOptionAttribute($id);
        $options = $this->loadOptions($id);
        if (!isset($options[$optionId])) {
            throw new NotFoundHttpException('Attribute option not found');
        }
        $defaults = $this->defaultOptionIds($attribute);

        $this->errors = new \Mage_Core_Exception_Input();
        $this->rejectUnknownOptionFields($body);
        [$options[$optionId], $defaults] = $this->applyOptionFields($options[$optionId], $defaults, $optionId, $body, $attribute);
        $this->errors->throwIfErrors();

        $oldData = $attribute->getData();
        $this->saveOptions($attribute, $options, $defaults, []);
        $this->logApiActivity('product_attribute', 'update', $oldData, $attribute);

        return $this->provider->attributeDto($this->loadAttribute($id));
    }

    private function deleteOption(int $id, int $optionId): void
    {
        $attribute = $this->loadOptionAttribute($id);
        $options = $this->loadOptions($id);
        if (!isset($options[$optionId])) {
            throw new NotFoundHttpException('Attribute option not found');
        }
        $defaults = array_values(array_diff($this->defaultOptionIds($attribute), [$optionId]));

        $oldData = $attribute->getData();
        $this->saveOptions($attribute, $options, $defaults, [$optionId]);
        $this->logApiActivity('product_attribute', 'update', $oldData, $attribute);
    }

    /**
     * Return the option $key with the fields of $body, and the new list of default options.
     *
     * @param array{order: int, labels: array<int, string>} $option
     * @param list<int|string> $defaults
     * @param array<string, mixed> $body
     * @return array{0: array{order: int, labels: array<int, string>}, 1: list<int|string>}
     */
    private function applyOptionFields(array $option, array $defaults, int|string $key, array $body, \Mage_Catalog_Model_Resource_Eav_Attribute $attribute): array
    {
        if (array_key_exists('label', $body)) {
            $label = is_string($body['label']) ? trim(\Mage::helper('catalog')->stripTags($body['label'])) : '';
            if ($label === '' || mb_strlen($label) > 255) {
                $this->addError('label', 'label must be a text of 1 to 255 characters');
            } else {
                $option['labels'][0] = $label;
            }
        }

        if (array_key_exists('sortOrder', $body)) {
            $order = $this->readInteger($body['sortOrder']);
            if ($order === null || $order < 0) {
                $this->addError('sortOrder', 'sortOrder must be an integer of 0 or more');
            } else {
                $option['order'] = $order;
            }
        }

        if (array_key_exists('isDefault', $body)) {
            $value = $body['isDefault'];
            if (!is_bool($value) && $value !== 0 && $value !== 1) {
                $this->addError('isDefault', 'isDefault must be true or false');
            } elseif ($value) {
                // A select attribute has one default value
                $defaults = $attribute->getFrontendInput() === 'multiselect' ? [...array_diff($defaults, [$key]), $key] : [$key];
            } else {
                $defaults = array_values(array_diff($defaults, [$key]));
            }
        }

        if (array_key_exists('storeLabels', $body)) {
            $input = $body['storeLabels'];
            if (!is_array($input) || ($input !== [] && array_is_list($input))) {
                $this->addError('storeLabels', 'storeLabels must be an object of store view code to label');
                return [$option, $defaults];
            }
            $storeIds = [];
            foreach (\Mage::app()->getStores() as $store) {
                $storeIds[(string) $store->getCode()] = (int) $store->getId();
            }
            foreach ($input as $code => $label) {
                if (!isset($storeIds[(string) $code])) {
                    $this->addError("storeLabels.{$code}", "'{$code}' is not the code of a store view");
                    continue;
                }
                if (!is_string($label) || mb_strlen($label) > 255) {
                    $this->addError("storeLabels.{$code}", 'label must be a text of at most 255 characters');
                    continue;
                }
                $label = trim(\Mage::helper('catalog')->stripTags($label));
                if ($label === '') {
                    unset($option['labels'][$storeIds[(string) $code]]);
                } else {
                    $option['labels'][$storeIds[(string) $code]] = $label;
                }
            }
        }

        return [$option, $defaults];
    }

    /**
     * Save every option of the attribute, as the admin form posts them all. The resource writes
     * the default value from the options that it saves, so a partial list would reset it.
     *
     * @param array<int|string, array{order: int, labels: array<int, string>}> $options
     * @param list<int|string> $defaults
     * @param list<int> $deleteIds
     */
    private function saveOptions(\Mage_Catalog_Model_Resource_Eav_Attribute $attribute, array $options, array $defaults, array $deleteIds): void
    {
        $payload = ['value' => [], 'order' => [], 'delete' => []];
        foreach ($options as $key => $option) {
            $payload['value'][$key] = $option['labels'];
            $payload['order'][$key] = $option['order'];
            if (in_array($key, $deleteIds, true)) {
                $payload['delete'][$key] = 1;
            }
        }
        $attribute->setData('option', $payload);
        $attribute->setData('default', $defaults);

        $this->safeSave($attribute, 'save product attribute options');
        \Mage::app()->cleanCache([\Mage_Core_Model_Translate::CACHE_TAG, 'eav']);
    }

    /**
     * @return array<int, array{order: int, labels: array<int, string>}> Options by ID, in position order
     */
    private function loadOptions(int $attributeId): array
    {
        $resource = \Mage::getSingleton('core/resource');
        $adapter = $resource->getConnection('core_read');

        $options = [];
        $select = $adapter->select()
            ->from($resource->getTableName('eav/attribute_option'), ['option_id', 'sort_order'])
            ->where('attribute_id = ?', $attributeId)
            ->order(['sort_order ASC', 'option_id ASC']);
        foreach ($adapter->fetchAll($select) as $row) {
            $options[(int) $row['option_id']] = ['order' => (int) $row['sort_order'], 'labels' => []];
        }
        if ($options === []) {
            return [];
        }

        $select = $adapter->select()
            ->from($resource->getTableName('eav/attribute_option_value'), ['option_id', 'store_id', 'value'])
            ->where('option_id IN (?)', array_keys($options));
        foreach ($adapter->fetchAll($select) as $row) {
            $options[(int) $row['option_id']]['labels'][(int) $row['store_id']] = (string) $row['value'];
        }

        return $options;
    }

    /**
     * @return list<int>
     */
    private function defaultOptionIds(\Mage_Catalog_Model_Resource_Eav_Attribute $attribute): array
    {
        $value = (string) $attribute->getData('default_value');
        if ($value === '') {
            return [];
        }
        return array_values(array_map(intval(...), array_filter(explode(',', $value), is_numeric(...))));
    }

    private function loadOptionAttribute(int $id): \Mage_Catalog_Model_Resource_Eav_Attribute
    {
        $attribute = $this->loadAttribute($id);
        $sourceModel = (string) $attribute->getData('source_model');
        if (!in_array($attribute->getFrontendInput(), self::OPTION_INPUT_TYPES, true)
            || ($sourceModel !== '' && $sourceModel !== 'eav/entity_attribute_source_table')
        ) {
            throw new UnprocessableEntityHttpException('Only a select or multiselect attribute with its own option list has options');
        }
        return $attribute;
    }

    /**
     * Load a product attribute or answer 404.
     */
    public function loadAttribute(int $id): \Mage_Catalog_Model_Resource_Eav_Attribute
    {
        /** @var \Mage_Catalog_Model_Resource_Eav_Attribute $attribute */
        $attribute = $this->loadOrFail('catalog/resource_eav_attribute', $id, 'Product attribute not found');
        if ((int) $attribute->getEntityTypeId() !== $this->productEntityTypeId()) {
            throw new NotFoundHttpException('Product attribute not found');
        }
        return $attribute;
    }

    private function attributeExists(string $code): bool
    {
        $attribute = \Mage::getSingleton('eav/config')->getAttribute(\Mage_Catalog_Model_Product::ENTITY, $code);
        return $attribute && $attribute->getId();
    }

    private function productEntityTypeId(): int
    {
        return (int) \Mage::getSingleton('eav/config')->getEntityType(\Mage_Catalog_Model_Product::ENTITY)->getId();
    }

    /**
     * @param array<string, mixed> $body
     */
    private function rejectUnknownFields(array $body): void
    {
        $unknown = array_diff(array_keys($body), self::WRITABLE_FIELDS, array_keys(self::BOOLEAN_FIELDS), self::READ_ONLY_FIELDS);
        if ($unknown !== []) {
            throw new ExtraAttributesException(array_map(strval(...), array_values($unknown)));
        }
    }

    /**
     * @param array<string, mixed> $body
     */
    private function rejectUnknownOptionFields(array $body): void
    {
        $unknown = array_diff(array_keys($body), ['label', 'sortOrder', 'isDefault', 'storeLabels']);
        if ($unknown !== []) {
            throw new ExtraAttributesException(array_map(strval(...), array_values($unknown)));
        }
    }

    private function readInteger(mixed $value): ?int
    {
        if (is_bool($value) || !is_scalar($value)) {
            return null;
        }
        $number = filter_var($value, FILTER_VALIDATE_INT);
        return $number === false ? null : $number;
    }

    private function addError(string $field, string $message): void
    {
        $this->errors->addError($field, $message);
    }

}
