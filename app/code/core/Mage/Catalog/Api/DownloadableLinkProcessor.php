<?php

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_Catalog
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Mage;
use Mage_Catalog_Model_Product;
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
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            $linkId = (int) ($body['linkId'] ?? $body['link_id'] ?? 0);
            if ($linkId <= 0) {
                $linkId = (int) ($request?->query->get('linkId') ?? 0);
            }
            return $this->handleDelete($productId, $linkId);
        }

        $this->requirePermission($user, 'products/write');

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

        /** @var \Mage_Downloadable_Model_Link $link */
        $link = Mage::getModel('downloadable/link');
        $link->setProductId($productId);
        $link->setStoreId(0);
        $link->setTitle($title);
        $link->setPrice((float) ($body['price'] ?? 0));
        $link->setSortOrder((int) ($body['sortOrder'] ?? $body['sort_order'] ?? 0));
        $link->setNumberOfDownloads((int) ($body['numberOfDownloads'] ?? $body['number_of_downloads'] ?? 0));
        $link->setLinkType($linkType);
        $link->setIsShareable(\Mage_Downloadable_Model_Link::LINK_SHAREABLE_CONFIG);

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
            $link->setPrice((float) $body['price']);
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

}
