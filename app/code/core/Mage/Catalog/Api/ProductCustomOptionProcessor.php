<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Catalog
 */

declare(strict_types=1);

namespace Mage\Catalog\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use Mage;
use Mage_Catalog_Model_Product_Option;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 */
final class ProductCustomOptionProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    private const SELECT_TYPES = ['drop_down', 'radio', 'checkbox', 'multiple'];
    private const VALID_TYPES = [
        'field', 'area', 'drop_down', 'radio', 'checkbox', 'multiple',
        'file', 'date', 'date_time', 'time',
    ];

    public function __construct(
        Security $security,
        private readonly ProductCustomOptionProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductCustomOption
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);

        if ($operation instanceof DeleteOperationInterface) {
            return $this->handleDelete($productId, (int) ($uriVariables['id'] ?? 0));
        }

        $request = $context['request'] ?? null;
        $body = $this->parseRequestBody($request);

        if ($operation instanceof Post) {
            return $this->handleCreate($productId, $body);
        }

        return $this->handleUpdate($productId, (int) ($uriVariables['id'] ?? 0), $body);
    }

    private function handleCreate(int $productId, array $body): ProductCustomOption
    {
        $input = RequestInput::of($body);
        $product = $this->loadProduct($productId);
        [$title, $type] = $this->validateOptionData($body);

        /** @var Mage_Catalog_Model_Product_Option $option */
        $option = Mage::getModel('catalog/product_option');
        $option->setProduct($product);
        $option->setProductId((string) $productId);
        $option->setStoreId(0);
        $option->setTitle($title);
        $option->setType($type);
        $option->setIsRequire($input->bool('required') ?? false);
        $option->setSortOrder($input->int('sortOrder', 'sort_order') ?? 0);

        if (in_array($type, self::SELECT_TYPES)) {
            $values = $input->list('values') ?? [];
            if (empty($values)) {
                throw new UnprocessableEntityHttpException('Select-type options require at least one value');
            }
            $cleanValues = $this->prepareValues($values);
            $option->setData('values', $cleanValues);
        } else {
            $price = $input->float('price');
            if ($price !== null) {
                if ($price < 0) {
                    throw new UnprocessableEntityHttpException('Price must not be negative');
                }
                $option->setPrice($price);
            }
            $option->setPriceType($input->string('priceType', 'price_type') ?? 'fixed');
            $sku = $input->string('sku');
            if ($sku !== null) {
                $option->setSku($sku);
            }
            $maxChars = $input->int('maxCharacters', 'max_characters');
            if ($maxChars !== null) {
                $option->setMaxCharacters($maxChars);
            }
            $fileExt = $input->string('fileExtensions', 'file_extensions');
            if ($fileExt !== null) {
                $option->setFileExtension($fileExt);
            }
            $imageSizeX = $input->int('imageSizeX', 'image_size_x');
            if ($imageSizeX !== null) {
                $option->setImageSizeX($imageSizeX);
            }
            $imageSizeY = $input->int('imageSizeY', 'image_size_y');
            if ($imageSizeY !== null) {
                $option->setImageSizeY($imageSizeY);
            }
        }

        $this->safeSave($option, 'create option');

        $this->syncProductOptionFlags($productId);

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
        $input = RequestInput::of($body);
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
        $newType = $input->string('type');
        if ($newType !== null) {
            if (!in_array($newType, self::VALID_TYPES)) {
                throw new UnprocessableEntityHttpException('Invalid option type: ' . $newType);
            }
            $optionUpdate['type'] = $newType;
        }
        $required = $input->bool('required');
        if ($required !== null) {
            $optionUpdate['is_require'] = $required ? 1 : 0;
        }
        $sortOrder = $input->int('sortOrder', 'sort_order');
        if ($sortOrder !== null) {
            $optionUpdate['sort_order'] = $sortOrder;
        }
        $sku = $input->string('sku');
        if ($sku !== null) {
            $optionUpdate['sku'] = $sku;
        }
        $maxChars = $input->int('maxCharacters', 'max_characters');
        if ($maxChars !== null) {
            $optionUpdate['max_characters'] = $maxChars;
        }
        $fileExt = $input->string('fileExtensions', 'file_extensions');
        if ($fileExt !== null) {
            $optionUpdate['file_extension'] = $fileExt;
        }
        $imageSizeX = $input->int('imageSizeX', 'image_size_x');
        if ($imageSizeX !== null) {
            $optionUpdate['image_size_x'] = $imageSizeX;
        }
        $imageSizeY = $input->int('imageSizeY', 'image_size_y');
        if ($imageSizeY !== null) {
            $optionUpdate['image_size_y'] = $imageSizeY;
        }
        $title = $input->string('title');
        $price = $input->float('price');
        $priceType = $input->string('priceType', 'price_type');
        $values = $input->list('values');

        // Check all of the input before the first write
        $oldType = $option->getType();
        $type = $newType ?? $oldType;
        $isSelect = in_array($type, self::SELECT_TYPES);
        if (!$isSelect && $price !== null && $price < 0) {
            throw new UnprocessableEntityHttpException('Price must not be negative');
        }
        $cleanValues = $isSelect && $values !== null ? $this->prepareValues($values) : null;

        // One transaction, so an update that fails part way keeps the option as it was
        $write->beginTransaction();
        try {
            if (!empty($optionUpdate)) {
                $write->update(
                    $resource->getTableName('catalog/product_option'),
                    $optionUpdate,
                    ['option_id = ?' => $optionId],
                );
            }

            // Update title via catalog_product_option_title table
            if ($title !== null) {
                $titleTable = $resource->getTableName('catalog/product_option_title');
                $write->insertOnDuplicate($titleTable, [
                    'option_id' => $optionId,
                    'store_id' => 0,
                    'title' => $title,
                ], ['title']);
            }

            // Clean up orphan rows when the type category changes (select <-> non-select)
            $oldIsSelect = in_array($oldType, self::SELECT_TYPES);
            if ($oldIsSelect && !$isSelect) {
                // Select -> non-select: drop now-orphaned option value rows
                $write->delete(
                    $resource->getTableName('catalog/product_option_type_value'),
                    ['option_id = ?' => $optionId],
                );
            } elseif (!$oldIsSelect && $isSelect) {
                // Non-select -> select: drop the now-orphaned price row
                $write->delete(
                    $resource->getTableName('catalog/product_option_price'),
                    ['option_id = ?' => $optionId],
                );
            }

            // Update price for non-select types
            if (!$isSelect) {
                $priceUpdate = [];
                if ($price !== null) {
                    $priceUpdate['price'] = $price;
                }
                if ($priceType !== null) {
                    $priceUpdate['price_type'] = $priceType;
                }
                if (!empty($priceUpdate)) {
                    $priceTable = $resource->getTableName('catalog/product_option_price');
                    // Only the value columns may be updated on conflict; the
                    // identity columns (option_id, store_id) form the unique key
                    // and must not appear in the ON DUPLICATE/CONFLICT update list.
                    $updateFields = array_keys($priceUpdate);
                    $priceUpdate['option_id'] = $optionId;
                    $priceUpdate['store_id'] = 0;
                    $write->insertOnDuplicate($priceTable, $priceUpdate, $updateFields);
                }
            }

            // Update select-type values
            if ($cleanValues !== null) {
                // Delete existing values and re-create
                $typeValueTable = $resource->getTableName('catalog/product_option_type_value');
                $write->delete($typeValueTable, ['option_id = ?' => $optionId]);
                // Re-create via model (reload option, set values, save)
                $option = Mage::getModel('catalog/product_option')->load($optionId);
                $product = Mage::getModel('catalog/product')->load($productId);
                $option->setProduct($product);
                $option->setStoreId(0);
                $option->setData('values', $cleanValues);
                $this->safeSave($option, 'update option values');
            }

            $write->commit();
        } catch (\Throwable $e) {
            $write->rollBack();
            throw $e;
        }

        $this->syncProductOptionFlags($productId);

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

        $this->safeDelete($option, 'delete option');

        $this->syncProductOptionFlags($productId);

        return null;
    }

    /**
     * @return array{0: string, 1: string} the title and the type
     */
    private function validateOptionData(array $body): array
    {
        $input = RequestInput::of($body);
        $title = $input->string('title');
        if ($title === null || $title === '') {
            throw new UnprocessableEntityHttpException('Title is required');
        }
        $type = $input->string('type');
        if ($type === null || $type === '') {
            throw new UnprocessableEntityHttpException('Type is required');
        }
        if (!in_array($type, self::VALID_TYPES)) {
            throw new UnprocessableEntityHttpException('Invalid option type: ' . $type);
        }
        return [$title, $type];
    }

    /**
     * Prepare option values for save, stripping option_type_id to prevent FK constraint errors.
     */
    private function prepareValues(array $values): array
    {
        $cleanValues = [];
        foreach (RequestInput::rowsOf($values, 'values') as $i => $input) {
            $title = $input->string('title');
            if ($title === null || $title === '') {
                throw new UnprocessableEntityHttpException("Value at index {$i} must have a title");
            }
            $cleanValues[] = [
                'title' => $title,
                'price' => $input->float('price') ?? 0.0,
                'price_type' => $input->string('priceType', 'price_type') ?? 'fixed',
                'sku' => $input->string('sku') ?? '',
                'sort_order' => $input->int('sortOrder', 'sort_order') ?? $i,
            ];
        }
        return $cleanValues;
    }

    /**
     * Update the product's has_options and required_options flags based on current options.
     * Must be called after any option create/update/delete.
     */
    private function syncProductOptionFlags(int $productId): void
    {
        $product = \Mage::getModel('catalog/product')
            ->setStoreId(\Mage_Core_Model_App::ADMIN_STORE_ID)
            ->load($productId);
        if ($product->getId()) {
            $product->save();
        }
    }
}
