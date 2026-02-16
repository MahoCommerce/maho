<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @category   Maho
 * @package    Maho_ApiPlatform
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

namespace Maho\ApiPlatform\State\Processor;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use Mage;
use Mage_Catalog_Model_Product;
use Mage_Catalog_Model_Product_Status;
use Mage_Catalog_Model_Product_Type;
use Mage_Catalog_Model_Product_Visibility;
use Mage_CatalogInventory_Model_Stock_Item;
use Maho\ApiPlatform\ApiResource\Product;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Product State Processor
 *
 * Handles create, update, and delete operations for products.
 * Requires JWT authentication with products/write or products/delete permission.
 *
 * @implements ProcessorInterface<Product, Product|null>
 */
final class ProductProcessor implements ProcessorInterface
{
    private const VISIBILITY_MAP = [
        'not_visible' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_NOT_VISIBLE,
        'catalog' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_CATALOG,
        'search' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_IN_SEARCH,
        'catalog_search' => Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
    ];

    public function __construct(
        private readonly Security $security,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?Product
    {
        $user = $this->getAuthorizedUser();

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            return $this->handleDelete((int) $uriVariables['id'], $user);
        }

        $this->requirePermission($user, 'products/write');

        assert($data instanceof Product);

        if (isset($uriVariables['id'])) {
            return $this->handleUpdate((int) $uriVariables['id'], $data, $user);
        }

        return $this->handleCreate($data, $user);
    }

    private function handleCreate(Product $data, ApiUser $user): Product
    {
        StoreContext::ensureStore();

        if (empty($data->sku)) {
            throw new BadRequestHttpException('SKU is required');
        }

        if (empty($data->name)) {
            throw new BadRequestHttpException('Name is required');
        }

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');

        $product->setData([
            'sku' => $data->sku,
            'name' => $data->name,
            'type_id' => $data->type ?: Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
            'attribute_set_id' => $this->resolveAttributeSetId($data),
            'status' => $data->isActive
                ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                : Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
            'visibility' => self::VISIBILITY_MAP[$data->visibility] ?? Mage_Catalog_Model_Product_Visibility::VISIBILITY_BOTH,
            'tax_class_id' => 0,
        ]);

        $this->applyProductData($product, $data);

        $websiteIds = $data->websiteIds ?? $this->getDefaultWebsiteIds();
        $product->setWebsiteIds($websiteIds);

        try {
            $product->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to create product: ' . $e->getMessage());
        }

        if (!empty($data->categoryIds)) {
            $this->assignCategories($product, $data->categoryIds);
        }

        $this->updateStockData($product, $data);
        $this->invalidateCache((int) $product->getId());
        $this->logActivity('create', null, $product, $user);

        return $this->refreshDto($product, $data);
    }

    private function handleUpdate(int $id, Product $data, ApiUser $user): Product
    {
        StoreContext::ensureStore();

        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product');

        $storeId = StoreContext::getStoreId();
        if ($storeId) {
            $product->setStoreId($storeId);
        }

        $product->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        $oldData = $product->getData();

        if ($data->name !== '') {
            $product->setName($data->name);
        }
        if ($data->sku !== '') {
            $product->setSku($data->sku);
        }

        $product->setStatus(
            $data->isActive
                ? Mage_Catalog_Model_Product_Status::STATUS_ENABLED
                : Mage_Catalog_Model_Product_Status::STATUS_DISABLED,
        );

        if ($data->visibility !== 'catalog_search' || isset($oldData['visibility'])) {
            $product->setVisibility(
                self::VISIBILITY_MAP[$data->visibility] ?? (int) $oldData['visibility'],
            );
        }

        $this->applyProductData($product, $data);

        if ($data->websiteIds !== null) {
            $product->setWebsiteIds($data->websiteIds);
        }

        try {
            $product->save();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to update product: ' . $e->getMessage());
        }

        if ($data->categoryIds !== []) {
            $this->assignCategories($product, $data->categoryIds);
        }

        $this->updateStockData($product, $data);
        $this->invalidateCache((int) $product->getId());
        $this->logActivity('update', $oldData, $product, $user);

        return $this->refreshDto($product, $data);
    }

    private function handleDelete(int $id, ApiUser $user): null
    {
        /** @var Mage_Catalog_Model_Product $product */
        $product = Mage::getModel('catalog/product')->load($id);

        if (!$product->getId()) {
            throw new NotFoundHttpException('Product not found');
        }

        $oldData = $product->getData();

        try {
            $product->delete();
        } catch (\Exception $e) {
            throw new UnprocessableEntityHttpException('Failed to delete product: ' . $e->getMessage());
        }

        $this->invalidateCache($id);
        $this->logActivity('delete', $oldData, null, $user);

        return null;
    }

    private function applyProductData(Mage_Catalog_Model_Product $product, Product $data): void
    {
        if ($data->description !== null) {
            $product->setDescription($data->description);
        }
        if ($data->shortDescription !== null) {
            $product->setShortDescription($data->shortDescription);
        }
        if ($data->price !== null) {
            $product->setPrice($data->price);
        }
        if ($data->specialPrice !== null) {
            $product->setSpecialPrice($data->specialPrice);
        }
        if ($data->weight !== null) {
            $product->setWeight($data->weight);
        }
        if ($data->urlKey !== null) {
            $product->setUrlKey($data->urlKey);
        }
        if ($data->metaTitle !== null) {
            $product->setMetaTitle($data->metaTitle);
        }
        if ($data->metaDescription !== null) {
            $product->setMetaDescription($data->metaDescription);
        }
        if ($data->metaKeywords !== null) {
            $product->setData('meta_keyword', $data->metaKeywords);
        }
        if ($data->barcode !== null) {
            /** @phpstan-ignore-next-line */
            $posHelper = Mage::helper('maho_pos');
            $barcodeAttr = ($posHelper && method_exists($posHelper, 'getBarcodeAttributeCode'))
                ? $posHelper->getBarcodeAttributeCode()
                : 'barcode';
            $product->setData($barcodeAttr, $data->barcode);
        }
        if ($data->pageLayout !== null) {
            $product->setData('page_layout', $data->pageLayout);
        }
    }

    private function assignCategories(Mage_Catalog_Model_Product $product, array $categoryIds): void
    {
        $categoryIds = array_map('intval', $categoryIds);
        $product->setCategoryIds($categoryIds);

        try {
            $product->save();
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }

    private function updateStockData(Mage_Catalog_Model_Product $product, Product $data): void
    {
        $qty = null;
        $isInStock = null;

        if ($data->stockData !== null) {
            $qty = isset($data->stockData['qty']) ? (float) $data->stockData['qty'] : null;
            $isInStock = isset($data->stockData['is_in_stock']) ? (bool) $data->stockData['is_in_stock'] : null;
        }

        if ($qty === null && $data->stockQty !== null) {
            $qty = $data->stockQty;
        }

        if ($qty === null && $isInStock === null) {
            return;
        }

        /** @var Mage_CatalogInventory_Model_Stock_Item $stockItem */
        $stockItem = Mage::getModel('cataloginventory/stock_item')->loadByProduct($product);

        if (!$stockItem->getId()) {
            $stockItem->setProductId($product->getId());
            $stockItem->setStockId(1);
        }

        if ($qty !== null) {
            if ($qty < 0 || $qty > 99999999) {
                throw new BadRequestHttpException('Invalid stock quantity');
            }
            $stockItem->setQty($qty);
            if ($isInStock === null) {
                $isInStock = $qty > 0;
            }
        }

        if ($isInStock !== null) {
            $stockItem->setIsInStock($isInStock ? 1 : 0);
        }

        $stockItem->setManageStock(1);

        try {
            $stockItem->save();
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }

    private function resolveAttributeSetId(Product $data): int
    {
        $entityTypeId = Mage::getModel('eav/entity')->setType('catalog_product')->getTypeId();
        return (int) Mage::getModel('eav/entity_attribute_set')
            ->getCollection()
            ->setEntityTypeFilter($entityTypeId)
            ->setOrder('attribute_set_id', 'ASC')
            ->getFirstItem()
            ->getId();
    }

    /**
     * @return int[]
     */
    private function getDefaultWebsiteIds(): array
    {
        $websiteId = (int) Mage::app()->getStore()->getWebsiteId();
        return $websiteId ? [$websiteId] : [1];
    }

    private function invalidateCache(int $productId): void
    {
        Mage::app()->cleanCache(["API_PRODUCT_{$productId}", 'API_PRODUCTS']);
    }

    private function refreshDto(Mage_Catalog_Model_Product $product, Product $data): Product
    {
        $data->id = (int) $product->getId();
        $data->status = $product->getStatus() == Mage_Catalog_Model_Product_Status::STATUS_ENABLED
            ? 'enabled' : 'disabled';
        $data->createdAt = $product->getCreatedAt();
        $data->updatedAt = $product->getUpdatedAt();
        return $data;
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

    private function logActivity(
        string $action,
        ?array $oldData,
        ?Mage_Catalog_Model_Product $product,
        ApiUser $user,
    ): void {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => 'catalog/product',
                'action' => $action,
                'entity_id' => $product ? (int) $product->getId() : ($oldData['entity_id'] ?? 0),
                'old_data' => $oldData,
                'new_data' => $product?->getData(),
                'api_user_id' => $user->getApiUserId(),
                'username' => 'API: ' . $user->getUserIdentifier(),
            ]);
        } catch (\Exception $e) {
            Mage::logException($e);
        }
    }
}
