<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Blog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\Blog\Api;

use Mage;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Maho_Blog_Model_Post;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Blog Post State Processor
 *
 * Handles create, update, and delete operations for blog posts.
 * Requires JWT authentication with blog-posts/write permission.
 */
final class BlogPostProcessor extends \Maho\ApiPlatform\Processor
{
    protected ?string $modelAlias = 'blog/post';
    protected ?string $writePermission = 'blog-posts/write';
    protected ?string $deletePermission = 'blog-posts/delete';
    protected ?string $entityType = 'blog/post';
    protected ?string $entityLabel = 'blog post';

    public function __construct(
        Security $security,
        private readonly ContentSanitizer $contentSanitizer,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    protected function applyData(object $model, mixed $data, ApiUser $user): void
    {
        $storeIds = $this->resolveStoreIds($data->stores, $user);
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content ?? '');

        $postData = [
            'url_key' => $data->urlKey,
            'title' => $data->title,
            'content' => $sanitizedContent,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
            'meta_title' => $data->metaTitle,
            'meta_keywords' => $data->metaKeywords,
            'meta_description' => $data->metaDescription,
        ];

        if ($data->publishedAt !== null) {
            $postData['publish_date'] = $data->publishedAt;
        } elseif ($data->publishDate !== null) {
            $postData['publish_date'] = $data->publishDate;
        }

        $model->addData($postData);
    }

    #[\Override]
    protected function buildResponse(object $model, mixed $data): BlogPost
    {
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content ?? '');
        $sanitizedShortContent = $data->shortContent !== null
            ? $this->contentSanitizer->sanitize($data->shortContent)
            : null;

        $data->id = (int) $model->getId();
        $data->content = $sanitizedContent;
        $data->shortContent = $sanitizedShortContent;
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    #[\Override]
    protected function processCreate(mixed $data, ApiUser $user): mixed
    {
        /** @var Maho_Blog_Model_Post $model */
        $model = Mage::getModel($this->modelAlias);
        $this->applyData($model, $data, $user);
        $this->safeSave($model, "create {$this->entityLabel}");

        if ($data->image !== null) {
            $this->saveImageAttribute($model, $this->processImage($data->image));
        }

        $this->logApiActivity($this->entityType, 'create', null, $model, $user);
        return $this->buildResponse($model, $data);
    }

    #[\Override]
    protected function processUpdate(int $id, mixed $data, ApiUser $user): mixed
    {
        /** @var Maho_Blog_Model_Post $model */
        $model = $this->loadOrFail($this->modelAlias, $id, 'Blog post not found');
        $this->validateEntityStoreAccess($model->getStores(), $user, 'post');

        $oldData = $model->getData();
        $this->applyData($model, $data, $user);
        $this->safeSave($model, "update {$this->entityLabel}");

        if ($data->image !== null) {
            $this->saveImageAttribute($model, $this->processImage($data->image));
        }

        $this->logApiActivity($this->entityType, 'update', $oldData, $model, $user);
        return $this->buildResponse($model, $data);
    }

    #[\Override]
    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'Blog post not found');
        $this->validateEntityStoreAccess($model->getStores(), $user, 'post');

        $oldData = $model->getData();
        $this->safeDelete($model, "delete {$this->entityLabel}");
        $this->logApiActivity($this->entityType, 'delete', $oldData, null, $user);
        return null;
    }

    /**
     * Process image field - handles URLs and relative paths
     * If URL points to media/wysiwyg, copies file to media/blog/
     */
    private function processImage(string $image): string
    {
        if (!str_starts_with($image, 'http://') && !str_starts_with($image, 'https://')) {
            return $image;
        }

        $urlPath = parse_url($image, PHP_URL_PATH);
        $filename = basename($urlPath);

        if (str_contains($image, '/media/wysiwyg/')) {
            if (preg_match('#/media/wysiwyg/(.+)$#', $image, $matches)) {
                $sourceFile = Mage::getBaseDir('media') . '/wysiwyg/' . $matches[1];
                $mediaDir = realpath(Mage::getBaseDir('media') . '/wysiwyg');
                $realSource = realpath($sourceFile);

                // Validate path doesn't escape media directory (path traversal protection)
                if ($realSource === false || $mediaDir === false || !str_starts_with($realSource, $mediaDir)) {
                    return basename($urlPath);
                }

                $destDir = Mage::getBaseDir('media') . '/blog/';
                $destFile = $destDir . $filename;

                if (file_exists($sourceFile) && !file_exists($destFile)) {
                    if (!is_dir($destDir)) {
                        mkdir($destDir, 0755, true);
                    }
                    copy($sourceFile, $destFile);
                }
                return $filename;
            }
        }

        return $filename;
    }

    /**
     * Save image value directly to EAV table since backend model only handles file uploads
     */
    private function saveImageAttribute(Maho_Blog_Model_Post $post, string $imageValue): void
    {
        try {
            $attribute = Mage::getSingleton('eav/config')->getAttribute('blog_post', 'image');
            if (!$attribute || !$attribute->getId()) {
                return;
            }

            $table = $attribute->getBackend()->getTable();
            $entityTypeId = $attribute->getEntityTypeId();
            $attributeId = $attribute->getId();
            $entityId = (int) $post->getId();

            $adapter = Mage::getSingleton('core/resource')->getConnection('core_write');

            $data = [
                'entity_type_id' => $entityTypeId,
                'attribute_id' => $attributeId,
                'store_id' => 0,
                'entity_id' => $entityId,
                'value' => $imageValue,
            ];

            $adapter->insertOnDuplicate($table, $data, ['value']);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }
}
