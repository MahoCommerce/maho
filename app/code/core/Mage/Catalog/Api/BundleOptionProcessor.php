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
use Maho\ApiPlatform\Http\RequestInput;
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
        $input = RequestInput::of($this->parseRequestBody($request));
        /** @var \Mage_Bundle_Service_Option $service */
        $service = \Mage::getService('bundle/option');

        if ($operation instanceof Post) {
            /** @var \Mage_Bundle_Model_Option $option */
            $option = \Mage::getModel('bundle/option');
            $title = $input->string('title') ?? '';
            $option->setData('title', $title);
            $option->setType($input->string('type') ?? 'select');
            $option->setRequired($input->bool('required') ?? true);
            $option->setPosition($input->int('position') ?? 0);
            $option = $service->save($product, $option, $this->parseSelections($input), $this->allowedWebsiteIds($user));

            $dto = new BundleOption();
            $dto->id = (int) $option->getId();
            $dto->title = $title;
            $dto->type = (string) $option->getType();
            $dto->required = (bool) $option->getRequired();
            $dto->position = (int) $option->getPosition();
            $dto->selections = $input->list('selections') ?? [];
            return $dto;
        }

        // The routes carry the option ID in {id}, but API Platform declares only the productId link
        $pathOptionId = $request instanceof Request && preg_match('#/bundle-options/(\d+)#', $request->getPathInfo(), $m) ? (int) $m[1] : 0;
        $optionId = (int) ($uriVariables['id'] ?? $input->int('optionId', 'option_id', 'id') ?? 0) ?: $pathOptionId;
        $option = $service->getById($product, $optionId);

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($product, $option);
            return null;
        }

        $title = $input->string('title');
        if ($title !== null) {
            $option->setData('title', $title);
        }
        $type = $input->string('type');
        if ($type !== null) {
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
        $service->save($product, $option);

        return $this->provider->getBundleOptions($this->loadProduct($productId, Mage_Catalog_Model_Product_Type::TYPE_BUNDLE));
    }

    /**
     * @return list<array{product_id: int, selection_qty: float, selection_price_value: float, selection_price_type: int, selection_can_change_qty: int, is_default: int, position: int}>
     */
    private function parseSelections(RequestInput $input): array
    {
        $result = [];
        foreach ($input->rows('selections') ?? [] as $selection) {
            $productId = $selection->int('productId', 'product_id') ?? 0;
            if ($productId <= 0) {
                continue;
            }
            $result[] = [
                'product_id' => $productId,
                'selection_qty' => $selection->float('qty') ?? 1.0,
                'selection_price_value' => $selection->float('price') ?? 0.0,
                'selection_price_type' => ($selection->string('priceType', 'price_type') ?? 'fixed') === 'percent' ? 1 : 0,
                'selection_can_change_qty' => (int) ($selection->bool('canChangeQty', 'can_change_qty') ?? true),
                'is_default' => (int) ($selection->bool('isDefault', 'is_default') ?? false),
                'position' => $selection->int('position') ?? 0,
            ];
        }
        return $result;
    }
}
