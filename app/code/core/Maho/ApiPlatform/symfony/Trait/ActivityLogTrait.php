<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Maho\DataObject;

trait ActivityLogTrait
{
    protected function logApiActivity(
        string $entityType,
        string $action,
        ?array $oldData,
        ?DataObject $model,
    ): void {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = \Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => $entityType,
                'action_type' => $action,
                'entity_id' => $model ? (int) $model->getId() : ($oldData['entity_id'] ?? $oldData['page_id'] ?? $oldData['block_id'] ?? 0),
                'old_data' => $oldData,
                'new_data' => $model?->getData(),
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
        }
    }
}
