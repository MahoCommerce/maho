<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Cms_Model_Block;
use Mage_Core_Model_Store;
use Mage_Oauth_Model_Consumer;
use Maho\ApiPlatform\ApiResource\Admin\CmsBlock;
use Maho\ApiPlatform\Security\AdminApiAuthenticator;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * CMS Block State Processor for Admin API
 *
 * @implements ProcessorInterface<CmsBlock, CmsBlock|null>
 */
final class CmsBlockProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContentSanitizer $contentSanitizer,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CmsBlock
    {
        $request = $this->requestStack->getCurrentRequest();
        $consumer = $request?->attributes->get(AdminApiAuthenticator::CONSUMER_ATTRIBUTE);

        if (!$consumer || !$consumer->hasPermission('cms_blocks')) {
            throw new AccessDeniedHttpException('Token does not have permission for cms_blocks');
        }

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables['id'], $consumer);
        }

        assert($data instanceof CmsBlock);

        // Resolve store IDs
        $storeIds = $this->resolveStoreIds($data->stores, $consumer);

        // Sanitize content
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content);

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate((int) $uriVariables['id'], $data, $storeIds, $sanitizedContent, $consumer);
        }

        return $this->handleCreate($data, $storeIds, $sanitizedContent, $consumer);
    }

    private function handleCreate(CmsBlock $data, array $storeIds, string $sanitizedContent, Mage_Oauth_Model_Consumer $consumer): CmsBlock
    {
        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block');

        $block->setData([
            'identifier' => $data->identifier,
            'title' => $data->title,
            'content' => $sanitizedContent,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
        ]);

        try {
            $block->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create block: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('create', null, $block, $consumer);

        $data->id = (int) $block->getId();
        $data->content = $sanitizedContent;
        return $data;
    }

    private function handleUpdate(int $id, CmsBlock $data, array $storeIds, string $sanitizedContent, Mage_Oauth_Model_Consumer $consumer): CmsBlock
    {
        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            throw new NotFoundHttpException('CMS block not found');
        }

        // Check store access
        $this->validateStoreAccess($block, $consumer);

        $oldData = $block->getData();

        $block->addData([
            'identifier' => $data->identifier,
            'title' => $data->title,
            'content' => $sanitizedContent,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
        ]);

        try {
            $block->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update block: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('update', $oldData, $block, $consumer);

        $data->id = (int) $block->getId();
        $data->content = $sanitizedContent;
        return $data;
    }

    private function handleDelete(int $id, Mage_Oauth_Model_Consumer $consumer): null
    {
        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            throw new NotFoundHttpException('CMS block not found');
        }

        // Check store access
        $this->validateStoreAccess($block, $consumer);

        $oldData = $block->getData();

        try {
            $block->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete block: ' . $e->getMessage());
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

    private function validateStoreAccess(Mage_Cms_Model_Block $block, Mage_Oauth_Model_Consumer $consumer): void
    {
        $blockStores = $block->getStoreId();
        if (!is_array($blockStores)) {
            $blockStores = [$blockStores];
        }

        // If block is on all stores (0), check if consumer has 'all' access
        if (in_array(0, $blockStores, true)) {
            $allowedStores = $consumer->getAllowedStoreIds();
            if ($allowedStores !== 'all') {
                throw new AccessDeniedHttpException('Token does not have access to all-stores content');
            }
            return;
        }

        // Check if consumer can access at least one of the block's stores
        foreach ($blockStores as $storeId) {
            if ($consumer->canAccessStore((int) $storeId)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Token does not have access to this block\'s stores');
    }

    private function logActivity(string $action, ?array $oldData, ?Mage_Cms_Model_Block $block, Mage_Oauth_Model_Consumer $consumer): void
    {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => 'cms/block',
                'action' => $action,
                'entity_id' => $block ? (int) $block->getId() : ($oldData['block_id'] ?? 0),
                'old_data' => $oldData,
                'new_data' => $block?->getData(),
                'consumer_id' => $consumer->getId(),
                'username' => 'API: ' . $consumer->getName(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the request
            Mage::logException($e);
        }
    }
}
