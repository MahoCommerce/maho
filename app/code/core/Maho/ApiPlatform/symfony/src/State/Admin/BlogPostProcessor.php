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
use Mage_Oauth_Model_Consumer;
use Maho\ApiPlatform\ApiResource\Admin\BlogPost;
use Maho\ApiPlatform\Security\AdminApiAuthenticator;
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
        $consumer = $request?->attributes->get(AdminApiAuthenticator::CONSUMER_ATTRIBUTE);

        if (!$consumer || !$consumer->hasPermission('blog_posts')) {
            throw new AccessDeniedHttpException('Token does not have permission for blog_posts');
        }

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables['id'], $consumer);
        }

        assert($data instanceof BlogPost);

        // Resolve store IDs
        $storeIds = $this->resolveStoreIds($data->stores, $consumer);

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
                $consumer,
            );
        }

        return $this->handleCreate($data, $storeIds, $sanitizedContent, $sanitizedShortContent, $consumer);
    }

    private function handleCreate(
        BlogPost $data,
        array $storeIds,
        string $sanitizedContent,
        ?string $sanitizedShortContent,
        Mage_Oauth_Model_Consumer $consumer,
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

        // Note: shortContent and author fields may need custom attributes or schema updates
        // if they don't exist in the blog model. The model currently doesn't have these fields.
        // TODO: Add short_content and author attributes to blog model if needed

        $post->setData($postData);

        try {
            $post->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create blog post: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('create', null, $post, $consumer);

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
        Mage_Oauth_Model_Consumer $consumer,
    ): BlogPost {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post')->load($id);

        if (!$post->getId()) {
            throw new NotFoundHttpException('Blog post not found');
        }

        // Check store access
        $this->validateStoreAccess($post, $consumer);

        $oldData = $post->getData();

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
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update blog post: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('update', $oldData, $post, $consumer);

        $data->id = (int) $post->getId();
        $data->content = $sanitizedContent;
        $data->shortContent = $sanitizedShortContent;
        return $data;
    }

    private function handleDelete(int $id, Mage_Oauth_Model_Consumer $consumer): null
    {
        /** @var Maho_Blog_Model_Post $post */
        $post = Mage::getModel('blog/post')->load($id);

        if (!$post->getId()) {
            throw new NotFoundHttpException('Blog post not found');
        }

        // Check store access
        $this->validateStoreAccess($post, $consumer);

        $oldData = $post->getData();

        try {
            $post->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete blog post: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('delete', $oldData, null, $consumer);

        return null;
    }

    private function resolveStoreIds(array $stores, Mage_Oauth_Model_Consumer $consumer): array
    {
        if (in_array('all', $stores, true)) {
            $allowedStores = $consumer->getAllowedStoreIds();
            if ($allowedStores === 'all') {
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

            if (!$consumer->canAccessStore($storeId)) {
                throw new AccessDeniedHttpException("Token does not have access to store: {$storeCode}");
            }

            $storeIds[] = $storeId;
        }

        return $storeIds;
    }

    private function validateStoreAccess(Maho_Blog_Model_Post $post, Mage_Oauth_Model_Consumer $consumer): void
    {
        $postStores = $post->getStores();

        // If post is on all stores (0), check if consumer has 'all' access
        if (in_array(0, $postStores, true)) {
            $allowedStores = $consumer->getAllowedStoreIds();
            if ($allowedStores !== 'all') {
                throw new AccessDeniedHttpException('Token does not have access to all-stores content');
            }
            return;
        }

        // Check if consumer can access at least one of the post's stores
        foreach ($postStores as $storeId) {
            if ($consumer->canAccessStore((int) $storeId)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Token does not have access to this post\'s stores');
    }

    private function logActivity(
        string $action,
        ?array $oldData,
        ?Maho_Blog_Model_Post $post,
        Mage_Oauth_Model_Consumer $consumer,
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
                'consumer_id' => $consumer->getId(),
                'username' => 'API: ' . $consumer->getName(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the request
            Mage::logException($e);
        }
    }
}
