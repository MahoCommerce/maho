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
use Maho\ApiPlatform\Http\RequestInput;
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
        $input = RequestInput::of($this->parseRequestBody($request));
        /** @var \Mage_Downloadable_Service_Link $service */
        $service = \Mage::getService('downloadable/link');

        if ($operation instanceof Post) {
            /** @var Mage_Downloadable_Model_Link $link */
            $link = \Mage::getModel('downloadable/link');
            $link->setData('title', $input->string('title') ?? '');
            $link->setPrice($input->float('price') ?? 0.0);
            $link->setSortOrder($input->int('sortOrder', 'sort_order') ?? 0);
            $link->setNumberOfDownloads($input->int('numberOfDownloads', 'number_of_downloads') ?? 0);
            $link->setLinkType($input->string('linkType', 'link_type') ?? 'url');
            $link->setData('is_shareable', $input->int('isShareable', 'is_shareable') ?? Mage_Downloadable_Model_Link::LINK_SHAREABLE_CONFIG);
            if ($link->getLinkType() === 'url') {
                $link->setLinkUrl($input->string('linkUrl', 'link_url') ?? '');
            }
            if ($input->string('sampleType', 'sample_type') === 'url') {
                $link->setSampleType('url');
                $link->setSampleUrl($input->string('sampleUrl', 'sample_url') ?? '');
            }
            return $this->mapToDto($service->save($product, $link));
        }

        $linkId = $input->int('linkId', 'link_id')
            ?? ($operation instanceof DeleteOperationInterface ? (int) $request?->query->get('linkId') : $input->int('id') ?? 0);
        $link = $service->getById($product, $linkId);

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($product, $link);
            return null;
        }

        if (($title = $input->string('title')) !== null) {
            $link->setData('title', $title);
        }
        if (($price = $input->float('price')) !== null) {
            $link->setPrice($price);
        }
        if (($sortOrder = $input->int('sortOrder', 'sort_order')) !== null) {
            $link->setSortOrder($sortOrder);
        }
        if (($downloads = $input->int('numberOfDownloads', 'number_of_downloads')) !== null) {
            $link->setNumberOfDownloads($downloads);
        }
        if (($linkUrl = $input->string('linkUrl', 'link_url')) !== null) {
            $link->setLinkUrl($linkUrl);
        }
        if (($isShareable = $input->int('isShareable', 'is_shareable')) !== null) {
            $link->setData('is_shareable', $isShareable);
        }
        if (($sampleUrl = $input->string('sampleUrl', 'sample_url')) !== null) {
            $link->setSampleType($sampleUrl === '' ? null : 'url');
            $link->setSampleUrl($sampleUrl === '' ? null : $sampleUrl);
        }
        $service->save($product, $link);

        return $this->provider->getLinks(
            $this->loadProduct($productId, Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE),
            withLinkUrl: true,
        );
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
