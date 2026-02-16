<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Cms_Model_Block;
use Maho\ApiPlatform\ApiResource\Admin\CmsBlock;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider for Admin CMS Block API - loads existing blocks for GET/PUT/DELETE operations
 *
 * @implements ProviderInterface<CmsBlock>
 */
final class CmsBlockProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsBlock|array|null
    {
        if ($operation instanceof GetCollection) {
            return $this->getCollection();
        }

        if (!isset($uriVariables['id'])) {
            return null;
        }

        return $this->getItem((int) $uriVariables['id']);
    }

    /**
     * @return CmsBlock[]
     */
    private function getCollection(): array
    {
        $collection = Mage::getResourceModel('cms/block_collection')
            ->setOrder('title', 'ASC');

        $blocks = [];
        foreach ($collection as $block) {
            $blocks[] = $this->mapBlockToDto($block);
        }

        return $blocks;
    }

    private function getItem(int $id): CmsBlock
    {
        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block')->load($id);

        if (!$block->getId()) {
            throw new NotFoundHttpException('CMS block not found');
        }

        return $this->mapBlockToDto($block);
    }

    private function mapBlockToDto(Mage_Cms_Model_Block $block): CmsBlock
    {
        $dto = new CmsBlock();
        $dto->id = (int) $block->getId();
        $dto->identifier = $block->getIdentifier() ?? '';
        $dto->title = $block->getTitle() ?? '';
        $dto->content = $block->getContent() ?? '';
        $dto->isActive = (bool) $block->getIsActive();

        return $dto;
    }
}
