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

namespace Maho\Catalog\Api\State\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Downloadable_Model_Product_Type;
use Maho\Catalog\Api\Resource\DownloadableLink;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\Catalog\Api\State\Provider\DownloadableLinkProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<DownloadableLink, DownloadableLink|DownloadableLink[]|null>
 */
final class DownloadableLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly DownloadableLinkProvider $provider,
    ) {}

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
        $this->loadDownloadableProduct($productId);

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

        try {
            $link->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to create link: ' . $e->getMessage());
        }

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
        $this->loadDownloadableProduct($productId);

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

        try {
            $link->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to update link: ' . $e->getMessage());
        }

        return $this->provider->getLinks($this->loadDownloadableProduct($productId));
    }

    private function handleDelete(int $productId, int $linkId): null
    {
        $this->loadDownloadableProduct($productId);

        if ($linkId <= 0) {
            throw new BadRequestHttpException('linkId is required');
        }

        /** @var \Mage_Downloadable_Model_Link $link */
        $link = Mage::getModel('downloadable/link')->load($linkId);
        if (!$link->getId() || (int) $link->getProductId() !== $productId) {
            throw new NotFoundHttpException('Downloadable link not found');
        }

        try {
            $link->delete();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to delete link: ' . $e->getMessage());
        }

        return null;
    }

    private function loadDownloadableProduct(int $id): Mage_Catalog_Model_Product
    {
        StoreContext::ensureStore();
        $storeId = StoreContext::getStoreId();

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');
        if ($storeId) {
            $product->setStoreId($storeId);
        }
        $product->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        if ($product->getTypeId() !== Mage_Downloadable_Model_Product_Type::TYPE_DOWNLOADABLE) {
            throw new BadRequestHttpException('Product is not a downloadable product');
        }

        return $product;
    }

    private function getAuthorizedUser(): ApiUser
    {
        $user = $this->security->getUser();
        if (!$user instanceof ApiUser) {
            throw new AccessDeniedHttpException('Authentication required');
        }
        return $user;
    }

    private function requirePermission(ApiUser $user, string $permission): void
    {
        if (!$user->hasPermission($permission)) {
            throw new AccessDeniedHttpException("Missing permission: {$permission}");
        }
    }
}
