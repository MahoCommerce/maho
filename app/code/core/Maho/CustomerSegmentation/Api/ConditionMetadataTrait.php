<?php

/**
 * Give the condition metadata of customer segments to the providers and the processor of the segment API.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_CustomerSegmentation
 */

declare(strict_types=1);

namespace Maho\CustomerSegmentation\Api;

trait ConditionMetadataTrait
{
    use \Maho\ApiPlatform\Trait\ConditionMetadataTrait;

    private ?\Mage_Rule_Model_Condition_Metadata $conditionMetadataModel = null;

    #[\Override]
    protected function conditionMetadataModel(): \Mage_Rule_Model_Condition_Metadata
    {
        return $this->conditionMetadataModel ??= new \Mage_Rule_Model_Condition_Metadata(
            \Mage::getModel('customersegmentation/segment'),
            ['conditions' => CustomerSegment::ROOT_CONDITIONS],
            'customersegmentation',
        );
    }

    #[\Override]
    protected function conditionMetadataCacheKey(): string
    {
        return 'API_CUSTOMER_SEGMENT_CONDITION_METADATA';
    }
}
