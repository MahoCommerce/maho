<?php

/**
 * Save and delete the notes on the days of the report charts for the administrator of the token.
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

final class ReportAnnotationProcessor extends \Maho\ApiPlatform\Processor
{
    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?\Mage_Reports_Model_Annotation
    {
        if (!$data instanceof \Mage_Reports_Model_Annotation) {
            throw new \LogicException('The note resource maps to Mage_Reports_Model_Annotation');
        }
        AdminAcl::checkPath(ReportAnnotation::ADMIN_RESOURCE);
        $adminUserId = $this->requireUser()->getAdminId()
            ?? throw new \Mage_Core_Exception_Forbidden(\Mage::helper('reports')->__('Only an administrator can write a note.'));
        $service = \Mage::getService('reports/annotation');
        if ($operation instanceof DeleteOperationInterface) {
            $service->delete($data, $adminUserId);
            return null;
        }
        return $service->save($data, $adminUserId);
    }
}
