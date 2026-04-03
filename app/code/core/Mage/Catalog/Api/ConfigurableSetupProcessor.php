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
use Mage_Catalog_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 */
final class ConfigurableSetupProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly ConfigurableSetupProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ConfigurableSetup|null
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

    private function handleSetup(int $productId, array $body): ConfigurableSetup
    {
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);
        /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
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

        // Assign children via model save
        $childProductIds = $body['childProductIds'] ?? $body['child_product_ids'] ?? [];
        if (!empty($childProductIds)) {
            $childData = [];
            foreach (array_map('intval', $childProductIds) as $childId) {
                $childData[$childId] = [];
            }
            $product->setConfigurableProductsData($childData);
            $this->safeSave($product, 'assign configurable children');
        }

        return $this->provider->getSetup($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));
    }

    private function handleAddChild(int $productId, array $body): ConfigurableSetup
    {
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);

        $childId = (int) ($body['childProductId'] ?? $body['child_product_id'] ?? $body['childId'] ?? 0);
        if ($childId <= 0) {
            throw new BadRequestHttpException('childProductId is required and must be positive');
        }

        // Build data array with existing children + new one
        $existingChildren = $this->getExistingChildIds($product);
        $existingChildren[$childId] = [];
        $product->setConfigurableProductsData($existingChildren);
        $this->safeSave($product, 'add configurable child');

        return $this->provider->getSetup($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE));
    }

    private function handleRemoveChild(int $productId, int $childId): null
    {
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE);

        $existingChildren = $this->getExistingChildIds($product);
        unset($existingChildren[$childId]);
        $product->setConfigurableProductsData($existingChildren);
        $this->safeSave($product, 'remove configurable child');

        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function getExistingChildIds(Mage_Catalog_Model_Product $product): array
    {
        /** @var \Mage_Catalog_Model_Product_Type_Configurable $typeInstance */
        $typeInstance = $product->getTypeInstance(true);
        $children = $typeInstance->getUsedProducts(null, $product);
        $data = [];
        foreach ($children as $child) {
            $data[(int) $child->getId()] = [];
        }
        return $data;
    }

}
