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
use Mage_Core_Model_Store;
use Maho\ApiPlatform\ApiResource\BlogPost;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Maho_Blog_Model_Post;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Blog Post State Processor
 *
 * Handles create, update, and delete operations for blog posts.
 * Requires JWT authentication with admin/blog-posts/write permission.
 *
 * @implements ProcessorInterface<BlogPost, BlogPost|null>
 */
final class BlogPostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ContentSanitizer $contentSanitizer,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?BlogPost
    {
        $user = $this->getAuthorizedUser();

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'admin/blog-posts/delete');
            return $this->handleDelete((int) $uriVariables['id'], $user);
        }

        $this->requirePermission($user, 'admin/blog-posts/write');

        assert($data instanceof BlogPost);

        $storeIds = $this->resolveStoreIds($data->stores, $user);
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content ?? '');
        $sanitizedShortContent = $data->shortContent !== null
            ? $this->contentSanitizer->sanitize($data->shortContent)
            : null;

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate(
                (int) $uriVariables['id'],
                $data,
                $storeIds,
                $sanitizedContent,
                $sanitizedShortContent,
                $user,
            );
        }

        return $this->handleCreate($data, $storeIds, $sanitizedContent, $sanitizedShortContent, $user);
    }

    private function handleCreate(
        BlogPost $data,
        array $storeIds,
        string $sanitizedContent,
        ?string $sanitizedShortContent,
        ApiUser $user,
    ): BlogPost {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post');

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

        $post->setData($postData);

        try {
            $post->save();

            if ($data->image !== null) {
                $this->saveImageAttribute($post, $this->processImage($data->image));
            }
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create blog post: ' . $e->getMessage());
        }

        $this->logActivity('create', null, $post, $user);

        $data->id = (int) $post->getId();
        $data->content = $sanitizedContent;
        $data->shortContent = $sanitizedShortContent;
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    private function handleUpdate(
        int $id,
        BlogPost $data,
        array $storeIds,
        string $sanitizedContent,
        ?string $sanitizedShortContent,
        ApiUser $user,
    ): BlogPost {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post')->load($id);

        if (!$post->getId()) {
            throw new NotFoundHttpException('Blog post not found');
        }

        $this->validateStoreAccess($post, $user);

        $oldData = $post->getData();

        $updateData = [
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
            $updateData['publish_date'] = $data->publishedAt;
        } elseif ($data->publishDate !== null) {
            $updateData['publish_date'] = $data->publishDate;
        }

        $post->addData($updateData);

        try {
            $post->save();

            if ($data->image !== null) {
                $this->saveImageAttribute($post, $this->processImage($data->image));
            }
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update blog post: ' . $e->getMessage());
        }

        $this->logActivity('update', $oldData, $post, $user);

        $data->id = (int) $post->getId();
        $data->content = $sanitizedContent;
        $data->shortContent = $sanitizedShortContent;
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    private function handleDelete(int $id, ApiUser $user): null
    {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post')->load($id);

        if (!$post->getId()) {
            throw new NotFoundHttpException('Blog post not found');
        }

        $this->validateStoreAccess($post, $user);

        $oldData = $post->getData();

        try {
            $post->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete blog post: ' . $e->getMessage());
        }

        $this->logActivity('delete', $oldData, null, $user);

        return null;
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

    /**
     * @return array<int>
     */
    private function resolveStoreIds(array $stores, ApiUser $user): array
    {
        if (in_array('all', $stores, true)) {
            $allowedStores = $user->getAllowedStoreIds();
            if ($allowedStores === null) {
                return [0];
            }
            return $allowedStores;
        }

        $storeIds = [];
        foreach ($stores as $storeCode) {
            /** @var Mage_Core_Model_Store $store */
            $store = Mage::app()->getStore($storeCode);
            $storeId = (int) $store->getId();

            if (!$user->canAccessStore($storeId)) {
                throw new AccessDeniedHttpException("Access denied for store: {$storeCode}");
            }

            $storeIds[] = $storeId;
        }

        return $storeIds;
    }

    private function validateStoreAccess(Maho_Blog_Model_Post $post, ApiUser $user): void
    {
        $postStores = $post->getStores();

        if (in_array(0, $postStores, true)) {
            $allowedStores = $user->getAllowedStoreIds();
            if ($allowedStores !== null) {
                throw new AccessDeniedHttpException('Access denied for all-stores content');
            }
            return;
        }

        foreach ($postStores as $storeId) {
            if ($user->canAccessStore((int) $storeId)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Access denied for this post\'s stores');
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

            /** @var \Maho\Db\Adapter\AdapterInterface $adapter */
            $adapter = $post->getResource()->getWriteConnection(); /** @phpstan-ignore method.notFound */

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

    private function logActivity(
        string $action,
        ?array $oldData,
        ?Maho_Blog_Model_Post $post,
        ApiUser $user,
    ): void {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => 'blog/post',
                'action' => $action,
                'entity_id' => $post ? (int) $post->getId() : ($oldData['entity_id'] ?? 0),
                'old_data' => $oldData,
                'new_data' => $post?->getData(),
                'api_user_id' => $user->getApiUserId(),
                'username' => 'API: ' . $user->getUserIdentifier(),
            ]);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }
}
