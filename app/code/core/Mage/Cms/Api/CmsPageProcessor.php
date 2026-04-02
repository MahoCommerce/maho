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

use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * CMS Page State Processor
 *
 * Handles create, update, and delete operations for CMS pages.
 * Requires JWT authentication with cms-pages/write permission.
 */
final class CmsPageProcessor extends \Maho\ApiPlatform\Processor
{
    protected ?string $modelAlias = 'cms/page';
    protected ?string $writePermission = 'cms-pages/write';
    protected ?string $deletePermission = 'cms-pages/delete';
    protected ?string $entityType = 'cms/page';
    protected ?string $entityLabel = 'page';

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

        $pageData = [
            'identifier' => $data->identifier,
            'title' => $data->title,
            'content_heading' => $data->contentHeading,
            'content' => $this->contentSanitizer->sanitize($data->content ?? ''),
            'meta_keywords' => $data->metaKeywords,
            'meta_description' => $data->metaDescription,
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
        ];

        if ($data->pageLayout !== null) {
            $pageData['root_template'] = $data->pageLayout;
        }

        $model->addData($pageData);
    }

    #[\Override]
    protected function buildResponse(object $model, mixed $data): CmsPage
    {
        $data->id = (int) $model->getId();
        $data->content = $model->getData('content');
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    #[\Override]
    protected function processUpdate(int $id, mixed $data, ApiUser $user): mixed
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'CMS page not found');

        $pageStores = $model->getStoreId();
        $this->validateEntityStoreAccess(is_array($pageStores) ? $pageStores : [$pageStores], $user, 'page');

        $oldData = $model->getData();
        $this->applyData($model, $data, $user);
        $this->safeSave($model, 'update page');
        $this->logApiActivity('cms/page', 'update', $oldData, $model, $user);
        return $this->buildResponse($model, $data);
    }

    #[\Override]
    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'CMS page not found');

        $pageStores = $model->getStoreId();
        $this->validateEntityStoreAccess(is_array($pageStores) ? $pageStores : [$pageStores], $user, 'page');

        $oldData = $model->getData();
        $this->safeDelete($model, 'delete page');
        $this->logApiActivity('cms/page', 'delete', $oldData, null, $user);
        return null;
    }
}
