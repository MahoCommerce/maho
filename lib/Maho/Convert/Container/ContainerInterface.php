<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Convert\Container;

use Maho\Convert\Exception;
use Maho\Convert\Profile\AbstractProfile;

interface ContainerInterface
{
    public function getData(): mixed;

    public function setData(mixed $data): self;

    public function setProfile(AbstractProfile $profile): self;

    public function addException(string $error, string|int|null $level = null): Exception;

    public function setVars(array $vars): self;
}
