<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_ProductAlert
 */

declare(strict_types=1);

namespace Mage\ProductAlert\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Maho\ApiPlatform\Service\StoreContext;
use Mage_ProductAlert_Model_Type as Type;

/**
 * Read the product alerts of the customer of the token from the alert service.
 */
final class ProductAlertProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return TraversablePaginator<ProductAlert>|ProductAlert
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator|ProductAlert
    {
        StoreContext::ensureStore();
        $customerId = $this->requireCustomerId();
        $storeId = StoreContext::getStoreId();
        /** @var \Mage_ProductAlert_Service_Alert $service */
        $service = \Mage::getService('productalert/alert');

        if ($operation instanceof CollectionOperationInterface) {
            $alerts = [];
            foreach (Type::cases() as $type) {
                foreach ($service->getByCustomer($customerId, $storeId, $type) as $alert) {
                    $alerts[] = ProductAlert::fromModel($type, $alert);
                }
            }
            return new TraversablePaginator(new \ArrayIterator($alerts), 1, max(count($alerts), 50), count($alerts));
        }

        [$type, $productId] = self::parseId((string) ($uriVariables['id'] ?? ''));
        return ProductAlert::fromModel($type, $service->getByProduct($type, $customerId, $productId, $storeId));
    }

    /**
     * Read the type and the product ID from an alert ID such as "price-42".
     *
     * @return array{Type, int}
     * @throws \Mage_Core_Exception_NoSuchEntity when $id is not an alert ID
     */
    public static function parseId(string $id): array
    {
        [$type, $productId] = explode('-', $id, 2) + [1 => ''];
        $type = Type::tryFrom($type);
        if ($type === null || !ctype_digit($productId)) {
            throw new \Mage_Core_Exception_NoSuchEntity('Product alert not found');
        }
        return [$type, (int) $productId];
    }
}
