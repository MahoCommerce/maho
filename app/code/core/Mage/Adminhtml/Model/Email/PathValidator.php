<?php

/**
 * Maho
 *
 * @package    Mage_Adminhtml
 * @copyright  Copyright (c) 2006-2020 Magento, Inc. (https://magento.com)
 * @copyright  Copyright (c) 2022-2024 The OpenMage Contributors (https://openmage.org)
 * @copyright  Copyright (c) 2024-2026 Maho (https://mahocommerce.com)
 * @license    https://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
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
