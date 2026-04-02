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
 * CMS Block State Processor
 *
 * Handles create, update, and delete operations for CMS blocks.
 * Requires JWT authentication with cms-blocks/write permission.
 */
final class CmsBlockProcessor extends \Maho\ApiPlatform\Processor
{
    protected ?string $modelAlias = 'cms/block';
    protected ?string $writePermission = 'cms-blocks/write';
    protected ?string $deletePermission = 'cms-blocks/delete';
    protected ?string $entityType = 'cms/block';
    protected ?string $entityLabel = 'block';

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

        $model->addData([
            'identifier' => $data->identifier,
            'title' => $data->title,
            'content' => $this->contentSanitizer->sanitize($data->content ?? ''),
            'is_active' => $data->isActive ? 1 : 0,
            'stores' => $storeIds,
        ]);
    }

    #[\Override]
    protected function buildResponse(object $model, mixed $data): CmsBlock
    {
        $data->id = (int) $model->getId();
        $data->content = $model->getData('content');
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    #[\Override]
    protected function processUpdate(int $id, mixed $data, ApiUser $user): mixed
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'CMS block not found');

        $blockStores = $model->getStoreId();
        $this->validateEntityStoreAccess(is_array($blockStores) ? $blockStores : [$blockStores], $user, 'block');

        $oldData = $model->getData();
        $this->applyData($model, $data, $user);
        $this->safeSave($model, 'update block');
        $this->logApiActivity('cms/block', 'update', $oldData, $model, $user);
        return $this->buildResponse($model, $data);
    }

    #[\Override]
    protected function processDelete(int $id, ApiUser $user): null
    {
        $model = $this->loadOrFail($this->modelAlias, $id, 'CMS block not found');

        $blockStores = $model->getStoreId();
        $this->validateEntityStoreAccess(is_array($blockStores) ? $blockStores : [$blockStores], $user, 'block');

        $oldData = $model->getData();
        $this->safeDelete($model, 'delete block');
        $this->logApiActivity('cms/block', 'delete', $oldData, null, $user);
        return null;
    }
}
