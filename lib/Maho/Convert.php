<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

namespace Maho;

use Maho\Convert\Action;

class Convert
{
    /**
     * @param array<string, string> $vars
     */
    public static function convert(string $class, string $method, mixed $data, array $vars = []): mixed
    {
        $action = new Action();
        $action->setParam('method', $method)->setParam('class', $class);

        $container = $action->getContainer();
        $container->setData($data)->setVars($vars);

        $action->run();
        return $container->getData();
    }
}
