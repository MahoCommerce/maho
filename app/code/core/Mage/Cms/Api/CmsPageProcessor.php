<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Mage_Cms
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Mage\Cms\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Mage;
use Mage_Cms_Model_Page;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\ActivityLogTrait;
use Maho\ApiPlatform\Trait\StoreAccessTrait;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * CMS Page State Processor
 *
 * Handles create, update, and delete operations for CMS pages.
 * Requires JWT authentication with cms-pages/write permission.
 */
final class CmsPageProcessor extends \Maho\ApiPlatform\Processor
{
    use ActivityLogTrait;
    use StoreAccessTrait;

    public function __construct(
        Security $security,
        private readonly ContentSanitizer $contentSanitizer,
    ) {
        parent::__construct($security);
    }

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

        if ($data->pageLayout !== null) {
            $pageData['root_template'] = $data->pageLayout;
        }

        $page->setData($pageData);

        $this->safeSave($page, 'create page');

        $this->logApiActivity('cms/page', 'create', null, $page, $user);

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

        $pageStores = $page->getStoreId();
        $this->validateEntityStoreAccess(is_array($pageStores) ? $pageStores : [$pageStores], $user, 'page');

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

        if ($data->pageLayout !== null) {
            $updateData['root_template'] = $data->pageLayout;
        }

        $page->addData($updateData);

        $this->safeSave($page, 'update page');

        $this->logApiActivity('cms/page', 'update', $oldData, $page, $user);

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

        $pageStores = $page->getStoreId();
        $this->validateEntityStoreAccess(is_array($pageStores) ? $pageStores : [$pageStores], $user, 'page');

        $oldData = $page->getData();

        $this->safeDelete($page, 'delete page');

        $this->logApiActivity('cms/page', 'delete', $oldData, null, $user);

        return null;
    }
}
