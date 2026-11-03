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
use Mage_Catalog_Model_Product_Type;
use Maho\ApiPlatform\Trait\ProductLoaderTrait;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;

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
        $this->assertProductWebsitesAllowed($this->loadProduct($productId), $user);
        $product = $this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE);

        $request = $context['request'] ?? null;
        $body = $this->parseRequestBody($request);
        /** @var \Mage_Bundle_Service_Option $service */
        $service = \Mage::getService('bundle/option');

        if ($operation instanceof Post) {
            /** @var \Mage_Bundle_Model_Option $option */
            $option = \Mage::getModel('bundle/option');
            $option->setData('title', (string) ($body['title'] ?? ''));
            $option->setType((string) ($body['type'] ?? 'select'));
            $option->setRequired((bool) ($body['required'] ?? true));
            $option->setPosition((int) ($body['position'] ?? 0));
            $option = $service->save($product, $option, $this->parseSelections($body['selections'] ?? []), $this->getAllowedWebsiteIds($user));

            $dto = new BundleOption();
            $dto->id = (int) $option->getId();
            $dto->title = (string) ($body['title'] ?? '');
            $dto->type = (string) $option->getType();
            $dto->required = (bool) $option->getRequired();
            $dto->position = (int) $option->getPosition();
            $dto->selections = $body['selections'] ?? [];
            return $dto;
        }

        // The routes carry the option ID in {id}, but API Platform declares only the productId link
        $pathOptionId = $request instanceof Request && preg_match('#/bundle-options/(\d+)#', $request->getPathInfo(), $m) ? (int) $m[1] : 0;
        $optionId = (int) ($uriVariables['id'] ?? $body['optionId'] ?? $body['option_id'] ?? $body['id'] ?? 0) ?: $pathOptionId;
        $option = $service->getById($product, $optionId);

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($product, $option);
            return null;
        }

        if (isset($body['title'])) {
            $option->setData('title', (string) $body['title']);
        }
        if (isset($body['type'])) {
            $option->setType((string) $body['type']);
        }
        if (isset($body['required'])) {
            $option->setRequired((bool) $body['required']);
        }
        if (isset($body['position'])) {
            $option->setPosition((int) $body['position']);
        }
        $service->save($product, $option);

        return $this->provider->getBundleOptions($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE));
    }

    /**
     * @return list<array{product_id: int, selection_qty: float, selection_price_value: float, selection_price_type: int, selection_can_change_qty: int, is_default: int, position: int}>
     */
    private function parseSelections(mixed $selections): array
    {
        $result = [];
        foreach ((array) $selections as $selection) {
            $selection = is_array($selection) ? $selection : [];
            $productId = (int) ($selection['productId'] ?? $selection['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $result[] = [
                'product_id' => $productId,
                'selection_qty' => (float) ($selection['qty'] ?? 1),
                'selection_price_value' => (float) ($selection['price'] ?? 0),
                'selection_price_type' => ($selection['priceType'] ?? $selection['price_type'] ?? 'fixed') === 'percent' ? 1 : 0,
                'selection_can_change_qty' => (int) (bool) ($selection['canChangeQty'] ?? $selection['can_change_qty'] ?? 1),
                'is_default' => (int) (bool) ($selection['isDefault'] ?? $selection['is_default'] ?? 0),
                'position' => (int) ($selection['position'] ?? 0),
            ];
        }
        return $result;
    }
}
