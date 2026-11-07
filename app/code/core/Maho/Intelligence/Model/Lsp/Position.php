<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_Intelligence
 */

declare(strict_types=1);

/**
 * Converts LSP UTF-16 columns to and from UTF-8 byte offsets.
 */
class Maho_Intelligence_Model_Lsp_Position
{
    public static function toByteOffset(string $line, int $character): int
    {
        $utf16 = mb_convert_encoding($line, 'UTF-16LE', 'UTF-8');
        $prefix = mb_strcut($utf16, 0, min(max(0, $character), intdiv(strlen($utf16), 2)) * 2, 'UTF-16LE');
        return strlen(mb_convert_encoding($prefix, 'UTF-8', 'UTF-16LE'));
    }

    public static function toUtf16Offset(string $line, int $byteOffset): int
    {
        $prefix = mb_strcut($line, 0, max(0, $byteOffset), 'UTF-8');
        return intdiv(strlen(mb_convert_encoding($prefix, 'UTF-16LE', 'UTF-8')), 2);
    }
}
