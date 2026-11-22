<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

declare(strict_types=1);

class Mage_Adminhtml_Model_Email_PathValidator
{
    /**
     * A plain config path: segments of letters, digits, underscore, dot and hyphen.
     * Maho\Simplexml\Element::descend() reads other syntax, such as a `node@attribute=value` segment, as a node selector.
     */
    public const string PLAIN_PATH_PATTERN = '#^[A-Za-z0-9_.\-]+(?:/[A-Za-z0-9_.\-]+)*$#';

    public function isValid(mixed $value): bool
    {
        if (null === $value || '' === $value) {
            return false;
        }

        $pathNode = is_array($value) ? array_shift($value) : $value;
        // A template can pass a number, such as getConfig(1)
        if (is_int($pathNode) || is_float($pathNode)) {
            $pathNode = (string) $pathNode;
        }
        if (!$this->isEncryptedNodePath($pathNode)) {
            return false;
        }

        return true;
    }

    /**
     * A parent of an encrypted path is encrypted too: getConfig() on a group returns its children decrypted.
     * A path that is not plain is encrypted too: a string comparison cannot tell which node it selects.
     */
    private function isEncryptedNodePath(string $path): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return false;
        }

        if (preg_match(self::PLAIN_PATH_PATTERN, $path) !== 1) {
            return true;
        }

        foreach (Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths() as $encryptedPath) {
            if ($encryptedPath === $path || str_starts_with($encryptedPath, $path . '/')) {
                return true;
            }
        }

        return false;
    }
}
