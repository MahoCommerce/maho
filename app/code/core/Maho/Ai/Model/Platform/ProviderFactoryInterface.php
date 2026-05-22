<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Maho_Ai
 * @copyright  Copyright (c) 2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

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
