<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Mage_Downloadable_Model_Link;
use Mage_Downloadable_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;

final class DownloadableLinkProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly DownloadableLinkProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DownloadableLink|array|null
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);
        $product = $this->loadProduct($productId, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE);

        $request = $context['request'] ?? null;
        $body = $this->parseRequestBody($request);
        $field = static fn(string $camel, string $snake): mixed => $body[$camel] ?? $body[$snake] ?? null;
        /** @var \Mage_Downloadable_Service_Link $service */
        $service = \Mage::getService('downloadable/link');

        if ($operation instanceof Post) {
            /** @var Mage_Downloadable_Model_Link $link */
            $link = \Mage::getModel('downloadable/link');
            $link->setData('title', (string) ($body['title'] ?? ''));
            $link->setPrice((float) ($body['price'] ?? 0));
            $link->setSortOrder((int) $field('sortOrder', 'sort_order'));
            $link->setNumberOfDownloads((int) $field('numberOfDownloads', 'number_of_downloads'));
            $link->setLinkType((string) ($field('linkType', 'link_type') ?? 'url'));
            $link->setData('is_shareable', (int) ($field('isShareable', 'is_shareable') ?? Mage_Downloadable_Model_Link::LINK_SHAREABLE_CONFIG));
            if ($link->getLinkType() === 'url') {
                $link->setLinkUrl((string) $field('linkUrl', 'link_url'));
            }
            if ($field('sampleType', 'sample_type') === 'url') {
                $link->setSampleType('url');
                $link->setSampleUrl((string) $field('sampleUrl', 'sample_url'));
            }
            return $this->mapToDto($service->save($product, $link));
        }

        $linkId = (int) ($field('linkId', 'link_id') ?? ($operation instanceof DeleteOperationInterface ? $request?->query->get('linkId') : $body['id'] ?? 0));
        $link = $service->getById($product, $linkId);

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($product, $link);
            return null;
        }

        if (isset($body['title'])) {
            $link->setData('title', (string) $body['title']);
        }
        if (isset($body['price'])) {
            $link->setPrice((float) $body['price']);
        }
        if (($sortOrder = $field('sortOrder', 'sort_order')) !== null) {
            $link->setSortOrder((int) $sortOrder);
        }
        if (($downloads = $field('numberOfDownloads', 'number_of_downloads')) !== null) {
            $link->setNumberOfDownloads((int) $downloads);
        }
        if (($linkUrl = $field('linkUrl', 'link_url')) !== null) {
            $link->setLinkUrl((string) $linkUrl);
        }
        if (($isShareable = $field('isShareable', 'is_shareable')) !== null) {
            $link->setData('is_shareable', (int) $isShareable);
        }
        if (($sampleUrl = $field('sampleUrl', 'sample_url')) !== null) {
            $link->setSampleType($sampleUrl === '' ? null : 'url');
            $link->setSampleUrl($sampleUrl === '' ? null : (string) $sampleUrl);
        }
        $service->save($product, $link);

        return $this->provider->getLinks($this->loadProduct($productId, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE));
    }

    private function mapToDto(Mage_Downloadable_Model_Link $link): DownloadableLink
    {
        $dto = new DownloadableLink();
        $dto->id = (int) $link->getId();
        $dto->title = (string) $link->getTitle();
        $dto->price = (float) $link->getPrice();
        $dto->sortOrder = (int) $link->getSortOrder();
        $dto->numberOfDownloads = (int) $link->getNumberOfDownloads();
        $dto->isShareable = (int) $link->getIsShareable();
        $dto->linkType = (string) $link->getLinkType();
        $dto->linkUrl = $link->getLinkUrl();
        $dto->sampleUrl = $link->getSampleUrl();
        $dto->sampleType = $link->getSampleType();
        return $dto;
    }
}
