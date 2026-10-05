<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

namespace Maho\Ai\Api;

use Maho\ApiPlatform\CrudProcessor;
use Maho\ApiPlatform\CrudResource;
use Maho\ApiPlatform\Exception\ValidationException;
use Maho\ApiPlatform\Security\ApiUser;

final class ScheduledTaskProcessor extends CrudProcessor
{
    /** A run uses the permissions of its owner, so the caller must be an administrator, who becomes the owner. */
    #[\Override]
    protected function beforeSave(object $model, CrudResource $data, ApiUser $user): void
    {
        /** @var \Mage_Admin_Model_User $owner */
        $owner = \Mage::getModel('admin/user')->load((int) $user->getAdminId());
        if (!$owner->getId()) {
            throw new ValidationException('A scheduled task runs as an administrator: only an administrator can save one.', 'adminUserId');
        }
        if (!$model instanceof \Maho_Ai_Model_Task_Schedule) {
            throw new \LogicException('The scheduled task model is not configured.');
        }
        try {
            $model->validateFor($owner);
        } catch (\Mage_Core_Exception $e) {
            throw new ValidationException($e->getMessage(), previous: $e);
        }
    }
}
