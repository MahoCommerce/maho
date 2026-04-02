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
use Mage_Catalog_Model_Product_Link;
use Mage_Catalog_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

/**
 */
final class GroupedProductLinkProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly GroupedProductLinkProvider $provider,
    ) {
        parent::__construct($security);
    }

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
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_GROUPED);

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

        $this->safeSave($product, 'save grouped links');

        return $this->provider->getGroupedLinks($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_GROUPED));
    }

    private function handleAdd(int $productId, array $body): array
    {
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_GROUPED);

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

        $this->safeSave($product, 'add grouped link');

        return $this->provider->getGroupedLinks($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_GROUPED));
    }

    private function handleRemove(int $productId, int $childProductId): null
    {
        $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_GROUPED);

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
        /** @var \Mage_Catalog_Model_Product_Type_Grouped $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $associated = $typeInstance->getAssociatedProducts($product);
        $links = [];
        foreach ($associated as $child) {
            $links[(int) $child->getId()] = [
                'qty' => (float) ($child->getQty() ?: 1),
                'position' => (int) $child->getPosition(),
            ];
        }
        return $links;
    }

}
