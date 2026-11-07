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
use Mage_Catalog_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Maho\ApiPlatform\Http\RequestInput;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 */
final class BundleOptionProcessor extends \Maho\ApiPlatform\Processor
{
    use ProductLoaderTrait;

    public function __construct(
        Security $security,
        private readonly BundleOptionProvider $provider,
    ) {
        parent::__construct($security);
    }

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BundleOption|array|null
    {
        $user = $this->requireUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);

        $request = $context['request'] ?? null;
        $body = $this->parseRequestBody($request);

        // The PUT/DELETE routes carry the option id in {id}, but the operation's
        // uriVariables only declare the productId Link, so API Platform doesn't
        // reliably surface {id}. Recover it from the request path as a fallback.
        $pathOptionId = 0;
        if ($request instanceof \Symfony\Component\HttpFoundation\Request
            && preg_match('#/bundle-options/(\d+)#', $request->getPathInfo(), $m)) {
            $pathOptionId = (int) $m[1];
        }

        if ($operation instanceof DeleteOperationInterface) {
            $optionId = (int) ($uriVariables['id'] ?? RequestInput::of($body)->int('optionId', 'option_id') ?? 0) ?: $pathOptionId;
            return $this->handleDelete($productId, $optionId);
        }

        if ($operation instanceof Post) {
            return $this->handleCreate($productId, $body);
        }

        return $this->handleUpdate($productId, $body, $pathOptionId);
    }

    private function handleCreate(int $productId, array $body): BundleOption
    {
        $input = RequestInput::of($body);
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);

        $title = $input->string('title') ?? '';
        $type = $input->string('type') ?? 'select';
        $required = $input->bool('required') ?? true;
        $position = $input->int('position') ?? 0;
        // Read every selection before saving anything, so a bad one keeps no option
        $selectionRows = [];
        foreach ($input->rows('selections') ?? [] as $selection) {
            $selProductId = $selection->int('productId', 'product_id') ?? 0;
            if ($selProductId <= 0) {
                continue;
            }
            $this->authorizeAssociatedProductWebsites($selProductId);
            $qty = $selection->float('qty') ?? 1.0;
            if ($qty <= 0) {
                throw new UnprocessableEntityHttpException('Quantity must be greater than 0');
            }
            $price = $selection->float('price') ?? 0.0;
            if ($price < 0) {
                throw new UnprocessableEntityHttpException('Price must not be negative');
            }
            $selectionRows[] = [
                'product_id' => $selProductId,
                'qty' => $qty,
                'price' => $price,
                'can_change_qty' => $selection->bool('canChangeQty', 'can_change_qty') ?? true,
                'is_default' => $selection->bool('isDefault', 'is_default') ?? false,
                'price_type' => ($selection->string('priceType', 'price_type') ?? 'fixed') === 'percent' ? 1 : 0,
                'position' => $selection->int('position') ?? 0,
            ];
        }

        if ($title === '') {
            throw new UnprocessableEntityHttpException('title is required');
        }

        $validTypes = ['select', 'radio', 'checkbox', 'multi'];
        if (!in_array($type, $validTypes, true)) {
            throw new UnprocessableEntityHttpException("Invalid type: {$type}. Valid: " . implode(', ', $validTypes));
        }

        /** @var \Mage_Bundle_Model_Option $option */
        $option = Mage::getModel('bundle/option');
        $option->setStoreId(0);
        $option->setParentId($productId);
        $option->setTitle($title);
        $option->setDefaultTitle($title);
        $option->setType($type);
        $option->setRequired((bool) $required);
        $option->setPosition($position);

        $this->safeSave($option, 'create bundle option');

        // Register product in Mage registry, Selection::_afterSave() requires it.
        // Force the correct product (a stale entry from a prior operation in the
        // same process would otherwise leak the wrong store scope into the
        // selection price save), then restore the previous registry state.
        $previousProduct = Mage::registry('product');
        if ($previousProduct !== null) {
            Mage::unregister('product');
        }
        Mage::register('product', $product);

        try {
            // Add selections
            foreach ($selectionRows as $row) {
                /** @var \Mage_Bundle_Model_Selection $selection */
                $selection = Mage::getModel('bundle/selection');
                $selection->setOptionId($option->getId());
                $selection->setParentProductId($productId);
                $selection->setProductId($row['product_id']);
                $selection->setSelectionQty($row['qty']);
                $selection->setSelectionCanChangeQty($row['can_change_qty']);
                $selection->setIsDefault($row['is_default']);
                $selection->setSelectionPriceType($row['price_type']);
                $selection->setSelectionPriceValue($row['price']);
                $selection->setPosition($row['position']);

                $this->safeSave($selection, 'create selection');
            }
        } finally {
            Mage::unregister('product');
            if ($previousProduct !== null) {
                Mage::register('product', $previousProduct);
            }
        }

        // Return the created option
        $dto = new BundleOption();
        $dto->id = (int) $option->getId();
        $dto->title = $title;
        $dto->type = $type;
        $dto->required = $required;
        $dto->position = $position;
        $dto->selections = $body['selections'] ?? [];

        return $dto;
    }

    private function handleUpdate(int $productId, array $body, int $pathOptionId = 0): array
    {
        $input = RequestInput::of($body);
        $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);

        $optionId = ($input->int('optionId', 'option_id', 'id') ?? 0) ?: $pathOptionId;
        if ($optionId <= 0) {
            throw new UnprocessableEntityHttpException('optionId is required');
        }

        /** @var \Mage_Bundle_Model_Option $option */
        $option = Mage::getModel('bundle/option')->load($optionId);
        if (!$option->getId() || (int) $option->getParentId() !== $productId) {
            throw new NotFoundHttpException('Bundle option not found');
        }

        $title = $input->string('title');
        if ($title !== null) {
            $option->setTitle($title);
            $option->setDefaultTitle($title);
        }
        $type = $input->string('type');
        if ($type !== null) {
            $validTypes = ['select', 'radio', 'checkbox', 'multi'];
            if (!in_array($type, $validTypes, true)) {
                throw new UnprocessableEntityHttpException("Invalid type: {$type}. Valid: " . implode(', ', $validTypes));
            }
            $option->setType($type);
        }
        $required = $input->bool('required');
        if ($required !== null) {
            $option->setRequired($required);
        }
        $position = $input->int('position');
        if ($position !== null) {
            $option->setPosition($position);
        }

        $this->safeSave($option, 'update option');

        return $this->provider->getBundleOptions($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE));
    }

    private function handleDelete(int $productId, int $optionId): null
    {
        $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);

        if ($optionId <= 0) {
            throw new UnprocessableEntityHttpException('optionId is required');
        }

        /** @var \Mage_Bundle_Model_Option $option */
        $option = Mage::getModel('bundle/option')->load($optionId);
        if (!$option->getId() || (int) $option->getParentId() !== $productId) {
            throw new NotFoundHttpException('Bundle option not found');
        }

        $this->safeDelete($option, 'delete option');

        return null;
    }

    /**
     * Guard against pulling a foreign-website product into a bundle selection: a
     * store-restricted token may only add selections within its own website
     * scope. No-op for unrestricted users (skips the selection-product load).
     */
    private function authorizeAssociatedProductWebsites(int $selProductId): void
    {
        $user = $this->requireUser();
        if ($this->getAllowedWebsiteIds($user) === null) {
            return;
        }
        $this->assertProductWebsitesAllowed($this->loadProduct($selProductId), $user);
    }

}
