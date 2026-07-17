<?php

/**
 * SPDX-FileCopyrightText: 2026 Maho <https://mahocommerce.com>
 * SPDX-License-Identifier: OSL-3.0
 * @package Maho_ContentVersion
 */

declare(strict_types=1);

class Maho_ContentVersion_Model_Version extends Mage_Core_Model_Abstract
{
    #[\Override]
    protected function _construct(): void
    {
        $this->_init('contentversion/version');
    }

    public function getContentDataDecoded(): array
    {
        $data = $this->getData('content_data');
        if (is_string($data)) {
            return Mage::helper('core')->jsonDecode($data);
        }
        return [];
    }

    public function setContentDataEncoded(array $data): self
    {
        $this->setData('content_data', Mage::helper('core')->jsonEncode($data));
        return $this;
    }
}
