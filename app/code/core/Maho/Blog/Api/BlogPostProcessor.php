<?php

/**
 * Give a blog post write to the blog post service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;

final class BlogPostProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?BlogPost
    {
        $user = $this->requireUser();
        /** @var \Maho_Blog_Service_Post $service */
        $service = \Mage::getService('blog/post');
        $post = isset($uriVariables['id']) ? $service->getById((int) $uriVariables['id']) : $service->newPost();
        $oldData = $post->getId() ? $post->getData() : null;
        if ($oldData !== null) {
            $this->validateEntityStoreAccess($post->getStores(), $user, 'BlogPost');
        }

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($post);
            $this->logApiActivity('blog_post', 'delete', $oldData, null);
            return null;
        }

        /** @var BlogPost $data */
        $sent = $oldData === null ? null : array_map(strval(...), array_keys($this->parseRequestBody($context['request'] ?? null)));
        $data->applyToModel($post, $sent);
        if ($data->stores !== null && ($sent === null || in_array('stores', $sent, true))) {
            $post->setData('stores', $this->resolveStoreIds($data->stores, $user));
        }
        $this->validateEntityStoreAccess($post->getStores(), $user, 'BlogPost');

        $service->save($post);
        $this->logApiActivity('blog_post', $oldData === null ? 'create' : 'update', $oldData, $post);

        return BlogPost::fromModel($service->getById((int) $post->getId()));
    }
}
