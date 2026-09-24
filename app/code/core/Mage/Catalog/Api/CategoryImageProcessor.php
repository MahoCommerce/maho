<?php

/**
 * Uploads and removes the image of a category.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Processor;
use Maho\ApiPlatform\Service\ImageUpload;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The file goes through Mage_Catalog_Model_Category_Attribute_Backend_Image, so an upload gets
 * the same file name and folder as an upload in the admin. The write goes to the scope of
 * ?store=, as for PUT /categories/{id}: the global value without it, the store view value with it.
 */
final class CategoryImageProcessor extends Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Category
    {
        $user = $this->requireUser();
        StoreContext::ensureStore();
        $storeId = $this->resolveWriteScope($user);

        /** @var \Mage_Catalog_Model_Category $category */
        $category = \Mage::getModel('catalog/category');
        $category->setStoreId($storeId);
        $category->load((int) ($uriVariables['id'] ?? 0));
        if (!$category->getId()) {
            throw new NotFoundHttpException('Category not found');
        }
        CategoryProcessor::authorizeCategoryStore($category, $user);

        $backend = $category->getResource()->getAttribute('image')?->getBackend();
        if (!$backend instanceof \Mage_Catalog_Model_Category_Attribute_Backend_Image) {
            throw new UnprocessableEntityHttpException('The image attribute of categories is not available');
        }

        $oldData = $category->getData();
        $oldImage = (string) $category->getData('image');

        if ($operation instanceof DeleteOperationInterface) {
            if ($oldImage === '') {
                return null;
            }
            // The same as PUT with "image": "", so the store view shows no image
            $category->setData('image');
            $this->saveCategory($category, $oldData, 'delete the image of the category');
            $backend->deleteUnusedFile($oldImage);
            $this->logApiActivity('catalog/category', 'update', $oldData, $category, $user);
            return null;
        }

        ImageUpload::save(
            $this->parseRequestBody($context['request'] ?? null),
            $backend->getAllowedExtensions(),
            static fn(\Mage_Core_Model_File_Uploader $uploader): ?string => $backend->saveImage($category, $uploader),
        );
        $this->saveCategory($category, $oldData, 'update category');
        $this->logApiActivity('catalog/category', 'update', $oldData, $category, $user);

        /** @var \Mage_Catalog_Model_Category $fresh */
        $fresh = \Mage::getModel('catalog/category');
        $fresh->setStoreId($storeId);
        $fresh->load((int) $category->getId());

        return new CategoryProvider($this->security)->mapToDto($fresh, includeChildren: true, withStoreOverrides: true);
    }

    /**
     * Save the category as PUT does, so the indexes and caches of the category are updated.
     * A store view save writes only the image.
     *
     * @param array<string, mixed> $oldData
     */
    private function saveCategory(\Mage_Catalog_Model_Category $category, array $oldData, string $action): void
    {
        $inherited = StoreScopeWrite::keepInheritedValues($category, $oldData, ['image' => true]);
        $this->safeSave($category, $action);
        StoreScopeWrite::restoreInheritedValues($category, $oldData, $inherited);
    }
}
