<?php

/**
 * Load, create, save, move and delete categories for the admin pages, the API and the importer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

use Mage_Catalog_Model_Category as Category;

class Mage_Catalog_Service_Category
{
    /** The attributes that take the value of the configuration when they hold null. */
    public const USE_CONFIG_CODES = ['available_sort_by', 'default_sort_by', 'filter_price_range'];

    /** The highest ID that the SMALLINT column cms_block.block_id holds. PostgreSQL refuses a larger value. */
    private const MAX_CMS_BLOCK_ID = 32767;

    private const MAX_ENTITY_ID = 2147483647;

    /**
     * Load the category $id with the values of the store view $storeId.
     *
     * @throws Mage_Core_Exception_NoSuchEntity
     */
    public function getById(int $id, int $storeId = Mage_Core_Model_App::ADMIN_STORE_ID): Category
    {
        /** @var Category $category */
        $category = Mage::getModel('catalog/category');
        $category->setStoreId($storeId);
        if ($id > 0 && $id <= self::MAX_ENTITY_ID) {
            $category->load($id);
        }
        if (!$category->getId()) {
            throw new Mage_Core_Exception_NoSuchEntity(Mage::helper('catalog')->__('Category not found'));
        }
        return $category;
    }

    /**
     * Return a new category under $parentId, with the values of the admin scope and the default attribute set.
     *
     * @param list<int>|null $storeIds with a list, the parent must be in the tree of one of these store views
     * @throws Mage_Core_Exception when the parent does not exist
     * @throws Mage_Core_Exception_Forbidden when the parent is outside the trees of $storeIds
     */
    public function newCategory(int $parentId, ?array $storeIds = null): Category
    {
        $parent = $this->getParent($parentId);
        if (!$parent->getId()) {
            throw new Mage_Core_Exception(Mage::helper('catalog')->__('Parent category not found'));
        }
        $this->assertInStores($parent, $storeIds);

        /** @var Category $category */
        $category = Mage::getModel('catalog/category');
        return $category->setStoreId(Mage_Core_Model_App::ADMIN_STORE_ID)
            ->setAttributeSetId((int) $category->getDefaultAttributeSetId())
            ->setParentId((int) $parent->getId())
            ->setPath($parent->getPath());
    }

    /**
     * Return the root categories of the store views $storeIds, or null when $storeIds is null. A store view
     * that does not exist gives no root.
     *
     * @param list<int>|null $storeIds
     * @return list<int>|null
     */
    public function getRootIds(?array $storeIds): ?array
    {
        if ($storeIds === null) {
            return null;
        }
        $rootIds = [];
        foreach (Mage::app()->getStores(true) as $store) {
            if (in_array((int) $store->getId(), $storeIds, true)) {
                $rootIds[] = (int) $store->getRootCategoryId();
            }
        }
        return array_values(array_unique($rootIds));
    }

    /**
     * Tell whether $category is one of the root categories $rootIds, or a category below one of them.
     *
     * @param list<int> $rootIds
     */
    public function isInTrees(Category $category, array $rootIds): bool
    {
        return array_intersect($rootIds, array_map(intval(...), explode('/', (string) $category->getPath()))) !== [];
    }

    /**
     * @param list<int>|null $storeIds
     * @throws Mage_Core_Exception_Forbidden when $category is outside the trees of the store views $storeIds
     */
    public function assertInStores(Category $category, ?array $storeIds): void
    {
        $rootIds = $this->getRootIds($storeIds);
        if ($rootIds !== null && !$this->isInTrees($category, $rootIds)) {
            throw new Mage_Core_Exception_Forbidden(Mage::helper('catalog')->__("Access denied for this category's store"));
        }
    }

    /**
     * Remove the store view value of each attribute in $codes, so that the store view shows the default value.
     *
     * @param list<string> $codes
     * @throws Mage_Core_Exception_Input for the admin scope, an unknown attribute and a global attribute
     */
    public function useDefault(Category $category, array $codes): void
    {
        $errors = new Mage_Core_Exception_Input();
        if ($codes !== [] && (int) $category->getStoreId() === Mage_Core_Model_App::ADMIN_STORE_ID) {
            $errors->addError('use_default', Mage::helper('catalog')->__('useDefault requires a store context (?store=): global values have no default to revert to.'));
            $errors->throwIfErrors();
        }
        foreach ($codes as $code) {
            $attribute = Mage::getSingleton('eav/config')->getAttribute(Category::ENTITY, $code);
            if (!$attribute instanceof Mage_Catalog_Model_Resource_Eav_Attribute || !$attribute->getId() || $attribute->getBackend()->isStatic()) {
                $errors->addError('use_default', Mage::helper('catalog')->__('Unknown attribute in useDefault: %s', $code));
            } elseif ($attribute->isScopeGlobal()) {
                $errors->addError('use_default', Mage::helper('catalog')->__("Attribute '%s' is global scope and has no store override to revert.", $code));
            } else {
                $category->setData($code, false);
            }
        }
        $errors->throwIfErrors();
    }

    /**
     * Set the position of each product of $positions, by product ID. A product that is not in the category is skipped:
     * the product decides its categories.
     *
     * @param array<int|string, int|string> $positions
     * @throws Mage_Core_Exception_Input for a new category, which has no products yet
     */
    public function setProductPositions(Category $category, array $positions): void
    {
        if (!$category->getId()) {
            throw new Mage_Core_Exception_Input()->addError('product_positions', Mage::helper('catalog')->__(
                'productPositions applies to products already assigned to the category: create it first, assign products, then set their positions.',
            ));
        }
        $existing = $category->getProductsPosition();
        $assigned = array_intersect_key(array_map(intval(...), $positions), $existing);
        if ($assigned !== []) {
            $category->setPostedProducts(array_replace($existing, $assigned));
        }
    }

    /**
     * Check and save $category. The transport puts its input into the category first. An attribute of
     * USE_CONFIG_CODES that holds null takes the value of the configuration.
     *
     * @throws Mage_Core_Exception_Input with one error for each invalid attribute
     * @throws Mage_Core_Exception when the default sort order is not one of the available sort orders
     */
    public function save(Category $category): Category
    {
        $availableSortBy = $category->getData('available_sort_by');
        if (is_string($availableSortBy) && $availableSortBy !== '') {
            $category->setData('available_sort_by', explode(',', $availableSortBy));
        }
        $this->validate($category);
        $category->save();
        return $category;
    }

    /**
     * Move $category under $parentId, after the category $afterId or first. A URL that changes keeps a redirect
     * when the configuration asks for it.
     *
     * @param list<int>|null $storeIds with a list, the new parent must be in the tree of one of these store views
     * @throws Mage_Core_Exception when the parent does not exist or is in the category
     * @throws Mage_Core_Exception_Forbidden when the new parent is outside the trees of $storeIds
     * @throws Mage_Core_Exception_Conflict for a root category
     */
    public function move(Category $category, int $parentId, ?int $afterId = null, ?array $storeIds = null): Category
    {
        $parent = $this->getParent($parentId);
        if ($parent->getId()) {
            $this->assertInStores($parent, $storeIds);
        }
        if (!$category->hasData('save_rewrites_history')) {
            $category->setData('save_rewrites_history', Mage::helper('catalog')->shouldSaveUrlRewritesHistory());
        }
        return $category->move($parentId, $afterId);
    }

    /**
     * @throws Mage_Core_Exception_Conflict for the tree root and the root category of a store
     */
    public function delete(Category $category): void
    {
        Mage::dispatchEvent('catalog_controller_category_delete', ['category' => $category]);
        $wasSecure = (bool) Mage::registry('isSecureArea');
        if (!$wasSecure) {
            Mage::register('isSecureArea', true);
        }
        try {
            $category->delete();
        } finally {
            if (!$wasSecure) {
                Mage::unregister('isSecureArea');
            }
        }
    }

    private function getParent(int $parentId): Category
    {
        /** @var Category $parent */
        $parent = Mage::getModel('catalog/category');
        if ($parentId > 0 && $parentId <= self::MAX_ENTITY_ID) {
            $parent->load($parentId);
        }
        return $parent;
    }

    /**
     * Check each value that changed, then the required attributes, the backends of the attributes and the name.
     *
     * @throws Mage_Core_Exception_Input
     */
    private function validate(Category $category): void
    {
        $errors = new Mage_Core_Exception_Input();
        foreach ($this->getValueErrors($category) as $code => $message) {
            $errors->addError($code, $message);
        }
        $errors->throwIfErrors();

        $category->setData('use_post_data_config', array_values(array_filter(
            self::USE_CONFIG_CODES,
            fn(string $code): bool => $category->getData($code) === null,
        )));
        try {
            $result = $category->validate();
        } catch (Mage_Eav_Model_Entity_Attribute_Exception $e) {
            $result = [(string) $e->getAttributeCode() => $e->getMessage()];
        } finally {
            $category->unsetData('use_post_data_config');
        }
        $result = $result === true ? [] : $result;
        if (is_string($category->getName()) && trim($category->getName()) === '') {
            $result['name'] = true;
        }

        foreach ($result as $code => $error) {
            $errors->addError((string) $code, $error === true
                ? Mage::helper('catalog')->__('Attribute "%s" is required.', $category->getResource()->getAttribute($code)->getFrontend()->getLabel())
                : (string) $error);
        }
        $errors->throwIfErrors();
    }

    /**
     * Check the sort orders, the landing page and the image name, when they changed.
     *
     * @return array<string, string> the message for each invalid attribute
     */
    private function getValueErrors(Category $category): array
    {
        $errors = [];
        $sortBy = array_keys(Mage::getSingleton('catalog/config')->getAttributeUsedForSortByArray());
        $codes = match (true) {
            !$category->dataHasChangedFor('available_sort_by') => [],
            is_array($category->getData('available_sort_by')) => $category->getData('available_sort_by'),
            default => [],
        };
        $default = $category->dataHasChangedFor('default_sort_by') ? $category->getData('default_sort_by') : null;
        foreach (['available_sort_by' => $codes, 'default_sort_by' => is_string($default) && $default !== '' ? [$default] : []] as $field => $values) {
            foreach ($values as $value) {
                if (!is_string($value) || !in_array($value, $sortBy, true)) {
                    $errors[$field] = Mage::helper('catalog')->__('Invalid sort-by code "%s". Valid: %s', is_scalar($value) ? (string) $value : gettype($value), implode(', ', $sortBy));
                    break;
                }
            }
        }

        $landingPage = $category->getData('landing_page');
        if ($category->dataHasChangedFor('landing_page') && is_numeric($landingPage) && (int) $landingPage > 0
            && ((int) $landingPage > self::MAX_CMS_BLOCK_ID || !Mage::getModel('cms/block')->load((int) $landingPage)->getId())
        ) {
            $errors['landing_page'] = Mage::helper('catalog')->__('CMS block %s not found', (int) $landingPage);
        }

        $image = $category->getData('image');
        if ($category->dataHasChangedFor('image') && is_string($image) && $image !== '') {
            if (str_contains($image, '/') || str_contains($image, '\\') || str_contains($image, '..') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $image)) {
                $errors['image'] = Mage::helper('catalog')->__('image must be a bare filename (stored under media/catalog/category), with no path, no ".." and no scheme');
            } elseif (!in_array(strtolower(pathinfo($image, PATHINFO_EXTENSION)), \Maho\Io\File::ALLOWED_IMAGES_EXTENSIONS, true)) {
                $errors['image'] = Mage::helper('catalog')->__('image must have a valid image file extension');
            }
        }
        return $errors;
    }
}
