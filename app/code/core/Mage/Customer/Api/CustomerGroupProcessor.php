<?php

/**
 * Give a customer group write to the customer group service.
 *
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Customer
 */

declare(strict_types=1);

namespace Mage\Customer\Api;

use Maho\ApiPlatform\CrudProcessor;

final class CustomerGroupProcessor extends CrudProcessor
{
    #[\Override]
    protected function loadOrFail(string $modelAlias, int|string $id, string $notFoundMessage): \Mage_Customer_Model_Group
    {
        return $this->groupService()->getById((int) $id);
    }

    #[\Override]
    protected function safeSave(\Mage_Core_Model_Abstract $model, string $entityLabel): void
    {
        /** @var \Mage_Customer_Model_Group $model */
        $this->groupService()->save($model);
    }

    #[\Override]
    protected function safeDelete(\Mage_Core_Model_Abstract $model, string $entityLabel): void
    {
        /** @var \Mage_Customer_Model_Group $model */
        $this->groupService()->delete($model);
    }

    private function groupService(): \Mage_Customer_Service_Group
    {
        /** @var \Mage_Customer_Service_Group */
        return \Mage::getService('customer/group');
    }
}
