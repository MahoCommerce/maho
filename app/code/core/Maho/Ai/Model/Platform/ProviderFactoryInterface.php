<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Ai
 */

declare(strict_types=1);

/**
 * Contract for community provider factories.
 *
 * Implementations are declared via <factory_class> in provider config
 * and are called by the core Factory when a community provider is selected.
 */
interface Maho_Ai_Model_Platform_ProviderFactoryInterface
{
    /**
     * Create a configured provider instance.
     */
    public function create(?int $storeId = null): Maho_Ai_Model_Platform_ProviderInterface;
}
