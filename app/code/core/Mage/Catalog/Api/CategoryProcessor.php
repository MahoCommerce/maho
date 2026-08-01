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
use Mage_Core_Model_App;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\ActivityLogTrait;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Category State Processor.
 *
 * Handles create, update, and delete operations for categories.
 * Requires JWT authentication with categories/write or categories/delete permission.
 */
final class CategoryProcessor extends \Maho\ApiPlatform\Processor
{
    use ActivityLogTrait;

    /**
     * Upper bound for ids stored in SMALLINT columns (cms_block.block_id). PostgreSQL has no
     * unsigned integers, so comparing such a column against a larger value is a query error
     * rather than a non-match: out-of-range ids must be rejected before they reach SQL.
     */
    private const MAX_SMALLINT = 32767;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Category
    {
        $user = $this->getAuthorizedUser();

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'categories/delete');
            return $this->handleDelete((int) $uriVariables['id'], $user);
        }

        $this->requirePermission($user, 'categories/write');

        assert($data instanceof Category);

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate((int) $uriVariables['id'], $data, $user);
        }

        return $this->handleCreate($data, $user);
    }

    private function handleCreate(Category $data, ApiUser $user): Category
    {
        StoreContext::ensureStore();

        if (empty($data->name)) {
            throw new BadRequestHttpException('Name is required');
        }

        $parentId = $data->parentId;
        if ($parentId === null) {
            $parentId = StoreContext::getRootCategoryId();
        }

        /** @var Mage_Catalog_Model_Category $parentCategory */
        $parentCategory = Mage::getModel('catalog/category')->load($parentId);
        if (!$parentCategory->getId()) {
            throw new BadRequestHttpException('Parent category not found');
        }

        // A store-restricted token may only create under a category in one of
        // its own stores' root trees. The new category inherits the parent's
        // path, so authorizing the parent scopes the child too.
        $this->authorizeCategoryStore($parentCategory, $user);

        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::getModel('catalog/category');

        $category->setData([
            'name' => $data->name,
            'is_active' => ($data->isActive ?? true) ? 1 : 0,
            'include_in_menu' => ($data->includeInMenu ?? true) ? 1 : 0,
            'parent_id' => $parentId,
            'path' => $parentCategory->getPath(),
        ]);

        // After setData(), which replaces the whole data array including store_id.
        $this->useAdminScope($category);

        if ($data->urlKey !== null) {
            $category->setUrlKey($data->urlKey);
        } else {
            $category->setUrlKey($category->formatUrlKey($data->name));
        }

        $this->applyCategoryData($category, $data);

        $this->safeSave($category, 'create category');

        $this->logApiActivity('catalog/category', 'create', null, $category, $user);

        return $this->refreshDto($category, $data);
    }

    private function handleUpdate(int $id, Category $data, ApiUser $user): Category
    {
        StoreContext::ensureStore();

        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::getModel('catalog/category');

        $this->useAdminScope($category);

        $category->load($id);

        if (!$category->getId()) {
            throw new NotFoundHttpException('Category not found');
        }

        $this->authorizeCategoryStore($category, $user);

        $oldData = $category->getData();

        if ($data->name !== '') {
            $category->setName($data->name);
        }

        if ($data->isActive !== null) {
            $category->setIsActive($data->isActive ? 1 : 0);
        }
        if ($data->includeInMenu !== null) {
            $category->setData('include_in_menu', $data->includeInMenu ? 1 : 0);
        }

        if ($data->urlKey !== null) {
            $category->setUrlKey($data->urlKey);
        }

        if ($data->parentId !== null && $data->parentId !== (int) $category->getParentId()) {
            $this->moveCategory($category, $data->parentId, $user);
        }

        $this->applyCategoryData($category, $data);

        $this->safeSave($category, 'update category');

        $this->logApiActivity('catalog/category', 'update', $oldData, $category, $user);

        return $this->refreshDto($category, $data);
    }

    private function handleDelete(int $id, ApiUser $user): null
    {
        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::getModel('catalog/category')->load($id);

        if (!$category->getId()) {
            throw new NotFoundHttpException('Category not found');
        }

        $this->authorizeCategoryStore($category, $user);

        // Prevent deletion of root categories
        if ((int) $category->getLevel() <= 1) {
            throw new BadRequestHttpException('Cannot delete root categories');
        }

        $oldData = $category->getData();

        $this->secureAreaDelete($category, 'delete category');

        $this->logApiActivity('catalog/category', 'delete', $oldData, null, $user);

        return null;
    }

    private function applyCategoryData(Mage_Catalog_Model_Category $category, Category $data): void
    {
        if ($data->description !== null) {
            $category->setDescription($data->description);
        }
        if ($data->position !== null) {
            $category->setPosition($data->position);
        }
        if ($data->metaTitle !== null) {
            $category->setData('meta_title', $data->metaTitle);
        }
        if ($data->metaDescription !== null) {
            $category->setData('meta_description', $data->metaDescription);
        }
        if ($data->metaKeywords !== null) {
            $category->setData('meta_keywords', $data->metaKeywords);
        }
        if ($data->displayMode !== null) {
            $category->setData('display_mode', $data->displayMode);
        }
        if ($data->pageLayout !== null) {
            $category->setData('page_layout', $data->pageLayout);
        }
        if ($data->metaRobots !== null) {
            $category->setData('meta_robots', $data->metaRobots !== '' ? $data->metaRobots : null);
        }
        if ($data->isAnchor !== null) {
            $category->setData('is_anchor', $data->isAnchor ? 1 : 0);
        }
        if ($data->availableSortBy !== null) {
            $category->setData('available_sort_by', $this->validateAttributeValue('available_sort_by', $data->availableSortBy));
        }
        if ($data->defaultSortBy !== null) {
            $category->setData('default_sort_by', $this->validateAttributeValue('default_sort_by', $data->defaultSortBy));
        }
        if ($data->landingPageId !== null) {
            $category->setData('landing_page', $this->validateAttributeValue('landing_page', $data->landingPageId));
        }
        if ($data->image !== null) {
            $category->setData('image', $this->validateAttributeValue('image', $data->image));
        }
        if ($data->customDesign !== null) {
            $category->setData('custom_design', $data->customDesign !== '' ? $data->customDesign : null);
        }
        if ($data->customDesignFrom !== null) {
            $category->setData('custom_design_from', $this->validateAttributeValue('custom_design_from', $data->customDesignFrom));
        }
        if ($data->customDesignTo !== null) {
            $category->setData('custom_design_to', $this->validateAttributeValue('custom_design_to', $data->customDesignTo));
        }
        if ($data->customLayoutUpdate !== null) {
            $category->setData('custom_layout_update', $this->validateAttributeValue('custom_layout_update', $data->customLayoutUpdate));
        }
        if ($data->customUseParentSettings !== null) {
            $category->setData('custom_use_parent_settings', $data->customUseParentSettings ? 1 : 0);
        }
        if ($data->customApplyToProducts !== null) {
            $category->setData('custom_apply_to_products', $data->customApplyToProducts ? 1 : 0);
        }
        if ($data->filterPriceRange !== null) {
            $category->setData('filter_price_range', $data->filterPriceRange > 0 ? $data->filterPriceRange : null);
        }
        if (!empty($data->customAttributesWrite)) {
            $this->applyCustomAttributes($category, $data->customAttributesWrite);
        }
        if (!empty($data->productPositions)) {
            if (!$category->getId()) {
                throw new BadRequestHttpException(
                    'productPositions applies to products already assigned to the category: create it first, '
                    . 'assign products, then set their positions.',
                );
            }
            $this->applyProductPositions($category, $data->productPositions);
        }

        // The sortby backend implodes an array on save and wipes any non-array
        // value to ''. The EAV backend explodes the stored string on load, so the
        // guard is a no-op on the load→save path; it protects values that reach
        // the model as a comma string (flat resource, customAttributesWrite).
        $availableSortBy = $category->getData('available_sort_by');
        if (is_string($availableSortBy) && $availableSortBy !== '') {
            $category->setData('available_sort_by', explode(',', $availableSortBy));
        }
    }

    /**
     * Per-attribute validation shared by the dedicated DTO fields and the generic
     * customAttributesWrite bag, so the bag cannot bypass it.
     */
    private function validateAttributeValue(string $code, mixed $value): mixed
    {
        return match ($code) {
            'image' => $this->validateImageFilename($this->scalarValue($code, $value)),
            'landing_page' => $this->validateLandingPage((int) $this->scalarValue($code, $value)),
            'available_sort_by' => $this->validateSortByCodes($this->toSortByCodes($value)),
            'default_sort_by' => $this->validateDefaultSortBy($this->scalarValue($code, $value)),
            'custom_design_from', 'custom_design_to' => $this->normalizeDateInput($this->scalarValue($code, $value), $code),
            'custom_layout_update' => $this->validateLayoutUpdate($this->scalarValue($code, $value)),
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
     * @return string[]
     */
    private function toSortByCodes(mixed $value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }
        if (is_string($value)) {
            return array_values(array_filter(array_map('trim', explode(',', $value)), static fn(string $code): bool => $code !== ''));
        }
        throw new BadRequestHttpException('available_sort_by must be an array of sort-by codes');
    }

    private function validateDefaultSortBy(string $code): ?string
    {
        if ($code === '') {
            return null;
        }
        $this->validateSortByCodes([$code]);
        return $code;
    }

    /**
     * Layout updates are executed when the storefront renders the category, so the
     * API must apply the same validator the admin form does (blocked templates,
     * disallowed blocks, helper attributes) instead of storing arbitrary XML.
     */
    private function validateLayoutUpdate(string $xml): ?string
    {
        if (trim($xml) === '') {
            return null;
        }

        $this->requireAdminOrApiUser('Layout updates require admin or API access');

        /** @var \Mage_Adminhtml_Model_LayoutUpdate_Validator $validator */
        $validator = Mage::getModel('adminhtml/layoutUpdate_validator');
        try {
            $isValid = $validator->isValid($xml);
        } catch (\Throwable) {
            $isValid = false;
        }

        if (!$isValid) {
            $messages = implode(' ', $validator->getMessages());
            throw new BadRequestHttpException(trim('customLayoutUpdate is not a valid layout update. ' . $messages));
        }

        return $xml;
    }

    /**
     * @param string[] $codes
     * @return string[]
     */
    private function validateSortByCodes(array $codes): array
    {
        $valid = array_keys(Mage::getSingleton('catalog/config')->getAttributeUsedForSortByArray());
        foreach ($codes as $code) {
            if (!is_string($code) || !in_array($code, $valid, true)) {
                $label = is_scalar($code) ? (string) $code : gettype($code);
                throw new BadRequestHttpException(
                    'Invalid sort-by code "' . $label . '". Valid: ' . implode(', ', $valid),
                );
            }
        }
        return array_values($codes);
    }

    private function validateLandingPage(int $blockId): ?int
    {
        if ($blockId <= 0) {
            return null;
        }
        if ($blockId > self::MAX_SMALLINT) {
            throw new BadRequestHttpException("CMS block {$blockId} not found");
        }
        $block = Mage::getModel('cms/block')->load($blockId);
        if (!$block->getId()) {
            throw new BadRequestHttpException("CMS block {$blockId} not found");
        }
        return $blockId;
    }

    /**
     * Accept a bare media filename as stored by the admin (media/catalog/category).
     */
    private function validateImageFilename(string $image): ?string
    {
        if ($image === '') {
            return null;
        }
        if (str_contains($image, '/') || str_contains($image, '\\') || str_contains($image, '..')) {
            throw new BadRequestHttpException('image must be a bare filename (stored under media/catalog/category)');
        }
        $extension = strtolower(pathinfo($image, PATHINFO_EXTENSION));
        if (!in_array($extension, \Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS, true)) {
            throw new BadRequestHttpException('image must have a valid image file extension');
        }
        return $image;
    }

    private function normalizeDateInput(string $value, string $field): ?string
    {
        if ($value === '') {
            return null;
        }
        try {
            return Mage::app()->getLocale()->formatDateForDb($value, withTime: false);
        } catch (\Throwable) {
            throw new BadRequestHttpException("{$field} must be a valid date (Y-m-d)");
        }
    }

    /**
     * Codes handled by dedicated DTO fields or structural/system columns. They
     * must never be written through the generic customAttributesWrite bag.
     */
    private const PROTECTED_ATTRIBUTE_CODES = [
        'entity_id', 'entity_type_id', 'attribute_set_id', 'parent_id', 'path',
        'level', 'position', 'children_count', 'children', 'all_children',
        'url_path', 'created_at', 'updated_at',
    ];

    /**
     * Apply arbitrary EAV attribute values supplied via customAttributesWrite.
     *
     * Protected/system codes are rejected outright; unknown codes (not real
     * catalog_category EAV attributes) are skipped silently so a typo can't
     * inject an arbitrary column. Codes that have a validated dedicated DTO
     * field run through the same validation, the bag is not a way around it.
     *
     * @param array<string, mixed> $attributes
     */
    private function applyCustomAttributes(Mage_Catalog_Model_Category $category, array $attributes): void
    {
        $eavConfig = Mage::getSingleton('eav/config');

        foreach ($attributes as $code => $value) {
            $code = (string) $code;

            if (in_array($code, self::PROTECTED_ATTRIBUTE_CODES, true)) {
                throw new BadRequestHttpException(
                    "Attribute '{$code}' cannot be set via customAttributes; use the dedicated field.",
                );
            }

            $attribute = $eavConfig->getAttribute(Mage_Catalog_Model_Category::ENTITY, $code);
            if (!$attribute || !$attribute->getId()) {
                // Unknown attribute, skip silently.
                continue;
            }

            $category->setData($code, $this->validateAttributeValue($code, $value));
        }
    }

    /**
     * Update positions of products already assigned to the category. Products
     * not currently assigned are ignored; assignment itself is managed on the
     * product resource (categoryIds).
     *
     * @param array<int|string, mixed> $positions
     */
    private function applyProductPositions(Mage_Catalog_Model_Category $category, array $positions): void
    {
        $existing = $category->getProductsPosition();

        $changed = false;
        foreach ($positions as $productId => $position) {
            $productId = (int) $productId;
            if (!array_key_exists($productId, $existing)) {
                continue;
            }
            $existing[$productId] = (int) $position;
            $changed = true;
        }

        if ($changed) {
            $category->setPostedProducts($existing);
        }
    }

    private function moveCategory(Mage_Catalog_Model_Category $category, int $newParentId, ApiUser $user): void
    {
        /** @var Mage_Catalog_Model_Category $newParent */
        $newParent = Mage::getModel('catalog/category')->load($newParentId);
        if (!$newParent->getId()) {
            throw new BadRequestHttpException('New parent category not found');
        }

        // The destination must also be within the caller's store tree, otherwise
        // a store-restricted token could move a category into another store's root.
        $this->authorizeCategoryStore($newParent, $user);

        try {
            $category->move($newParentId, 0);
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to move category: ' . $e->getMessage());
        }
    }

    /**
     * Authorize a category against a store-restricted API user: the category
     * must live under the root-category tree of at least one store the user may
     * access. No-op for unrestricted users (getAllowedStoreIds() === null).
     * Mirrors the root-tree scoping the read-side CategoryProvider applies, so a
     * store-scoped write token cannot create/update/delete/move categories
     * belonging to a store it was never granted.
     */
    private function authorizeCategoryStore(Mage_Catalog_Model_Category $category, ApiUser $user): void
    {
        $allowedStoreIds = $user->getAllowedStoreIds();
        if ($allowedStoreIds === null) {
            return;
        }

        $allowedRootIds = [];
        foreach ($allowedStoreIds as $storeId) {
            $allowedRootIds[] = (int) Mage::app()->getStore($storeId)->getRootCategoryId();
        }

        // The store root and every descendant carry the root id in their path.
        $pathIds = array_map('intval', explode('/', (string) $category->getPath()));
        if (array_intersect($pathIds, $allowedRootIds) === []) {
            throw new AccessDeniedHttpException("Access denied for this category's store");
        }
    }


    /**
     * Category writes load and save in the admin scope, the scope every store view
     * falls back to. A store-scoped write only ever adds an override on top of it:
     * values the caller never mentioned stay invisible to the other stores, and a
     * cleared value keeps resolving to the global row the override was layered on,
     * with no "use default value" toggle in the API to remove it. `?store=` still
     * scopes reads and the root-tree authorization check.
     */
    private function useAdminScope(Mage_Catalog_Model_Category $category): void
    {
        $category->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID);
    }

    private function refreshDto(Mage_Catalog_Model_Category $category, Category $data): Category
    {
        $data->id = (int) $category->getId();
        $data->path = $category->getPath();
        $data->level = (int) $category->getLevel();
        $data->createdAt = $category->getCreatedAt();
        $data->updatedAt = $category->getUpdatedAt();
        return $data;
    }

}
