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
use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Security\ApiUser;
use Maho_Blog_Model_Post;

/**
 * Blog Post Processor — extends CrudProcessor with content sanitization,
 * store access checks, and image handling.
 *
 * All field mapping and CRUD routing is handled by CrudResource/CrudProcessor.
 * This class adds content sanitization, store-level authorization, and image processing.
 */
final class BlogPostProcessor extends CrudProcessor
{
    protected ?string $writePermission = 'blog-posts/write';
    protected ?string $deletePermission = 'blog-posts/delete';

    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        $content = $model->getData('content');
        if ($content !== null) {
            $model->setData('content', \Mage::getSingleton('core/input_filter_maliciousCode')->filter($content));
        }

        if ($data instanceof BlogPost) {
            $storeIds = $this->resolveStoreIds($data->stores, $user);
            $model->setData('stores', $storeIds);

            if ($data->publishedAt !== null) {
                $model->setData('publish_date', $data->publishedAt);
            }
        }
    }

    #[\Override]
    protected function afterSave(object $model, CrudResource $data): void
    {
        if ($data instanceof BlogPost && $data->image !== null) {
            $this->saveImageAttribute($model, $this->processImage($data->image));
        }
    }

    #[\Override]
    protected function processUpdate(int $id, mixed $data, ApiUser $user): mixed
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'Blog post not found');
        $this->validateEntityStoreAccess($model->getStores(), $user, 'post');

        return parent::processUpdate($id, $data, $user);
    }

    #[\Override]
    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'Blog post not found');
        $this->validateEntityStoreAccess($model->getStores(), $user, 'post');

        return parent::processDelete($id, $user);
    }

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

    private function saveImageAttribute(object $post, string $imageValue): void
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
