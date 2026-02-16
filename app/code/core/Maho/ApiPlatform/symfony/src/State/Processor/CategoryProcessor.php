<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Catalog_Model_Category;
use Maho\ApiPlatform\ApiResource\Category;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Category State Processor
 *
 * Handles create, update, and delete operations for categories.
 * Requires JWT authentication with categories/write or categories/delete permission.
 *
 * @implements ProcessorInterface<Category, Category|null>
 */
final class CategoryProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
    ) {}

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

        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::getModel('catalog/category');

        $category->setData([
            'name' => $data->name,
            'is_active' => $data->isActive ? 1 : 0,
            'include_in_menu' => $data->includeInMenu ? 1 : 0,
            'parent_id' => $parentId,
            'path' => $parentCategory->getPath(),
        ]);

        if ($data->urlKey !== null) {
            $category->setUrlKey($data->urlKey);
        } else {
            $category->setUrlKey($this->generateUrlKey($data->name));
        }

        $this->applyCategoryData($category, $data);

        try {
            $category->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create category: ' . $e->getMessage());
        }

        $this->logActivity('create', null, $category, $user);

        return $this->refreshDto($category, $data);
    }

    private function handleUpdate(int $id, Category $data, ApiUser $user): Category
    {
        StoreContext::ensureStore();

        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::getModel('catalog/category');

        $storeId = StoreContext::getStoreId();
        if ($storeId) {
            $category->setStoreId($storeId);
        }

        $category->load($id);

        if (!$category->getId()) {
            throw new NotFoundHttpException('Category not found');
        }

        $oldData = $category->getData();

        if ($data->name !== '') {
            $category->setName($data->name);
        }

        $category->setIsActive($data->isActive ? 1 : 0);
        $category->setData('include_in_menu', $data->includeInMenu ? 1 : 0);

        if ($data->urlKey !== null) {
            $category->setUrlKey($data->urlKey);
        }

        if ($data->parentId !== null && $data->parentId !== (int) $category->getParentId()) {
            $this->moveCategory($category, $data->parentId);
        }

        $this->applyCategoryData($category, $data);

        try {
            $category->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update category: ' . $e->getMessage());
        }

        $this->logActivity('update', $oldData, $category, $user);

        return $this->refreshDto($category, $data);
    }

    private function handleDelete(int $id, ApiUser $user): null
    {
        /** @var Mage_Catalog_Model_Category $category */
        $category = Mage::getModel('catalog/category')->load($id);

        if (!$category->getId()) {
            throw new NotFoundHttpException('Category not found');
        }

        // Prevent deletion of root categories
        if ((int) $category->getLevel() <= 1) {
            throw new BadRequestHttpException('Cannot delete root categories');
        }

        $oldData = $category->getData();

        try {
            $category->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete category: ' . $e->getMessage());
        }

        $this->logActivity('delete', $oldData, null, $user);

        return null;
    }

    private function applyCategoryData(Mage_Catalog_Model_Category $category, Category $data): void
    {
        if ($data->description !== null) {
            $category->setDescription($data->description);
        }
        if ($data->position !== 0) {
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
    }

    private function moveCategory(Mage_Catalog_Model_Category $category, int $newParentId): void
    {
        /** @var Mage_Catalog_Model_Category $newParent */
        $newParent = Mage::getModel('catalog/category')->load($newParentId);
        if (!$newParent->getId()) {
            throw new BadRequestHttpException('New parent category not found');
        }

        try {
            $category->move($newParentId, 0);
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to move category: ' . $e->getMessage());
        }
    }

    private function generateUrlKey(string $name): string
    {
        $urlKey = strtolower(trim($name));
        $urlKey = preg_replace('/[^a-z0-9\s-]/', '', $urlKey);
        $urlKey = preg_replace('/[\s-]+/', '-', $urlKey);
        return trim($urlKey, '-');
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

    private function getAuthorizedUser(): ApiUser
    {
        $user = $this->security->getUser();

        if (!$user instanceof ApiUser) {
            throw new AccessDeniedHttpException('Authentication required');
        }

        return $user;
    }

    private function requirePermission(ApiUser $user, string $permission): void
    {
        if (!$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Missing permission: {$permission}");
        }
    }

    private function logActivity(
        string $action,
        ?array $oldData,
        ?Mage_Catalog_Model_Category $category,
        ApiUser $user,
    ): void {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => 'catalog/category',
                'action' => $action,
                'entity_id' => $category ? (int) $category->getId() : ($oldData['entity_id'] ?? 0),
                'old_data' => $oldData,
                'new_data' => $category?->getData(),
                'api_user_id' => $user->getApiUserId(),
                'username' => 'API: ' . $user->getUserIdentifier(),
            ]);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }
}
