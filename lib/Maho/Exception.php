<?php

/**
 * SPDX-FileCopyrightText: 2025-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 */

declare(strict_types=1);

namespace Maho;

class Exception extends \Exception
{
    /**
     * Check PCRE PREG error and throw exception
     *
     * @throws Exception
     */
    public static function processPcreError(): void
    {
        if (preg_last_error() != PREG_NO_ERROR) {
            switch (preg_last_error()) {
                case PREG_INTERNAL_ERROR:
                    throw new Exception('PCRE PREG internal error');
                case PREG_BACKTRACK_LIMIT_ERROR:
                    throw new Exception('PCRE PREG Backtrack limit error');
                case PREG_RECURSION_LIMIT_ERROR:
                    throw new Exception('PCRE PREG Recursion limit error');
                case PREG_BAD_UTF8_ERROR:
                    throw new Exception('PCRE PREG Bad UTF-8 error');
                case PREG_BAD_UTF8_OFFSET_ERROR:
                    throw new Exception('PCRE PREG Bad UTF-8 offset error');
            }
        }
    }
}
