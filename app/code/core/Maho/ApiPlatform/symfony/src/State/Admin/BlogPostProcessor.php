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

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Core_Model_Store;
use Maho\ApiPlatform\ApiResource\Admin\BlogPost;
use Maho\ApiPlatform\Security\AdminApiAuthenticator;
use Maho\ApiPlatform\Security\AdminApiUser;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Maho_Blog_Model_Post;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Blog Post State Processor for Admin API
 *
 * @implements ProcessorInterface<BlogPost, BlogPost|null>
 */
final class BlogPostProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContentSanitizer $contentSanitizer,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?BlogPost
    {
        $request = $this->requestStack->getCurrentRequest();
        $user = $request?->attributes->get(AdminApiAuthenticator::CONSUMER_ATTRIBUTE);

        if (!$user instanceof AdminApiUser || !$user->hasPermission('admin/blog-posts/write')) {
            throw new AccessDeniedHttpException('Token does not have write permission for blog_posts');
        }

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables['id'], $user);
        }

        assert($data instanceof BlogPost);

        // Resolve store IDs
        $storeIds = $this->resolveStoreIds($data->stores, $user);

        // Sanitize content fields
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content);
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
        AdminApiUser $user,
    ): BlogPost {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post');

        $postData = [
            'url_key' => $data->identifier,
            'title' => $data->title,
            'content' => $sanitizedContent,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
            'meta_title' => $data->metaTitle,
            'meta_keywords' => $data->metaKeywords,
            'meta_description' => $data->metaDescription,
        ];

        // Handle optional fields
        if ($data->publishedAt !== null) {
            $postData['publish_date'] = $data->publishedAt;
        }

        $post->setData($postData);

        try {
            $post->save();

            // Save image directly to EAV since backend only handles file uploads
            if ($data->image !== null) {
                $this->saveImageAttribute($post, $this->processImage($data->image));
            }
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create blog post: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('create', null, $post, $user);

        $data->id = (int) $post->getId();
        $data->content = $sanitizedContent;
        $data->shortContent = $sanitizedShortContent;
        return $data;
    }

    private function handleUpdate(
        int $id,
        BlogPost $data,
        array $storeIds,
        string $sanitizedContent,
        ?string $sanitizedShortContent,
        AdminApiUser $user,
    ): BlogPost {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post')->load($id);

        if (!$post->getId()) {
            throw new NotFoundHttpException('Blog post not found');
        }

        // Check store access
        $this->validateStoreAccess($post, $user);

        $oldData = $post->getData();

        // Create content version before updating (for rollback capability)
        $this->createContentVersion($post, 'blog_post', $user);

        $updateData = [
            'url_key' => $data->identifier,
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
        }

        $post->addData($updateData);

        try {
            $post->save();

            // Save image directly to EAV since backend only handles file uploads
            if ($data->image !== null) {
                $this->saveImageAttribute($post, $this->processImage($data->image));
            }
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update blog post: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('update', $oldData, $post, $user);

        $data->id = (int) $post->getId();
        $data->content = $sanitizedContent;
        $data->shortContent = $sanitizedShortContent;
        return $data;
    }

    private function handleDelete(int $id, AdminApiUser $user): null
    {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post')->load($id);

        if (!$post->getId()) {
            throw new NotFoundHttpException('Blog post not found');
        }

        // Check store access
        $this->validateStoreAccess($post, $user);

        $oldData = $post->getData();

        try {
            $post->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete blog post: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('delete', $oldData, null, $user);

        return null;
    }

    private function resolveStoreIds(array $stores, AdminApiUser $user): array
    {
        if (in_array('all', $stores, true)) {
            $allowedStores = $user->getAllowedStoreIds();
            if ($allowedStores === null) {
                return [0]; // Admin store = all stores
            }
            return $allowedStores;
        }

        // Convert store codes to IDs and validate access
        $storeIds = [];
        foreach ($stores as $storeCode) {
            /** @var Mage_Core_Model_Store $store */
            $store = Mage::app()->getStore($storeCode);
            $storeId = (int) $store->getId();

            if (!$user->canAccessStore($storeId)) {
                throw new AccessDeniedHttpException("Token does not have access to store: {$storeCode}");
            }

            $storeIds[] = $storeId;
        }

        return $storeIds;
    }

    private function validateStoreAccess(Maho_Blog_Model_Post $post, AdminApiUser $user): void
    {
        $postStores = $post->getStores();

        // If post is on all stores (0), check if user has all-stores access
        if (in_array(0, $postStores, true)) {
            $allowedStores = $user->getAllowedStoreIds();
            if ($allowedStores !== null) {
                throw new AccessDeniedHttpException('Token does not have access to all-stores content');
            }
            return;
        }

        // Check if user can access at least one of the post's stores
        foreach ($postStores as $storeId) {
            if ($user->canAccessStore((int) $storeId)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Token does not have access to this post\'s stores');
    }

    /**
     * Process image field - handles URLs and relative paths
     * If URL points to media/wysiwyg, copies file to media/blog/
     */
    private function processImage(string $image): string
    {
        // If it's already a relative path (no http), use as-is
        if (!str_starts_with($image, 'http://') && !str_starts_with($image, 'https://')) {
            return $image;
        }

        // Extract filename from URL
        $urlPath = parse_url($image, PHP_URL_PATH);
        $filename = basename($urlPath);

        // Check if this is a wysiwyg media URL we can copy locally
        if (str_contains($image, '/media/wysiwyg/')) {
            // Extract path after wysiwyg/
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

        // For external URLs, just use the filename (assumes it exists or will be uploaded separately)
        return $filename;
    }

    /**
     * Save image value directly to EAV table since backend model only handles file uploads
     */
    private function saveImageAttribute(Maho_Blog_Model_Post $post, string $imageValue): void
    {
        try {
            // Get the image attribute
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

    private function createContentVersion(\Mage_Core_Model_Abstract $model, string $entityType, AdminApiUser $user): void
    {
        try {
            /** @var \Maho_ContentVersion_Model_Service $versionService */
            $versionService = Mage::getSingleton('contentversion/service');
            $versionService->createVersion($model, $entityType, 'API: ' . $user->getConsumerName());
        } catch (\Exception $e) {
            // Log but don't fail the request - versioning is not critical
            Mage::logException($e);
        }
    }

    private function logActivity(
        string $action,
        ?array $oldData,
        ?Maho_Blog_Model_Post $post,
        AdminApiUser $user,
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
                'consumer_id' => $user->getConsumer()->getId(),
                'username' => 'API: ' . $user->getConsumerName(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the request
            Mage::logException($e);
        }
    }
}
