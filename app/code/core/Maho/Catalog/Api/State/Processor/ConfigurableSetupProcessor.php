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
use Mage_Catalog_Model_Product_Type;
use Maho\Catalog\Api\Resource\ConfigurableSetup;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\Catalog\Api\State\Provider\ConfigurableSetupProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<ConfigurableSetup, ConfigurableSetup[]|null>
 */
final class ConfigurableSetupProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly ConfigurableSetupProvider $provider,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): array|null
    {
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            $childId = self::extractChildId($context);
            return $this->handleRemoveChild($productId, $childId);
        }

        $this->requirePermission($user, 'products/write');

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

        if ($operation instanceof Post) {
            return $this->handleAddChild($productId, $body);
        }

        return $this->handleSetup($productId, $body);
    }

    /**
     * Extract childId from request path since API Platform only
     * populates declared Link uriVariables.
     */
    private static function extractChildId(array $context): int
    {
        $request = $context['request'] ?? null;
        if (!$request) {
            return 0;
        }

        $path = $request->getPathInfo();
        if (preg_match('#/configurable/children/(\d+)#', $path, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function handleSetup(int $productId, array $body): array
    {
        $product = $this->loadConfigurableProduct($productId);
        $typeInstance = $product->getTypeInstance(true);

        // Set super attributes if provided and none exist yet
        $superAttributes = $body['superAttributes'] ?? $body['super_attributes'] ?? [];
        if (!empty($superAttributes)) {
            $existingAttrIds = $typeInstance->getConfigurableAttributeCollection($product)->getAllIds();

            if (empty($existingAttrIds)) {
                $superAttributeData = [];
                $position = 0;
                foreach ($superAttributes as $code) {
                    $attribute = Mage::getSingleton('eav/config')
                        ->getAttribute('catalog_product', $code);
                    if (!$attribute || !$attribute->getId()) {
                        throw new BadRequestHttpException("Attribute not found: {$code}");
                    }
                    $superAttributeData[] = [
                        'attribute_id' => $attribute->getId(),
                        'position' => $position++,
                    ];
                }
                $product->setConfigurableAttributesData($superAttributeData);
                $product->setCanSaveConfigurableAttributes(true);

                try {
                    $product->save();
                } catch (\Throwable $e) {
                    throw new UnprocessableEntityHttpException('Failed to set super attributes: ' . $e->getMessage());
                }
            }
        }

        // Assign children via direct SQL (DataSync pattern)
        $childProductIds = $body['childProductIds'] ?? $body['child_product_ids'] ?? [];
        if (!empty($childProductIds)) {
            $this->replaceChildLinks($productId, array_map('intval', $childProductIds));
        }

        return [$this->provider->getSetup($this->loadConfigurableProduct($productId))];
    }

    private function handleAddChild(int $productId, array $body): array
    {
        $this->loadConfigurableProduct($productId);

        $childId = (int) ($body['childProductId'] ?? $body['child_product_id'] ?? $body['childId'] ?? 0);
        if ($childId <= 0) {
            throw new BadRequestHttpException('childProductId is required and must be positive');
        }

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/product_super_link');

        $write->insertOnDuplicate($table, [
            'product_id' => $childId,
            'parent_id' => $productId,
        ], ['product_id']);

        return [$this->provider->getSetup($this->loadConfigurableProduct($productId))];
    }

    private function handleRemoveChild(int $productId, int $childId): null
    {
        $this->loadConfigurableProduct($productId);

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/product_super_link');

        $write->delete($table, [
            'product_id = ?' => $childId,
            'parent_id = ?' => $productId,
        ]);

        return null;
    }

    private function replaceChildLinks(int $parentId, array $childIds): void
    {
        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');
        $table = $resource->getTableName('catalog/product_super_link');

        // Delete existing
        $write->delete($table, ['parent_id = ?' => $parentId]);

        // Insert new
        foreach ($childIds as $childId) {
            $write->insertOnDuplicate($table, [
                'product_id' => $childId,
                'parent_id' => $parentId,
            ], ['product_id']);
        }
    }

    private function loadConfigurableProduct(int $id): Mage_Catalog_Model_Product
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

        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
            throw new BadRequestHttpException('Product is not a configurable product');
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
