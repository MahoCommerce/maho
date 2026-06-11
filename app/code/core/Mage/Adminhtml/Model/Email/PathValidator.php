<?php

/**
 * SPDX-FileCopyrightText: 2024-2026 Maho <https://mahocommerce.com>
 * SPDX-FileCopyrightText: 2022-2024 The OpenMage Contributors <https://openmage.org>
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

    private function isEncryptedNodePath(string $path): bool
    {
        $configModel = Mage::getSingleton('adminhtml/config');

        return in_array((string) $path, $configModel->getEncryptedNodeEntriesPaths());
    }
}
