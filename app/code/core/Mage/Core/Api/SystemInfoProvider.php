<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Core
 */

declare(strict_types=1);

namespace Mage\Core\Api;

use ApiPlatform\Metadata\Operation;

final class SystemInfoProvider extends \Maho\ApiPlatform\Provider
{
    #[\Override]
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): SystemInfo
    {
        $this->requireUser();

        $dto = new SystemInfo();
        $dto->version = \Mage::getVersion();
        $dto->build = \Maho\Build\Info::get()->describe();

        return $dto;
    }
}
