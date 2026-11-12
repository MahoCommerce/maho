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

        /** @var \Mage_Catalog_Service_Category $service */
        $service = \Mage::getService('catalog/category');
        $category = $service->getById((int) ($uriVariables['id'] ?? 0), $storeId);
        $service->assertInStores($category, $user->getAllowedStoreIds());

        /** @var \Mage_Catalog_Model_Category_Attribute_Backend_Image $backend */
        $backend = $category->getResource()->getAttribute('image')->getBackend();
        $oldData = $category->getData();
        $oldImage = (string) $category->getData('image');

        if ($operation instanceof DeleteOperationInterface) {
            if ($oldImage === '') {
                return null;
            }
            // The same as PUT with "image": "", so the store view shows no image
            $category->setData('image');
            $this->saveCategory($service, $category, $oldData);
            $backend->deleteUnusedFile($oldImage);
            $this->logApiActivity('catalog/category', 'update', $oldData, $category);
            return null;
        }

        ImageUpload::save(
            $this->parseRequestBody($context['request'] ?? null),
            $backend->getAllowedExtensions(),
            static fn(\Mage_Core_Model_File_Uploader $uploader): ?string => $backend->saveImage($category, $uploader),
        );
        $this->saveCategory($service, $category, $oldData);
        $this->logApiActivity('catalog/category', 'update', $oldData, $category);

        return new CategoryProvider($this->security)
            ->mapToDto($service->getById((int) $category->getId(), $storeId), includeChildren: true, withStoreOverrides: true);
    }

    /**
     * Save the category as PUT does, so the indexes and caches of the category are updated.
     * A store view save writes only the image.
     *
     * @param array<string, mixed> $oldData
     */
    private function saveCategory(\Mage_Catalog_Service_Category $service, \Mage_Catalog_Model_Category $category, array $oldData): void
    {
        $inherited = StoreScopeWrite::keepInheritedValues($category, $oldData, ['image' => true]);
        $service->save($category);
        StoreScopeWrite::restoreInheritedValues($category, $oldData, $inherited);
    }
}
