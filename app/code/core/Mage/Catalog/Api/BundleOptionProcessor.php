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
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

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
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        // Enforce website scope for store-restricted API users on every
        // sub-resource write/delete (mirrors ProductProcessor's main CRUD check).
        $this->authorizeProductWebsites($this->loadProduct($productId), $user);

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
            $this->requirePermission($user, 'products/delete');
            $optionId = (int) ($uriVariables['id'] ?? $body['optionId'] ?? $body['option_id'] ?? 0) ?: $pathOptionId;
            return $this->handleDelete($productId, $optionId);
        }

        $this->requirePermission($user, 'products/write');

        if ($operation instanceof Post) {
            return $this->handleCreate($productId, $body);
        }

        return $this->handleUpdate($productId, $body, $pathOptionId);
    }

    private function handleCreate(int $productId, array $body): BundleOption
    {
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);

        $title = (string) ($body['title'] ?? '');
        $type = (string) ($body['type'] ?? 'select');
        $required = (bool) ($body['required'] ?? true);
        $position = (int) ($body['position'] ?? 0);
        $selections = $body['selections'] ?? [];

        if ($title === '') {
            throw new BadRequestHttpException('title is required');
        }

        $validTypes = ['select', 'radio', 'checkbox', 'multi'];
        if (!in_array($type, $validTypes, true)) {
            throw new BadRequestHttpException("Invalid type: {$type}. Valid: " . implode(', ', $validTypes));
        }

        /** @var \Mage_Bundle_Model_Option $option */
        $option = Mage::getModel('bundle/option');
        $option->setStoreId(0);
        $option->setParentId($productId);
        $option->setTitle($title);
        $option->setDefaultTitle($title);
        $option->setType($type);
        $option->setRequired($required ? 1 : 0);
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
            foreach ($selections as $sel) {
                $selProductId = (int) ($sel['productId'] ?? $sel['product_id'] ?? 0);
                if ($selProductId <= 0) {
                    continue;
                }

                $this->authorizeAssociatedProductWebsites($selProductId);

                $qty = (float) ($sel['qty'] ?? 1);
                if ($qty <= 0) {
                    throw new BadRequestHttpException('Quantity must be greater than 0');
                }
                $price = (float) ($sel['price'] ?? 0);
                if ($price < 0) {
                    throw new BadRequestHttpException('Price must not be negative');
                }

                /** @var \Mage_Bundle_Model_Selection $selection */
                $selection = Mage::getModel('bundle/selection');
                $selection->setOptionId($option->getId());
                $selection->setProductId($selProductId);
                $selection->setSelectionQty($qty);
                $selection->setSelectionCanChangeQty((int) ($sel['canChangeQty'] ?? $sel['can_change_qty'] ?? 1));
                $selection->setIsDefault((int) ($sel['isDefault'] ?? $sel['is_default'] ?? 0));
                $selection->setSelectionPriceType(($sel['priceType'] ?? $sel['price_type'] ?? 'fixed') === 'percent' ? 1 : 0);
                $selection->setSelectionPriceValue($price);
                $selection->setPosition((int) ($sel['position'] ?? 0));

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
        $dto->selections = $selections;

        return $dto;
    }

    private function handleUpdate(int $productId, array $body, int $pathOptionId = 0): array
    {
        $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);

        $optionId = (int) ($body['optionId'] ?? $body['option_id'] ?? $body['id'] ?? 0) ?: $pathOptionId;
        if ($optionId <= 0) {
            throw new BadRequestHttpException('optionId is required');
        }

        /** @var \Mage_Bundle_Model_Option $option */
        $option = Mage::getModel('bundle/option')->load($optionId);
        if (!$option->getId() || (int) $option->getParentId() !== $productId) {
            throw new NotFoundHttpException('Bundle option not found');
        }

        if (isset($body['title'])) {
            $option->setTitle($body['title']);
            $option->setDefaultTitle($body['title']);
        }
        if (isset($body['type'])) {
            $validTypes = ['select', 'radio', 'checkbox', 'multi'];
            if (!in_array($body['type'], $validTypes, true)) {
                throw new BadRequestHttpException("Invalid type: {$body['type']}. Valid: " . implode(', ', $validTypes));
            }
            $option->setType($body['type']);
        }
        if (isset($body['required'])) {
            $option->setRequired($body['required'] ? 1 : 0);
        }
        if (isset($body['position'])) {
            $option->setPosition((int) $body['position']);
        }

        $this->safeSave($option, 'update option');

        return $this->provider->getBundleOptions($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE));
    }

    private function handleDelete(int $productId, int $optionId): null
    {
        $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);

        if ($optionId <= 0) {
            throw new BadRequestHttpException('optionId is required');
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
        $user = $this->getAuthorizedUser();
        if ($this->getAllowedWebsiteIds($user) === null) {
            return;
        }
        $this->authorizeProductWebsites($this->loadProduct($selProductId), $user);
    }

}
