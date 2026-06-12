<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ApiPlatform
 */

declare(strict_types=1);

namespace Maho\ApiPlatform\Trait;

use Maho\ApiPlatform\Security\ApiUser;
use Maho\DataObject;

trait ActivityLogTrait
{
    protected function logApiActivity(
        string $entityType,
        string $action,
        ?array $oldData,
        ?DataObject $model,
        ApiUser $user,
    ): void {
        try {
            /** @var \Maho_AdminActivityLog_Model_Activity $activity */
            $activity = \Mage::getModel('adminactivitylog/activity');
            $activity->logActivity([
                'entity_type' => $entityType,
                'action' => $action,
                'entity_id' => $model ? (int) $model->getId() : ($oldData['entity_id'] ?? $oldData['page_id'] ?? $oldData['block_id'] ?? 0),
                'old_data' => $oldData,
                'new_data' => $model?->getData(),
                'api_user_id' => $user->getApiUserId(),
                'username' => 'API: ' . $user->getUserIdentifier(),
            ]);
        } catch (\Exception $e) {
            \Mage::logException($e);
        }
    }
}
