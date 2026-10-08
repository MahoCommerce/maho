<?php

/**
 * Give the segment API classes the segment service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

trait SegmentApiTrait
{
    protected function segmentService(): \Maho_CustomerSegmentation_Service_Segment
    {
        /** @var \Maho_CustomerSegmentation_Service_Segment */
        return \Mage::getService('customersegmentation/segment');
    }
}
