<?php

/**
 * Give a design change write to the design change service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;

final class DesignChangeProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?DesignChange
    {
        $user = $this->requireUser();
        $service = \Mage::getService('core/design');
        $design = isset($uriVariables['id'])
            ? $service->getById((int) $uriVariables['id'], $user->getAllowedStoreIds())
            : $service->newDesignChange();
        $oldData = $design->getId() ? $design->getData() : null;

        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($design);
            $this->logApiActivity('design_change', 'delete', $oldData, null);
            return null;
        }

        /** @var DesignChange $data */
        $body = $this->parseRequestBody($context['request'] ?? null);
        $data->applyToModel($design, $oldData === null ? null : array_map(strval(...), array_keys($body)));
        // A date sent as null removes that bound, which applyToModel() does not write
        foreach (['dateFrom' => 'date_from', 'dateTo' => 'date_to'] as $property => $column) {
            if (array_key_exists($property, $body) && $body[$property] === null) {
                $design->setData($column);
            }
        }
        if ($design->getStoreId() !== null) {
            $this->validateEntityStoreAccess([$design->getStoreId()], $user, 'design change');
        }

        $service->save($design);
        $this->logApiActivity('design_change', $oldData === null ? 'create' : 'update', $oldData, $design);

        return DesignChange::fromModel($design);
    }
}
