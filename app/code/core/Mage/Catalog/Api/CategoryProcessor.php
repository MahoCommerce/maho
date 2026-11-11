<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Mage;
use Mage_Catalog_Model_Category;
use Mage_Catalog_Service_Category;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\CustomAttributes;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 * Give a category write to the category service. Requires the categories/write or the categories/delete permission.
 */
final class CategoryProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Category
    {
        $user = $this->requireUser();
        $service = self::service();

        if ($operation instanceof DeleteOperationInterface) {
            $category = $service->getById((int) $uriVariables['id']);
            $service->assertInStores($category, $user->getAllowedStoreIds());
            $oldData = $category->getData();
            $service->delete($category);
            $this->logApiActivity('catalog/category', 'delete', $oldData, null);
            return null;
        }

        assert($data instanceof Category);
        StoreContext::ensureStore();

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate((int) $uriVariables['id'], $data, $user);
        }

        $category = $service->newCategory($data->parentId ?? (int) StoreContext::getRootCategoryId(), $user->getAllowedStoreIds());
        $category->setName($data->name)
            ->setIsActive(($data->isActive ?? true) ? 1 : 0)
            ->setIncludeInMenu(($data->includeInMenu ?? true) ? 1 : 0)
            ->setUrlKey($data->urlKey);
        $this->applyCategoryData($category, $data);
        $service->save($category, 'customAttributes.');
        $this->logApiActivity('catalog/category', 'create', null, $category);

        return $this->refreshDto($category);
    }

    private static function service(): Mage_Catalog_Service_Category
    {
        /** @var Mage_Catalog_Service_Category */
        return Mage::getService('catalog/category');
    }

    private function handleUpdate(int $id, Category $data, ApiUser $user): Category
    {
        $service = self::service();
        $storeId = $this->resolveWriteScope($user);
        $category = $service->getById($id, $storeId);
        $service->assertInStores($category, $user->getAllowedStoreIds());
        $oldData = $category->getData();

        if ($data->name !== '') {
            $category->setName($data->name);
        }
        if ($data->isActive !== null) {
            $category->setIsActive($data->isActive ? 1 : 0);
        }
        if ($data->includeInMenu !== null) {
            $category->setIncludeInMenu($data->includeInMenu ? 1 : 0);
        }
        if ($data->urlKey !== null) {
            $category->setUrlKey($data->urlKey);
        }
        if ($data->parentId !== null && $data->parentId !== (int) $category->getParentId()) {
            $service->move($category, $data->parentId, storeIds: $user->getAllowedStoreIds());
        }

        $this->applyCategoryData($category, $data);
        $service->useDefault($category, array_map(strval(...), $data->useDefault ?? []));

        // A store view write stores only the fields of the request.
        $inherited = StoreScopeWrite::keepInheritedValues($category, $oldData, StoreScopeWrite::requestedCodes($data));
        $service->save($category, 'customAttributes.');
        StoreScopeWrite::restoreInheritedValues($category, $oldData, $inherited);
        $this->logApiActivity('catalog/category', 'update', $oldData, $category);

        return $this->refreshDto($category);
    }

    private function applyCategoryData(Mage_Catalog_Model_Category $category, Category $data): void
    {
        $category->assignAttributeSet($data->attributeSetId);
        $fields = [
            'description' => $data->description,
            'position' => $data->position,
            'meta_title' => $data->metaTitle,
            'meta_description' => $data->metaDescription,
            'meta_keywords' => $data->metaKeywords,
            'display_mode' => $data->displayMode,
            'page_layout' => $data->pageLayout,
            'meta_robots' => $data->metaRobots,
            'is_anchor' => $data->isAnchor,
            'available_sort_by' => $data->availableSortBy,
            'default_sort_by' => $data->defaultSortBy,
            'landing_page' => $data->landingPageId,
            'image' => $data->image,
            'custom_design' => $data->customDesign,
            'custom_design_from' => $data->customDesignFrom,
            'custom_design_to' => $data->customDesignTo,
            'custom_layout_update' => $data->customLayoutUpdate,
            'custom_use_parent_settings' => $data->customUseParentSettings,
            'custom_apply_to_products' => $data->customApplyToProducts,
            'filter_price_range' => $data->filterPriceRange,
        ];
        foreach ($fields as $code => $value) {
            if ($value !== null) {
                $category->setData($code, $this->toModelValue($code, $value));
            }
        }
        $this->applyCustomAttributes($category, $data->customAttributes ?? []);
        if (!empty($data->productPositions)) {
            self::service()->setProductPositions($category, $data->productPositions);
        }
    }

    /**
     * Convert a value of the request into the value of the model attribute $code: an empty value clears the
     * attribute. The service checks the value.
     */
    private function toModelValue(string $code, mixed $value): mixed
    {
        return match ($code) {
            'is_anchor', 'custom_use_parent_settings', 'custom_apply_to_products' => $value ? 1 : 0,
            'filter_price_range' => (float) $value > 0 ? (float) $value : null,
            'landing_page' => (int) $this->scalarValue($code, $value) > 0 ? (int) $value : null,
            'available_sort_by' => $this->toSortByCodes($value),
            'custom_design_from', 'custom_design_to' => $this->normalizeDateInput($this->scalarValue($code, $value), $code),
            'image', 'default_sort_by', 'meta_robots', 'custom_design' => $this->scalarValue($code, $value) === '' ? null : (string) $value,
            'custom_layout_update' => trim($this->scalarValue($code, $value)) === '' ? null : (string) $value,
            default => $value,
        };
    }

    private function scalarValue(string $code, mixed $value): string
    {
        if ($value !== null && !is_scalar($value)) {
            throw new BadRequestHttpException("Attribute '{$code}' must be a scalar value");
        }
        return (string) $value;
    }

    /**
     * @return list<mixed>
     */
    private function toSortByCodes(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value)) {
            return array_values(array_filter(array_map(trim(...), explode(',', $value)), static fn(string $code): bool => $code !== ''));
        }
        throw new BadRequestHttpException('available_sort_by must be an array of sort-by codes');
    }

    private function normalizeDateInput(string $value, string $field): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return Mage::app()->getLocale()->formatDateForDb($value, withTime: false);
        } catch (\Throwable) {
            throw new \Mage_Core_Exception_Input()->addError($field, "{$field} must be a valid date (Y-m-d)");
        }
    }

    /**
     * Apply the values of the customAttributes map: the user-defined attributes of the category. A
     * user-defined attribute must be in the set of the category, because the nightly
     * eav_clean_orphaned_records cron deletes a value whose attribute is not in the set.
     *
     * @param array<string, mixed> $attributes
     * @throws \Mage_Core_Exception_Input
     */
    private function applyCustomAttributes(Mage_Catalog_Model_Category $category, array $attributes): void
    {
        if ($attributes === []) {
            return;
        }
        CustomAttributes::apply(
            $category,
            $attributes,
            CustomAttributes::userDefined(Mage_Catalog_Model_Category::ENTITY),
            array_keys($category->getAttributes()),
        );
    }

    /**
     * Map the response from a new load in the same store scope, as a GET does: the save changes several values,
     * and useDefault leaves the EAV false value in the model.
     */
    private function refreshDto(Mage_Catalog_Model_Category $category): Category
    {
        $fresh = self::service()->getById((int) $category->getId(), (int) $category->getStoreId());
        return new CategoryProvider($this->security)->mapToDto($fresh, withStoreOverrides: true);
    }
}
