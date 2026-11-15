<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho\Convert\Action;

interface ActionInterface
{
    /**
     * Run current action
     *
     * @return AbstractAction
     */
    public function run();

    public function setProfile(\Maho\Convert\Profile\AbstractProfile $profile): static;

    public function getParam(string $key, mixed $default = null): mixed;

    public function setParam(array|string $key, mixed $value = null): static;

    public function getContainer(string|int|null $name = null): \Maho\Convert\Container\ContainerInterface;

    public function setContainer(\Maho\Convert\Container\ContainerInterface $container): static;
}
