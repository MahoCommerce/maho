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
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * The file goes through Maho_Blog_Model_Post_Attribute_Backend_Image, so an upload gets
 * the same file name, folder and old file removal as an upload in the admin.
 */
final class BlogPostImageProcessor extends Processor
{
    /** The largest decoded image that an upload accepts, in bytes. */
    public const MAX_IMAGE_BYTES = 5 * 1024 * 1024;

    private const IMAGE_TYPES = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP, IMAGETYPE_AVIF];

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
        $base64 = $body['base64'] ?? null;
        $fileName = $body['filename'] ?? null;
        if (!is_string($base64) || $base64 === '') {
            throw new BadRequestHttpException('base64 is required');
        }
        if (!is_string($fileName) || trim($fileName) === '') {
            throw new BadRequestHttpException('filename is required');
        }

        $tooLarge = sprintf('The image is larger than %d MB', self::MAX_IMAGE_BYTES / 1024 / 1024);
        // Four base64 characters hold three bytes, so a longer string cannot decode to an allowed size
        if (strlen($base64) > (int) ceil(self::MAX_IMAGE_BYTES / 3) * 4) {
            throw new BadRequestHttpException($tooLarge);
        }
        $decoded = base64_decode($base64, true);
        if ($decoded === false || $decoded === '') {
            throw new BadRequestHttpException('Invalid base64 image data');
        }
        if (strlen($decoded) > self::MAX_IMAGE_BYTES) {
            throw new BadRequestHttpException($tooLarge);
        }

        $attribute = \Mage::getResourceSingleton('blog/post')->getAttribute('image');
        $backend = $attribute ? $attribute->getBackend() : null;
        if (!$backend instanceof \Maho_Blog_Model_Post_Attribute_Backend_Image) {
            throw new UnprocessableEntityHttpException('The image attribute of blog posts is not available');
        }
        $fileName = basename(str_replace('\\', '/', $fileName));
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        $allowed = $backend->getAllowedExtensions();
        if (!in_array($extension, $allowed, true)) {
            throw new BadRequestHttpException('The filename extension must be one of: ' . implode(', ', $allowed));
        }

        $tmpPath = $this->writeTempFile($decoded);
        try {
            $info = \Maho\Io::getImageSize($tmpPath);
            if ($info === false || !in_array($info[2], self::IMAGE_TYPES, true)) {
                throw new BadRequestHttpException('The data is not a valid JPEG, PNG, GIF, WEBP or AVIF image');
            }

            $backend->saveImage($post, new LocalFileUploader($tmpPath, $fileName));
        } catch (\Mage_Core_Exception $e) {
            // The image validator of the uploader rejects the file
            throw new BadRequestHttpException($e->getMessage());
        } catch (BadRequestHttpException $e) {
            throw $e;
        } catch (\Throwable $e) {
            \Mage::logException($e);
            throw new UnprocessableEntityHttpException('Failed to save the image: ' . $e->getMessage());
        } finally {
            if (is_file($tmpPath)) {
                unlink($tmpPath);
            }
        }
    }

    private function writeTempFile(string $data): string
    {
        $path = tempnam(\Mage::getBaseDir('tmp'), 'blog_image_');
        if ($path === false || file_put_contents($path, $data) === false) {
            if ($path !== false) {
                @unlink($path);
            }
            throw new UnprocessableEntityHttpException('Failed to create a temporary file for the image');
        }

        return $path;
    }
}
