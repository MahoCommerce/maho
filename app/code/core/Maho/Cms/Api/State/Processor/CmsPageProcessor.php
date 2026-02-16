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

namespace Maho\Cms\Api\State\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Cms_Model_Page;
use Mage_Core_Model_Store;
use Maho\Cms\Api\Resource\CmsPage;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * CMS Page State Processor
 *
 * Handles create, update, and delete operations for CMS pages.
 * Requires JWT authentication with cms-pages/write permission.
 *
 * @implements ProcessorInterface<CmsPage, CmsPage|null>
 */
final class CmsPageProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ContentSanitizer $contentSanitizer,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CmsPage
    {
        $user = $this->getAuthorizedUser();

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'cms-pages/delete');
            return $this->handleDelete((int) $uriVariables['id'], $user);
        }

        $this->requirePermission($user, 'cms-pages/write');

        assert($data instanceof CmsPage);

        $storeIds = $this->resolveStoreIds($data->stores, $user);
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content ?? '');

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate((int) $uriVariables['id'], $data, $storeIds, $sanitizedContent, $user);
        }

        return $this->handleCreate($data, $storeIds, $sanitizedContent, $user);
    }

    private function handleCreate(CmsPage $data, array $storeIds, string $sanitizedContent, ApiUser $user): CmsPage
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

        if ($data->rootTemplate !== null) {
            $pageData['root_template'] = $data->rootTemplate;
        }

        $page->setData($pageData);

        try {
            $page->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create page: ' . $e->getMessage());
        }

        $this->logActivity('create', null, $page, $user);

        $data->id = (int) $page->getId();
        $data->content = $sanitizedContent;
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    private function handleUpdate(int $id, CmsPage $data, array $storeIds, string $sanitizedContent, ApiUser $user): CmsPage
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            throw new NotFoundHttpException('CMS page not found');
        }

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

        if ($data->rootTemplate !== null) {
            $updateData['root_template'] = $data->rootTemplate;
        }

        $page->addData($updateData);

        try {
            $page->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update page: ' . $e->getMessage());
        }

        $this->logActivity('update', $oldData, $page, $user);

        $data->id = (int) $page->getId();
        $data->content = $sanitizedContent;
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    private function handleDelete(int $id, ApiUser $user): null
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            throw new NotFoundHttpException('CMS page not found');
        }

        $this->validateStoreAccess($page, $user);

        $oldData = $page->getData();

        try {
            $page->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete page: ' . $e->getMessage());
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
                return [0]; // Admin store = all stores
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

    private function validateStoreAccess(Mage_Cms_Model_Page $page, ApiUser $user): void
    {
        $pageStores = $page->getStoreId();
        if (!is_array($pageStores)) {
            $pageStores = [$pageStores];
        }

        if (in_array(0, $pageStores, true)) {
            $allowedStores = $user->getAllowedStoreIds();
            if ($allowedStores !== null) {
                throw new AccessDeniedHttpException('Access denied for all-stores content');
            }
            return;
        }

        foreach ($pageStores as $storeId) {
            if ($user->canAccessStore((int) $storeId)) {
                return;
            }
        }

        throw new AccessDeniedHttpException('Access denied for this page\'s stores');
    }

    private function logActivity(string $action, ?array $oldData, ?Mage_Cms_Model_Page $page, ApiUser $user): void
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
                'api_user_id' => $user->getApiUserId(),
                'username' => 'API: ' . $user->getUserIdentifier(),
            ]);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }
}
