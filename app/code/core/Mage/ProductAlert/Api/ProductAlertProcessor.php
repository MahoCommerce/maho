<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

namespace Mage\ProductAlert\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Service\StoreContext;
use Mage_ProductAlert_Model_Type as Type;

/**
 * Give a subscribe or an unsubscribe request of the customer of the token to the alert service.
 */
final class ProductAlertProcessor extends \Maho\ApiPlatform\Processor
{
    /**
     * @param ProductAlert|null $data
     */
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?ProductAlert
    {
        StoreContext::ensureStore();
        $customerId = $this->requireCustomerId();
        $storeId = StoreContext::getStoreId();
        /** @var \Mage_ProductAlert_Service_Alert $service */
        $service = \Mage::getService('productalert/alert');

        if ($operation instanceof DeleteOperationInterface) {
            [$type, $productId] = ProductAlertProvider::parseId((string) ($uriVariables['id'] ?? ''));
            $service->unsubscribe($type, $customerId, $productId, $storeId);
            return null;
        }

        $input = $context['args']['input'] ?? [];
        $type = $this->requireType($data instanceof ProductAlert && $data->type !== null ? $data->type : ($input['type'] ?? null));
        $productId = (int) ($data instanceof ProductAlert && $data->productId !== null ? $data->productId : ($input['productId'] ?? 0));

        if ($operation->getName() === 'unsubscribe') {
            $service->unsubscribe($type, $customerId, $productId, $storeId);
            return null;
        }

        return ProductAlert::fromModel($type, $service->subscribe($type, $customerId, $productId, $storeId));
    }

    /**
     * @throws \Mage_Core_Exception_Input when $type is not a type of alert
     */
    private function requireType(mixed $type): Type
    {
        return (is_string($type) ? Type::tryFrom($type) : null)
            ?? throw new \Mage_Core_Exception_Input()->addError('type', 'The type must be price or stock.');
    }
}
