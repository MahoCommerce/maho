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
use Mage;
use Mage_Downloadable_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 */
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

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);

        $request = $context['request'] ?? null;
        $body = $this->parseRequestBody($request);

        if ($operation instanceof DeleteOperationInterface) {
            $linkId = (int) ($body['linkId'] ?? $body['link_id'] ?? 0);
            if ($linkId <= 0) {
                $linkId = (int) ($request?->query->get('linkId') ?? 0);
            }
            return $this->handleDelete($productId, $linkId);
        }

        if ($operation instanceof Post) {
            return $this->handleCreate($productId, $body);
        }

        return $this->handleUpdate($productId, $body);
    }

    private function handleCreate(int $productId, array $body): DownloadableLink
    {
        $this->loadProduct($productId, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE);

        $title = (string) ($body['title'] ?? '');
        if ($title === '') {
            throw new BadRequestHttpException('title is required');
        }

        $linkType = (string) ($body['linkType'] ?? $body['link_type'] ?? 'url');
        if (!in_array($linkType, ['url', 'file'], true)) {
            throw new BadRequestHttpException("Invalid linkType: {$linkType}. Valid: url, file");
        }

        $price = (float) ($body['price'] ?? 0);
        if ($price < 0) {
            throw new BadRequestHttpException('Price must not be negative');
        }

        /** @var \Mage_Downloadable_Model_Link $link */
        $link = Mage::getModel('downloadable/link');
        $link->setProductId($productId);
        $link->setStoreId(0);
        $link->setTitle($title);
        $link->setPrice($price);
        $link->setSortOrder((int) ($body['sortOrder'] ?? $body['sort_order'] ?? 0));
        $link->setNumberOfDownloads((int) ($body['numberOfDownloads'] ?? $body['number_of_downloads'] ?? 0));
        $link->setLinkType($linkType);
        $link->setIsShareable($this->validateIsShareable(
            $body['isShareable'] ?? $body['is_shareable'] ?? \Mage_Downloadable_Model_Link::LINK_SHAREABLE_CONFIG,
        ));

        if ($linkType === 'url') {
            $linkUrl = (string) ($body['linkUrl'] ?? $body['link_url'] ?? '');
            if ($linkUrl === '') {
                throw new BadRequestHttpException('linkUrl is required for url type links');
            }
            $link->setLinkUrl($linkUrl);
        }

        // Sample
        $sampleType = $body['sampleType'] ?? $body['sample_type'] ?? null;
        if ($sampleType === 'url') {
            $link->setSampleType('url');
            $link->setSampleUrl($body['sampleUrl'] ?? $body['sample_url'] ?? '');
        }

        $this->safeSave($link, 'create link');

        $dto = new DownloadableLink();
        $dto->id = (int) $link->getId();
        $dto->title = $title;
        $dto->price = (float) $link->getPrice();
        $dto->sortOrder = (int) $link->getSortOrder();
        $dto->numberOfDownloads = (int) $link->getNumberOfDownloads();
        $dto->isShareable = (int) $link->getIsShareable();
        $dto->linkType = $linkType;
        $dto->linkUrl = $link->getLinkUrl();
        $dto->sampleUrl = $link->getSampleUrl();
        $dto->sampleType = $link->getSampleType();

        return $dto;
    }

    private function handleUpdate(int $productId, array $body): array
    {
        $this->loadProduct($productId, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE);

        $linkId = (int) ($body['linkId'] ?? $body['link_id'] ?? $body['id'] ?? 0);
        if ($linkId <= 0) {
            throw new BadRequestHttpException('linkId is required');
        }

        /** @var \Mage_Downloadable_Model_Link $link */
        $link = Mage::getModel('downloadable/link')->load($linkId);
        if (!$link->getId() || (int) $link->getProductId() !== $productId) {
            throw new NotFoundHttpException('Downloadable link not found');
        }

        if (isset($body['title'])) {
            $link->setTitle($body['title']);
        }
        if (isset($body['price'])) {
            $price = (float) $body['price'];
            if ($price < 0) {
                throw new BadRequestHttpException('Price must not be negative');
            }
            $link->setPrice($price);
        }
        if (isset($body['sortOrder']) || isset($body['sort_order'])) {
            $link->setSortOrder((int) ($body['sortOrder'] ?? $body['sort_order']));
        }
        if (isset($body['numberOfDownloads']) || isset($body['number_of_downloads'])) {
            $link->setNumberOfDownloads((int) ($body['numberOfDownloads'] ?? $body['number_of_downloads']));
        }
        if (isset($body['linkUrl']) || isset($body['link_url'])) {
            $link->setLinkUrl($body['linkUrl'] ?? $body['link_url']);
        }
        if (isset($body['isShareable']) || isset($body['is_shareable'])) {
            $link->setIsShareable($this->validateIsShareable($body['isShareable'] ?? $body['is_shareable']));
        }
        $sampleUrl = $body['sampleUrl'] ?? $body['sample_url'] ?? null;
        if ($sampleUrl !== null) {
            if ($sampleUrl === '') {
                $link->setData('sample_type');
                $link->setData('sample_url');
            } else {
                $link->setSampleType('url');
                $link->setSampleUrl($sampleUrl);
            }
        }

        $this->safeSave($link, 'update link');

        return $this->provider->getLinks($this->loadProduct($productId, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE));
    }

    private function handleDelete(int $productId, int $linkId): null
    {
        $this->loadProduct($productId, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE);

        if ($linkId <= 0) {
            throw new BadRequestHttpException('linkId is required');
        }

        /** @var \Mage_Downloadable_Model_Link $link */
        $link = Mage::getModel('downloadable/link')->load($linkId);
        if (!$link->getId() || (int) $link->getProductId() !== $productId) {
            throw new NotFoundHttpException('Downloadable link not found');
        }

        $this->safeDelete($link, 'delete link');

        return null;
    }

    private function validateIsShareable(mixed $value): int
    {
        $isShareable = (int) $value;
        if (!in_array($isShareable, [
            \Mage_Downloadable_Model_Link::LINK_SHAREABLE_NO,
            \Mage_Downloadable_Model_Link::LINK_SHAREABLE_YES,
            \Mage_Downloadable_Model_Link::LINK_SHAREABLE_CONFIG,
        ], true)) {
            throw new BadRequestHttpException('isShareable must be 0 (no), 1 (yes), or 2 (use config)');
        }
        return $isShareable;
    }

}
