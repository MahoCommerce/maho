<?php

/**
 * Give a blog category write to the blog category service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;

final class BlogCategoryProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?BlogCategory
    {
        $user = $this->requireUser();
        /** @var \Maho_Blog_Service_Category $service */
        $service = \Mage::getService('blog/category');
        $category = isset($uriVariables['id']) ? $service->getById((int) $uriVariables['id']) : $service->newCategory();
        $oldData = $category->getId() ? $category->getData() : null;
        if ($oldData !== null) {
            $this->validateEntityStoreAccess($category->getStores(), $user, 'BlogCategory');
        }

        if ($operation instanceof DeleteOperationInterface) {
            // The subtree goes with the category, so a token that is limited to some store views needs each descendant
            foreach (\Mage::getResourceSingleton('blog/category')->getDescendantIds((int) $category->getId()) as $descendantId) {
                $this->validateEntityStoreAccess($service->getById($descendantId)->getStores(), $user, 'BlogCategory');
            }
            $service->delete($category);
            $this->logApiActivity('blog_category', 'delete', $oldData, null);
            return null;
        }

        /** @var BlogCategory $data */
        $sent = $oldData === null ? null : array_map(strval(...), array_keys($this->parseRequestBody($context['request'] ?? null)));
        $data->applyToModel($category, $sent);
        if ($data->stores !== null && ($sent === null || in_array('stores', $sent, true))) {
            $category->setData('stores', $this->resolveStoreIds($data->stores, $user));
        }
        $this->validateEntityStoreAccess($category->getStores(), $user, 'BlogCategory');

        $service->save($category);
        $this->logApiActivity('blog_category', $oldData === null ? 'create' : 'update', $oldData, $category);

        return BlogCategory::fromModel($service->getById((int) $category->getId()));
    }
}
