<?php

/**
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Api2
 */

declare(strict_types=1);

interface Mage_Api2_Model_Request_Interpreter_Interface
{
    /**
     * Parse request body into array of params
     *
     * @param string $body  Posted content from request
     * @return array|null   Return NULL if content is invalid
     */
    public function interpret($body);
}
