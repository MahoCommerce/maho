<?php

declare(strict_types=1);

namespace Maho\Catalog\Api\State\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Link;
use Mage_Catalog_Model_Product_Type;
use Maho\Catalog\Api\Resource\GroupedProductLink;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\Catalog\Api\State\Provider\GroupedProductLinkProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<GroupedProductLink, GroupedProductLink[]|null>
 */
final class GroupedProductLinkProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly GroupedProductLinkProvider $provider,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?array
    {
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            $childProductId = self::extractChildProductId($context);
            return $this->handleRemove($productId, $childProductId);
        }

        $this->requirePermission($user, 'products/write');

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

        if ($operation instanceof Post) {
            return $this->handleAdd($productId, $body);
        }

        return $this->handleReplaceAll($productId, $body);
    }

    /**
     * Extract childProductId from request path since API Platform only
     * populates declared Link uriVariables.
     */
    private static function extractChildProductId(array $context): int
    {
        $request = $context['request'] ?? null;
        if (!$request) {
            return 0;
        }

        $path = $request->getPathInfo();
        if (preg_match('#/products/\d+/grouped/(\d+)#', $path, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function handleReplaceAll(int $productId, array $body): array
    {
        $product = $this->loadGroupedProduct($productId);

        $linkData = [];
        foreach ($body as $link) {
            if (!is_array($link)) {
                throw new BadRequestHttpException('Each link must be an object');
            }
            $childId = (int) ($link['childProductId'] ?? $link['child_product_id'] ?? 0);
            if ($childId <= 0) {
                throw new BadRequestHttpException('childProductId is required and must be positive');
            }
            $linkData[$childId] = [
                'qty' => (float) ($link['qty'] ?? 1),
                'position' => (int) ($link['position'] ?? 0),
            ];
        }

        $product->setGroupedLinkData($linkData);

        try {
            $product->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to save grouped links: ' . $e->getMessage());
        }

        return $this->provider->getGroupedLinks($this->loadGroupedProduct($productId));
    }

    private function handleAdd(int $productId, array $body): array
    {
        $product = $this->loadGroupedProduct($productId);

        $childId = (int) ($body['childProductId'] ?? $body['child_product_id'] ?? 0);
        if ($childId <= 0) {
            throw new BadRequestHttpException('childProductId is required and must be positive');
        }

        // Get existing links
        $existing = $this->getExistingLinkData($product);
        $existing[$childId] = [
            'qty' => (float) ($body['qty'] ?? 1),
            'position' => (int) ($body['position'] ?? 0),
        ];

        $product->setGroupedLinkData($existing);

        try {
            $product->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to add grouped link: ' . $e->getMessage());
        }

        return $this->provider->getGroupedLinks($this->loadGroupedProduct($productId));
    }

    private function handleRemove(int $productId, int $childProductId): null
    {
        $this->loadGroupedProduct($productId);

        // Use direct SQL to remove the link — setGroupedLinkData + save
        // doesn't reliably delete entries
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/product_link');

        // Grouped link type ID = Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED (3)
        $write->delete($table, [
            'product_id = ?' => $productId,
            'linked_product_id = ?' => $childProductId,
            'link_type_id = ?' => Mage_Catalog_Model_Product_Link::LINK_TYPE_GROUPED,
        ]);

        return null;
    }

    /**
     * @return array<int, array{qty: float, position: int}>
     */
    private function getExistingLinkData(Mage_Catalog_Model_Product $product): array
    {
        $associated = $product->getTypeInstance(true)->getAssociatedProducts($product);
        $links = [];
        foreach ($associated as $child) {
            $links[(int) $child->getId()] = [
                'qty' => (float) ($child->getQty() ?: 1),
                'position' => (int) $child->getPosition(),
            ];
        }
        return $links;
    }

    private function loadGroupedProduct(int $id): Mage_Catalog_Model_Product
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

        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type::TYPE_GROUPED) {
            throw new BadRequestHttpException('Product is not a grouped product');
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
