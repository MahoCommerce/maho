<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Cms_Model_Page;
use Maho\ApiPlatform\ApiResource\Admin\CmsPage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider for Admin CMS Page API - loads existing pages for PUT/DELETE operations
 *
 * @implements ProviderInterface<CmsPage>
 */
final class CmsPageProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): ?CmsPage
    {
        if (!isset($uriVariables['id'])) {
            return null;
        }

        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($uriVariables['id']);

        if (!$page->getId()) {
            throw new NotFoundHttpException('CMS page not found');
        }

        $dto = new CmsPage();
        $dto->id = (int) $page->getId();
        $dto->identifier = $page->getIdentifier();
        $dto->title = $page->getTitle();
        $dto->contentHeading = $page->getContentHeading();
        $dto->content = $page->getContent();
        $dto->metaKeywords = $page->getMetaKeywords();
        $dto->metaDescription = $page->getMetaDescription();
        $dto->isActive = (bool) $page->getIsActive();

        return $dto;
    }
}
