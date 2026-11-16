<?php

/**
 * Save and delete the monthly targets of the report explorer.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Reports
 */

declare(strict_types=1);

namespace Mage\Reports\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\AdminAcl;

final class ReportTargetProcessor extends \Maho\ApiPlatform\Processor
{
    use ExplorerAccessTrait;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?\Mage_Reports_Model_Target
    {
        if (!$data instanceof \Mage_Reports_Model_Target) {
            throw new \LogicException('The target resource maps to Mage_Reports_Model_Target');
        }
        AdminAcl::checkPath(ReportTarget::ADMIN_RESOURCE);
        $service = \Mage::getService('reports/target');
        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($data);
            return null;
        }
        $service->save($data, $this->explorerAccess($this->requireUser()));
        return $data->setMonth(substr((string) $data->getMonth(), 0, 7));
    }
}
