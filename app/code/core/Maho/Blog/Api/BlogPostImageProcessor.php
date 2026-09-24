<?php

/**
 * Uploads and removes the image of a blog post.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Blog
 */

declare(strict_types=1);

namespace Maho\Blog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Processor;
use Maho\ApiPlatform\Service\ImageUpload;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The file goes through Maho_Blog_Model_Post_Attribute_Backend_Image, so an upload gets
 * the same file name, folder and old file removal as an upload in the admin.
 * ImageUpload checks the request body.
 */
final class BlogPostImageProcessor extends Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?BlogPost
    {
        $user = $this->requireUser();

        $post = \Mage::getModel('blog/post')->load((int) ($uriVariables['id'] ?? 0));
        if (!$post->getId()) {
            throw new NotFoundHttpException('BlogPost not found');
        }
        $this->validateEntityStoreAccess($post->getStores(), $user, 'BlogPost');
        $oldData = $post->getData();

        if ($operation instanceof DeleteOperationInterface) {
            // The same value as the "Delete Image" checkbox of the admin form
            $post->setImage(['delete' => 1]);
            $this->safeSave($post, 'delete the image of the BlogPost');
            $this->logApiActivity('blog_post', 'update', $oldData, $post, $user);
            return null;
        }

        $this->uploadImage($post, $this->parseRequestBody($context['request'] ?? null));
        $this->safeSave($post, 'update BlogPost');
        $this->logApiActivity('blog_post', 'update', $oldData, $post, $user);

        return BlogPost::fromModel(\Mage::getModel('blog/post')->load($post->getId()));
    }

    /**
     * @param array<mixed> $body
     */
    private function uploadImage(\Maho_Blog_Model_Post $post, array $body): void
    {
        $attribute = \Mage::getResourceSingleton('blog/post')->getAttribute('image');
        $backend = $attribute ? $attribute->getBackend() : null;
        if (!$backend instanceof \Maho_Blog_Model_Post_Attribute_Backend_Image) {
            throw new UnprocessableEntityHttpException('The image attribute of blog posts is not available');
        }

        ImageUpload::save(
            $body,
            $backend->getAllowedExtensions(),
            static fn(\Mage_Core_Model_File_Uploader $uploader): ?string => $backend->saveImage($post, $uploader),
        );
    }
}
