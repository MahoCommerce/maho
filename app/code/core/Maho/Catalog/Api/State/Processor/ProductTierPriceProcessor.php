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
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Maho\Catalog\Api\Resource\ProductTierPrice;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\Catalog\Api\State\Provider\ProductTierPriceProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<ProductTierPrice|ProductTierPrice[], ProductTierPrice[]|null>
 */
final class ProductTierPriceProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ProductTierPriceProvider $provider,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?array
    {
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            return $this->handleDeleteAll($productId);
        }

        $this->requirePermission($user, 'products/write');
        return $this->handleReplace($productId, $context);
    }

    private function handleReplace(int $productId, array $context): array
    {
        $product = $this->loadProduct($productId);

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

        if (!is_array($body)) {
            throw new BadRequestHttpException('Request body must be an array of tier prices');
        }

        $tierPrices = [];
        foreach ($body as $tp) {
            if (!is_array($tp)) {
                throw new BadRequestHttpException('Each tier price must be an object');
            }

            $groupId = $tp['customerGroupId'] ?? $tp['customer_group_id'] ?? 'all';
            if ($groupId === 'all') {
                $groupId = \Mage_Customer_Model_Group::CUST_GROUP_ALL;
            }

            $price = (float) ($tp['price'] ?? 0);
            if ($price < 0) {
                throw new BadRequestHttpException('Price must not be negative');
            }

            $qty = (float) ($tp['qty'] ?? 1);
            if ($qty <= 0) {
                throw new BadRequestHttpException('Quantity must be greater than 0');
            }

            $tierPrices[] = [
                'website_id' => (int) ($tp['websiteId'] ?? $tp['website_id'] ?? 0),
                'cust_group' => (int) $groupId,
                'price_qty' => $qty,
                'price' => $price,
            ];
        }

        // Delete all existing tier prices first for clean replacement
        $this->deleteAllTierPrices($productId);

        // Force-load existing state so backend model knows to insert (not diff)
        $product->getTierPrice();
        $product->setTierPrice($tierPrices);

        try {
            $product->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to save tier prices: ' . $e->getMessage());
        }

        // Re-read and return
        return $this->provider->provide(
            new \ApiPlatform\Metadata\GetCollection(),
            ['productId' => $productId],
            [],
        );
    }

    private function handleDeleteAll(int $productId): null
    {
        // Verify product exists
        $this->loadProduct($productId);

        $this->deleteAllTierPrices($productId);

        return null;
    }

    private function deleteAllTierPrices(int $productId): void
    {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/product_attribute_tier_price');

        $write->delete($table, ['entity_id = ?' => $productId]);
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
