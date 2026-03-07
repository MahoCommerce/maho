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
use Maho\Catalog\Api\Resource\BundleOption;
use Maho\ApiPlatform\Security\ApiUser;
use Maho\ApiPlatform\Service\StoreContext;
use Maho\Catalog\Api\State\Provider\BundleOptionProvider;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * @implements ProcessorInterface<BundleOption, BundleOption|BundleOption[]|null>
 */
final class BundleOptionProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly BundleOptionProvider $provider,
    ) {}

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): BundleOption|array|null
    {
        $user = $this->getAuthorizedUser();
        $productId = (int) ($uriVariables['productId'] ?? 0);

        $request = $context['request'] ?? null;
        $body = $request ? json_decode($request->getContent(), true) : [];

        if ($operation instanceof DeleteOperationInterface) {
            $this->requirePermission($user, 'products/delete');
            $optionId = (int) ($body['optionId'] ?? $body['option_id'] ?? 0);
            if ($optionId <= 0) {
                // Try extracting from query
                $optionId = (int) ($request?->query->get('optionId') ?? 0);
            }
            return $this->handleDelete($productId, $optionId);
        }

        $this->requirePermission($user, 'products/write');

        if ($operation instanceof Post) {
            return $this->handleCreate($productId, $body);
        }

        return $this->handleUpdate($productId, $body);
    }

    private function handleCreate(int $productId, array $body): BundleOption
    {
        $product = $this->loadBundleProduct($productId);

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

        try {
            $option->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to create bundle option: ' . $e->getMessage());
        }

        // Register product in Mage registry — Selection::_afterSave() requires it

        if (!Mage::registry('product')) {
            Mage::register('product', $product);
        }

        // Add selections
        foreach ($selections as $sel) {
            $selProductId = (int) ($sel['productId'] ?? $sel['product_id'] ?? 0);
            if ($selProductId <= 0) {
                continue;
            }

            /** @var \Mage_Bundle_Model_Selection $selection */
            $selection = Mage::getModel('bundle/selection');
            $selection->setOptionId($option->getId());
            $selection->setProductId($selProductId);
            $selection->setSelectionQty((float) ($sel['qty'] ?? 1));
            $selection->setSelectionCanChangeQty((int) ($sel['canChangeQty'] ?? $sel['can_change_qty'] ?? 1));
            $selection->setIsDefault((int) ($sel['isDefault'] ?? $sel['is_default'] ?? 0));
            $selection->setSelectionPriceType(($sel['priceType'] ?? $sel['price_type'] ?? 'fixed') === 'percent' ? 1 : 0);
            $selection->setSelectionPriceValue((float) ($sel['price'] ?? 0));
            $selection->setPosition((int) ($sel['position'] ?? 0));

            try {
                $selection->save();
            } catch (\Throwable $e) {
                throw new UnprocessableEntityHttpException('Failed to create selection: ' . $e->getMessage());
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

    private function handleUpdate(int $productId, array $body): array
    {
        $this->loadBundleProduct($productId);

        $optionId = (int) ($body['optionId'] ?? $body['option_id'] ?? $body['id'] ?? 0);
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
            $option->setType($body['type']);
        }
        if (isset($body['required'])) {
            $option->setRequired($body['required'] ? 1 : 0);
        }
        if (isset($body['position'])) {
            $option->setPosition((int) $body['position']);
        }

        try {
            $option->save();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to update option: ' . $e->getMessage());
        }

        return $this->provider->getBundleOptions($this->loadBundleProduct($productId));
    }

    private function handleDelete(int $productId, int $optionId): null
    {
        $this->loadBundleProduct($productId);

        if ($optionId <= 0) {
            throw new BadRequestHttpException('optionId is required');
        }

        /** @var \Mage_Bundle_Model_Option $option */
        $option = Mage::getModel('bundle/option')->load($optionId);
        if (!$option->getId() || (int) $option->getParentId() !== $productId) {
            throw new NotFoundHttpException('Bundle option not found');
        }

        try {
            $option->delete();
        } catch (\Throwable $e) {
            throw new UnprocessableEntityHttpException('Failed to delete option: ' . $e->getMessage());
        }

        return null;
    }

    private function loadBundleProduct(int $id): Mage_Catalog_Model_Product
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

        if ($product->getTypeId() !== Mage_Catalog_Model_Product_Type::TYPE_BUNDLE) {
            throw new BadRequestHttpException('Product is not a bundle product');
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
