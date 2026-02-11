<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Cms_Model_Page;
use Mage_Core_Model_Store;
use Mage_Oauth_Model_Consumer;
use Maho\ApiPlatform\ApiResource\Admin\CmsPage;
use Maho\ApiPlatform\Security\AdminApiAuthenticator;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * CMS Page State Processor for Admin API
 *
 * @implements ProcessorInterface<CmsPage, CmsPage|null>
 */
final class CmsPageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly RequestStack $requestStack,
        private readonly ContentSanitizer $contentSanitizer,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CmsPage
    {
        $request = $this->requestStack->getCurrentRequest();
        $consumer = $request?->attributes->get(AdminApiAuthenticator::CONSUMER_ATTRIBUTE);

        if (!$consumer || !$consumer->hasPermission('cms_pages')) {
            throw new AccessDeniedHttpException('Token does not have permission for cms_pages');
        }

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables['id'], $consumer);
        }

        assert($data instanceof CmsPage);

        // Resolve store IDs
        $storeIds = $this->resolveStoreIds($data->stores, $consumer);

        // Sanitize content
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content);

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate((int) $uriVariables['id'], $data, $storeIds, $sanitizedContent, $consumer);
        }

        return $this->handleCreate($data, $storeIds, $sanitizedContent, $consumer);
    }

    private function handleCreate(CmsPage $data, array $storeIds, string $sanitizedContent, Mage_Oauth_Model_Consumer $consumer): CmsPage
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page');

        $page->setData([
            'identifier' => $data->identifier,
            'title' => $data->title,
            'content_heading' => $data->contentHeading,
            'content' => $sanitizedContent,
            'meta_keywords' => $data->metaKeywords,
            'meta_description' => $data->metaDescription,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
        ]);

        try {
            $page->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create page: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('create', null, $page, $consumer);

        $data->id = (int) $page->getId();
        $data->content = $sanitizedContent;
        return $data;
    }

    private function handleUpdate(int $id, CmsPage $data, array $storeIds, string $sanitizedContent, Mage_Oauth_Model_Consumer $consumer): CmsPage
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            throw new NotFoundHttpException('CMS page not found');
        }

        // Check store access
        $this->validateStoreAccess($page, $consumer);

        $oldData = $page->getData();

        $page->addData([
            'identifier' => $data->identifier,
            'title' => $data->title,
            'content_heading' => $data->contentHeading,
            'content' => $sanitizedContent,
            'meta_keywords' => $data->metaKeywords,
            'meta_description' => $data->metaDescription,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
        ]);

        try {
            $page->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update page: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('update', $oldData, $page, $consumer);

        $data->id = (int) $page->getId();
        $data->content = $sanitizedContent;
        return $data;
    }

    private function handleDelete(int $id, Mage_Oauth_Model_Consumer $consumer): null
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            throw new NotFoundHttpException('CMS page not found');
        }

        // Check store access
        $this->validateStoreAccess($page, $consumer);

        $oldData = $page->getData();

        try {
            $page->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete page: ' . $e->getMessage());
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

    private function validateStoreAccess(Mage_Cms_Model_Page $page, Mage_Oauth_Model_Consumer $consumer): void
    {
        $pageStores = $page->getStoreId();
        if (!is_array($pageStores)) {
            $pageStores = [$pageStores];
        }

        // If page is on all stores (0), check if consumer has 'all' access
        if (in_array(0, $pageStores, true)) {
            $allowedStores = $consumer->getAllowedStoreIds();
            if ($allowedStores !== 'all') {
                throw new AccessDeniedHttpException('Token does not have access to all-stores content');
            }
            return;
        }

        // Check if consumer can access at least one of the page's stores
        foreach ($pageStores as $storeId) {
            if ($consumer->canAccessStore((int) $storeId)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Token does not have access to this page\'s stores');
    }

    private function logActivity(string $action, ?array $oldData, ?Mage_Cms_Model_Page $page, Mage_Oauth_Model_Consumer $consumer): void
    {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => 'cms/page',
                'action' => $action,
                'entity_id' => $page ? (int) $page->getId() : ($oldData['page_id'] ?? 0),
                'old_data' => $oldData,
                'new_data' => $page?->getData(),
                'consumer_id' => $consumer->getId(),
                'username' => 'API: ' . $consumer->getName(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the request
            Mage::logException($e);
        }
    }
}
