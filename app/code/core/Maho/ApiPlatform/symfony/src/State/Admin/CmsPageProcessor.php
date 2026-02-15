<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Cms_Model_Page;
use Mage_Core_Model_Store;
use Maho\ApiPlatform\ApiResource\Admin\CmsPage;
use Maho\ApiPlatform\Security\AdminApiAuthenticator;
use Maho\ApiPlatform\Security\AdminApiUser;
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
        $user = $request?->attributes->get(AdminApiAuthenticator::CONSUMER_ATTRIBUTE);

        if (!$user instanceof AdminApiUser || !$user->hasPermission('admin/cms-pages/write')) {
            throw new AccessDeniedHttpException('Token does not have write permission for cms_pages');
        }

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($uriVariables['id'], $user);
        }

        assert($data instanceof CmsPage);

        // Resolve store IDs
        $storeIds = $this->resolveStoreIds($data->stores, $user);

        // Sanitize content
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content);

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate((int) $uriVariables['id'], $data, $storeIds, $sanitizedContent, $user);
        }

        return $this->handleCreate($data, $storeIds, $sanitizedContent, $user);
    }

    private function handleCreate(CmsPage $data, array $storeIds, string $sanitizedContent, AdminApiUser $user): CmsPage
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page');

        $pageData = [
            'identifier' => $data->identifier,
            'title' => $data->title,
            'content_heading' => $data->contentHeading,
            'content' => $sanitizedContent,
            'meta_keywords' => $data->metaKeywords,
            'meta_description' => $data->metaDescription,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
        ];

        // Add root_template if provided
        if ($data->rootTemplate !== null) {
            $pageData['root_template'] = $data->rootTemplate;
        }

        $page->setData($pageData);

        try {
            $page->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create page: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('create', null, $page, $user);

        $data->id = (int) $page->getId();
        $data->content = $sanitizedContent;
        return $data;
    }

    private function handleUpdate(int $id, CmsPage $data, array $storeIds, string $sanitizedContent, AdminApiUser $user): CmsPage
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            throw new NotFoundHttpException('CMS page not found');
        }

        // Check store access
        $this->validateStoreAccess($page, $user);

        $oldData = $page->getData();

        $updateData = [
            'identifier' => $data->identifier,
            'title' => $data->title,
            'content_heading' => $data->contentHeading,
            'content' => $sanitizedContent,
            'meta_keywords' => $data->metaKeywords,
            'meta_description' => $data->metaDescription,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
        ];

        // Add root_template if provided
        if ($data->rootTemplate !== null) {
            $updateData['root_template'] = $data->rootTemplate;
        }

        $page->addData($updateData);

        try {
            $page->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update page: ' . $e->getMessage());
        }

        // Log activity
        $this->logActivity('update', $oldData, $page, $user);

        $data->id = (int) $page->getId();
        $data->content = $sanitizedContent;
        return $data;
    }

    private function handleDelete(int $id, AdminApiUser $user): null
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            throw new NotFoundHttpException('CMS page not found');
        }

        // Check store access
        $this->validateStoreAccess($page, $user);

        $oldData = $page->getData();

        try {
            $page->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete page: ' . $e->getMessage());
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

    private function validateStoreAccess(Mage_Cms_Model_Page $page, AdminApiUser $user): void
    {
        $pageStores = $page->getStoreId();
        if (!is_array($pageStores)) {
            $pageStores = [$pageStores];
        }

        // If page is on all stores (0), check if user has all-stores access
        if (in_array(0, $pageStores, true)) {
            $allowedStores = $user->getAllowedStoreIds();
            if ($allowedStores !== null) {
                throw new AccessDeniedHttpException('Token does not have access to all-stores content');
            }
            return;
        }

        // Check if user can access at least one of the page's stores
        foreach ($pageStores as $storeId) {
            if ($user->canAccessStore((int) $storeId)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Token does not have access to this page\'s stores');
    }

    private function logActivity(string $action, ?array $oldData, ?Mage_Cms_Model_Page $page, AdminApiUser $user): void
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
                'consumer_id' => $user->getConsumer()->getId(),
                'username' => 'API: ' . $user->getConsumerName(),
            ]);
        } catch (\Exception $e) {
            // Log but don't fail the request
            Mage::logException($e);
        }
    }
}
