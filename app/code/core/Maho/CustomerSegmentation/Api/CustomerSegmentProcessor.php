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
use Maho\ApiPlatform\Exception\ApiException;
use Maho\ApiPlatform\Security\ApiUser;

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
            $this->assertSegmentAcl(CustomerSegment::ACL_REFRESH);
            $this->assertWebsitesWritable($data, $context, $user);
            $this->segmentService()->refresh($data);
            $this->logApiActivity('customer_segment', 'refresh', null, $data, $user);
            return $data;
        }

        if ($operation instanceof DeleteOperationInterface) {
            $this->assertSegmentAcl(CustomerSegment::ACL_DELETE);
            $this->assertWebsitesWritable($data, $context, $user);
            $this->segmentService()->delete($data);
            $this->logApiActivity('customer_segment', 'delete', $oldData, null, $user);
            return null;
        }

        $this->assertSegmentAcl(CustomerSegment::ACL_SAVE);
        $this->assertWebsitesWritable($data, $context, $user);
        $isNew = !$data->getId();
        $this->segmentService()->save($data);
        $this->logApiActivity('customer_segment', $isNew ? 'create' : 'update', $isNew ? null : $oldData, $data, $user);

        return $data;
    }

    /**
     * A restricted token changes only a segment whose stored websites and new websites are all in its scope.
     *
     * @param array<string, mixed> $context
     */
    private function assertWebsitesWritable(\Maho_CustomerSegmentation_Model_Segment $segment, array $context, ApiUser $user): void
    {
        $allowedWebsiteIds = $this->allowedWebsiteIds($user);
        if ($allowedWebsiteIds === null) {
            return;
        }
        $previous = $context['previous_data'] ?? null;
        if ($segment->getId() && $previous instanceof CustomerSegment) {
            $this->assertAllWebsitesAllowed($previous->websiteIds, $user, 'customer segment');
        }
        if (array_diff($segment->getWebsiteIds(), $allowedWebsiteIds) !== []) {
            throw new ApiException('Access denied for a website of this customer segment', 'forbidden', 403);
        }
    }
}
