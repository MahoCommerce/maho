<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2026 The OpenMage Contributors <https://openmage.org>
 * SPDX-FileCopyrightText: 2006-2020 Magento, Inc. <https://magento.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Mage_Adminhtml
 */

class Mage_Adminhtml_Model_Email_PathValidator
{
    public function isValid(mixed $value): bool
    {
        if (null === $value || '' === $value) {
            return false;
        }

        $pathNode = is_array($value) ? array_shift($value) : $value;
        if (!$this->isEncryptedNodePath($pathNode)) {
            return false;
        }

        return true;
    }

    /**
     * A parent of an encrypted path is encrypted too: getConfig() on a group returns its children decrypted.
     */
    private function isEncryptedNodePath(string $path): bool
    {
        $path = trim($path, '/');
        if ($path === '') {
            return false;
        }

        foreach (Mage::getSingleton('adminhtml/config')->getEncryptedNodeEntriesPaths() as $encryptedPath) {
            if ($encryptedPath === $path || str_starts_with($encryptedPath, $path . '/')) {
                return true;
            }
        }

        return false;
    }
}
