<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Cms_Model_Block;
use Maho\ApiPlatform\ApiResource\Admin\CmsBlock;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * @implements ProviderInterface<CmsBlock>
 */
final class CmsBlockProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CmsBlock
    {
        if (!isset($uriVariables['id'])) {
            return null;
        }

        /** @var Mage_Cms_Model_Block $block */
        $block = Mage::getModel('cms/block')->load($uriVariables['id']);

        if (!$block->getId()) {
            throw new NotFoundHttpException('CMS block not found');
        }

        $dto = new CmsBlock();
        $dto->id = (int) $block->getId();
        $dto->identifier = $block->getIdentifier();
        $dto->title = $block->getTitle();
        $dto->content = $block->getContent();
        $dto->isActive = (bool) $block->getIsActive();

        return $dto;
    }
}
