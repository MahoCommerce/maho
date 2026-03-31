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
use Mage_Cms_Model_Block;
use Maho\Cms\Api\Resource\CmsBlock;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Trait\AuthenticationTrait;
use Maho\ApiPlatform\Trait\StoreAccessTrait;
use Maho\ApiPlatform\Service\ContentSanitizer;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * CMS Block State Processor
 *
 * Handles create, update, and delete operations for CMS blocks.
 * Requires JWT authentication with cms-blocks/write permission.
 *
 * @implements ProcessorInterface<CmsBlock, CmsBlock|null>
 */
final class CmsBlockProcessor implements ProcessorInterface
{
    use AuthenticationTrait;
    use StoreAccessTrait;

    public function __construct(
        Security $security,
        private readonly ContentSanitizer $contentSanitizer,
    ) {
        $this->security = $security;
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?CmsBlock
    {
        $user = $this->getAuthorizedUser();

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'cms-blocks/delete');
            return $this->handleDelete((int) $uriVariables['id'], $user);
        }

        $this->requirePermission($user, 'cms-blocks/write');

        assert($data instanceof CmsBlock);

        $storeIds = $this->resolveStoreIds($data->stores, $user);
        $sanitizedContent = $this->contentSanitizer->sanitize($data->content ?? '');

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate((int) $uriVariables['id'], $data, $storeIds, $sanitizedContent, $user);
        }

        return $this->handleCreate($data, $storeIds, $sanitizedContent, $user);
    }

    private function handleCreate(CmsBlock $data, array $storeIds, string $sanitizedContent, ApiUser $user): CmsBlock
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

        $this->logActivity('create', null, $block, $user);

        $data->id = (int) $block->getId();
        $data->content = $sanitizedContent;
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    private function handleUpdate(int $id, CmsBlock $data, array $storeIds, string $sanitizedContent, ApiUser $user): CmsBlock
    {
        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            throw new NotFoundHttpException('CMS block not found');
        }

        $blockStores = $block->getStoreId();
        $this->validateEntityStoreAccess(is_array($blockStores) ? $blockStores : [$blockStores], $user, 'block');

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

        $this->logActivity('update', $oldData, $block, $user);

        $data->id = (int) $block->getId();
        $data->content = $sanitizedContent;
        $data->status = $data->isActive ? 'enabled' : 'disabled';
        return $data;
    }

    private function handleDelete(int $id, ApiUser $user): null
    {
        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            throw new NotFoundHttpException('CMS block not found');
        }

        $blockStores = $block->getStoreId();
        $this->validateEntityStoreAccess(is_array($blockStores) ? $blockStores : [$blockStores], $user, 'block');

        $oldData = $block->getData();

        try {
            $block->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete block: ' . $e->getMessage());
        }

        $this->logActivity('delete', $oldData, null, $user);

        return null;
    }

    private function logActivity(string $action, ?array $oldData, ?Mage_Cms_Model_Block $block, ApiUser $user): void
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
                'api_user_id' => $user->getApiUserId(),
                'username' => 'API: ' . $user->getUserIdentifier(),
            ]);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }
}
