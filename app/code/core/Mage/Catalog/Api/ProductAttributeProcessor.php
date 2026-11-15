<?php

/**
 * Give a write of a product attribute or of its options to the attribute service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Resource_Eav_Attribute as Attribute;
use Mage_Eav_Service_Attribute;
use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\NotNormalizableValueException;

final class ProductAttributeProcessor extends \Maho\ApiPlatform\Processor
{
    private const SCOPES = [
        'store' => Attribute::SCOPE_STORE,
        'global' => Attribute::SCOPE_GLOBAL,
        'website' => Attribute::SCOPE_WEBSITE,
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

    private const TEXT_FIELDS = [
        'attributeCode' => 'attribute_code',
        'frontendInput' => 'frontend_input',
        'frontendLabel' => 'frontend_label',
    ];

    private const INTEGER_FIELDS = [
        'isFilterable' => 'is_filterable',
        'position' => 'position',
    ];

    private const WRITABLE_FIELDS = ['scope', 'isGlobal', 'defaultValue', 'applyTo', 'frontendClass', 'note'];

    /**
     * A client can send back an attribute that it read, so these keys do not cause an error.
     */
    private const READ_ONLY_FIELDS = ['id', 'backendType', 'isUserDefined', 'options', 'extensions', '@context', '@id', '@type'];

    private const OPTION_FIELDS = ['label', 'sortOrder', 'isDefault', 'storeLabels'];

    public function __construct(
        Security $security,
        private readonly ProductAttributeProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductAttribute
    {
        $this->requireUser();
        $service = self::service();
        $id = (int) ($uriVariables['id'] ?? 0);
        $optionId = (int) ($uriVariables['optionId'] ?? 0);
        $name = (string) $operation->getName();

        if ($operation instanceof DeleteOperationInterface) {
            $attribute = self::load($id);
            $oldData = $attribute->getData();
            if ($name === 'product_attribute_option_delete') {
                $service->saveOptions($attribute, [$optionId => ['delete' => true]]);
                $this->logApiActivity('product_attribute', 'update', $oldData, $attribute);
            } else {
                $service->delete($attribute);
                $this->logApiActivity('product_attribute', 'delete', $oldData, null);
            }
            return null;
        }

        $body = $this->parseRequestBody($context['request'] ?? null);

        if ($name === 'product_attribute_option_create' || $name === 'product_attribute_option_update') {
            $attribute = self::load($id);
            $oldData = $attribute->getData();
            $key = $name === 'product_attribute_option_create' ? 'option_new' : $optionId;
            $service->saveOptions($attribute, [$key => $this->readOption($body)]);
            $this->logApiActivity('product_attribute', 'update', $oldData, $attribute);
            return $this->provider->attributeDto(self::load($id));
        }

        self::rejectUnknownFields($body, [...self::WRITABLE_FIELDS, ...array_keys(self::TEXT_FIELDS), ...array_keys(self::BOOLEAN_FIELDS), ...array_keys(self::INTEGER_FIELDS), ...self::READ_ONLY_FIELDS]);
        if ($id > 0) {
            $attribute = self::load($id);
            $oldData = $attribute->getData();
        } else {
            /** @var Attribute $attribute */
            $attribute = $service->newAttribute(Mage_Catalog_Model_Product::ENTITY);
            $attribute->setData('frontend_input', 'text');
            $attribute->setData('is_configurable', 0);
            $oldData = null;
        }

        $this->applyFields($attribute, $body);
        $service->save($attribute);
        $this->logApiActivity('product_attribute', $oldData === null ? 'create' : 'update', $oldData, $attribute);

        return $this->provider->attributeDto(self::load((int) $attribute->getId()));
    }

    private static function service(): Mage_Eav_Service_Attribute
    {
        /** @var Mage_Eav_Service_Attribute */
        return Mage::getService('eav/attribute');
    }

    private static function load(int $id): Attribute
    {
        /** @var Attribute */
        return self::service()->getById($id, Mage_Catalog_Model_Product::ENTITY);
    }

    /**
     * Put the fields of $body into $attribute. The service checks the values.
     *
     * @param array<string, mixed> $body
     * @throws NotNormalizableValueException|BadRequestHttpException for a value of a wrong type
     * @throws \Mage_Core_Exception_Input for an unknown scope, and for a default value of a select attribute
     */
    private function applyFields(Attribute $attribute, array $body): void
    {
        $input = RequestInput::of($body);
        foreach (self::TEXT_FIELDS as $field => $column) {
            $value = $input->string($field);
            if ($value !== null) {
                $attribute->setData($column, $value);
            }
        }
        foreach (self::BOOLEAN_FIELDS as $field => $column) {
            $value = $input->bool($field);
            if ($value !== null) {
                $attribute->setData($column, (int) $value);
            }
        }
        foreach (self::INTEGER_FIELDS as $field => $column) {
            $value = $input->int($field);
            if ($value !== null) {
                $attribute->setData($column, $value);
            }
        }

        $scope = self::readScope($input);
        if ($scope !== null) {
            $attribute->setData('is_global', $scope);
        }

        if (array_key_exists('defaultValue', $body)) {
            $value = $body['defaultValue'];
            if ($value !== null && !is_scalar($value)) {
                throw new BadRequestHttpException('defaultValue must be a text, a number, a boolean or null');
            }
            $value = $value === null ? null : (is_bool($value) ? (string) (int) $value : (string) $value);
            if (in_array($attribute->getFrontendInput(), Mage_Eav_Service_Attribute::OPTION_INPUT_TYPES, true)) {
                // A client can send back the value that it read
                if ((string) $value !== (string) $attribute->getData('default_value')) {
                    throw new \Mage_Core_Exception_Input()->addError('default_value', 'The default of a select or multiselect attribute is set with isDefault on an option');
                }
            } else {
                $attribute->setData('default_value', $value);
            }
        }

        $applyTo = $input->stringList('applyTo');
        if ($applyTo !== null) {
            $attribute->setApplyTo(array_values(array_unique($applyTo)));
        }

        // null clears these two fields
        if (array_key_exists('frontendClass', $body)) {
            $value = $input->string('frontendClass');
            $attribute->setData('frontend_class', $value === '' ? null : $value);
        }
        if (array_key_exists('note', $body)) {
            $attribute->setData('note', $input->string('note'));
        }
    }

    /**
     * Return the is_global value of the scope and isGlobal fields: two forms of the same value. The body may give
     * one or both, but they must agree.
     *
     * @throws \Mage_Core_Exception_Input
     */
    private static function readScope(RequestInput $input): ?int
    {
        $fromScope = null;
        $scope = $input->string('scope');
        if ($scope !== null) {
            if (!array_key_exists($scope, self::SCOPES)) {
                throw new \Mage_Core_Exception_Input()->addError('scope', 'scope must be one of: ' . implode(', ', array_keys(self::SCOPES)));
            }
            $fromScope = self::SCOPES[$scope];
        }
        $fromFlag = $input->int('isGlobal');
        if ($fromScope !== null && $fromFlag !== null && $fromScope !== $fromFlag) {
            throw new \Mage_Core_Exception_Input()->addError('scope', 'scope and isGlobal give two different scopes');
        }
        return $fromScope ?? $fromFlag;
    }

    /**
     * Return the change of one option for Mage_Eav_Service_Attribute::saveOptions().
     *
     * @param array<string, mixed> $body
     * @return array{labels?: array<int, string>, order?: int, default?: bool}
     * @throws \Mage_Core_Exception_Input for a store view code that does not exist
     */
    private function readOption(array $body): array
    {
        self::rejectUnknownFields($body, self::OPTION_FIELDS);
        $input = RequestInput::of($body);
        $change = [];
        $label = $input->string('label');
        if ($label !== null) {
            $change['labels'][0] = $label;
        }
        $order = $input->int('sortOrder');
        if ($order !== null) {
            $change['order'] = $order;
        }
        $default = $input->bool('isDefault');
        if ($default !== null) {
            $change['default'] = $default;
        }
        $labels = $input->array('storeLabels');
        if ($labels !== null) {
            if ($labels !== [] && array_is_list($labels)) {
                throw new BadRequestHttpException('storeLabels must be an object of store view code to label');
            }
            $storeIds = [];
            foreach (Mage::app()->getStores() as $store) {
                $storeIds[(string) $store->getCode()] = (int) $store->getId();
            }
            $labelInput = RequestInput::of($labels, 'storeLabels');
            $errors = new \Mage_Core_Exception_Input();
            foreach (array_keys($labels) as $code) {
                $label = $labelInput->string((string) $code);
                if ($label === null) {
                    continue;
                }
                if (!isset($storeIds[(string) $code])) {
                    $errors->addError("storeLabels.{$code}", "'{$code}' is not the code of a store view");
                    continue;
                }
                $change['labels'][$storeIds[(string) $code]] = $label;
            }
            $errors->throwIfErrors();
        }
        return $change;
    }

    /**
     * @param array<string, mixed> $body
     * @param list<string> $fields
     */
    private static function rejectUnknownFields(array $body, array $fields): void
    {
        $unknown = array_diff(array_keys($body), $fields);
        if ($unknown !== []) {
            throw new ExtraAttributesException(array_map(strval(...), array_values($unknown)));
        }
    }
}
