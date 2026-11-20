<?php

/**
 * Save and delete the saved reports of the report explorer for the administrator of the token.
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

final class SavedReportProcessor extends \Maho\ApiPlatform\Processor
{
    use ExplorerAccessTrait;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?\Mage_Reports_Model_SavedReport
    {
        if (!$data instanceof \Mage_Reports_Model_SavedReport) {
            throw new \LogicException('The saved report resource maps to Mage_Reports_Model_SavedReport');
        }
        AdminAcl::checkPath(SavedReport::ADMIN_RESOURCE);
        $user = $this->requireUser();
        $adminUserId = $user->getAdminId();
        if ($adminUserId === null) {
            throw new \Mage_Core_Exception_Forbidden(\Mage::helper('reports')->__('Only an administrator can save or delete a report.'));
        }
        $service = \Mage::getService('reports/savedReport');

        if ($operation->getName() === SavedReport::OPERATION_PIN) {
            $service->pin($data, $adminUserId);
            return $data->setIsPinned();
        }
        if ($operation->getName() === SavedReport::OPERATION_UNPIN) {
            $service->unpin($data, $adminUserId);
            return null;
        }
        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($data, $adminUserId);
            return null;
        }
        return $service->save($data, $adminUserId, $this->explorerAccess($user))->setIsPinned($service->isPinned($data, $adminUserId));
    }
}
