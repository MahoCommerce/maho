<?php

/**
 * Blog category write processor: validation, tree integrity and subtree delete.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Security\ApiUser;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

final class BlogCategoryProcessor extends CrudProcessor
{
    #[\Override]
    protected function validate(CrudResource $data, object $model, bool $isNew): void
    {
        if (!$data instanceof BlogCategory) {
            return;
        }

        if ($data->name !== null && trim($data->name) === '') {
            throw new BadRequestHttpException('Blog category name cannot be empty');
        }
        if ($isNew && $data->name === null) {
            throw new BadRequestHttpException('Blog category name is required');
        }

        if ($data->parentId !== null && $data->parentId !== \Maho_Blog_Model_Category::ROOT_PARENT_ID) {
            $parent = \Mage::getModel('blog/category')->load($data->parentId);
            if (!$parent->getId()) {
                throw new BadRequestHttpException("Unknown parent blog category id {$data->parentId}");
            }
            if ($model->getId() && (
                (int) $data->parentId === (int) $model->getId()
                || in_array((int) $model->getId(), $parent->getPathIds(), true)
            )) {
                throw new BadRequestHttpException('A blog category cannot be moved under itself or its descendants');
            }
        }
    }

    /** Mirrors the admin delete flow: the whole subtree goes with the category. */
    #[\Override]
    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'Blog category not found');
        $this->authorizeEntity($model, $user);
        $oldData = $model->getData();
        \Mage::getResourceSingleton('blog/category')->deleteDescendants((int) $model->getId());
        $this->safeDelete($model, "delete {$this->entityLabel}");
        $this->logApiActivity($this->entityType, 'delete', $oldData, null, $user);
        return null;
    }
}
