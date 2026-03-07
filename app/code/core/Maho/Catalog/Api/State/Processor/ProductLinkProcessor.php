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
use Maho\Catalog\Api\Resource\ProductLink;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\Catalog\Api\State\Provider\ProductLinkProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<ProductLink, ProductLink|ProductLink[]|null>
 */
final class ProductLinkProcessor implements ProcessorInterface
{
    private const LINK_SETTER_MAP = [
        'related' => 'setRelatedLinkData',
        'cross_sell' => 'setCrossSellLinkData',
        'up_sell' => 'setUpSellLinkData',
    ];

    private const LINK_COLLECTION_MAP = [
        'related' => 'getRelatedProductCollection',
        'cross_sell' => 'getCrossSellProductCollection',
        'up_sell' => 'getUpSellProductCollection',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly ProductLinkProvider $provider,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductLink|array|null
    {
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);
        $linkType = ProductLinkProvider::extractLinkType($context);

        if (!isset(self::LINK_SETTER_MAP[$linkType])) {
            throw new BadRequestHttpException("Invalid link type: {$linkType}. Valid types: related, cross_sell, up_sell");
        }

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            $linkedProductId = ProductLinkProvider::extractLinkedProductId($context);
            return $this->handleRemoveLink($productId, $linkType, $linkedProductId);
        }

        $this->requirePermission($user, 'products/write');

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

        if ($operation instanceof Post) {
            return $this->handleAddLink($productId, $linkType, $body);
        }

        // PUT = replace all
        return $this->handleReplaceAll($productId, $linkType, $body);
    }

    private function handleReplaceAll(int $productId, string $linkType, array $body): array
    {
        $product = $this->loadProduct($productId);

        if (!is_array($body)) {
            throw new BadRequestHttpException('Request body must be an array of links');
        }

        $linkData = [];
        foreach ($body as $link) {
            if (!is_array($link)) {
                throw new BadRequestHttpException('Each link must be an object with linkedProductId');
            }
            $linkedId = (int) ($link['linkedProductId'] ?? $link['linked_product_id'] ?? 0);
            if ($linkedId <= 0) {
                throw new BadRequestHttpException('linkedProductId is required and must be positive');
            }
            $linkData[$linkedId] = ['position' => (int) ($link['position'] ?? 0)];
        }

        $setter = self::LINK_SETTER_MAP[$linkType];
        $product->$setter($linkData);

        try {
            $product->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to save links: ' . $e->getMessage());
        }

        return $this->provider->getLinks($this->loadProduct($productId), $linkType);
    }

    private function handleAddLink(int $productId, string $linkType, array $body): ProductLink
    {
        $product = $this->loadProduct($productId);

        $linkedId = (int) ($body['linkedProductId'] ?? $body['linked_product_id'] ?? 0);
        if ($linkedId <= 0) {
            throw new BadRequestHttpException('linkedProductId is required and must be positive');
        }

        // Get existing links
        $existingLinks = $this->getExistingLinkData($product, $linkType);
        $position = (int) ($body['position'] ?? 0);
        $existingLinks[$linkedId] = ['position' => $position];

        $setter = self::LINK_SETTER_MAP[$linkType];
        $product->$setter($existingLinks);

        try {
            $product->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to add link: ' . $e->getMessage());
        }

        // Return the single added link
        $dto = new ProductLink();
        $dto->id = $productId . '_' . $linkType . '_' . $linkedId;
        $dto->linkedProductId = $linkedId;
        $dto->position = $position;

        // Try to get name/sku
        $linked = Mage::getModel('catalog/product')->load($linkedId);
        if ($linked->getId()) {
            $dto->linkedProductSku = (string) $linked->getSku();
            $dto->linkedProductName = (string) $linked->getName();
        }

        return $dto;
    }

    private function handleRemoveLink(int $productId, string $linkType, int $linkedProductId): null
    {
        $product = $this->loadProduct($productId);

        $existingLinks = $this->getExistingLinkData($product, $linkType);
        unset($existingLinks[$linkedProductId]);

        $setter = self::LINK_SETTER_MAP[$linkType];
        $product->$setter($existingLinks);

        try {
            $product->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to remove link: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * @return array<int, array{position: int}>
     */
    private function getExistingLinkData(Mage_Catalog_Model_Product $product, string $linkType): array
    {
        $method = self::LINK_COLLECTION_MAP[$linkType];
        $collection = $product->$method();
        $links = [];
        foreach ($collection as $linked) {
            $links[(int) $linked->getId()] = ['position' => (int) $linked->getPosition()];
        }
        return $links;
    }

    private function loadProduct(int $id): Mage_Catalog_Model_Product
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
