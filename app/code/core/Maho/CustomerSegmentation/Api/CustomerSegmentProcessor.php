<?php

/**
 * Give a segment write to the segment service. API Platform has mapped the body into the segment before.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use ApiPlatform\Metadata\DeleteOperationInterface;
use ApiPlatform\Metadata\Operation;
use Maho\ApiPlatform\Security\AdminAcl;

final class CustomerSegmentProcessor extends \Maho\ApiPlatform\Processor
{
    use SegmentApiTrait;

    #[\Override]
    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): ?\Maho_CustomerSegmentation_Model_Segment
    {
        if (!$data instanceof \Maho_CustomerSegmentation_Model_Segment) {
            throw new \LogicException('The customer segment resource maps to Maho_CustomerSegmentation_Model_Segment');
        }
        $user = $this->requireUser();
        $oldData = $data->getOrigData();

        if ($operation->getName() === 'refresh_customer_segment') {
            AdminAcl::checkPath(CustomerSegment::ACL_REFRESH);
            $this->segmentService()->refresh($data, websiteIds: $this->allowedWebsiteIds($user));
            $this->logApiActivity('customer_segment', 'refresh', null, $data);
            return $data;
        }

        if ($operation instanceof DeleteOperationInterface) {
            AdminAcl::checkPath(CustomerSegment::ACL_DELETE);
            $this->segmentService()->delete($data, $this->allowedWebsiteIds($user));
            $this->logApiActivity('customer_segment', 'delete', $oldData, null);
            return null;
        }

        AdminAcl::checkPath(CustomerSegment::ACL_SAVE);
        $isNew = !$data->getId();
        $this->segmentService()->save($data, $this->allowedWebsiteIds($user));
        $this->logApiActivity('customer_segment', $isNew ? 'create' : 'update', $isNew ? null : $oldData, $data);

        return $data;
    }
}
