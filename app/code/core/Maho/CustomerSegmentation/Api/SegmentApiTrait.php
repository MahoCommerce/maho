<?php

/**
 * Give the segment API classes the segment service and the ACL check of each segment action.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

use Maho\ApiPlatform\Exception\ApiException;

trait SegmentApiTrait
{
    protected function segmentService(): \Maho_CustomerSegmentation_Service_Segment
    {
        /** @var \Maho_CustomerSegmentation_Service_Segment */
        return \Mage::getService('customersegmentation/segment');
    }

    /**
     * Refuse an admin token whose role does not allow $acl, as the segment admin pages do.
     * The operation security checks the grants of a service token.
     */
    protected function assertSegmentAcl(string $acl): void
    {
        if ($this->requireUser()->getAdminId() !== null && !\Mage::getSingleton('admin/session')->isAllowed($acl)) {
            throw new ApiException(sprintf('Your admin role does not grant access to "%s".', $acl), 'forbidden', 403);
        }
    }
}
