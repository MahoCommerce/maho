<?php

declare(strict_types=1);

/**
 * Maho
 *
 * @package    Mage_Api2
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2025-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

interface Mage_Api2_Model_Route_Interface
{
    /**
     * Matches a Request with parts defined by a map. Assigns and
     * returns an array of variables on a successful match.
     *
     * @param Mage_Api2_Model_Request $request
     * @param bool $partial Partial path matching
     * @return array<string, mixed>|false An array of assigned values or false on a mismatch
     */
    public function match($request, bool $partial = false): array|false;
}
