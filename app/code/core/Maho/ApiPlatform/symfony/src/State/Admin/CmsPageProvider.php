<?php

declare(strict_types=1);

namespace Maho\ApiPlatform\State\Admin;

use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use Mage;
use Mage_Cms_Model_Page;
use Maho\ApiPlatform\ApiResource\Admin\CmsPage;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provider for Admin CMS Page API - loads existing pages for GET/PUT/DELETE operations
 *
 * @implements ProviderInterface<CmsPage>
 */
final class CmsPageProvider implements ProviderInterface
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CmsPage|array|null
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
     * @return CmsPage[]
     */
    private function getCollection(): array
    {
        $collection = Mage::getResourceModel('cms/page_collection')
            ->setOrder('title', 'ASC');

        $pages = [];
        foreach ($collection as $page) {
            $pages[] = $this->mapPageToDto($page);
        }

        return $pages;
    }

    private function getItem(int $id): CmsPage
    {
        /** @var Mage_Cms_Model_Page $page */
        $page = Mage::getModel('cms/page')->load($id);

        if (!$page->getId()) {
            throw new NotFoundHttpException('CMS page not found');
        }

        return $this->mapPageToDto($page);
    }

    private function mapPageToDto(Mage_Cms_Model_Page $page): CmsPage
    {
        $dto = new CmsPage();
        $dto->id = (int) $page->getId();
        $dto->identifier = $page->getIdentifier() ?? '';
        $dto->title = $page->getTitle() ?? '';
        $dto->contentHeading = $page->getContentHeading();
        $dto->content = $page->getContent() ?? '';
        $dto->metaKeywords = $page->getMetaKeywords();
        $dto->metaDescription = $page->getMetaDescription();
        $dto->isActive = (bool) $page->getIsActive();
        $dto->rootTemplate = $page->getRootTemplate();

        return $dto;
    }
}
