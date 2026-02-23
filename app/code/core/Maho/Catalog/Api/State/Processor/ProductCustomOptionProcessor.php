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
use Mage_Catalog_Model_Product_Option;
use Maho\Catalog\Api\Resource\ProductCustomOption;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\Catalog\Api\State\Provider\ProductCustomOptionProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<ProductCustomOption, ProductCustomOption|null>
 */
final class ProductCustomOptionProcessor implements ProcessorInterface
{
    private const SELECT_TYPES = ['drop_down', 'radio', 'checkbox', 'multiple'];
    private const VALID_TYPES = [
        'field', 'area', 'drop_down', 'radio', 'checkbox', 'multiple',
        'file', 'date', 'date_time', 'time',
    ];

    public function __construct(
        private readonly Security $security,
        private readonly ProductCustomOptionProvider $provider,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ProductCustomOption|array|null
    {
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            return $this->handleDelete($productId, (int) ($uriVariables['optionId'] ?? 0));
        }

        $this->requirePermission($user, 'products/write');

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

        if ($operation instanceof Post) {
            return $this->handleCreate($productId, $body);
        }

        return $this->handleUpdate($productId, (int) ($uriVariables['optionId'] ?? 0), $body);
    }

    private function handleCreate(int $productId, array $body): ProductCustomOption
    {
        $product = $this->loadProduct($productId);
        $this->validateOptionData($body);

        /** @var Mage_Catalog_Model_Product_Option $option */
        $option = Mage::getModel('catalog/product_option');
        $option->setProduct($product);
        $option->setProductId($productId);
        $option->setStoreId(0);
        $option->setTitle($body['title']);
        $option->setType($body['type']);
        $option->setIsRequire(!empty($body['required']) ? 1 : 0);
        $option->setSortOrder((int) ($body['sortOrder'] ?? $body['sort_order'] ?? 0));

        if (in_array($body['type'], self::SELECT_TYPES)) {
            $values = $body['values'] ?? [];
            if (empty($values)) {
                throw new BadRequestHttpException('Select-type options require at least one value');
            }
            $cleanValues = $this->prepareValues($values);
            $option->setData('values', $cleanValues);
        } else {
            if (isset($body['price'])) {
                $option->setPrice((float) $body['price']);
            }
            $option->setPriceType($body['priceType'] ?? $body['price_type'] ?? 'fixed');
            if (isset($body['sku'])) {
                $option->setSku($body['sku']);
            }
            $maxChars = $body['maxCharacters'] ?? $body['max_characters'] ?? null;
            if ($maxChars !== null) {
                $option->setMaxCharacters((int) $maxChars);
            }
            $fileExt = $body['fileExtensions'] ?? $body['file_extensions'] ?? null;
            if ($fileExt !== null) {
                $option->setFileExtension($fileExt);
            }
        }

        try {
            $option->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to create option: ' . $e->getMessage());
        }

        // Reload and return
        $option = Mage::getModel('catalog/product_option')->load($option->getId());
        return $this->provider->provide(
            new \ApiPlatform\Metadata\GetCollection(),
            ['productId' => $productId, 'optionId' => (int) $option->getId()],
            [],
        );
    }

    private function handleUpdate(int $productId, int $optionId, array $body): ProductCustomOption
    {
        $this->loadProduct($productId);

        /** @var Mage_Catalog_Model_Product_Option $option */
        $option = Mage::getModel('catalog/product_option')->load($optionId);

        if (!$option->getId() || (int) $option->getProductId() !== $productId) {
            throw new NotFoundHttpException('Option not found');
        }

        $resource = Mage::getSingleton('core/resource');
        $write = $resource->getConnection('core_write');

        // Update main option table fields
        $optionUpdate = [];
        if (isset($body['type'])) {
            if (!in_array($body['type'], self::VALID_TYPES)) {
                throw new BadRequestHttpException('Invalid option type: ' . $body['type']);
            }
            $optionUpdate['type'] = $body['type'];
        }
        if (isset($body['required'])) {
            $optionUpdate['is_require'] = $body['required'] ? 1 : 0;
        }
        $sortOrder = $body['sortOrder'] ?? $body['sort_order'] ?? null;
        if ($sortOrder !== null) {
            $optionUpdate['sort_order'] = (int) $sortOrder;
        }
        if (isset($body['sku'])) {
            $optionUpdate['sku'] = $body['sku'];
        }
        if (isset($body['maxCharacters']) || isset($body['max_characters'])) {
            $optionUpdate['max_characters'] = (int) ($body['maxCharacters'] ?? $body['max_characters']);
        }
        if (isset($body['fileExtensions']) || isset($body['file_extensions'])) {
            $optionUpdate['file_extension'] = $body['fileExtensions'] ?? $body['file_extensions'];
        }

        if (!empty($optionUpdate)) {
            $write->update(
                $resource->getTableName('catalog/product_option'),
                $optionUpdate,
                ['option_id = ?' => $optionId],
            );
        }

        // Update title via catalog_product_option_title table
        if (isset($body['title'])) {
            $titleTable = $resource->getTableName('catalog/product_option_title');
            $write->insertOnDuplicate($titleTable, [
                'option_id' => $optionId,
                'store_id' => 0,
                'title' => $body['title'],
            ], ['title']);
        }

        // Update price for non-select types
        $type = $body['type'] ?? $option->getType();
        if (!in_array($type, self::SELECT_TYPES)) {
            $priceUpdate = [];
            if (isset($body['price'])) {
                $priceUpdate['price'] = (float) $body['price'];
            }
            $priceType = $body['priceType'] ?? $body['price_type'] ?? null;
            if ($priceType !== null) {
                $priceUpdate['price_type'] = $priceType;
            }
            if (!empty($priceUpdate)) {
                $priceTable = $resource->getTableName('catalog/product_option_price');
                $priceUpdate['option_id'] = $optionId;
                $priceUpdate['store_id'] = 0;
                $write->insertOnDuplicate($priceTable, $priceUpdate, array_keys($priceUpdate));
            }
        }

        // Update select-type values
        if (in_array($type, self::SELECT_TYPES) && isset($body['values'])) {
            // Delete existing values and re-create
            $typeValueTable = $resource->getTableName('catalog/product_option_type_value');
            $write->delete($typeValueTable, ['option_id = ?' => $optionId]);
            // Re-create via model (reload option, set values, save)
            $option = Mage::getModel('catalog/product_option')->load($optionId);
            $product = Mage::getModel('catalog/product')->load($productId);
            $option->setProduct($product);
            $option->setStoreId(0);
            $cleanValues = $this->prepareValues($body['values']);
            $option->setData('values', $cleanValues);
            try {
                $option->save();
            } catch (\Throwable $e) {
                throw new UnprocessableEntityHttpException('Failed to update option values: ' . $e->getMessage());
            }
        }

        return $this->provider->provide(
            new \ApiPlatform\Metadata\GetCollection(),
            ['productId' => $productId, 'optionId' => $optionId],
            [],
        );
    }

    private function handleDelete(int $productId, int $optionId): null
    {
        $this->loadProduct($productId);

        /** @var Mage_Catalog_Model_Product_Option $option */
        $option = Mage::getModel('catalog/product_option')->load($optionId);

        if (!$option->getId() || (int) $option->getProductId() !== $productId) {
            throw new NotFoundHttpException('Option not found');
        }

        try {
            $option->delete();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to delete option: ' . $e->getMessage());
        }

        return null;
    }

    private function validateOptionData(array $body): void
    {
        if (empty($body['title'])) {
            throw new BadRequestHttpException('Title is required');
        }
        if (empty($body['type'])) {
            throw new BadRequestHttpException('Type is required');
        }
        if (!in_array($body['type'], self::VALID_TYPES)) {
            throw new BadRequestHttpException('Invalid option type: ' . $body['type']);
        }
    }

    /**
     * Prepare option values for save, stripping option_type_id to prevent FK constraint errors.
     */
    private function prepareValues(array $values): array
    {
        $cleanValues = [];
        foreach ($values as $i => $value) {
            if (!is_array($value) || empty($value['title'])) {
                throw new BadRequestHttpException("Value at index {$i} must have a title");
            }
            $cleanValue = [
                'title' => $value['title'],
                'price' => (float) ($value['price'] ?? 0),
                'price_type' => $value['priceType'] ?? $value['price_type'] ?? 'fixed',
                'sku' => $value['sku'] ?? '',
                'sort_order' => (int) ($value['sortOrder'] ?? $value['sort_order'] ?? $i),
            ];
            // Strip option_type_id to prevent FK constraint errors (DataSync pattern)
            unset($cleanValue['option_type_id']);
            $cleanValues[] = $cleanValue;
        }
        return $cleanValues;
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
