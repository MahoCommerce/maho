<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Checkout
 */

declare(strict_types=1);

namespace Mage\Checkout\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\Pagination\TraversablePaginator;
use Mage_Checkout_Model_Agreement_Acceptance as Acceptance;

final class AgreementAcceptanceProvider extends \Maho\ApiPlatform\Provider
{
    /**
     * @return TraversablePaginator<Acceptance>
     */
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): TraversablePaginator
    {
        $collection = \Mage::getService('checkout/agreement')->getAcceptances($this->requireCustomerId());
        ['page' => $page, 'pageSize' => $pageSize] = $this->extractPagination($context, $this->defaultPageSize, $this->maxPageSize);
        $collection->setPageSize($pageSize)->setCurPage($page);
        $items = [];
        foreach ($collection->getItems() as $acceptance) {
            if ($acceptance instanceof Acceptance) {
                $items[] = $acceptance;
            }
        }
        return new TraversablePaginator(new \ArrayIterator($items), $page, $pageSize, (int) $collection->getSize());
    }
}
